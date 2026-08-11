<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Dynamic XML sitemap — regenerated from the live database on every request
 * (catalog is small; no caching needed yet) so new/removed products and
 * categories are reflected without a manual rebuild step. Only public,
 * indexable URLs are listed: home, active categories, active products, and
 * static legal pages — never cart/checkout/account/admin.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $locales = array_keys(config('aroma.locales', []));

        $categories = Category::active()->get(['slug', 'updated_at']);
        $products = Product::active()->get(['slug', 'updated_at']);

        $urls = new Collection();

        foreach ($locales as $locale) {
            $urls->push([
                'loc' => route('home', $locale),
                'lastmod' => now()->toAtomString(),
                'priority' => '1.0',
            ]);

            foreach ($categories as $category) {
                $urls->push([
                    'loc' => route('category.show', [$locale, $category->slug]),
                    'lastmod' => $category->updated_at->toAtomString(),
                    'priority' => '0.8',
                ]);
            }

            foreach ($products as $product) {
                $urls->push([
                    'loc' => route('product.show', [$locale, $product->slug]),
                    'lastmod' => $product->updated_at->toAtomString(),
                    'priority' => '0.7',
                ]);
            }
        }

        foreach (['terms' => '0.3', 'about' => '0.5', 'privacy-policy' => '0.3', 'contact' => '0.5'] as $routeName => $priority) {
            $urls->push([
                'loc' => route($routeName),
                'lastmod' => now()->toAtomString(),
                'priority' => $priority,
            ]);
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
