<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\TamaraPaymentService;
use Illuminate\Console\Command;

/**
 * Registers / inspects / removes the Tamara order-notification webhook, so
 * subscribing an environment is one repeatable command instead of a portal
 * click-through (the Partner Portal remains an equal alternative:
 * Settings -> General Settings -> Webhooks).
 *
 *   php artisan tamara:webhook register --url=https://shop.example.com/webhooks/tamara
 *   php artisan tamara:webhook show <webhook_id>
 *   php artisan tamara:webhook delete <webhook_id>
 */
class TamaraWebhookCommand extends Command
{
    /** Events Tamara's API documents for order webhooks. */
    private const DOCUMENTED_EVENTS = [
        'order_approved', 'order_authorised', 'order_captured',
        'order_canceled', 'order_refunded', 'order_updated',
    ];

    /** Also asked for in the merchant checklist; not in the documented list, so tried first and dropped if rejected. */
    private const CHECKLIST_EXTRAS = ['order_declined', 'order_expired'];

    protected $signature = 'tamara:webhook
        {action : register | show | delete}
        {id? : Webhook id (for show / delete)}
        {--url= : Public HTTPS URL to notify (defaults to the app\'s /webhooks/tamara route)}
        {--events=* : Event names to subscribe to (defaults to the standard set)}';

    protected $description = 'Register, inspect or delete the Tamara order webhook';

    public function handle(TamaraPaymentService $tamara): int
    {
        if (! config('services.tamara.api_token')) {
            $this->error('TAMARA_API_TOKEN is not set.');

            return self::FAILURE;
        }

        switch ($this->argument('action')) {
            case 'register':
                return $this->register($tamara);

            case 'show':
                return $this->show($tamara);

            case 'delete':
                return $this->deleteWebhook($tamara);
        }

        $this->error('Unknown action. Use: register | show | delete');

        return self::FAILURE;
    }

    private function register(TamaraPaymentService $tamara): int
    {
        $url = (string) ($this->option('url') ?: route('payment.webhook.tamara'));

        if (strpos($url, 'https://') !== 0) {
            $this->error("Tamara needs a public HTTPS URL (got: {$url}). Pass --url=https://… (a staging host or a tunnel works).");

            return self::FAILURE;
        }

        $explicit = array_filter((array) $this->option('events'));
        $events   = $explicit ?: array_merge(self::DOCUMENTED_EVENTS, self::CHECKLIST_EXTRAS);

        $result = $tamara->registerWebhook($url, $events);

        // The declined/expired extras may not be accepted by the API — retry
        // with the documented set so the essential events are still subscribed.
        if (! $result['success'] && ! $explicit) {
            $this->warn('Tamara rejected the extended event list; retrying with the documented events.');
            $this->line('  ('.($result['error'] ?? 'no detail').')');

            $events = self::DOCUMENTED_EVENTS;
            $result = $tamara->registerWebhook($url, $events);

            if ($result['success']) {
                $this->warn('Add "Declined" and "Expired" in the Tamara Partner Portal (Settings -> Webhooks) if you want those too.');
            }
        }

        if (! $result['success']) {
            $this->error('Registration failed: '.($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info('Webhook registered.');
        $this->line('  webhook_id: '.($result['webhook_id'] ?? '(none returned)'));
        $this->line('  url:        '.$url);
        $this->line('  events:     '.implode(', ', $events));
        $this->line('Make sure TAMARA_NOTIFICATION_TOKEN matches the notification token in the Partner Portal, or notifications will be rejected.');

        return self::SUCCESS;
    }

    private function show(TamaraPaymentService $tamara): int
    {
        $id = (string) $this->argument('id');

        if ($id === '') {
            $this->error('Pass the webhook id: php artisan tamara:webhook show <id>');

            return self::FAILURE;
        }

        $data = $tamara->retrieveWebhook($id);

        if (! $data) {
            $this->error('Webhook not found (or Tamara could not be reached).');

            return self::FAILURE;
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function deleteWebhook(TamaraPaymentService $tamara): int
    {
        $id = (string) $this->argument('id');

        if ($id === '') {
            $this->error('Pass the webhook id: php artisan tamara:webhook delete <id>');

            return self::FAILURE;
        }

        if (! $tamara->deleteWebhook($id)) {
            $this->error('Could not delete the webhook.');

            return self::FAILURE;
        }

        $this->info('Webhook deleted.');

        return self::SUCCESS;
    }
}
