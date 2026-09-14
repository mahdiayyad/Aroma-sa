<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    private ReferralService $referrals;

    public function __construct(ReferralService $referrals)
    {
        $this->referrals = $referrals;
    }

    public function create()
    {
        return view('auth.register', [
            // Prefills the referral-code field from a shared referral link
            // (?ref=AROMA-XXXXXX) — see account/referrals/index.blade.php.
            'referralCode' => request()->query('ref'),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'] ?? null,
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'gender'   => $data['gender'] ?? 'unspecified',
                'locale'   => app()->getLocale(),
            ]);

            // Every registered customer gets a referral code — not
            // mass-assignable (not in User::$fillable), set directly here.
            $user->forceFill(['referral_code' => $this->referrals->generateCode($user)])->save();

            $this->referrals->applyReferral($user, $data['referral_code'] ?? null);

            return $user;
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()
            ->intended(route('account.dashboard'))
            ->with('status', __('auth_ui.flash.registered'));
    }
}
