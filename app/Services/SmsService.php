<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin SMS-sending abstraction. No real Unifonic credentials exist yet —
 * until SMS_API_KEY is configured, every send() resolves via stubSend()
 * below: a logged, deterministic SUCCESS (not a failure), so the OTP flow
 * stays fully usable end to end in dev/test without a real SMS spend —
 * mirrors LocationLookupService's stub-mode contract exactly (isConfigured()
 * / a same-shaped success either way / everything logged as stub so it's
 * never mistaken for a real send).
 *
 * STUB CONTRACT NOTICE: Unifonic is the provider already named in
 * config('services.sms') (scaffolded before this feature existed), but the
 * request shape below (endpoint, field names) is a reasonable guess against
 * Unifonic's REST SMS API, not verified against current live docs — flagged
 * here rather than presented as certain. Verify/adjust once real
 * SMS_API_KEY credentials are in hand; send()'s bool return contract
 * doesn't need to change either way.
 */
class SmsService
{
    private string $provider;
    private ?string $apiKey;
    private string $senderId;

    public function __construct()
    {
        $this->provider = (string) config('services.sms.provider', 'unifonic');
        $this->apiKey = config('services.sms.api_key') ?: null;
        $this->senderId = (string) config('services.sms.sender_id', 'Aroma');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null;
    }

    public function send(string $phone, string $message): bool
    {
        if (! $this->isConfigured()) {
            return $this->stubSend($phone, $message);
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://el.cloud.unifonic.com/rest/SMS/messages', [
                'AppSid' => $this->apiKey,
                'SenderID' => $this->senderId,
                'Body' => $message,
                'Recipient' => ltrim($phone, '+'),
                'responseType' => 'JSON',
            ]);

            if (! $response->successful()) {
                Log::warning('SMS send failed', ['phone' => $phone, 'provider' => $this->provider, 'status' => $response->status()]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('SMS send error', ['phone' => $phone, 'provider' => $this->provider, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function stubSend(string $phone, string $message): bool
    {
        Log::info('SMS (stub mode): SMS_API_KEY not set, logging instead of sending', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
