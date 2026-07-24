<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    use HandlesAdminForms;

    public function index(): View
    {
        return view('admin.brands.index', [
            'brands' => Brand::withCount('products')->orderBy('id', 'desc')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        Brand::create($this->payload($request));

        return redirect()->route('admin.brands.index')->with('status', __('admin.brands.saved'));
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->payload($request, $brand));

        return redirect()->route('admin.brands.index')->with('status', __('admin.brands.saved'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', __('admin.brands.deleted'));
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
