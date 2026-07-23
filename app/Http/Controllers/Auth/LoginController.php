<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt($request->credentials(), $remember)) {
            throw ValidationException::withMessages([
                'login' => __('auth_ui.errors.invalid'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()
            ->intended(route('account.dashboard'))
            ->with('status', __('auth_ui.flash.logged_in'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('root')
            ->with('status', __('auth_ui.flash.logged_out'));
    }
}
