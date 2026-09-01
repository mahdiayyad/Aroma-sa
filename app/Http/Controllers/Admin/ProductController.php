<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HandlesAdminForms;

    public function index(Request $request): View
    {
        // 'images' is eager-loaded (not just withCount) because the index
        // view's thumbnail calls $product->primaryImageUrl(), which reads
        // the images relation directly — without this it was a genuine N+1
        // (one extra query per row, up to 15/page) despite the withCount
        // already being here.
        $query = Product::query()->with(['category', 'brand', 'images'])->withCount('images');

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")
                ->orWhere('name->en', 'like', "%{$search}%")
                ->orWhere('name->ar', 'like', "%{$search}%"));
        }
        if ($request->filled('category')) {
            $query->where('category_id', (int) $request->query('category'));
        }
        if ($request->filled('active') && $request->query('active') !== 'all') {
            $query->where('is_active', $request->query('active') === '1');
        }

        return view('admin.products.index', [
            'products'   => $query->latest()->paginate(15)->withQueryString(),
            'categories' => Category::orderBy('sort_order')->get(),
            'filters'    => $request->only(['q', 'category', 'active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product'    => new Product(['is_active' => true, 'is_gift_eligible' => true, 'currency' => 'SAR']),
            'categories' => Category::orderBy('sort_order')->get(),
            'brands'     => Brand::orderBy('id')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($this->payload($request));
        $this->syncImages($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.products.saved'));
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product'    => $product->load('images'),
            'categories' => Category::orderBy('sort_order')->get(),
            'brands'     => Brand::orderBy('id')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->payload($request, $product));
        $this->syncImages($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.products.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', __('admin.products.deleted'));
    }

    /** @return array<string,mixed> */
    private function payload(ProductRequest $request, ?Product $product = null): array
    {
        $data = $request->validated();
        unset($data['images']);
        $data['slug'] = $this->uniqueSlug('products', $data['slug'] ?? null, $data['name']['en'], optional($product)->id);
        $data['currency'] = $data['currency'] ?? 'SAR';

        return $data;
    }

    private function syncImages(ProductRequest $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $sort = (int) $product->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $product->images()->create([
                'disk'       => 'public',
                'path'       => $this->storeUpload($file, 'products'),
                'is_primary' => ! $hasPrimary,
                'sort_order' => ++$sort,
            ]);
            $hasPrimary = true;
        }
    }
}
