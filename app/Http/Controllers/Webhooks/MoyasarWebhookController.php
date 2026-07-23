<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\MoyasarPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MoyasarWebhookController extends Controller
{
    private $paymentService;

    public function __construct(MoyasarPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Handle Moyasar webhook callbacks
     *
     * Moyasar sends payment status updates here.
     * Must verify signature before processing.
     */
    public function handle(Request $request): Response
    {
        $signature = $request->header('X-Moyasar-Signature');
        $body = $request->getContent();

        // Verify webhook signature for security
        if (!$signature || !$this->paymentService->verifyWebhookSignature($body, $signature)) {
            Log::warning('Webhook signature verification failed', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            return response('Unauthorized', 401);
        }

        $data = $request->json()->all();

        try {
            $this->paymentService->handleWebhook($data);

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            // Return 200 to prevent Moyasar from retrying
            return response('Processed', 200);
        }
    }
}
