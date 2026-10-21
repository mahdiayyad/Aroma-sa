<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\GatewayUnavailableException;
use App\Services\Payment\MoyasarPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moyasar payment webhooks (server-to-server).
 *
 * Authentication: the endpoint's Secret Token, which Moyasar sends inside
 * the JSON body as `secret_token` (see MoyasarPaymentService::verifyWebhookToken).
 * The payload is then only used for the payment id — the payment itself is
 * re-fetched from Moyasar's API before anything changes.
 *
 * Responses drive Moyasar's redelivery (5 retries over ~4 hours on any
 * non-2xx): 200 once handled or deliberately ignored, 401 for a bad token,
 * 503 when Moyasar's API is unreachable and 500 on unexpected errors — both
 * safe to retry, since every step is idempotent.
 */
class MoyasarWebhookController extends Controller
{
    /** @var MoyasarPaymentService */
    private $paymentService;

    public function __construct(MoyasarPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handle(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! $this->paymentService->verifyWebhookToken($payload['secret_token'] ?? null)) {
            // Never log the token itself.
            Log::warning('Moyasar webhook authentication failed', [
                'ip' => $request->ip(),
                'token_present' => isset($payload['secret_token']),
            ]);

            return response('Unauthorized', 401);
        }

        $context = [
            'event_id' => $payload['id'] ?? null,
            'type' => $payload['type'] ?? null,
            'moyasar_payment' => data_get($payload, 'data.id'),
        ];

        try {
            $this->paymentService->handleWebhook($payload);

            return response('OK', 200);
        } catch (GatewayUnavailableException $e) {
            Log::warning('Moyasar webhook deferred: Moyasar API unavailable', $context + ['error' => $e->getMessage()]);

            return response('Temporarily unavailable', 503);
        } catch (Throwable $e) {
            Log::error('Moyasar webhook processing failed', $context + ['error' => $e->getMessage()]);

            return response('Error', 500);
        }
    }
}
