<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ProductFilters;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Same filter object the export uses, so "Export current filter" matches this list exactly.
        ProductFilters::apply($query, $request->only(['q', 'category', 'active']));

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

    /**
     * Archived (soft-deleted) products — invisible everywhere else, but a
     * category whose only products are archived still can't be deleted
     * (products.category_id is restrictOnDelete()), so admins need a way to
     * restore or permanently remove them without developer intervention.
     */
    public function trashed(Request $request): View
    {
        $query = Product::onlyTrashed()->with(['category', 'brand']);

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")
                ->orWhere('name->en', 'like', "%{$search}%")
                ->orWhere('name->ar', 'like', "%{$search}%"));
        }

        return view('admin.products.trashed', [
            'products' => $query->orderByDesc('deleted_at')->paginate(15)->withQueryString(),
            'filters'  => $request->only('q'),
        ]);
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        return redirect()->route('admin.products.trashed')->with('status', __('admin.products.restored'));
    }

    /**
     * Permanent delete. Images/variants/options/wishlist rows cascade at the
     * DB level, but that raw cascade never fires ProductImage's own storage
     * cleanup — so uploaded files are removed here first, the same way
     * destroyImage() does it. order_items.product_id is restrictOnDelete(),
     * so a product with real order history is protected and reports back
     * as a friendly error instead of a 500.
     */
    public function forceDestroy(Product $product): RedirectResponse
    {
        foreach ($product->images as $image) {
            if ($image->path && ! preg_match('#^(https?:)?/#', $image->path)) {
                Storage::disk($image->disk)->delete($image->path);
            }
        }

        try {
            $product->forceDelete();
        } catch (QueryException $e) {
            return redirect()->route('admin.products.trashed')->with('error', __('admin.products.cannot_force_delete'));
        }

        return redirect()->route('admin.products.trashed')->with('status', __('admin.products.force_deleted'));
    }

    /**
     * Remove a single gallery image from a product (AJAX — the media picker
     * lives inside the product edit form, and a nested <form> per thumbnail
     * isn't valid HTML). Promotes the next remaining image to primary if the
     * deleted one was it, so the storefront/admin thumbnail stays deterministic.
     */
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $wasPrimary = (bool) $image->is_primary;
        $path = $image->path;
        $disk = $image->disk;

        $image->delete();

        // Root-relative/absolute paths (e.g. the seeded placeholder) never
        // lived on a disk — matches ProductImage::url()'s own check.
        if ($path && ! preg_match('#^(https?:)?/#', $path)) {
            Storage::disk($disk)->delete($path);
        }

        if ($wasPrimary) {
            $next = $product->images()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        return response()->json(['message' => __('admin.products.image_deleted')]);
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
