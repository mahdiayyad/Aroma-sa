<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('account.profile', ['user' => auth()->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        // Reflect a locale change immediately.
        if ($request->filled('locale')) {
            session(['locale' => $request->input('locale')]);
        }

        return back()->with('status', __('account.flash.profile_updated'));
    }
}
