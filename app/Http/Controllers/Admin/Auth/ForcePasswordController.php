<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service password change for the back-office. Reachable any time by an
 * authenticated admin/staff user, and the only route forced-redirect
 * destination for one whose must_change_password flag is still set (see
 * EnsureAdminPasswordIsCurrent) — e.g. straight after aroma:make-admin.
 */
class ForcePasswordController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.force-password', [
            'forced' => (bool) auth()->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('admin.change_password.wrong_current')]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('admin.dashboard')->with('status', __('admin.change_password.changed'));
    }
}
