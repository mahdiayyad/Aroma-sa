<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Jobs\SendPaymentReceipt;
use App\Models\Order;
use App\Services\AramexFulfilmentService;
use App\Services\Receipts\PaymentReceiptService;
use App\Services\OrderStatusService;
use App\Services\Payment\TamaraOrderOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    private OrderStatusService $status;
    private TamaraOrderOperations $tamara;

    public function __construct(OrderStatusService $status, TamaraOrderOperations $tamara)
    {
        $this->status = $status;
        $this->tamara = $tamara;
    }

    public function index(Request $request): View
    {
        $query = Order::query()->withCount('items');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%"));
        }
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        return view('admin.orders.index', [
            'orders'  => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status']),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order, AramexFulfilmentService $aramex): View
    {
        return view('admin.orders.show', [
            'order'            => $order->load(['items', 'payment', 'aramexShipment', 'receipt']),
            'allowedNext'      => $this->status->allowedNext($order),
            'aramexConfigured' => $aramex->isConfigured(),
            'aramexShippable'  => in_array($order->status, AramexFulfilmentService::SHIPPABLE_STATUSES, true),
        ]);
    }

    public function updateStatus(OrderStatusRequest $request, Order $order): RedirectResponse
    {
        try {
            $this->status->transition($order, $request->validated()['status'], [
                'tracking_number' => $request->input('tracking_number'),
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.orders.show', $order)->with('error', $e->getMessage());
        }

        // Payment side-effects of the transition (Tamara capture on shipment,
        // release/refund on cancellation): successes join the status message,
        // failures are raised as an error banner so they can't be missed.
        $notes    = collect($this->status->notes());
        $messages = $notes->where('success', true)->pluck('message')->prepend(__('admin.orders.status_updated'));
        $redirect = redirect()->route('admin.orders.show', $order)->with('status', $messages->implode(' '));

        if ($failed = $notes->where('success', false)->pluck('message')->implode(' ')) {
            $redirect->with('error', $failed);
        }

        return $redirect;
    }

    /** Download the payment receipt, issuing it first for a paid order that has none yet. */
    public function receipt(Order $order, PaymentReceiptService $receipts): Response
    {
        $receipt = $order->receipt;

        if (! $receipt && ($payment = $receipts->paidPaymentOf($order))) {
            $receipt = $receipts->issue($order, $payment);
        }

        if (! $receipt) {
            return redirect()->route('admin.orders.show', $order)->with('error', __('admin.receipts.unavailable'));
        }

        return $receipts->download($receipt);
    }

    /** Queue the receipt email again (the same retried job as the automatic send). */
    public function resendReceipt(Order $order, PaymentReceiptService $receipts): RedirectResponse
    {
        $payment = $receipts->paidPaymentOf($order);

        if (! $order->isPaid() || ! $payment) {
            return redirect()->route('admin.orders.show', $order)->with('error', __('admin.receipts.unavailable'));
        }

        SendPaymentReceipt::dispatch($order->id, $payment->id, true);

        return redirect()->route('admin.orders.show', $order)
            ->with('status', __('admin.receipts.resend_queued', ['email' => $order->customer_email]));
    }

    /** Retry a Tamara capture (e.g. after a failure when the order was shipped). */
    public function tamaraCapture(Order $order): RedirectResponse
    {
        $result = $this->tamara->capture($order) ?? ['success' => false, 'message' => __('admin.tamara.no_tamara_payment')];

        return redirect()->route('admin.orders.show', $order)
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /** Full or partial refund of a captured Tamara payment — through Tamara only. */
    public function tamaraRefund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'comment' => ['required', 'string', 'max:250'],
        ]);

        $result = $this->tamara->refund($order, (float) $data['amount'], $data['comment']);

        return redirect()->route('admin.orders.show', $order)
            ->with($result['success'] ? 'status' : 'error', $result['message']);
    }
}
