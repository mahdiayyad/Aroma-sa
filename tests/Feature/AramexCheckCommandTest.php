<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AramexCheckCommandTest extends TestCase
{
    private function configure(array $overrides = []): void
    {
        config(['services.aramex' => array_merge(config('services.aramex'), [
            'base_url' => 'http://ws.uat.aramex.net/shippingapi',
            'username' => 'api@aroma.test',
            'password' => 'secret-pass',
            'account_number' => '20016',
            'account_pin' => '331421',
            'account_entity' => 'JED',
            'shipper' => ['line1' => 'Omar Bin Al Khattab St', 'city' => 'Dammam', 'country_code' => 'SA'],
        ], $overrides)]);
    }

    public function test_it_reports_a_successful_rate_without_printing_credentials(): void
    {
        $this->configure();
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => false, 'TotalAmount' => ['CurrencyCode' => 'SAR', 'Value' => 27.5]])]);

        $this->artisan('aramex:check', ['--city' => 'Riyadh'])
            ->expectsOutput('Endpoint: http://ws.uat.aramex.net/shippingapi')
            ->expectsOutput('Account entity / country: JED / SA')
            ->expectsOutput('Connected. Dammam -> Riyadh, 1 kg: 27.50 SAR (DOM/CDS).')
            ->doesntExpectOutput('secret-pass')
            ->assertExitCode(0);
    }

    public function test_it_fails_clearly_when_credentials_are_missing(): void
    {
        $this->configure(['username' => null]);
        Http::fake();

        $this->artisan('aramex:check')
            ->expectsOutput('Aramex is not configured: set ARAMEX_USERNAME, ARAMEX_PASSWORD, ARAMEX_ACCOUNT_NUMBER, ARAMEX_ACCOUNT_PIN and ARAMEX_ACCOUNT_ENTITY.')
            ->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_it_reports_an_aramex_rejection(): void
    {
        $this->configure();
        Http::fake(['*/CalculateRate' => Http::response(['HasErrors' => true, 'Notifications' => [['Code' => 'ERR01', 'Message' => 'Invalid username or password']]])]);

        $this->artisan('aramex:check')
            ->expectsOutput('Aramex rejected the request: ERR01: Invalid username or password')
            ->assertExitCode(1);
    }
}
