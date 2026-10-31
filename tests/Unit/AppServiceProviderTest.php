<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * config('services.aramex.base_url') defaults to Aramex's sandbox URL —
 * this guards against a production deploy silently losing its override.
 * The provider's own boot() already ran before a test can change the
 * environment, so this calls it directly rather than booting a fresh app.
 */
class AppServiceProviderTest extends TestCase
{
    public function test_it_logs_critical_when_production_is_pointed_at_the_sandbox_url(): void
    {
        Log::spy();
        app()->instance('env', 'production');
        config(['services.aramex.base_url' => 'https://ws.sbx.aramex.net/ShippingAPI.V2']);

        (new AppServiceProvider(app()))->boot();

        Log::shouldHaveReceived('critical')->once()->withArgs(function (string $message) {
            return Str::contains($message, 'sandbox');
        });
    }

    public function test_it_does_not_log_when_production_is_pointed_at_the_real_url(): void
    {
        Log::spy();
        app()->instance('env', 'production');
        config(['services.aramex.base_url' => 'https://ws.aramex.net/ShippingAPI.V2']);

        (new AppServiceProvider(app()))->boot();

        Log::shouldNotHaveReceived('critical');
    }

    public function test_it_does_not_log_outside_production_even_on_the_sandbox_url(): void
    {
        Log::spy();
        app()->instance('env', 'local');
        config(['services.aramex.base_url' => 'https://ws.sbx.aramex.net/ShippingAPI.V2']);

        (new AppServiceProvider(app()))->boot();

        Log::shouldNotHaveReceived('critical');
    }
}
