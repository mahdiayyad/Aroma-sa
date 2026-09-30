<?php

namespace Tests\Unit;

use App\Services\Otp\TawkedClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TawkedClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tawked.api_key' => 'tk_test_fake', 'services.tawked.dev_code' => '']);
    }

    private function inEnvironment(string $env): void
    {
        $this->app['env'] = $env;
    }

    public function test_the_dev_bypass_is_off_unless_a_code_is_configured(): void
    {
        $this->inEnvironment('local');

        $this->assertFalse(app(TawkedClient::class)->devBypassActive());
    }

    public function test_the_dev_bypass_works_locally_without_touching_tawked(): void
    {
        $this->inEnvironment('local');
        config(['services.tawked.dev_code' => '424242']);
        Http::fake();
        $client = app(TawkedClient::class);

        $this->assertTrue($client->devBypassActive());

        $started = $client->start('+966570574471', 'login');
        $this->assertTrue($started['ok']);
        $this->assertStringStartsWith('dev-', $started['id']);

        $this->assertSame(['ok' => true, 'verified' => true, 'status' => 'verified'], $client->check($started['id'], '424242'));
        $this->assertSame(['ok' => true, 'verified' => false, 'status' => 'invalid_code'], $client->check($started['id'], '000000'));

        Http::assertNothingSent();
    }

    /** @dataProvider nonLocalEnvironments */
    public function test_the_dev_bypass_is_impossible_outside_local(string $env): void
    {
        $this->inEnvironment($env);
        config(['services.tawked.dev_code' => '424242']);
        Http::fake([
            'tawked.com/v1/verify/start' => Http::response(['id' => 'real-1', 'status' => 'pending', 'expires_at' => now()->addMinutes(5)->toIso8601String()], 201),
            'tawked.com/v1/verify/check' => Http::response(['verified' => false, 'status' => 'invalid_code'], 200),
        ]);
        $client = app(TawkedClient::class);

        $this->assertFalse($client->devBypassActive());

        // start() really calls Tawked…
        $this->assertSame('real-1', $client->start('+966570574471', 'login')['id']);
        Http::assertSentCount(1);

        // …and a "dev-" id is never trusted with the fixed code: the check
        // goes to Tawked, which is the only thing that can say "verified".
        $this->assertFalse($client->check('dev-anything', '424242')['verified']);
        Http::assertSentCount(2);
    }

    public function nonLocalEnvironments(): array
    {
        return [['production'], ['staging'], ['testing']];
    }

    public function test_it_fails_closed_without_an_api_key(): void
    {
        config(['services.tawked.api_key' => '']);
        Http::fake();
        $client = app(TawkedClient::class);

        $this->assertFalse($client->isConfigured());
        $this->assertSame(['ok' => false, 'error' => TawkedClient::ERROR_UNAVAILABLE], $client->start('+966570574471', 'login'));
        $this->assertSame(['ok' => false, 'error' => TawkedClient::ERROR_UNAVAILABLE], $client->check('x', '123456'));

        Http::assertNothingSent();
    }

    public function test_an_unknown_verification_id_reads_as_not_found_rather_than_an_outage(): void
    {
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['error' => 'not_found'], 404)]);

        $this->assertSame(
            ['ok' => true, 'verified' => false, 'status' => 'not_found'],
            app(TawkedClient::class)->check('gone', '123456')
        );
    }

    public function test_failures_are_logged_with_the_phone_masked(): void
    {
        Http::fake(['tawked.com/v1/verify/start' => Http::response(['error' => 'insufficient_credits'], 402)]);
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Tawked request rejected'
                && $context['error'] === 'insufficient_credits'
                && $context['phone'] === '•••••••••4471'
                && strpos(json_encode($context), '570574471') === false;
        });

        app(TawkedClient::class)->start('+966570574471', 'login');
    }

    public function test_the_api_key_is_sent_as_a_bearer_token_and_never_logged(): void
    {
        Http::fake(['tawked.com/v1/verify/start' => Http::response(['id' => 'v', 'status' => 'pending', 'expires_at' => now()->addMinutes(5)->toIso8601String()], 201)]);

        app(TawkedClient::class)->start('+966570574471', 'login');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer tk_test_fake')
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    /**
     * Guards against a customer's "OTP says expired immediately" report ever
     * being caused by OUR side: a bad expires_at from Tawked (unparseable,
     * missing, or already past/too-soon) must never be trusted as-is — it
     * falls back to a safe window instead of expiring the code before the
     * customer can possibly use it.
     *
     * @dataProvider implausibleExpiries
     */
    public function test_an_implausible_expires_at_falls_back_to_the_default_window($value): void
    {
        Http::fake(['tawked.com/v1/verify/start' => Http::response(['id' => 'v', 'status' => 'pending', 'expires_at' => $value], 201)]);

        $started = app(TawkedClient::class)->start('+966570574471', 'login');

        $this->assertTrue($started['ok']);
        $this->assertGreaterThan(4 * 60, now()->diffInSeconds($started['expires_at'], false));
    }

    public function implausibleExpiries(): array
    {
        return [
            'unparseable string' => ['not-a-date'],
            'null' => [null],
            'already in the past' => [now()->subMinute()->toIso8601String()],
            'a few seconds from now' => [now()->addSeconds(10)->toIso8601String()],
        ];
    }

    public function test_a_genuinely_short_but_plausible_expiry_is_trusted_as_is(): void
    {
        $expiry = now()->addMinutes(2)->toIso8601String();
        Http::fake(['tawked.com/v1/verify/start' => Http::response(['id' => 'v', 'status' => 'pending', 'expires_at' => $expiry], 201)]);

        $started = app(TawkedClient::class)->start('+966570574471', 'login');

        $this->assertEqualsWithDelta(now()->addMinutes(2)->timestamp, $started['expires_at']->timestamp, 2);
    }
}
