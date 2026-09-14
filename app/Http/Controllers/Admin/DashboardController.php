<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function index(): View
    {
        return view('admin.dashboard', [
            'revenue'       => (float) Order::whereIn('status', Order::PAID_STATUSES)->sum('total_amount'),
            'ordersTotal'   => Order::count(),
            'ordersPending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'customers'     => User::where('role', User::ROLE_CUSTOMER)->count(),
            'productsTotal' => Product::count(),
            'activePromoCodes' => PromoCode::active()->count(),
            'pendingReviews' => ProductReview::pending()->count(),
            'statusCounts'  => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'lowStock'      => Product::where('has_variants', false)
                ->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD)
                ->orderBy('stock_quantity')->limit(8)->get(),
            'recentOrders'  => Order::latest()->limit(8)->get(),
        ]);
    }
}
