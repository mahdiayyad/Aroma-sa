<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductOptionRequest;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Per-product customisation options (closure style, …). Nested under the
 * product it belongs to — reached from the product edit screen, not a
 * top-level nav item. Values are edited inline with their option.
 */
class ProductOptionController extends Controller
{
    public function index(Product $product): View
    {
        return view('admin.product-options.index', [
            'product' => $product,
            'options' => $product->allOptions()->with('values')->get(),
        ]);
    }

    public function create(Product $product): View
    {
        return view('admin.product-options.form', [
            'product' => $product,
            'option'  => new ProductOption(['is_required' => true, 'is_active' => true, 'type' => 'select']),
        ]);
    }

    public function store(ProductOptionRequest $request, Product $product): RedirectResponse
    {
        $this->persist($request, new ProductOption(['product_id' => $product->id]));

        return redirect()->route('admin.products.options.index', $product)
            ->with('status', __('admin.product_options.saved'));
    }

    public function edit(ProductOption $option): View
    {
        return view('admin.product-options.form', [
            'product' => $option->product,
            'option'  => $option->load('values'),
        ]);
    }

    public function update(ProductOptionRequest $request, ProductOption $option): RedirectResponse
    {
        $this->persist($request, $option);

        return redirect()->route('admin.products.options.index', $option->product)
            ->with('status', __('admin.product_options.saved'));
    }

    public function destroy(ProductOption $option): RedirectResponse
    {
        $product = $option->product;
        $option->delete();

        return redirect()->route('admin.products.options.index', $product)
            ->with('status', __('admin.product_options.deleted'));
    }

    /**
     * Save the option plus its values. Values are replaced wholesale (delete +
     * recreate) — there is no FK from order_items to product_option_values
     * (orders keep a JSON snapshot), so re-issuing value ids is safe and
     * avoids diffing.
     */
    private function persist(ProductOptionRequest $request, ProductOption $option): void
    {
        $data = $request->validated();
        $default = $request->input('default_index');

        DB::transaction(function () use ($option, $data, $default) {
            $option->fill([
                'key'         => $data['key'],
                'label'       => $data['label'],
                'type'        => 'select',
                'is_required' => $data['is_required'] ?? false,
                'is_active'   => $data['is_active'] ?? false,
                'sort_order'  => (int) ($data['sort_order'] ?? 0),
            ])->save();

            $option->values()->delete();

            foreach ($data['values'] as $i => $row) {
                $option->values()->create([
                    'label'       => $row['label'],
                    'price_delta' => $row['price_delta'],
                    'is_default'  => $default !== null && $default !== '' && (int) $default === $i,
                    'is_active'   => (bool) ($row['is_active'] ?? false),
                    'sort_order'  => (int) ($row['sort_order'] ?? $i),
                ]);
            }
        });
    }
}
