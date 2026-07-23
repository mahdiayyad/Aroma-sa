<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations so the rest of
 * the application can type-hint the contract. Add each aggregate's binding to
 * the $repositories map as modules land (Catalog, Orders, ...).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * interface => implementation
     *
     * @var array<class-string, class-string>
     */
    public array $repositories = [
        // Catalog
        \App\Repositories\Contracts\CategoryRepositoryInterface::class => \App\Repositories\Eloquent\CategoryRepository::class,
        \App\Repositories\Contracts\BrandRepositoryInterface::class    => \App\Repositories\Eloquent\BrandRepository::class,
        \App\Repositories\Contracts\ProductRepositoryInterface::class  => \App\Repositories\Eloquent\ProductRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
