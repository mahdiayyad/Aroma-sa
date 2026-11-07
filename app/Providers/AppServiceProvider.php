<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->guardAgainstSandboxAramexInProduction();
    }

    /**
     * config('services.aramex.base_url') defaults to Aramex's sandbox URL —
     * production's .env currently overrides it correctly, but nothing else
     * stops a future redeploy from silently losing that override and quietly
     * shipping against the sandbox instead of the real account. Cheap string
     * check, every request, no IO.
     */
    private function guardAgainstSandboxAramexInProduction(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $baseUrl = (string) config('services.aramex.base_url', '');

        if (preg_match('/sbx|sandbox|\.uat\./i', $baseUrl) === 1) {
            Log::critical('Aramex is configured against a sandbox/UAT URL in production', ['base_url' => $baseUrl]);
        }
    }
}
