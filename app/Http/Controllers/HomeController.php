<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Contracts\View\View;

/**
 * Storefront homepage. Sections are composed by the CatalogService (featured
 * categories, featured products, new arrivals). The nav category strip in the
 * header remains config/lang driven so navigation renders even before data.
 */
class HomeController extends Controller
{
    /** @var CatalogService */
    private $catalog;

    public function __construct(CatalogService $catalog)
    {
        $this->catalog = $catalog;
    }

    public function index(): View
    {
        return view('home.index', $this->catalog->homepage());
    }
}
