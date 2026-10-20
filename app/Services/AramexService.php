<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin client for Aramex's JSON Shipping Services API (v1.0).
 *
 * Every public method returns a predictable array and never throws:
 *   success: ['success' => true, ...method-specific keys]
 *   failure: ['success' => false, 'errors' => string[]]
 *
 * Aramex reports most problems inside a 200 response (HasErrors plus a
 * Notifications list, also nested per shipment), so those are checked
 * alongside transport and HTTP errors. Request payloads are never logged:
 * they carry the account password and PIN in ClientInfo.
 */
class AramexService
{
    public const PAYMENT_PREPAID = 'P';
    public const PAYMENT_COLLECT = 'C';

    private const PATH_RATE = '/RateCalculator/Service_1_0.svc/json/CalculateRate';
    private const PATH_CREATE_SHIPMENTS = '/Shipping/Service_1_0.svc/json/CreateShipments';
    private const PATH_PRINT_LABEL = '/Shipping/Service_1_0.svc/json/PrintLabel';
    private const PATH_TRACK = '/Tracking/Service_1_0.svc/json/TrackShipments';

    /** @var array<string,mixed> */
    private $config;

    /** @param array<string,mixed>|null $config defaults to config('services.aramex') */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('services.aramex', []);
    }

    public function isConfigured(): bool
    {
        foreach (['username', 'password', 'account_number', 'account_pin', 'account_entity'] as $key) {
            if (blank($this->config[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Price a shipment.
     *
     * @param array<string,mixed> $origin      line1, line2?, city, country_code, post_code?
     * @param array<string,mixed> $destination same keys as $origin
     * @param array<string,mixed> $packageDetails
     *        weight_kg (required, > 0), pieces (default 1),
     *        payment_type ('P' prepaid by Aroma | 'C' collect from consignee, default 'P'),
     *        product_group / product_type (default: domestic or international from config),
     *        currency (default config currency)
     *
     * @return array{success:bool,amount?:float,currency?:string,product_group?:string,product_type?:string,errors?:array<int,string>}
     */
    public function calculateRate(array $origin, array $destination, array $packageDetails): array
    {
        if (! $this->isConfigured()) {
            return $this->failure(['Aramex is not configured.']);
        }

        $paymentType = strtoupper((string) ($packageDetails['payment_type'] ?? self::PAYMENT_PREPAID));
        if (! in_array($paymentType, [self::PAYMENT_PREPAID, self::PAYMENT_COLLECT], true)) {
            return $this->failure(["Invalid payment type \"{$paymentType}\"; expected P or C."]);
        }

        $weight = (float) ($packageDetails['weight_kg'] ?? 0);
        if ($weight <= 0) {
            return $this->failure(['Package weight must be greater than zero.']);
        }

        if (blank($origin['city'] ?? null) || blank($destination['city'] ?? null)) {
            return $this->failure(['Origin and destination cities are required.']);
        }

        $product = $this->productFor($origin, $destination, $packageDetails);
        $currency = (string) ($packageDetails['currency'] ?? $this->config['currency'] ?? 'SAR');

        $response = $this->request(self::PATH_RATE, [
            'ClientInfo' => $this->clientInfo(),
            'Transaction' => $this->transaction((string) ($packageDetails['reference'] ?? '')),
            'OriginAddress' => $this->address($origin),
            'DestinationAddress' => $this->address($destination),
            'ShipmentDetails' => [
                'PaymentType' => $paymentType,
                'ProductGroup' => $product['group'],
                'ProductType' => $product['type'],
                'ActualWeight' => $this->weight($weight),
                'ChargeableWeight' => $this->weight($weight),
                'NumberOfPieces' => max(1, (int) ($packageDetails['pieces'] ?? 1)),
            ],
            'PreferredCurrencyCode' => $currency,
        ]);

        if (! $response['success']) {
            return $response;
        }

        $total = $response['body']['TotalAmount'] ?? null;
        if (! is_array($total) || ! is_numeric($total['Value'] ?? null)) {
            return $this->failure(['Aramex returned no rate.']);
        }

        return [
            'success' => true,
            'amount' => round((float) $total['Value'], 2),
            'currency' => (string) ($total['CurrencyCode'] ?? $currency),
            'product_group' => $product['group'],
            'product_type' => $product['type'],
        ];
    }

    /**
     * Create one shipment and ask for its PDF label.
     *
     * @param array<string,mixed> $orderData
     *        reference (required, e.g. the order number),
     *        consignee: name, phone, email?, line1, line2?, line3?, city, country_code, post_code?, state?
     *        weight_kg (> 0), pieces (default 1), description?, customs_value? (international only),
     *        payment_type ('P' | 'C', default 'P'), product_group?, product_type?,
     *        shipping_date? (CarbonInterface, default now)
     *
     * @return array{success:bool,awb?:string,product_group?:string,product_type?:string,label_url?:string|null,label_pdf?:string|null,errors?:array<int,string>}
     */
    public function createShipment(array $orderData): array
    {
        if (! $this->isConfigured()) {
            return $this->failure(['Aramex is not configured.']);
        }

        $reference = (string) ($orderData['reference'] ?? '');
        $consignee = (array) ($orderData['consignee'] ?? []);
        $shipper = (array) ($this->config['shipper'] ?? []);
        $weight = (float) ($orderData['weight_kg'] ?? 0);
        $paymentType = strtoupper((string) ($orderData['payment_type'] ?? self::PAYMENT_PREPAID));

        $missing = [];
        foreach (['name', 'phone', 'line1', 'city', 'country_code'] as $field) {
            if (blank($consignee[$field] ?? null)) {
                $missing[] = "consignee {$field}";
            }
        }
        foreach (['phone', 'line1', 'city', 'country_code'] as $field) {
            if (blank($shipper[$field] ?? null)) {
                $missing[] = "shipper {$field} (ARAMEX_SHIPPER_* in .env)";
            }
        }
        if ($reference === '') {
            $missing[] = 'reference';
        }
        if ($missing !== []) {
            return $this->failure(['Missing '.implode(', ', $missing).'.']);
        }
        if ($weight <= 0) {
            return $this->failure(['Package weight must be greater than zero.']);
        }
        if (! in_array($paymentType, [self::PAYMENT_PREPAID, self::PAYMENT_COLLECT], true)) {
            return $this->failure(["Invalid payment type \"{$paymentType}\"; expected P or C."]);
        }

        $shipperAddress = [
            'line1' => $shipper['line1'],
            'line2' => $shipper['line2'] ?? null,
            'city' => $shipper['city'],
            'post_code' => $shipper['post_code'] ?? null,
            'country_code' => $shipper['country_code'],
        ];
        $product = $this->productFor($shipperAddress, $consignee, $orderData);
        $shippingDate = $orderData['shipping_date'] ?? now();
        $currency = (string) ($this->config['currency'] ?? 'SAR');

        $details = [
            'Dimensions' => null,
            'ActualWeight' => $this->weight($weight),
            'ChargeableWeight' => $this->weight($weight),
            'DescriptionOfGoods' => Str::limit((string) ($orderData['description'] ?? $this->config['description_of_goods'] ?? 'Goods'), 100, ''),
            'GoodsOriginCountry' => strtoupper((string) $shipper['country_code']),
            'NumberOfPieces' => max(1, (int) ($orderData['pieces'] ?? 1)),
            'ProductGroup' => $product['group'],
            'ProductType' => $product['type'],
            'PaymentType' => $paymentType,
            'PaymentOptions' => '',
            'Services' => '',
            'Items' => [],
        ];

        if ($product['group'] === 'EXP' && is_numeric($orderData['customs_value'] ?? null)) {
            $details['CustomsValueAmount'] = [
                'CurrencyCode' => $currency,
                'Value' => round((float) $orderData['customs_value'], 2),
            ];
        }

        $response = $this->request(self::PATH_CREATE_SHIPMENTS, [
            'ClientInfo' => $this->clientInfo(),
            'Transaction' => $this->transaction($reference),
            'LabelInfo' => $this->labelInfo(),
            'Shipments' => [[
                'Reference1' => $reference,
                'Shipper' => [
                    'Reference1' => $reference,
                    'AccountNumber' => (string) $this->config['account_number'],
                    'PartyAddress' => $this->address($shipperAddress),
                    'Contact' => $this->contact([
                        'name' => $shipper['name'] ?? $shipper['company'] ?? 'Aroma',
                        'company' => $shipper['company'] ?? null,
                        'phone' => $shipper['phone'],
                        'email' => $shipper['email'] ?? null,
                    ]),
                ],
                'Consignee' => [
                    'Reference1' => $reference,
                    'PartyAddress' => $this->address($consignee),
                    'Contact' => $this->contact($consignee),
                ],
                'ShippingDateTime' => $this->wcfDate($shippingDate),
                'DueDate' => $this->wcfDate($shippingDate->copy()->addDays(2)),
                'Details' => $details,
            ]],
        ], $reference);

        if (! $response['success']) {
            return $response;
        }

        $shipment = $response['body']['Shipments'][0] ?? null;
        $awb = is_array($shipment) ? trim((string) ($shipment['ID'] ?? '')) : '';
        if ($awb === '') {
            Log::error('Aramex CreateShipments returned no AWB', ['reference' => $reference]);

            return $this->failure(['Aramex did not return a waybill number.']);
        }

        $label = (array) ($shipment['ShipmentLabel'] ?? []);

        return [
            'success' => true,
            'awb' => $awb,
            'product_group' => $product['group'],
            'product_type' => $product['type'],
            'label_url' => filled($label['LabelURL'] ?? null) ? (string) $label['LabelURL'] : null,
            'label_pdf' => $this->decodeLabelContents($label['LabelFileContents'] ?? null),
        ];
    }

    /**
     * Re-request the label of an existing shipment.
     *
     * @return array{success:bool,label_url?:string|null,label_pdf?:string|null,errors?:array<int,string>}
     */
    public function printLabel(string $awb, string $productGroup = 'DOM'): array
    {
        if (! $this->isConfigured()) {
            return $this->failure(['Aramex is not configured.']);
        }

        $response = $this->request(self::PATH_PRINT_LABEL, [
            'ClientInfo' => $this->clientInfo(),
            'Transaction' => $this->transaction($awb),
            'ShipmentNumber' => $awb,
            'ProductGroup' => $productGroup,
            'OriginEntity' => (string) $this->config['account_entity'],
            'LabelInfo' => $this->labelInfo(),
        ], $awb);

        if (! $response['success']) {
            return $response;
        }

        $label = (array) ($response['body']['ShipmentLabel'] ?? []);
        $url = filled($label['LabelURL'] ?? null) ? (string) $label['LabelURL'] : null;
        $pdf = $this->decodeLabelContents($label['LabelFileContents'] ?? null);

        if ($url === null && $pdf === null) {
            return $this->failure(['Aramex returned no label.']);
        }

        return ['success' => true, 'label_url' => $url, 'label_pdf' => $pdf];
    }

    /**
     * Full tracking history for up to 50 waybills.
     *
     * @param array<int,string> $awbs
     * @return array{success:bool,results?:array<string,array<int,array<string,mixed>>>,errors?:array<int,string>}
     */
    public function trackShipments(array $awbs): array
    {
        if (! $this->isConfigured()) {
            return $this->failure(['Aramex is not configured.']);
        }

        $awbs = array_values(array_unique(array_filter(array_map('strval', $awbs))));
        if ($awbs === []) {
            return ['success' => true, 'results' => []];
        }

        $response = $this->request(self::PATH_TRACK, [
            'ClientInfo' => $this->clientInfo(),
            'Transaction' => $this->transaction('tracking'),
            'Shipments' => $awbs,
            'GetLastTrackingUpdateOnly' => false,
        ]);

        if (! $response['success']) {
            return $response;
        }

        $results = [];
        foreach ((array) ($response['body']['TrackingResults'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = (string) ($entry['Key'] ?? '');
            if ($key !== '') {
                $results[$key] = array_values(array_filter((array) ($entry['Value'] ?? []), 'is_array'));
            }
        }

        return ['success' => true, 'results' => $results];
    }

    /** @return array<string,mixed> the mandatory ClientInfo block */
    public function clientInfo(): array
    {
        return [
            'UserName' => (string) ($this->config['username'] ?? ''),
            'Password' => (string) ($this->config['password'] ?? ''),
            'Version' => (string) ($this->config['version'] ?? 'v1.0'),
            'AccountNumber' => (string) ($this->config['account_number'] ?? ''),
            'AccountPin' => (string) ($this->config['account_pin'] ?? ''),
            'AccountEntity' => (string) ($this->config['account_entity'] ?? ''),
            'AccountCountryCode' => (string) ($this->config['account_country_code'] ?? 'SA'),
            'Source' => (int) ($this->config['source'] ?? 24),
        ];
    }

    /**
     * POST to Aramex and normalise every failure mode into errors.
     *
     * @param array<string,mixed> $payload
     * @return array{success:bool,body?:array<string,mixed>,errors?:array<int,string>}
     */
    private function request(string $path, array $payload, string $reference = ''): array
    {
        $url = rtrim((string) ($this->config['base_url'] ?? ''), '/').$path;

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 25))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Aramex connection failed', ['path' => $path, 'reference' => $reference, 'error' => $e->getMessage()]);

            return $this->failure(['Could not reach Aramex. Please try again.']);
        } catch (Throwable $e) {
            Log::error('Aramex request failed', ['path' => $path, 'reference' => $reference, 'error' => $e->getMessage()]);

            return $this->failure(['Aramex request failed: '.$e->getMessage()]);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $errors = $this->errorsFrom($body);

        if ($response->failed() && $errors === []) {
            $errors[] = 'Aramex returned HTTP '.$response->status().'.';
        }
        if ($errors === [] && $body === []) {
            $errors[] = 'Aramex returned an unreadable response.';
        }

        if ($errors !== []) {
            Log::warning('Aramex returned errors', [
                'path' => $path,
                'reference' => $reference,
                'status' => $response->status(),
                'errors' => $errors,
            ]);

            return $this->failure($errors);
        }

        return ['success' => true, 'body' => $body];
    }

    /**
     * Collect "Code: Message" strings from the top-level HasErrors /
     * Notifications and from each nested shipment's own.
     *
     * @param array<string,mixed> $body
     * @return array<int,string>
     */
    private function errorsFrom(array $body): array
    {
        // Without HasErrors, Notifications are informational only.
        $hasErrors = ! empty($body['HasErrors']);
        $errors = $hasErrors ? $this->notificationMessages($body['Notifications'] ?? []) : [];

        foreach ((array) ($body['Shipments'] ?? []) as $shipment) {
            if (is_array($shipment) && ! empty($shipment['HasErrors'])) {
                $hasErrors = true;
                $errors = array_merge($errors, $this->notificationMessages($shipment['Notifications'] ?? []));
            }
        }

        if ($hasErrors && $errors === []) {
            $errors[] = 'Aramex reported an error without details.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param mixed $notifications
     * @return array<int,string>
     */
    private function notificationMessages($notifications): array
    {
        $messages = [];

        foreach ((array) $notifications as $notification) {
            if (! is_array($notification)) {
                continue;
            }
            $code = trim((string) ($notification['Code'] ?? ''));
            $message = trim((string) ($notification['Message'] ?? ''));
            if ($code !== '' || $message !== '') {
                $messages[] = $code !== '' && $message !== '' ? "{$code}: {$message}" : ($message !== '' ? $message : $code);
            }
        }

        return $messages;
    }

    /**
     * @param array<string,mixed> $origin
     * @param array<string,mixed> $destination
     * @param array<string,mixed> $overrides
     * @return array{group:string,type:string}
     */
    private function productFor(array $origin, array $destination, array $overrides): array
    {
        $domestic = strtoupper((string) ($origin['country_code'] ?? ''))
            === strtoupper((string) ($destination['country_code'] ?? ''));
        $defaults = (array) ($this->config[$domestic ? 'domestic' : 'international'] ?? []);

        return [
            'group' => (string) ($overrides['product_group'] ?? $defaults['product_group'] ?? ($domestic ? 'DOM' : 'EXP')),
            'type' => (string) ($overrides['product_type'] ?? $defaults['product_type'] ?? ($domestic ? 'ONP' : 'PPX')),
        ];
    }

    /**
     * @param array<string,mixed> $a line1, line2?, line3?, city, state?, post_code?, country_code
     * @return array<string,string>
     */
    private function address(array $a): array
    {
        return [
            'Line1' => Str::limit((string) ($a['line1'] ?? ''), 50, ''),
            'Line2' => Str::limit((string) ($a['line2'] ?? ''), 50, ''),
            'Line3' => Str::limit((string) ($a['line3'] ?? ''), 50, ''),
            'City' => (string) ($a['city'] ?? ''),
            'StateOrProvinceCode' => (string) ($a['state'] ?? ''),
            'PostCode' => (string) ($a['post_code'] ?? ''),
            'CountryCode' => strtoupper((string) ($a['country_code'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $c name, company?, phone, email?
     * @return array<string,string>
     */
    private function contact(array $c): array
    {
        $name = (string) ($c['name'] ?? '');
        $phone = (string) ($c['phone'] ?? '');

        return [
            'PersonName' => Str::limit($name, 45, ''),
            'CompanyName' => Str::limit((string) ($c['company'] ?? $name), 45, ''),
            'PhoneNumber1' => $phone,
            'CellPhone' => $phone,
            'EmailAddress' => (string) ($c['email'] ?? ''),
        ];
    }

    /** @return array{Unit:string,Value:float} */
    private function weight(float $kg): array
    {
        return ['Unit' => 'KG', 'Value' => round($kg, 3)];
    }

    /** @return array<string,string> */
    private function transaction(string $reference): array
    {
        return [
            'Reference1' => Str::limit($reference, 50, ''),
            'Reference2' => '',
            'Reference3' => '',
            'Reference4' => '',
            'Reference5' => '',
        ];
    }

    /** @return array{ReportID:int,ReportType:string} */
    private function labelInfo(): array
    {
        return [
            'ReportID' => (int) ($this->config['label_report_id'] ?? 9201),
            'ReportType' => 'URL',
        ];
    }

    /**
     * Aramex's WCF JSON date: "/Date(<ms since epoch>)/". json_encode escapes
     * the slashes ("\/Date(...)\/"), which is the form WCF expects.
     */
    private function wcfDate(CarbonInterface $date): string
    {
        return '/Date('.($date->getTimestamp() * 1000).')/';
    }

    /**
     * LabelFileContents arrives either as a JSON byte array (WCF's byte[]
     * serialisation) or as base64. Anything else, or anything that isn't a
     * PDF, is treated as absent.
     *
     * @param mixed $contents
     */
    private function decodeLabelContents($contents): ?string
    {
        $bytes = null;

        if (is_array($contents) && $contents !== []) {
            $bytes = implode('', array_map(function ($byte): string {
                return chr(((int) $byte) & 0xFF);
            }, $contents));
        } elseif (is_string($contents) && $contents !== '') {
            $decoded = base64_decode($contents, true);
            $bytes = $decoded === false ? null : $decoded;
        }

        return $bytes !== null && strncmp($bytes, '%PDF', 4) === 0 ? $bytes : null;
    }

    /**
     * @param array<int,string> $errors
     * @return array{success:false,errors:array<int,string>}
     */
    private function failure(array $errors): array
    {
        return ['success' => false, 'errors' => $errors];
    }
}
