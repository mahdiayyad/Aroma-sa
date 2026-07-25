<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

        return view('catalog.product', [
            'product' => $model,
            'gallery' => $this->gallery($model),
        ]);
    }

    /**
     * The product's own photos, followed — always — by the signature Aroma
     * packaging and thank-you card, so every product shows how it arrives.
     *
     * @return Collection<int,array{url:string,alt:string,label:string}>
     */
    private function gallery(Product $product): Collection
    {
        $gallery = new Collection();

        foreach ($product->images as $image) {
            $gallery->push([
                'url'   => $image->url(),
                'alt'   => (string) ($image->translate('alt') ?? $product->name),
                'label' => '',
            ]);
        }

        $packaging = [
            'packaging-gift.jpg' => __('storefront.packaging.gift'),
            'packaging-bags.jpg' => __('storefront.packaging.bags'),
            'thank-you-card.jpg' => __('storefront.packaging.card'),
        ];

        foreach ($packaging as $file => $label) {
            if (is_file(public_path('images/brand/'.$file))) {
                $gallery->push(['url' => asset('images/brand/'.$file), 'alt' => (string) $label, 'label' => (string) $label]);
            }
        }

        if ($gallery->isEmpty()) {
            $gallery->push(['url' => $product->primaryImageUrl(), 'alt' => (string) $product->name, 'label' => '']);
        }

        return $gallery;
    }
}
