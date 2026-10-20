<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AramexShipment;
use App\Services\AramexFulfilmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aramex push tracking updates (server-to-server).
 *
 * Authentication: the shared ARAMEX_WEBHOOK_SECRET, sent as the
 * X-Aramex-Token header, a Bearer token, or ?token=. With no secret
 * configured every request is rejected.
 *
 * Payload: Aramex configures push notifications per account, so the field
 * names of its tracking API are accepted in any letter case — WaybillNumber
 * (or AWB / ShipmentNumber), UpdateCode, UpdateDescription, UpdateDateTime,
 * UpdateLocation, Comments — as a single update, a list of updates, or a
 * TrackingResults [{Key, Value: [...]}] wrapper.
 *
 * Once authenticated it always answers 200, like the payment webhooks: a
 * non-2xx only triggers redelivery, and failures are already logged.
 */
class AramexWebhookController extends Controller
{
    private const AWB_KEYS = ['waybillnumber', 'awb', 'awbnumber', 'shipmentnumber', 'waybill'];
    private const CODE_KEYS = ['updatecode', 'statuscode', 'code'];
    private const DESCRIPTION_KEYS = ['updatedescription', 'statusdescription', 'description', 'status'];
    private const DATE_KEYS = ['updatedatetime', 'updatedate', 'datetime', 'timestamp'];
    private const LOCATION_KEYS = ['updatelocation', 'location'];
    private const COMMENT_KEYS = ['comments', 'comment'];
    private const LIST_KEYS = ['updates', 'shipments', 'events', 'data'];

    /** @var AramexFulfilmentService */
    private $fulfilment;

    public function __construct(AramexFulfilmentService $fulfilment)
    {
        $this->fulfilment = $fulfilment;
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->authenticated($request)) {
            Log::warning('Aramex webhook authentication failed', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->except('token');
        }

        try {
            $eventsByAwb = $this->extractEvents($payload);

            if ($eventsByAwb === []) {
                Log::warning('Aramex webhook carried no recognisable tracking update', [
                    'payload' => mb_substr((string) json_encode($payload), 0, 2000),
                ]);

                return response()->json(['received' => 0, 'updated' => 0]);
            }

            $updated = 0;
            $unknown = [];

            foreach ($eventsByAwb as $awb => $events) {
                $shipment = AramexShipment::query()->where('awb_number', (string) $awb)->first();

                if (! $shipment) {
                    $unknown[] = (string) $awb;
                    continue;
                }

                if ($this->fulfilment->applyTrackingEvents($shipment, $events, 'webhook')) {
                    $updated++;
                }
            }

            Log::info('Aramex webhook processed', [
                'awbs' => array_keys($eventsByAwb),
                'updated' => $updated,
                'unknown' => $unknown,
            ]);

            return response()->json(['received' => count($eventsByAwb), 'updated' => $updated]);
        } catch (Throwable $e) {
            Log::error('Aramex webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => mb_substr((string) json_encode($payload), 0, 2000),
            ]);

            return response()->json(['message' => 'Processed'], 200);
        }
    }

    private function authenticated(Request $request): bool
    {
        $secret = (string) config('services.aramex.webhook_secret', '');
        $token = (string) ($request->header('X-Aramex-Token') ?: $request->bearerToken() ?: $request->query('token', ''));

        return $secret !== '' && $token !== '' && hash_equals($secret, $token);
    }

    /**
     * @param array<mixed> $payload
     * @return array<string,array<int,array<string,?string>>> events grouped by AWB
     */
    private function extractEvents(array $payload): array
    {
        $byAwb = [];
        $tracking = $this->value($payload, ['trackingresults']);

        if (is_array($tracking)) {
            foreach ($tracking as $result) {
                if (! is_array($result)) {
                    continue;
                }
                $key = $this->text($this->value($result, ['key']));
                foreach ((array) $this->value($result, ['value']) as $update) {
                    if (is_array($update)) {
                        $this->collect($byAwb, $update, $key);
                    }
                }
            }

            return $byAwb;
        }

        $wrapped = $this->value($payload, self::LIST_KEYS);
        if (is_array($wrapped)) {
            $updates = $this->isList($wrapped) ? $wrapped : [$wrapped];
        } else {
            $updates = $this->isList($payload) ? $payload : [$payload];
        }

        foreach ($updates as $update) {
            if (is_array($update)) {
                $this->collect($byAwb, $update, null);
            }
        }

        return $byAwb;
    }

    /**
     * @param array<string,array<int,array<string,?string>>> $byAwb
     * @param array<mixed> $update
     */
    private function collect(array &$byAwb, array $update, ?string $fallbackAwb): void
    {
        $awb = $this->text($this->value($update, self::AWB_KEYS)) ?? $fallbackAwb;
        $description = $this->text($this->value($update, self::DESCRIPTION_KEYS));
        $code = $this->text($this->value($update, self::CODE_KEYS));

        if ($awb === null || ($description === null && $code === null)) {
            return;
        }

        $byAwb[$awb][] = [
            'code' => $code,
            'description' => $description,
            'occurred_at' => $this->text($this->value($update, self::DATE_KEYS)),
            'location' => $this->text($this->value($update, self::LOCATION_KEYS)),
            'comment' => $this->text($this->value($update, self::COMMENT_KEYS)),
        ];
    }

    /**
     * Case-insensitive lookup of the first present key.
     *
     * @param array<mixed> $data
     * @param array<int,string> $keys lowercase
     * @return mixed
     */
    private function value(array $data, array $keys)
    {
        $lower = array_change_key_case($data, CASE_LOWER);

        foreach ($keys as $key) {
            if (array_key_exists($key, $lower) && $lower[$key] !== null && $lower[$key] !== '') {
                return $lower[$key];
            }
        }

        return null;
    }

    /** @param mixed $value */
    private function text($value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<mixed> $array */
    private function isList(array $array): bool
    {
        return $array !== [] && array_keys($array) === range(0, count($array) - 1);
    }
}
