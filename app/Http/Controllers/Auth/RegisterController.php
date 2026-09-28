<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\StashesReferralCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Create your account" — the sign-up face of the phone-number OTP flow. It
 * posts to the same endpoints as sign-in (see OtpController): a number with no
 * account gets one after its code is verified, asking only for a name. Someone
 * who already has an account and uses this page is simply signed in.
 */
class RegisterController extends Controller
{
    use StashesReferralCode;

    public function create(Request $request): View
    {
        $this->stashReferralCode($request);

        return view('auth.register');
    }
}
