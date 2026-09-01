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

        $productSchema = $model->toSchemaOrgArray();
        $productSchema['offers']['url'] = route('product.show', [$locale, $model->slug]);

        return view('catalog.product', [
            'product' => $model,
            'gallery' => $this->gallery($model),
            'productSchema' => $productSchema,
            'breadcrumbSchema' => $this->breadcrumbSchema($model, $locale),
        ]);
    }

    /** @return array<string,mixed> */
    private function breadcrumbSchema(Product $product, string $locale): array
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.nav.home'), 'item' => route('home', $locale)],
        ];

        if ($product->category) {
            $items[] = [
                '@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name,
                'item' => route('category.show', [$locale, $product->category->slug]),
            ];
        }

        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $product->name];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * The product's own photos only — as uploaded in the admin. Used to
     * show the signature Aroma packaging and thank-you card here too (see
     * git history), but the gallery is the product's own images now, full
     * stop; nothing static gets appended.
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

        if ($gallery->isEmpty()) {
            $gallery->push(['url' => $product->primaryImageUrl(), 'alt' => (string) $product->name, 'label' => '']);
        }

        return $gallery;
    }
}
