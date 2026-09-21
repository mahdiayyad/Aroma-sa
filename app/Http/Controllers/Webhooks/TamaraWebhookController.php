<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\Payment\TamaraOrderOperations;
use App\Services\Payment\TamaraPaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Tamara order notifications (server-to-server).
 *
 * The customer-present return (CheckoutController::paymentCallback) is the
 * fast path; this is what makes the order correct when that path can't run —
 * the shopper never came back (tab closed, ID-verification handed off to
 * another browser/app), or Tamara changes the order later (capture, refund,
 * cancel, a delayed decline).
 *
 * Authentication: Tamara signs a JWT with the merchant's notification token
 * and sends it as the `tamaraToken` query parameter and as a Bearer token
 * (see TamaraPaymentService::verifyNotificationToken).
 *
 * Payload: { order_id, order_reference_id, order_number, event_type, data }
 * with event_type like `order_approved`; older IPN-style deliveries carry
 * `order_status` instead. Both are understood. The raw payload is always
 * logged, so a shape difference on a given account is diagnosable.
 *
 * Success statuses per Tamara: approved / authorised (intermediate) and
 * fully captured (final) all mean the customer has paid.
 */
class TamaraWebhookController extends Controller
{
    /** @var TamaraPaymentService */
    private $tamara;

    /** @var CheckoutService */
    private $checkout;

    /** @var TamaraOrderOperations */
    private $operations;

    public function __construct(TamaraPaymentService $tamara, CheckoutService $checkout, TamaraOrderOperations $operations)
    {
        $this->tamara = $tamara;
        $this->checkout = $checkout;
        $this->operations = $operations;
    }

    public function handle(Request $request): Response
    {
        $token = $request->bearerToken() ?: $request->query('tamaraToken') ?: $request->query('token');

        if (! $this->tamara->verifyNotificationToken($token ? (string) $token : null)) {
            Log::warning('Tamara webhook token verification failed', ['ip' => $request->ip()]);

            return response('Unauthorized', 401);
        }

        $data = (array) $request->json()->all();
        $event = $this->normalizeEvent($data);
        $reference = (string) ($data['order_reference_id'] ?? '');
        $tamaraOrderId = (string) ($data['order_id'] ?? '');

        Log::info('Tamara webhook received', [
            'event' => $event,
            'reference' => $reference,
            'tamara_order_id' => $tamaraOrderId,
        ]);

        $order = $this->findOrder($reference, $tamaraOrderId);

        if (! $order) {
            Log::warning('Tamara webhook for unknown order', ['reference' => $reference, 'event' => $event]);

            // Acknowledge anyway — there is nothing local to retry towards,
            // and a 4xx/5xx here just earns repeated redelivery from Tamara.
            return response('OK', 200);
        }

        $payment = $order->payment()->where('gateway', 'tamara')->latest('id')->first();

        try {
            // Tamara's id is what capture/cancel/refund need later; the
            // session that held it at checkout time is long gone by now.
            if ($payment && ! $payment->reference_number && $tamaraOrderId !== '') {
                $payment->update(['reference_number' => $tamaraOrderId]);
            }

            switch ($event) {
                case 'approved':
                case 'authorised':
                case 'captured':
                    $this->handlePaid($order, $payment, $tamaraOrderId);
                    break;

                case 'canceled':
                case 'declined':
                case 'expired':
                    if ($payment && ! $order->isPaid()) {
                        $payment->update([
                            'status' => Payment::STATUS_FAILED,
                            'gateway_response' => $data,
                        ]);
                        $order->update([
                            'status' => $event === 'expired' ? Order::STATUS_CANCELLED : Order::STATUS_PENDING,
                        ]);
                    }
                    break;

                case 'refunded':
                    if ($payment) {
                        $this->operations->syncRefundFromTamara($payment, $this->reportedRefund($data));
                    }
                    break;

                default:
                    Log::info('Unhandled Tamara webhook event', ['event' => $event]);
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

    /**
     * approved / authorised / captured: ask Tamara what the order really is
     * (authoritative; also performs the authorise call when needed) and
     * reconcile our records with it.
     */
    private function handlePaid(Order $order, ?Payment $payment, string $tamaraOrderId): void
    {
        if (! $payment) {
            return;
        }

        $lookup = $tamaraOrderId !== '' ? $tamaraOrderId : (string) $this->tamara->tamaraOrderId($payment);
        $state = $lookup !== '' ? $this->tamara->fetchStatus($lookup) : null;

        if (! $state || ! ($state['paid'] ?? false)) {
            return;
        }

        $paymentStatus = ($state['captured'] ?? false) ? Payment::STATUS_CAPTURED : Payment::STATUS_AUTHORIZED;

        if (! $order->isPaid()) {
            $this->checkout->markOrderAsPaid($order, $payment, $paymentStatus);

            return;
        }

        // Already paid on our side — just catch the payment record up when the
        // money has since been captured (shipment capture, auto-capture).
        if ($paymentStatus === Payment::STATUS_CAPTURED
            && ! in_array($payment->status, [Payment::STATUS_CAPTURED, Payment::STATUS_REFUNDED], true)) {
            $payment->update(['status' => Payment::STATUS_CAPTURED]);
        }
    }

    /**
     * event_type `order_approved` and legacy order_status `approved` both
     * reduce to the bare status word; Tamara's various captured/refunded
     * status names collapse to captured / refunded.
     *
     * @param array<string,mixed> $data
     */
    private function normalizeEvent(array $data): string
    {
        $raw = strtolower((string) ($data['event_type'] ?? $data['order_status'] ?? ''));

        if (strpos($raw, 'order_') === 0) {
            $raw = substr($raw, 6);
        }

        if (in_array($raw, ['fully_captured', 'partially_captured'], true)) {
            return 'captured';
        }

        if (in_array($raw, ['fully_refunded', 'partially_refunded'], true)) {
            return 'refunded';
        }

        return $raw === 'cancelled' ? 'canceled' : $raw;
    }

    private function findOrder(string $reference, string $tamaraOrderId): ?Order
    {
        if ($reference !== '') {
            $order = Order::where('order_number', $reference)->first();

            if ($order) {
                return $order;
            }
        }

        if ($tamaraOrderId !== '') {
            $payment = Payment::where('gateway', 'tamara')->where('reference_number', $tamaraOrderId)->latest('id')->first();

            return $payment ? $payment->order : null;
        }

        return null;
    }

    /** @param array<string,mixed> $data */
    private function reportedRefund(array $data): ?float
    {
        $refunded = $data['data']['refunded_amount'] ?? null;

        if (is_array($refunded)) {
            $refunded = $refunded['amount'] ?? null;
        }

        return is_numeric($refunded) ? (float) $refunded : null;
    }
}
