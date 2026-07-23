<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    /** @var CatalogService */
    private $catalog;

    public function __construct(CatalogService $catalog)
    {
        $this->catalog = $catalog;
    }

    public function show(string $locale, string $product): View
    {
        $model = $this->catalog->findProduct($product);

        if (! $model) {
            throw new NotFoundHttpException();
        }

        return view('catalog.product', ['product' => $model]);
    }
}
