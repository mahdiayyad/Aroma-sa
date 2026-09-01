<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the CSRF-exemption bug: POST /webhooks/moyasar
 * used to return 419 for every real webhook delivery (it sits under the
 * 'web' middleware group, which includes CSRF verification, and the route
 * was never added to VerifyCsrfToken::$except) — Moyasar's server-to-server
 * request can never carry a Laravel CSRF token, so the signature-verifying
 * controller code never actually ran. See app/Http/Middleware/
 * VerifyCsrfToken.php and app/Services/Payment/MoyasarPaymentService.php.
 */
class MoyasarWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_route_no_longer_419s_without_a_csrf_token(): void
    {
        // No X-CSRF-TOKEN header, no _token field — exactly how Moyasar's
        // real server-to-server request arrives. Before the fix this was a
        // 419; the signature check (missing here) now correctly rejects it
        // with 401 instead, proving the request actually reached the
        // controller's own auth logic rather than being blocked earlier.
        $this->postJson('/webhooks/moyasar', ['event' => 'invoice.paid', 'data' => []])
            ->assertStatus(401);
    }

    public function test_a_correctly_signed_payload_is_accepted(): void
    {
        config(['services.moyasar.webhook_secret' => 'test_secret']);

        $payload = json_encode([
            'event' => 'invoice.paid',
            'data'  => ['metadata' => ['order_id' => 999999]], // no matching order — handled gracefully, still 200
        ]);
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test_secret', true));

        $this->call('POST', '/webhooks/moyasar', [], [], [], [
            'HTTP_X-Moyasar-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    public function test_an_empty_webhook_secret_fails_closed_not_open(): void
    {
        // A misconfigured deployment (secret unset) must never accept ANY
        // signature — including one computed the same way with an empty
        // key, which an attacker could replicate just as easily.
        config(['services.moyasar.webhook_secret' => '']);

        $payload = json_encode(['event' => 'invoice.paid', 'data' => []]);
        $forgedSignature = base64_encode(hash_hmac('sha256', $payload, '', true));

        $this->call('POST', '/webhooks/moyasar', [], [], [], [
            'HTTP_X-Moyasar-Signature' => $forgedSignature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(401);
    }
}
