<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RefundRequestStoreRequest;
use App\Mail\RefundRequestSubmittedMail;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RefundRequestController extends Controller
{
    public function create(Request $request): View
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->whereIn('status', Order::PAID_STATUSES)
            ->latest()
            ->get();

        return view('account.refund-requests.create', [
            'orders' => $orders,
            'selectedOrderId' => (int) $request->query('order_id'),
        ]);
    }

    public function store(RefundRequestStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $order = Order::findOrFail($data['order_id']);

        $existingPending = $order->refundRequests()->where('status', RefundRequest::STATUS_PENDING)->exists();

        if ($existingPending) {
            return redirect()->route('order.show', $order)->with('status', __('refund.errors.already_pending'));
        }

        $refundRequest = RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
        ]);

        try {
            Mail::to(config('aroma.contact.email'))->send(new RefundRequestSubmittedMail($refundRequest));
        } catch (\Exception $e) {
            Log::error('Refund request notification email failed', ['error' => $e->getMessage()]);
        }

        return redirect()->route('order.show', $order)->with('status', __('refund.form.success'));
    }
}
