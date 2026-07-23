<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List user's orders
     */
    public function index(): View
    {
        $orders = auth()->user()->orders()->latest()->paginate(15);

        return view('orders.index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show single order details
     */
    public function show(Order $order): View
    {
        if (auth()->id() !== $order->user_id) {
            abort(403, 'Unauthorized');
        }

        return view('orders.show', [
            'order' => $order->load('items'),
        ]);
    }
}
