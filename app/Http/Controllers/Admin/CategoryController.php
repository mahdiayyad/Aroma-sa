<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use HandlesAdminForms;

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('products')->with('parent')->orderBy('sort_order')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
            'parents'  => Category::orderBy('sort_order')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($this->payload($request));

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.saved'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents'  => Category::where('id', '!=', $category->id)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->payload($request, $category));

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.saved'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        // products.category_id is restrictOnDelete() at the DB level, and
        // Product uses SoftDeletes — an unscoped exists() check would miss
        // archived products, pass this guard, then hit the FK constraint
        // anyway. withTrashed() keeps this guard honest with the schema.
        if ($category->products()->withTrashed()->exists()) {
            return redirect()->route('admin.categories.index')->with('error', __('admin.categories.cannot_delete'));
        }

        try {
            $category->delete();
        } catch (QueryException $e) {
            return redirect()->route('admin.categories.index')->with('error', __('admin.categories.cannot_delete'));
        }

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.deleted'));
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
