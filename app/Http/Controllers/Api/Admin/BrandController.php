<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\Admin\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    use HandlesAdminForms;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Brand::query()->withCount('products');

        if ($search = $request->query('q')) {
            $query->where('slug', 'like', "%{$search}%")
                ->orWhere('name->en', 'like', "%{$search}%")
                ->orWhere('name->ar', 'like', "%{$search}%");
        }

        return BrandResource::collection(
            $query->orderBy('id', 'desc')->paginate((int) $request->query('per_page', 20))
        );
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $brand = Brand::create($this->payload($request));

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand->loadCount('products'));
    }

    public function update(BrandRequest $request, Brand $brand): BrandResource
    {
        $brand->update($this->payload($request, $brand));

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        // products.brand_id is nullOnDelete — deleting simply unassigns products.
        $brand->delete();

        return response()->json(null, 204);
    }

    /** @return array<string,mixed> */
    private function payload(BrandRequest $request, ?Brand $brand = null): array
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug('brands', $data['slug'] ?? null, $data['name']['en'], optional($brand)->id);

        if ($request->hasFile('logo')) {
            $data['logo'] = $this->storeUpload($request->file('logo'), 'brands');
        } else {
            unset($data['logo']);
        }

        return $data;
    }
}
