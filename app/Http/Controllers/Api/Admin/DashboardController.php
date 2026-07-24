<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function index(): JsonResponse
    {
        $paidStatuses = [
            Order::STATUS_PAID,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ];

        return response()->json([
            'revenue'        => (float) Order::whereIn('status', $paidStatuses)->sum('total_amount'),
            'orders_total'   => Order::count(),
            'orders_pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'customers'      => User::where('role', User::ROLE_CUSTOMER)->count(),
            'products_total' => Product::count(),
            'status_counts'  => Order::selectRaw('status, count(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
            'low_stock'      => ProductResource::collection(
                Product::where('has_variants', false)
                    ->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD)
                    ->orderBy('stock_quantity')->limit(8)->get()
            ),
            'recent_orders'  => OrderResource::collection(Order::latest()->limit(8)->get()),
        ]);
    }
}
