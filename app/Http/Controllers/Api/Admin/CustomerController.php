<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->withCount('orders');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CustomerResource::collection(
            $query->latest()->paginate((int) $request->query('per_page', 20))
        );
    }

    public function show(User $customer): CustomerResource
    {
        return new CustomerResource(
            $customer->loadCount('orders')->load(['orders' => fn ($q) => $q->latest()->limit(20)])
        );
    }

    public function update(Request $request, User $customer): CustomerResource
    {
        $data = $request->validate([
            'is_active'      => ['sometimes', 'boolean'],
            'loyalty_points' => ['sometimes', 'integer', 'min:0'],
            'role'           => ['sometimes', Rule::in([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_ADMIN])],
        ]);

        $customer->update($data);

        return new CustomerResource($customer->loadCount('orders'));
    }
}
