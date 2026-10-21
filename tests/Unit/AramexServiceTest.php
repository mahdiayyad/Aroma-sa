<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AramexService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AramexServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.aramex' => array_merge(config('services.aramex'), [
            'base_url' => 'https://ws.dev.aramex.net/ShippingAPI.V2',
            'username' => 'api@aroma.test',
            'password' => 'secret-pass',
            'account_number' => '20016',
            'account_pin' => '331421',
            'account_entity' => 'RUH',
            'account_country_code' => 'SA',
            'shipper' => [
                'name' => 'Aroma', 'company' => 'Aroma Gift Center', 'phone' => '+966500000000',
                'email' => 'ops@aroma.test', 'line1' => 'King Fahd Rd', 'line2' => null,
                'city' => 'Riyadh', 'post_code' => '12211', 'country_code' => 'SA',
            ],
        ])]);
    }

    private function origin(): array
    {
        return ['line1' => 'King Fahd Rd', 'city' => 'Riyadh', 'country_code' => 'SA'];
    }

    private function destination(string $country = 'SA'): array
    {
        return ['line1' => 'Tahlia St', 'city' => 'Jeddah', 'country_code' => $country];
    }

    private function orderData(array $overrides = []): array
    {
        return array_replace_recursive([
            'reference' => 'AR-2026-000042',
            'consignee' => [
                'name' => 'Sara Ali', 'phone' => '+966511111111', 'email' => 'sara@example.com',
                'line1' => 'Tahlia St', 'line2' => 'Bldg 7', 'city' => 'Jeddah', 'country_code' => 'SA',
            ],
            'weight_kg' => 2.0,
            'pieces' => 2,
        ], $overrides);
    }

    public function test_calculate_rate_returns_the_total_and_sends_client_info(): void
    {
        Http::fake(['*/CalculateRate' => Http::response([
            'HasErrors' => false,
            'Notifications' => [],
            'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 27.5],
        ])]);

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 2]);

        $this->assertTrue($result['success']);
        $this->assertSame(27.5, $result['amount']);
        $this->assertSame('SAR', $result['currency']);
        Http::assertSent(function (Request $request) {
            return Str::endsWith($request->url(), '/RateCalculator/Service_1_0.svc/json/CalculateRate')
                && $request['ClientInfo']['AccountPin'] === '331421'
                && $request['ClientInfo']['Version'] === 'v1.0'
                && $request['ShipmentDetails']['PaymentType'] === 'P'
                && $request['ShipmentDetails']['ProductGroup'] === 'DOM'
                && $request['ShipmentDetails']['ActualWeight'] == ['Unit' => 'KG', 'Value' => 2]
                && $request['DestinationAddress']['City'] === 'Jeddah';
        });
    }

    public function test_calculate_rate_supports_collect_payment_and_international_product(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 140]])]);

        (new AramexService())->calculateRate($this->origin(), $this->destination('AE'), ['weight_kg' => 1, 'payment_type' => 'c']);

        Http::assertSent(function (Request $request) {
            return $request['ShipmentDetails']['PaymentType'] === 'C'
                && $request['ShipmentDetails']['ProductGroup'] === 'EXP';
        });
    }

    public function test_an_invalid_payment_type_is_rejected_without_calling_aramex(): void
    {
        Http::fake();

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1, 'payment_type' => 'X']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid payment type', $result['errors'][0]);
        Http::assertNothingSent();
    }

    public function test_has_errors_notifications_become_errors(): void
    {
        Http::fake(['*/CalculateRate' => Http::response([
            'HasErrors' => true,
            'Notifications' => [['Code' => 'ERR52', 'Message' => 'Destination city is invalid']],
        ])]);

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame(['ERR52: Destination city is invalid'], $result['errors']);
    }

    public function test_notifications_without_has_errors_are_informational(): void
    {
        Http::fake(['*/CalculateRate' => Http::response([
            'HasErrors' => false,
            'Notifications' => [['Code' => 'INF01', 'Message' => 'Rate is indicative']],
            'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 30],
        ])]);

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $this->assertTrue($result['success']);
    }

    public function test_an_http_error_without_a_body_is_reported(): void
    {
        Http::fake(['*/CalculateRate' => Http::response('', 500)]);

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame(['Aramex returned HTTP 500.'], $result['errors']);
    }

    public function test_a_connection_failure_is_reported_not_thrown(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: timed out');
        });

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame(['Could not reach Aramex. Please try again.'], $result['errors']);
    }

    public function test_nothing_is_sent_when_not_configured(): void
    {
        config(['services.aramex.password' => null]);
        Http::fake();

        $service = new AramexService();

        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1])['success']);
        $this->assertFalse($service->createShipment($this->orderData())['success']);
        Http::assertNothingSent();
    }

    public function test_create_shipment_builds_the_full_payload_and_returns_the_awb(): void
    {
        Http::fake(['*/CreateShipments' => Http::response([
            'HasErrors' => false,
            'Notifications' => [],
            'Shipments' => [[
                'ID' => '44012345678',
                'Reference1' => 'AR-2026-000042',
                'HasErrors' => false,
                'Notifications' => [],
                'ShipmentLabel' => ['LabelURL' => 'https://ws.dev.aramex.net/content/rpt_cache/label.pdf', 'LabelFileContents' => null],
            ]],
        ])]);

        $result = (new AramexService())->createShipment($this->orderData());

        $this->assertTrue($result['success']);
        $this->assertSame('44012345678', $result['awb']);
        $this->assertSame('https://ws.dev.aramex.net/content/rpt_cache/label.pdf', $result['label_url']);
        $this->assertNull($result['label_pdf']);

        Http::assertSent(function (Request $request) {
            $shipment = $request['Shipments'][0];

            return Str::endsWith($request->url(), '/Shipping/Service_1_0.svc/json/CreateShipments')
                && $request['ClientInfo']['UserName'] === 'api@aroma.test'
                && $request['LabelInfo'] === ['ReportID' => 9729, 'ReportType' => 'URL']
                && $shipment['Details']['ProductType'] === 'CDS'
                && $shipment['Details']['GoodsOriginCountry'] === 'SA'
                && $shipment['Details']['PaymentOptions'] === 'ACCT'
                && $shipment['Shipper']['AccountNumber'] === '20016'
                && $shipment['Shipper']['PartyAddress']['City'] === 'Riyadh'
                && $shipment['Shipper']['Contact']['CompanyName'] === 'Aroma Gift Center'
                && $shipment['Consignee']['Contact']['PersonName'] === 'Sara Ali'
                && $shipment['Consignee']['PartyAddress']['Line2'] === 'Bldg 7'
                && $shipment['Details']['NumberOfPieces'] === 2
                && $shipment['Details']['ProductGroup'] === 'DOM'
                && $shipment['Details']['PaymentType'] === 'P'
                && ! isset($shipment['Details']['CustomsValueAmount'])
                && preg_match('#^/Date\(\d+\)/$#', $shipment['ShippingDateTime'])
                // WCF wants the escaped form on the wire.
                && strpos($request->body(), '"\/Date(') !== false;
        });
    }

    /**
     * Aramex's WCF service rejects a request outright when any contract member
     * is missing, even an unused one ("required data members ... were not
     * found"). Key sets below are Aramex's own Postman collection.
     */
    public function test_requests_carry_every_member_aramex_requires(): void
    {
        Http::fake([
            '*/CreateShipments' => Http::response(['HasErrors' => false, 'Shipments' => [['ID' => '1', 'HasErrors' => false]]]),
            '*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 1]]),
        ]);

        $service = new AramexService();
        $service->createShipment($this->orderData());
        $service->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $address = ['Line1', 'Line2', 'Line3', 'City', 'StateOrProvinceCode', 'PostCode', 'CountryCode',
            'Longitude', 'Latitude', 'BuildingNumber', 'BuildingName', 'Floor', 'Apartment', 'POBox', 'Description'];
        $contact = ['Department', 'PersonName', 'Title', 'CompanyName', 'PhoneNumber1', 'PhoneNumber1Ext',
            'PhoneNumber2', 'PhoneNumber2Ext', 'FaxNumber', 'CellPhone', 'EmailAddress', 'Type'];
        $shipment = ['Reference1', 'Reference2', 'Reference3', 'Shipper', 'Consignee', 'ThirdParty', 'ShippingDateTime',
            'DueDate', 'Comments', 'PickupLocation', 'OperationsInstructions', 'AccountingInstrcutions', 'Details',
            'Attachments', 'ForeignHAWB', 'TransportType ', 'PickupGUID', 'Number', 'ScheduledDelivery'];
        $details = ['Dimensions', 'ActualWeight', 'ChargeableWeight', 'DescriptionOfGoods', 'GoodsOriginCountry',
            'NumberOfPieces', 'ProductGroup', 'ProductType', 'PaymentType', 'PaymentOptions', 'CustomsValueAmount',
            'CashOnDeliveryAmount', 'InsuranceAmount', 'CashAdditionalAmount', 'CashAdditionalAmountDescription',
            'CollectAmount', 'Services', 'Items', 'DeliveryInstructions', 'AdditionalProperties', 'ContainsDangerousGoods'];
        $clientInfo = ['UserName', 'Password', 'Version', 'AccountNumber', 'AccountPin', 'AccountEntity',
            'AccountCountryCode', 'Source', 'PreferredLanguageCode'];

        $create = Http::recorded(function (Request $r) {
            return Str::endsWith($r->url(), '/CreateShipments');
        })->first()[0]->data();
        $rate = Http::recorded(function (Request $r) {
            return Str::endsWith($r->url(), '/CalculateRate');
        })->first()[0]->data();

        $s = $create['Shipments'][0];
        $this->assertEqualsCanonicalizing($clientInfo, array_keys($create['ClientInfo']));
        $this->assertEqualsCanonicalizing($shipment, array_keys($s));
        $this->assertEqualsCanonicalizing($details, array_keys($s['Details']));
        foreach (['Shipper', 'Consignee'] as $party) {
            $this->assertEqualsCanonicalizing(['Reference1', 'Reference2', 'AccountNumber', 'PartyAddress', 'Contact'], array_keys($s[$party]));
            $this->assertEqualsCanonicalizing($address, array_keys($s[$party]['PartyAddress']));
            $this->assertEqualsCanonicalizing($contact, array_keys($s[$party]['Contact']));
        }
        $this->assertEqualsCanonicalizing($address, array_keys($rate['OriginAddress']));
        $this->assertEqualsCanonicalizing($address, array_keys($rate['DestinationAddress']));
        $this->assertEqualsCanonicalizing($details, array_keys($rate['ShipmentDetails']));
    }

    public function test_an_unparseable_request_page_is_logged_with_its_reason(): void
    {
        Http::fake(['*/CalculateRate' => Http::response(
            '<html><head><style>p{}</style></head><body><p>Request Error</p><p>required data members &#39;Line2&#39; were not found</p></body></html>',
            400
        )]);
        \Illuminate\Support\Facades\Log::spy();

        $result = (new AramexService())->calculateRate($this->origin(), $this->destination(), ['weight_kg' => 1]);

        $this->assertSame(['Aramex returned HTTP 400.'], $result['errors']);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return $message === 'Aramex returned errors'
                && strpos((string) $context['detail'], "required data members 'Line2' were not found") !== false;
        });
    }

    public function test_international_shipments_carry_a_customs_value(): void
    {
        Http::fake(['*/CreateShipments' => Http::response(['HasErrors' => false, 'Shipments' => [['ID' => '1', 'HasErrors' => false]]])]);

        (new AramexService())->createShipment($this->orderData(['consignee' => ['country_code' => 'AE', 'city' => 'Dubai'], 'customs_value' => 450]));

        Http::assertSent(function (Request $request) {
            $details = $request['Shipments'][0]['Details'];

            return $details['ProductGroup'] === 'EXP'
                && $details['CustomsValueAmount'] == ['CurrencyCode' => 'SAR', 'Value' => 450];
        });
    }

    public function test_a_per_shipment_error_fails_the_call(): void
    {
        Http::fake(['*/CreateShipments' => Http::response([
            'HasErrors' => true,
            'Notifications' => [],
            'Shipments' => [[
                'ID' => null,
                'HasErrors' => true,
                'Notifications' => [['Code' => 'ERR03', 'Message' => 'Consignee phone is invalid']],
            ]],
        ])]);

        $result = (new AramexService())->createShipment($this->orderData());

        $this->assertFalse($result['success']);
        $this->assertSame(['ERR03: Consignee phone is invalid'], $result['errors']);
    }

    public function test_missing_consignee_fields_fail_before_calling_aramex(): void
    {
        Http::fake();

        $result = (new AramexService())->createShipment($this->orderData(['consignee' => ['city' => '']]));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('consignee city', $result['errors'][0]);
        Http::assertNothingSent();
    }

    public function test_label_file_contents_as_a_byte_array_are_decoded(): void
    {
        $pdf = "%PDF-1.4\nlabel";
        Http::fake(['*/CreateShipments' => Http::response([
            'HasErrors' => false,
            'Shipments' => [[
                'ID' => '440999',
                'HasErrors' => false,
                'ShipmentLabel' => ['LabelURL' => null, 'LabelFileContents' => array_map('ord', str_split($pdf))],
            ]],
        ])]);

        $result = (new AramexService())->createShipment($this->orderData());

        $this->assertSame($pdf, $result['label_pdf']);
        $this->assertNull($result['label_url']);
    }

    public function test_track_shipments_groups_updates_by_awb(): void
    {
        Http::fake(['*/TrackShipments' => Http::response([
            'HasErrors' => false,
            'TrackingResults' => [[
                'Key' => '440123',
                'Value' => [['WaybillNumber' => '440123', 'UpdateCode' => 'SH005', 'UpdateDescription' => 'Delivered']],
            ]],
        ])]);

        $result = (new AramexService())->trackShipments(['440123']);

        $this->assertTrue($result['success']);
        $this->assertSame('SH005', $result['results']['440123'][0]['UpdateCode']);
        Http::assertSent(function (Request $request) {
            return $request['Shipments'] === ['440123'] && $request['GetLastTrackingUpdateOnly'] === false;
        });
    }
}
