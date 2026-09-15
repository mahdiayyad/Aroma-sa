<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\RewardPointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    private RewardPointService $rewardPoints;
    private ReferralService $referrals;

    public function __construct(RewardPointService $rewardPoints, ReferralService $referrals)
    {
        $this->rewardPoints = $rewardPoints;
        $this->referrals = $referrals;
    }

    public function index(Request $request): View
    {
        $query = User::query()->withCount('orders');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }
        if ($request->filled('role') && $request->query('role') !== 'all') {
            $query->where('role', $request->query('role'));
        }

        return view('admin.customers.index', [
            'customers' => $query->latest()->paginate(20)->withQueryString(),
            'filters'   => $request->only(['q', 'role']),
        ]);
    }

    public function show(User $customer): View
    {
        return view('admin.customers.show', [
            'customer' => $customer->loadCount('orders')->load([
                'orders' => fn ($q) => $q->latest()->limit(20),
                'addresses' => fn ($q) => $q->orderByDesc('is_default')->latest(),
            ]),
            'referralStats' => $this->referrals->stats($customer),
            'pointTransactions' => $customer->pointTransactions()->latest()->limit(10)->get(),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'role'           => ['required', Rule::in([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_ADMIN])],
        ]);

        // Role changes are gated beyond the ['auth','admin'] route middleware:
        // that middleware only requires isAdmin(), which is also true for
        // 'staff' — without this extra check any staff account could grant
        // itself (or anyone else) the admin role through this same form.
        $roleChanged = $data['role'] !== $customer->role;
        if ($roleChanged) {
            if ($request->user()->id === $customer->id) {
                return redirect()->route('admin.customers.show', $customer)
                    ->with('error', __('admin.customers.role_self_forbidden'));
            }
            if ($request->user()->role !== User::ROLE_ADMIN) {
                return redirect()->route('admin.customers.show', $customer)
                    ->with('error', __('admin.customers.role_forbidden'));
            }
        }

        $customer->update([
            'is_active' => $request->boolean('is_active'),
            'role'      => $data['role'],
        ]);

        // Routed through RewardPointService rather than writing the column
        // directly, so an admin's manual adjustment leaves the same ledger
        // trail a referral/redemption would — the point_transactions table
        // stays the complete history, not just the automated entries.
        $requestedPoints = (int) ($data['loyalty_points'] ?? $customer->loyalty_points);
        $delta = $requestedPoints - $customer->loyalty_points;

        if ($delta > 0) {
            $this->rewardPoints->credit(
                $customer, $delta, PointTransaction::TYPE_ADMIN_ADJUSTMENT, null,
                __('admin.customers.points_adjustment_description', ['admin' => $request->user()->name])
            );
        } elseif ($delta < 0) {
            $this->rewardPoints->debit(
                $customer, abs($delta), PointTransaction::TYPE_ADMIN_ADJUSTMENT, null,
                __('admin.customers.points_adjustment_description', ['admin' => $request->user()->name])
            );
        }

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.saved'));
    }
}
