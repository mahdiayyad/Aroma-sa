<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\Payment\TamaraPaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Tamara IPN (server-to-server order status notification).
 *
 * This is the fallback confirmation path, not the primary one: the
 * customer-present return already verifies and finalises the order via
 * CheckoutController::paymentCallback (see TamaraPaymentService::fetchStatus,
 * which also performs the required "authorise" call). This webhook matters
 * for the cases that path can't cover — the shopper closes the tab before
 * being redirected back, or Tamara flips the order's status asynchronously
 * some time after checkout (e.g. a delayed decline).
 *
 * Payload/event names follow Tamara's documented webhook schema
 * (order_id, order_reference_id, event_type). If Tamara's dashboard shows a
 * different shape for your account, the raw payload is always logged below —
 * adjust the field names here to match.
 */
class TamaraWebhookController extends Controller
{
    /** @var TamaraPaymentService */
    private $tamara;

    /** @var CheckoutService */
    private $checkout;

    public function __construct(TamaraPaymentService $tamara, CheckoutService $checkout)
    {
        $this->tamara = $tamara;
        $this->checkout = $checkout;
    }

    public function handle(Request $request): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            $token = $request->query('token');
        }

        if (!$this->tamara->verifyNotificationToken($token)) {
            Log::warning('Tamara webhook token verification failed', ['ip' => $request->ip()]);

            return response('Unauthorized', 401);
        }

        $data = (array) $request->json()->all();
        $eventType = (string) ($data['event_type'] ?? '');
        $reference = (string) ($data['order_reference_id'] ?? '');
        $tamaraOrderId = (string) ($data['order_id'] ?? '');

        Log::info('Tamara webhook received', [
            'event' => $eventType,
            'reference' => $reference,
            'tamara_order_id' => $tamaraOrderId,
        ]);

        $order = $reference !== '' ? Order::where('order_number', $reference)->first() : null;

        if (!$order) {
            Log::warning('Tamara webhook for unknown order', ['reference' => $reference, 'event' => $eventType]);

            // Acknowledge anyway — there is nothing local to retry towards,
            // and a 4xx/5xx here just earns repeated redelivery from Tamara.
            return response('OK', 200);
        }

        $payment = $order->payment()->where('gateway', 'tamara')->latest('id')->first();

        try {
            switch ($eventType) {
                case 'order_approved':
                case 'order_authorised':
                case 'order_captured':
                    if ($payment && !$order->isPaid()) {
                        $status = $this->tamara->fetchStatus($tamaraOrderId !== '' ? $tamaraOrderId : $reference);
                        if ($status && ($status['paid'] ?? false)) {
                            $this->checkout->markOrderAsPaid($order, $payment);
                        }
                    }
                    break;

                case 'order_canceled':
                case 'order_declined':
                case 'order_expired':
                    if ($payment && !$order->isPaid()) {
                        $payment->update([
                            'status' => Payment::STATUS_FAILED,
                            'gateway_response' => $data,
                        ]);
                        $order->update([
                            'status' => $eventType === 'order_expired' ? Order::STATUS_CANCELLED : Order::STATUS_PENDING,
                        ]);
                    }
                    break;

                default:
                    Log::info('Unhandled Tamara webhook event', ['event' => $eventType]);
            }

            return response('OK', 200);
        } catch (Exception $e) {
            Log::error('Tamara webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            // 200 on purpose — same reasoning as MoyasarWebhookController:
            // the failure is already logged, and a non-2xx here just triggers
            // Tamara's retry storm instead of giving us a second chance.
            return response('Processed', 200);
        }
    }
}
