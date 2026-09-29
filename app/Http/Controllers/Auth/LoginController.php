<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\StashesReferralCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Renders the sign-in page (mobile number, or email + password — see OtpController
 * and EmailLoginController for the two flows) and handles logout.
 */
class LoginController extends Controller
{
    use StashesReferralCode;

    public function create(Request $request): View
    {
        $this->stashReferralCode($request);

        return view('auth.login');
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
