<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AramexService;
use Illuminate\Console\Command;

/**
 * Live connectivity check: prices a 1 kg domestic parcel from the configured
 * shipper address. Nothing is created on the Aramex account. Credentials are
 * never printed.
 */
class AramexCheckCommand extends Command
{
    protected $signature = 'aramex:check {--city= : Destination city (default: the shipper city)}';

    protected $description = 'Check the Aramex credentials and connection with a rate request';

    public function handle(AramexService $aramex): int
    {
        $config = (array) config('services.aramex', []);
        $this->line('Endpoint: '.($config['base_url'] ?? '(none)'));
        $this->line('Account entity / country: '.($config['account_entity'] ?? '?').' / '.($config['account_country_code'] ?? '?'));

        if (! $aramex->isConfigured()) {
            $this->error('Aramex is not configured: set ARAMEX_USERNAME, ARAMEX_PASSWORD, ARAMEX_ACCOUNT_NUMBER, ARAMEX_ACCOUNT_PIN and ARAMEX_ACCOUNT_ENTITY.');

            return self::FAILURE;
        }

        $shipper = (array) ($config['shipper'] ?? []);
        if (blank($shipper['city'] ?? null)) {
            $this->error('ARAMEX_SHIPPER_CITY is not set.');

            return self::FAILURE;
        }

        $origin = [
            'line1' => (string) ($shipper['line1'] ?? ''),
            'city' => (string) $shipper['city'],
            'post_code' => (string) ($shipper['post_code'] ?? ''),
            'country_code' => strtoupper((string) ($shipper['country_code'] ?? 'SA')),
        ];
        $destination = array_merge($origin, ['city' => (string) ($this->option('city') ?: $origin['city'])]);

        $result = $aramex->calculateRate($origin, $destination, ['weight_kg' => 1, 'pieces' => 1]);

        if (! $result['success']) {
            $this->error('Aramex rejected the request: '.implode(' ', $result['errors']));
            $this->line('If the endpoint is UAT, the server\'s public IP must be whitelisted by Aramex.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Connected. %s -> %s, 1 kg: %s %s (%s/%s).',
            $origin['city'],
            $destination['city'],
            number_format((float) $result['amount'], 2),
            $result['currency'],
            $result['product_group'],
            $result['product_type']
        ));

        return self::SUCCESS;
    }
}
