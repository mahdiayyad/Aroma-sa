<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    use HandlesAdminForms;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()->withCount('products')->with('parent');

        if ($search = $request->query('q')) {
            $query->where('slug', 'like', "%{$search}%")
                ->orWhere('name->en', 'like', "%{$search}%")
                ->orWhere('name->ar', 'like', "%{$search}%");
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CategoryResource::collection(
            $query->orderBy('sort_order')->paginate((int) $request->query('per_page', 20))
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($this->payload($request));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products')->load('children'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($this->payload($request, $category));

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        // products.category_id is restrictOnDelete — block instead of orphaning.
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that still has products. Reassign them first.',
            ], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    /** @return array<string,mixed> */
    private function payload(CategoryRequest $request, ?Category $category = null): array
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug('categories', $data['slug'] ?? null, $data['name']['en'], optional($category)->id);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeUpload($request->file('image'), 'categories');
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
