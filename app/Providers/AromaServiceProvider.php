<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ChatAssistant;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Services\Ai\GeminiAssistant;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Wires brand-level concerns into the framework:
 *  - shares the Aroma brand config (name, tagline, colours, locales) with every
 *    view so layouts never hard-code identity values;
 *  - registers a @price Blade directive backed by the Money formatter.
 */
class AromaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding to change AI provider — nothing else needs to know.
        $this->app->bind(ChatAssistant::class, GeminiAssistant::class);
    }

    public function boot(): void
    {
        // This framework build predates useBootstrapFive(); the Bootstrap-4
        // pagination view shares .page-item/.page-link classes with Bootstrap 5,
        // so it renders correctly against the CDN stylesheet.
        Paginator::useBootstrap();

        // Branded pagination (resources/views/vendor/pagination/aroma.blade.php)
        // applies automatically to every existing ->links() call site-wide.
        Paginator::defaultView('vendor.pagination.aroma');
        Paginator::defaultSimpleView('vendor.pagination.aroma');

        View::share('brand', config('aroma.brand'));
        View::share('aromaColors', config('aroma.colors'));
        View::share('locales', config('aroma.locales'));

        // Which OAuth buttons to actually render — a provider only shows up
        // once real credentials are configured, so an unconfigured button
        // (e.g. Apple, today) never sits on the page failing on click.
        View::composer('auth.partials.social', function ($view) {
            $view->with('socialProviders', SocialAuthController::configuredProviders());
        });

        // Usage in Blade: @price($product->price)
        Blade::directive('price', function ($expression) {
            return "<?php echo e(\\App\\Support\\Formatting\\Money::format($expression)); ?>";
        });
    }
}
