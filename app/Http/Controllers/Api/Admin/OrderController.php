<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderStatusService;
use App\Services\Payment\MoyasarPaymentService;
use App\Services\Payment\TamaraOrderOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class OrderController extends Controller
{
    private OrderStatusService $status;

    public function __construct(OrderStatusService $status)
    {
        $this->status = $status;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->withCount('items');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return OrderResource::collection(
            $query->latest()->paginate((int) $request->query('per_page', 20))
        );
    }

    public function show(Order $order): OrderResource
    {
        $order->load(['items', 'payment']);
        $order->allowed_next = $this->status->allowedNext($order);

        return new OrderResource($order);
    }

    public function updateStatus(OrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            $this->status->transition($order, $request->validated()['status'], [
                'tracking_number' => $request->input('tracking_number'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items', 'payment']);
        $order->allowed_next = $this->status->allowedNext($order);

        // Payment side-effects (Tamara capture on shipment / release on cancel).
        return (new OrderResource($order))->additional(['payment_notes' => $this->status->notes()])->response();
    }

    public function refund(Request $request, Order $order, MoyasarPaymentService $gateway, TamaraOrderOperations $tamara): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $payment = $order->payment()
            ->where('status', Payment::STATUS_CAPTURED)
            ->latest('id')->first();

        if (! $payment) {
            return response()->json(['message' => 'No captured payment to refund for this order.'], 422);
        }

        // Tamara refunds must go back through Tamara (never Moyasar / store credit).
        if ($payment->gateway === 'tamara') {
            $amount = isset($data['amount']) ? (float) $data['amount'] : round((float) $payment->amount - (float) $payment->refunded_amount, 2);
            $tamaraResult = $tamara->refund($order, $amount, 'Refund for order '.$order->order_number);

            if (! $tamaraResult['success']) {
                return response()->json(['message' => $tamaraResult['message']], 422);
            }

            return response()->json([
                'message' => $tamaraResult['message'],
                'payment' => new \App\Http\Resources\Admin\PaymentResource($payment->fresh()),
            ]);
        }

        $result = $gateway->refundPayment($payment, $data['amount'] ?? null);

        if (! ($result['success'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'Refund failed.'], 422);
        }

        return response()->json([
            'message' => 'Refund processed.',
            'payment' => new \App\Http\Resources\Admin\PaymentResource($payment->fresh()),
        ]);
    }
}
