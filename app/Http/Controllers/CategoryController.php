<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Services\TrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
    public function show(Request $request, TrackingService $tracking, string $locale, string $category): View
    {
        $model = $this->catalog->findCategory($category);

        if (! $model) {
            throw new NotFoundHttpException();
        }

        $filters = $request->only(['brand', 'price_min', 'price_max', 'on_sale', 'sort']);
        $products = $this->catalog->productsForCategory($model, $filters);

        if ($tracking->enabled()) {
            $tracking->push($tracking->viewItemList($model, EloquentCollection::make($products->items())->load('category')));
        }

        return view('catalog.category', [
            'category' => $model,
            'products' => $products,
            'subCategories' => $this->catalog->subCategories($model),
            'brands'   => $this->catalog->activeBrands(),
            'filters'  => $filters,
        ]);
    }
}
