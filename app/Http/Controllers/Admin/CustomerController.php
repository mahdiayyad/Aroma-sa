<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
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
            'customer' => $customer->loadCount('orders')->load(['orders' => fn ($q) => $q->latest()->limit(20)]),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'role'           => ['required', Rule::in([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_ADMIN])],
        ]);

        $customer->update([
            'is_active'      => $request->boolean('is_active'),
            'loyalty_points' => (int) ($data['loyalty_points'] ?? 0),
            'role'           => $data['role'],
        ]);

        return redirect()->route('admin.customers.show', $customer)->with('status', __('admin.customers.saved'));
    }
}
