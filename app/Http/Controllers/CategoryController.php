<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{
    /** @var CatalogService */
    private $catalog;

    public function __construct(CatalogService $catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * Category listing page with brand/price/sort filters.
     * Slug binding is manual so we can enforce the "active" scope + 404 cleanly.
     */
    public function show(Request $request, string $locale, string $category): View
    {
        $model = $this->catalog->findCategory($category);

        if (! $model) {
            throw new NotFoundHttpException();
        }

        $filters = $request->only(['brand', 'price_min', 'price_max', 'sort']);

        return view('catalog.category', [
            'category' => $model,
            'products' => $this->catalog->productsForCategory($model, $filters),
            'brands'   => $this->catalog->activeBrands(),
            'filters'  => $filters,
        ]);
    }
}
