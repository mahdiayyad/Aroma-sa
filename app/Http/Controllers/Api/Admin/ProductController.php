<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    use HandlesAdminForms;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(['category', 'brand']);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        $sort = $request->query('sort', 'newest');
        $query->when($sort === 'price_asc', fn ($q) => $q->orderBy('base_price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('base_price'))
            ->when($sort === 'newest', fn ($q) => $q->latest());

        return ProductResource::collection($query->paginate((int) $request->query('per_page', 20)));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($this->payload($request));
        $this->syncImages($request, $product);

        return (new ProductResource($product->load(['category', 'brand', 'variants', 'images'])))
            ->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'brand', 'variants', 'images']));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($this->payload($request, $product));
        $this->syncImages($request, $product);

        return new ProductResource($product->load(['category', 'brand', 'variants', 'images']));
    }

    public function destroy(Product $product): JsonResponse
    {
        // Product uses SoftDeletes — order_items keep their FK (restrictOnDelete
        // only blocks a hard delete). This is recoverable.
        $product->delete();

        return response()->json(null, 204);
    }

    /** @return array<string,mixed> */
    private function payload(ProductRequest $request, ?Product $product = null): array
    {
        $data = $request->validated();
        unset($data['images']); // handled separately
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
            $path = $this->storeUpload($file, 'products');
            $product->images()->create([
                'disk'       => 'public',
                'path'       => $path,
                'is_primary' => ! $hasPrimary,
                'sort_order' => ++$sort,
            ]);
            $hasPrimary = true;
        }
    }
}
