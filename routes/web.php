<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GiftCardController as AdminGiftCardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Storefront pages are bilingual and live under a "{locale}" prefix. Auth,
| account, cart and wishlist routes are locale-agnostic (language follows the
| session), so route('login') / route('cart.index') resolve without a locale
| argument — which also keeps the framework's auth redirect working.
|
*/

Route::get('/', function () {
    $locale = session('locale', config('aroma.default_locale', 'ar'));

    return redirect("/{$locale}");
})->name('root');

Route::get('locale/{locale}', function (string $locale) {
    $supported = array_keys(config('aroma.locales', []));

    if (! in_array($locale, $supported, true)) {
        return redirect()->back();
    }

    session(['locale' => $locale]);
    app()->setLocale($locale);

    // Return to the page the visitor came from — but if that URL is a storefront
    // page under a /{locale} prefix, swap the prefix so the switch actually
    // takes effect (the route prefix otherwise wins over the session choice).
    $previous = url()->previous();

    if ($previous && $previous !== url()->current()) {
        $path     = trim((string) parse_url($previous, PHP_URL_PATH), '/');
        $query    = parse_url($previous, PHP_URL_QUERY);
        $segments = $path === '' ? [] : explode('/', $path);

        if (isset($segments[0]) && in_array($segments[0], $supported, true)) {
            $segments[0] = $locale;

            return redirect(url(implode('/', $segments)).($query ? '?'.$query : ''));
        }

        return redirect($previous);
    }

    return redirect("/{$locale}");
})->name('locale.switch');

/* Authentication ----------------------------------------------------------- */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('password/forgot', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('password/forgot', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('password/reset/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');

    Route::get('auth/{provider}', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/* Account (authenticated) -------------------------------------------------- */
Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('account.dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');

    Route::prefix('addresses')->name('account.addresses.')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->name('index');
        Route::get('create', [AddressController::class, 'create'])->name('create');
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::get('{address}/edit', [AddressController::class, 'edit'])->name('edit');
        Route::put('{address}', [AddressController::class, 'update'])->name('update');
        Route::delete('{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::patch('{address}/default', [AddressController::class, 'setDefault'])->name('default');
    });
});

Route::post('wishlist/{product}', [WishlistController::class, 'toggle'])->middleware('auth')->name('wishlist.toggle');

/* Cart (session — guests welcome; admins can't shop) ---------------------- */
Route::prefix('cart')->middleware('not_admin')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::post('/', [CartController::class, 'store'])->name('cart.store');
    Route::patch('{rowId}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::delete('{rowId}', [CartController::class, 'remove'])->name('cart.remove');
});

/* Checkout (guests welcome, but prompted to sign in first) ---------------- */
Route::prefix('checkout')->name('checkout.')->middleware('not_admin')->group(function () {
    Route::get('review', [CheckoutController::class, 'review'])->name('review');
    Route::get('start', [CheckoutController::class, 'start'])->name('start');
    Route::get('login', [CheckoutController::class, 'redirectToLogin'])->name('login');
    Route::get('register', [CheckoutController::class, 'redirectToRegister'])->name('register');
    Route::get('address', [CheckoutController::class, 'showAddressForm'])->name('address');
    Route::post('address', [CheckoutController::class, 'storeAddress'])->name('address.store');
    Route::get('gift-options', [CheckoutController::class, 'showGiftOptions'])->name('gift-options');
    Route::post('gift-options', [CheckoutController::class, 'storeGiftOptions'])->name('gift-options.store');
    Route::get('delivery', [CheckoutController::class, 'showDelivery'])->name('delivery');
    Route::post('delivery', [CheckoutController::class, 'storeDelivery'])->name('delivery.store');
    Route::get('order-review', [CheckoutController::class, 'showOrderReview'])->name('order-review');
    Route::get('payment', [CheckoutController::class, 'showPaymentForm'])->name('payment');
    Route::post('payment', [CheckoutController::class, 'storePayment'])->name('payment.store');
});

Route::get('order/{order}/confirmation', [CheckoutController::class, 'confirmation'])
    ->name('order.confirmation');

/* Legal / static pages ----------------------------------------------------- */
Route::view('terms', 'pages.terms')->name('terms');

/* Fresh CSRF token — lets the storefront JS recover from a stale token on a
   long-open tab (419) instead of failing the shopper's action. */
Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.token');

/* AI concierge ------------------------------------------------------------- */
Route::prefix('assistant')->name('assistant.')->middleware('throttle:20,1')->group(function () {
    Route::post('chat', [AssistantController::class, 'chat'])->name('chat');
    Route::post('reset', [AssistantController::class, 'reset'])->name('reset');
});

/* Orders (authenticated) ------------------------------------------------- */
Route::middleware('auth')->prefix('orders')->name('order.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('{order}', [OrderController::class, 'show'])->name('show');
});

/* Admin back-office UI (Blade) --------------------------------------------- */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminLoginController::class, 'show'])->name('login');
        Route::post('login', [AdminLoginController::class, 'login'])->name('login.attempt');
    });
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('products', AdminProductController::class);
        Route::resource('categories', AdminCategoryController::class)->except('show');
        Route::resource('brands', AdminBrandController::class)->except('show');
        Route::resource('gift-cards', AdminGiftCardController::class)->except('show');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

        Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::patch('customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    });
});

/* Storefront (bilingual) --------------------------------------------------- */
Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('category/{category}', [CategoryController::class, 'show'])->name('category.show');
        Route::get('product/{product}', [ProductController::class, 'show'])->name('product.show');
    });

/* Webhooks (unauthenticated but verified) -------------------------------- */
Route::post('webhooks/moyasar', [MoyasarWebhookController::class, 'handle'])->name('payment.webhook');
Route::get('payment/callback', [CheckoutController::class, 'paymentCallback'])->name('payment.callback');
