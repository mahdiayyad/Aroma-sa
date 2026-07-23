<?php

declare(strict_types=1);

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WishlistController;
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
    if (array_key_exists($locale, config('aroma.locales', []))) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }

    return redirect(url()->previous() !== url()->current() ? url()->previous() : "/{$locale}");
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
});

Route::post('wishlist/{product}', [WishlistController::class, 'toggle'])->middleware('auth')->name('wishlist.toggle');

/* Cart (session — guests welcome) ----------------------------------------- */
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::post('/', [CartController::class, 'store'])->name('cart.store');
    Route::patch('{rowId}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::delete('{rowId}', [CartController::class, 'remove'])->name('cart.remove');
});

/* Storefront (bilingual) --------------------------------------------------- */
Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('category/{category}', [CategoryController::class, 'show'])->name('category.show');
        Route::get('product/{product}', [ProductController::class, 'show'])->name('product.show');
    });
