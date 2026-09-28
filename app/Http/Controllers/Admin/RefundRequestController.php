<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = RefundRequest::query()->with(['order', 'user']);

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        return view('admin.refund-requests.index', [
            'refundRequests' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function approve(RefundRequest $refundRequest): RedirectResponse
    {
        $refundRequest->update([
            'status' => RefundRequest::STATUS_APPROVED,
            'resolved_at' => now(),
        ]);

        return back()->with('status', __('admin.refund_requests.approved'));
    }

    public function reject(RefundRequest $refundRequest): RedirectResponse
    {
        $refundRequest->update([
            'status' => RefundRequest::STATUS_REJECTED,
            'resolved_at' => now(),
        ]);

        return back()->with('status', __('admin.refund_requests.rejected'));
    }
}
