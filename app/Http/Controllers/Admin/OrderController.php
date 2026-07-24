<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    private OrderStatusService $status;

    public function __construct(OrderStatusService $status)
    {
        $this->status = $status;
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

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order'       => $order->load(['items', 'payment']),
            'allowedNext' => $this->status->allowedNext($order),
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

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.orders.status_updated'));
    }
}
