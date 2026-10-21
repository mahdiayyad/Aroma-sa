<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ShipmentStatusUpdated;
use App\Models\AramexShipment;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Order-level Aramex workflow on top of the AramexService API client:
 * quoting a cart, shipping an order (claim, create, store label, move the
 * order to "shipped"), and applying tracking events from the webhook or the
 * tracking poller. Order status changes always go through OrderStatusService.
 */
class AramexFulfilmentService
{
    /** Orders Aroma can hand to Aramex. */
    public const SHIPPABLE_STATUSES = [Order::STATUS_PAID, Order::STATUS_PROCESSING];

    /** A "pending" claim older than this is assumed dead (e.g. a crashed request) and may be retried. */
    private const STALE_CLAIM_MINUTES = 5;

    private const QUOTE_CACHE_MINUTES = 10;

    private const MAX_HISTORY = 200;

    /** @var AramexService */
    private $aramex;

    /** @var OrderStatusService */
    private $orderStatus;

    public function __construct(AramexService $aramex, OrderStatusService $orderStatus)
    {
        $this->aramex = $aramex;
        $this->orderStatus = $orderStatus;
    }

    public function isConfigured(): bool
    {
        return $this->aramex->isConfigured();
    }

    /* ------------------------------------------------------------------ */
    /* Quotes                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Live rate from Aroma's warehouse to $destination, cached briefly so a
     * shopper re-rendering checkout doesn't call Aramex every time.
     *
     * @param array{city:string,country_code:string,line1?:string,post_code?:string} $destination
     * @return array{success:bool,amount?:float,currency?:string,errors?:array<int,string>}
     */
    public function quote(array $destination, float $weightKg, int $pieces): array
    {
        $origin = $this->shipperAddress();
        $key = 'aramex:quote:'.sha1((string) json_encode([
            mb_strtolower(trim((string) $destination['city'])),
            strtoupper((string) $destination['country_code']),
            round($weightKg, 3),
            $pieces,
            $origin['city'],
            $origin['country_code'],
        ]));

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->aramex->calculateRate($origin, $destination, [
            'weight_kg' => $weightKg,
            'pieces' => $pieces,
            'payment_type' => AramexService::PAYMENT_PREPAID,
        ]);

        if ($result['success']) {
            Cache::put($key, $result, now()->addMinutes(self::QUOTE_CACHE_MINUTES));
        }

        return $result;
    }

    /**
     * @param array<string,array<string,mixed>> $cartRows CartService::rows()
     * @return array{weight_kg:float,pieces:int}
     */
    public function cartWeight(array $cartRows): array
    {
        $units = 0;
        foreach ($cartRows as $row) {
            $units += max(0, (int) ($row['qty'] ?? 0));
        }

        return ['weight_kg' => $this->weightFor($units), 'pieces' => max(1, $units)];
    }

    /* ------------------------------------------------------------------ */
    /* Shipping an order                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Create the Aramex shipment for $order and store its label. Safe to call
     * repeatedly: an order that already has an AWB is never shipped twice (a
     * repeat call just makes sure its label is stored).
     *
     * @return array{success:bool,message:string,shipment:?AramexShipment,warnings:array<int,string>}
     */
    public function ship(Order $order): array
    {
        if (! $this->aramex->isConfigured()) {
            return $this->shipResult(false, __('admin.aramex.not_configured'));
        }

        $claim = $this->claim($order);

        if ($claim['state'] === 'existing') {
            $shipment = $claim['shipment'];
            $warnings = $this->ensureLabel($shipment) ? [] : [__('admin.aramex.label_failed')];

            return $this->shipResult(true, __('admin.aramex.already_shipped', ['awb' => $shipment->awb_number]), $shipment, $warnings);
        }
        if ($claim['state'] === 'busy') {
            return $this->shipResult(false, __('admin.aramex.in_progress'));
        }
        if ($claim['state'] === 'not_shippable') {
            return $this->shipResult(false, __('admin.aramex.not_shippable', ['status' => __('orders.status.'.$claim['status'])]));
        }

        /** @var AramexShipment $shipment */
        $shipment = $claim['shipment'];
        $order->loadMissing('items');

        try {
            $consignee = $this->consigneeFor($order);
        } catch (InvalidArgumentException $e) {
            return $this->failClaim($shipment, $e->getMessage());
        }

        $units = (int) $order->items->sum('quantity');
        $weight = $this->weightFor($units);

        $result = $this->aramex->createShipment([
            'reference' => $order->order_number,
            'consignee' => $consignee,
            'weight_kg' => $weight,
            'pieces' => max(1, $units),
            'description' => config('services.aramex.description_of_goods'),
            'customs_value' => (float) $order->subtotal,
            'payment_type' => AramexService::PAYMENT_PREPAID,
        ]);

        if (! $result['success']) {
            return $this->failClaim($shipment, implode(' ', $result['errors']));
        }

        // Persist the AWB before anything else can fail, so a retry can
        // never create a second Aramex shipment for this order.
        $shipment->fill([
            'awb_number' => $result['awb'],
            'shipping_status' => AramexShipment::STATUS_CREATED,
            'product_group' => $result['product_group'],
            'product_type' => $result['product_type'],
            'payment_type' => AramexService::PAYMENT_PREPAID,
            'weight_kg' => $weight,
            'pieces' => max(1, $units),
            'error_message' => null,
            'history' => array_merge($shipment->history ?? [], [[
                'code' => null,
                'description' => 'Shipment created (AWB '.$result['awb'].')',
                'location' => null,
                'comment' => null,
                'occurred_at' => now()->utc()->toIso8601String(),
                'source' => 'system',
                'received_at' => now()->utc()->toIso8601String(),
            ]]),
        ])->save();

        $messages = [__('admin.aramex.created', ['awb' => $result['awb']])];
        $warnings = [];

        foreach ($this->moveOrderToShipped($order, $result['awb']) as $note) {
            if ($note['success']) {
                $messages[] = $note['message'];
            } else {
                $warnings[] = $note['message'];
            }
        }

        if (! $this->storeLabel($shipment, $result['label_pdf'] ?? null, $result['label_url'] ?? null)) {
            $warnings[] = __('admin.aramex.label_failed');
        }

        ShipmentStatusUpdated::dispatch($order->fresh(), $shipment, null);

        return $this->shipResult(true, implode(' ', $messages), $shipment, $warnings);
    }

    /** Make sure the shipment's label PDF is stored, re-requesting it from Aramex if not. */
    public function ensureLabel(AramexShipment $shipment): bool
    {
        if (! $shipment->hasAwb()) {
            return false;
        }

        if ($shipment->label_path && $this->labelDisk()->exists($shipment->label_path)) {
            return true;
        }

        $result = $this->aramex->printLabel((string) $shipment->awb_number, (string) ($shipment->product_group ?: 'DOM'));

        if (! $result['success']) {
            return false;
        }

        return $this->storeLabel($shipment, $result['label_pdf'] ?? null, $result['label_url'] ?? null);
    }

    public function labelDisk(): FilesystemAdapter
    {
        return Storage::disk((string) config('services.aramex.label_disk', 'local'));
    }

    /* ------------------------------------------------------------------ */
    /* Tracking                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Record tracking events and move the shipment (and order) forward.
     *
     * Idempotent: an event already in the history is ignored, an event older
     * than the last applied one is recorded but doesn't change the status,
     * and delivered / returned are final.
     *
     * @param array<int,array{code?:mixed,description?:mixed,location?:mixed,comment?:mixed,occurred_at?:mixed}> $events
     * @param string $source webhook | tracking
     * @return bool whether the shipping status changed
     */
    public function applyTrackingEvents(AramexShipment $shipment, array $events, string $source): bool
    {
        $outcome = DB::transaction(function () use ($shipment, $events, $source): ?array {
            /** @var AramexShipment|null $locked */
            $locked = AramexShipment::query()->whereKey($shipment->getKey())->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $history = $locked->history ?? [];
            $known = [];
            foreach ($history as $entry) {
                $known[$this->eventKey($entry)] = true;
            }

            $newest = null;
            foreach ($events as $event) {
                $entry = $this->historyEntry($event, $source);
                $key = $this->eventKey($entry);
                if (isset($known[$key])) {
                    continue;
                }
                $known[$key] = true;
                $history[] = $entry;

                if ($newest === null || $entry['occurred_at'] > $newest['occurred_at']) {
                    $newest = $entry;
                }
            }

            if ($newest === null) {
                return null;
            }

            $previous = (string) $locked->shipping_status;
            $attributes = ['history' => array_slice($history, -self::MAX_HISTORY)];
            $newestAt = Carbon::parse($newest['occurred_at']);
            $isLatest = $locked->last_event_at === null || $newestAt->gte($locked->last_event_at);

            if ($isLatest && ! $locked->isTerminal()) {
                // Eloquent writes a datetime in the Carbon's own zone but reads it
                // back in app.timezone, so store it in app.timezone.
                $attributes['last_event_at'] = $newestAt->copy()->setTimezone((string) config('app.timezone'));
                $attributes['status_code'] = $newest['code'];
                $attributes['status_description'] = $newest['description'];
                $attributes['shipping_status'] = $this->classify((string) $newest['code'], (string) $newest['description']);
            }

            $locked->update($attributes);

            return ['shipment' => $locked, 'previous' => $previous];
        });

        if ($outcome === null) {
            return false;
        }

        /** @var AramexShipment $updated */
        $updated = $outcome['shipment'];
        $shipment->setRawAttributes($updated->getAttributes(), true);

        if ($outcome['previous'] === $updated->shipping_status) {
            return false;
        }

        $order = $updated->order()->first();
        if ($order) {
            $this->syncOrderStatus($order, $updated);
            ShipmentStatusUpdated::dispatch($order->fresh(), $updated, $outcome['previous']);
        }

        return true;
    }

    /** Map an Aramex UpdateCode / description to an internal shipping status. */
    public function classify(string $code, string $description): string
    {
        $codes = array_change_key_case((array) config('services.aramex.status_codes', []), CASE_UPPER);
        $mapped = $codes[strtoupper(trim($code))] ?? null;
        $known = [
            AramexShipment::STATUS_IN_TRANSIT, AramexShipment::STATUS_OUT_FOR_DELIVERY,
            AramexShipment::STATUS_DELIVERED, AramexShipment::STATUS_RETURNED, AramexShipment::STATUS_EXCEPTION,
        ];
        if (is_string($mapped) && in_array($mapped, $known, true)) {
            return $mapped;
        }

        $text = mb_strtolower(trim($description));

        if (preg_match('/\breturn(ed)? to (shipper|origin|sender)\b|\breturned\b/', $text)) {
            return AramexShipment::STATUS_RETURNED;
        }
        if (preg_match('/not delivered|undeliver|unable|attempt|refus|on hold|\bhold\b|exception|problem|delay|missed|wrong address|incorrect address|cancel/', $text)) {
            return AramexShipment::STATUS_EXCEPTION;
        }
        if (strpos($text, 'out for delivery') !== false) {
            return AramexShipment::STATUS_OUT_FOR_DELIVERY;
        }
        // Strict on purpose: "delivered" moves the order to delivered.
        if (preg_match('/^(shipment )?delivered\b|\bdelivered (to (the )?(consignee|customer|receiver|recipient)|successfully)\b/', $text)) {
            return AramexShipment::STATUS_DELIVERED;
        }

        return AramexShipment::STATUS_IN_TRANSIT;
    }

    /**
     * Aramex dates come as WCF JSON ("/Date(1700000000000+0300)/") or as a
     * plain date string. Returns UTC, or null when unreadable.
     *
     * @param mixed $value
     */
    public static function parseAramexDate($value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->utc();
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('#/?Date\((-?\d+)([+-]\d{4})?\)/?#', $text, $m)) {
            return Carbon::createFromTimestampMs((int) $m[1])->utc();
        }

        try {
            return Carbon::parse($text)->utc();
        } catch (Throwable $e) {
            return null;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Take the order's shipment row under a lock so concurrent "ship" clicks
     * can't both reach Aramex.
     *
     * @return array{state:string,shipment?:AramexShipment,status?:string}
     */
    private function claim(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
            $shipment = AramexShipment::query()->where('order_id', $order->getKey())->lockForUpdate()->first();

            if ($shipment && $shipment->hasAwb()) {
                return ['state' => 'existing', 'shipment' => $shipment];
            }

            if (! $lockedOrder || ! in_array($lockedOrder->status, self::SHIPPABLE_STATUSES, true)) {
                return ['state' => 'not_shippable', 'status' => $lockedOrder ? (string) $lockedOrder->status : 'unknown'];
            }

            if ($shipment
                && $shipment->shipping_status === AramexShipment::STATUS_PENDING
                && $shipment->updated_at
                && $shipment->updated_at->gt(now()->subMinutes(self::STALE_CLAIM_MINUTES))) {
                return ['state' => 'busy'];
            }

            $shipment = $shipment ?: new AramexShipment(['order_id' => $order->getKey()]);
            $shipment->shipping_status = AramexShipment::STATUS_PENDING;
            $shipment->error_message = null;
            $shipment->save();
            $shipment->touch();

            return ['state' => 'claimed', 'shipment' => $shipment];
        });
    }

    /** @return array{success:bool,message:string,shipment:?AramexShipment,warnings:array<int,string>} */
    private function failClaim(AramexShipment $shipment, string $error): array
    {
        $shipment->update([
            'shipping_status' => AramexShipment::STATUS_FAILED,
            'error_message' => $error,
        ]);

        return $this->shipResult(false, __('admin.aramex.create_failed', ['error' => $error]), $shipment);
    }

    /**
     * paid -> processing -> shipped (with the AWB as tracking number).
     *
     * @return array<int,array{success:bool,message:string}> payment side-effect notes
     */
    private function moveOrderToShipped(Order $order, string $awb): array
    {
        $order->refresh();

        try {
            if ($order->status === Order::STATUS_PAID) {
                $this->orderStatus->transition($order, Order::STATUS_PROCESSING);
            }

            if ($this->orderStatus->canTransition($order, Order::STATUS_SHIPPED)) {
                $this->orderStatus->transition($order, Order::STATUS_SHIPPED, ['tracking_number' => $awb]);

                return $this->orderStatus->notes();
            }
        } catch (InvalidArgumentException $e) {
            Log::warning('Aramex shipment created but order status not updated', [
                'order' => $order->order_number, 'awb' => $awb, 'error' => $e->getMessage(),
            ]);
        }

        return [['success' => false, 'message' => __('admin.aramex.order_not_moved', ['status' => __('orders.status.'.$order->status)])]];
    }

    /** Keep the order in step with a tracking-driven shipment status. */
    private function syncOrderStatus(Order $order, AramexShipment $shipment): void
    {
        $status = $shipment->shipping_status;
        $moving = [AramexShipment::STATUS_IN_TRANSIT, AramexShipment::STATUS_OUT_FOR_DELIVERY, AramexShipment::STATUS_DELIVERED];

        if (! in_array($status, $moving, true)) {
            return;
        }

        try {
            if ($order->status === Order::STATUS_PROCESSING) {
                $this->orderStatus->transition($order, Order::STATUS_SHIPPED, ['tracking_number' => $shipment->awb_number]);
            }

            if ($status === AramexShipment::STATUS_DELIVERED && $this->orderStatus->canTransition($order, Order::STATUS_DELIVERED)) {
                $this->orderStatus->transition($order, Order::STATUS_DELIVERED);
            }
        } catch (InvalidArgumentException $e) {
            Log::warning('Aramex tracking could not update order status', [
                'order' => $order->order_number, 'awb' => $shipment->awb_number, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Aramex consignee from the order's shipping-address snapshot.
     *
     * @return array<string,string|null>
     * @throws InvalidArgumentException when the address can't be shipped to
     */
    private function consigneeFor(Order $order): array
    {
        $a = (array) $order->shipping_address;
        $city = trim((string) ($a['city'] ?? ''));

        if (LocationLookupService::isDemoAddress($a)) {
            throw new InvalidArgumentException(__('admin.aramex.address_demo'));
        }

        if ($city === '') {
            throw new InvalidArgumentException(__('admin.aramex.address_no_city'));
        }

        $street = $this->firstFilled([$a['street_address'] ?? null, $a['formatted_address'] ?? null, $a['district'] ?? null, $city]);
        $unit = trim(implode(' ', array_filter([
            filled($a['building_number'] ?? null) ? 'Bldg '.$a['building_number'] : null,
            filled($a['apartment_number'] ?? null) ? 'Apt '.$a['apartment_number'] : null,
        ])));
        $extra = $this->firstFilled([
            filled($a['location_code'] ?? null) ? 'National Address '.$a['location_code'] : null,
            $a['additional_notes'] ?? null,
        ]);

        return [
            'name' => $this->firstFilled([$a['recipient_name'] ?? null, $order->customer_name]),
            'phone' => $this->firstFilled([$a['phone'] ?? null, $order->customer_phone]),
            'email' => (string) $order->customer_email,
            'line1' => $street,
            'line2' => trim(implode(', ', array_filter([$unit, $a['district'] ?? null])), ', '),
            'line3' => $extra,
            'city' => $city,
            'state' => (string) ($a['region'] ?? ''),
            'post_code' => (string) ($a['postal_code'] ?? ''),
            // Every address the checkout produces without a country is a Saudi National Address.
            'country_code' => strtoupper((string) ($a['country'] ?? '')) ?: 'SA',
        ];
    }

    private function storeLabel(AramexShipment $shipment, ?string $pdf, ?string $url): bool
    {
        $bytes = $pdf ?? ($url !== null ? $this->downloadLabel($url) : null);

        if ($bytes === null || strncmp($bytes, '%PDF', 4) !== 0) {
            Log::warning('Aramex label not stored', ['awb' => $shipment->awb_number, 'had_url' => $url !== null]);

            return false;
        }

        $order = $shipment->order()->first();
        $path = 'aramex-labels/'.($order ? $order->order_number.'-' : '').$shipment->awb_number.'.pdf';

        $this->labelDisk()->put($path, $bytes);
        $shipment->update(['label_path' => $path]);

        return true;
    }

    private function downloadLabel(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = false;

        foreach ((array) config('services.aramex.label_hosts', []) as $domain) {
            $domain = strtolower((string) $domain);
            if ($host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain) {
                $allowed = true;
                break;
            }
        }

        if (($parts['scheme'] ?? '') !== 'https' || ! $allowed) {
            Log::warning('Aramex label URL rejected', ['host' => $host]);

            return null;
        }

        try {
            $response = Http::timeout((int) config('services.aramex.timeout', 25))->get($url);
        } catch (Throwable $e) {
            Log::warning('Aramex label download failed', ['error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * @param array<string,mixed> $event
     * @return array{code:?string,description:string,location:?string,comment:?string,occurred_at:string,source:string,received_at:string}
     */
    private function historyEntry(array $event, string $source): array
    {
        $occurredAt = self::parseAramexDate($event['occurred_at'] ?? null) ?? now()->utc();
        $text = function ($value): ?string {
            $value = trim((string) $value);

            return $value === '' ? null : mb_substr($value, 0, 255);
        };

        return [
            'code' => $text($event['code'] ?? null),
            'description' => (string) $text($event['description'] ?? null),
            'location' => $text($event['location'] ?? null),
            'comment' => $text($event['comment'] ?? null),
            'occurred_at' => $occurredAt->toIso8601String(),
            'source' => $source,
            'received_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @param array<string,mixed> $entry */
    private function eventKey(array $entry): string
    {
        return sha1(strtoupper((string) ($entry['code'] ?? '')).'|'.($entry['description'] ?? '').'|'.($entry['occurred_at'] ?? ''));
    }

    /** @return array{line1:string,line2:?string,city:string,post_code:?string,country_code:string} */
    private function shipperAddress(): array
    {
        $shipper = (array) config('services.aramex.shipper', []);

        return [
            'line1' => (string) ($shipper['line1'] ?? ''),
            'line2' => $shipper['line2'] ?? null,
            'city' => (string) ($shipper['city'] ?? ''),
            'post_code' => $shipper['post_code'] ?? null,
            'country_code' => strtoupper((string) ($shipper['country_code'] ?? 'SA')),
        ];
    }

    private function weightFor(int $units): float
    {
        $perItem = (float) config('services.aramex.default_item_weight_kg', 1.0);

        return round(max(1, $units) * ($perItem > 0 ? $perItem : 1.0), 3);
    }

    /** @param array<int,mixed> $values */
    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<int,string> $warnings
     * @return array{success:bool,message:string,shipment:?AramexShipment,warnings:array<int,string>}
     */
    private function shipResult(bool $success, string $message, ?AramexShipment $shipment = null, array $warnings = []): array
    {
        return ['success' => $success, 'message' => $message, 'shipment' => $shipment, 'warnings' => $warnings];
    }
}
