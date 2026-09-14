<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function index(Request $request): View
    {
        $query = PromoCode::query()->withCount('redemptions');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }
        if ($request->filled('active') && $request->query('active') !== 'all') {
            $query->where('is_active', $request->query('active') === '1');
        }

        return view('admin.promo-codes.index', [
            'promoCodes' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.promo-codes.form', [
            'promoCode' => new PromoCode(['is_active' => true, 'discount_type' => PromoCode::TYPE_PERCENTAGE]),
            'products' => Product::orderBy('name')->limit(500)->get(),
            'categories' => Category::orderBy('sort_order')->get(),
            'customers' => User::where('role', User::ROLE_CUSTOMER)->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(PromoCodeRequest $request): RedirectResponse
    {
        $promoCode = PromoCode::create($this->payload($request));
        $this->syncRestrictions($request, $promoCode);

        return redirect()->route('admin.promo-codes.index')->with('status', __('admin.promo_codes.saved'));
    }

    public function show(PromoCode $promoCode): View
    {
        return view('admin.promo-codes.show', [
            'promoCode' => $promoCode->load(['redemptions.order', 'redemptions.user']),
        ]);
    }

    public function edit(PromoCode $promoCode): View
    {
        return view('admin.promo-codes.form', [
            'promoCode' => $promoCode->load(['customers', 'products', 'categories']),
            'products' => Product::orderBy('name')->limit(500)->get(),
            'categories' => Category::orderBy('sort_order')->get(),
            'customers' => User::where('role', User::ROLE_CUSTOMER)->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function update(PromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update($this->payload($request));
        $this->syncRestrictions($request, $promoCode);

        return redirect()->route('admin.promo-codes.index')->with('status', __('admin.promo_codes.saved'));
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        // Soft delete — a code with real redemption history stays queryable
        // for reporting (promo_code_redemptions.promo_code_id is
        // restrictOnDelete), "archive" is exactly what this is.
        $promoCode->delete();

        return redirect()->route('admin.promo-codes.index')->with('status', __('admin.promo_codes.deleted'));
    }

    /** @return array<string,mixed> */
    private function payload(PromoCodeRequest $request): array
    {
        $data = $request->validated();
        unset($data['customer_ids'], $data['product_ids'], $data['category_ids']);

        return $data;
    }

    private function syncRestrictions(PromoCodeRequest $request, PromoCode $promoCode): void
    {
        $promoCode->customers()->sync($request->validated()['customer_ids'] ?? []);
        $promoCode->products()->sync($request->validated()['product_ids'] ?? []);
        $promoCode->categories()->sync($request->validated()['category_ids'] ?? []);
    }
}
