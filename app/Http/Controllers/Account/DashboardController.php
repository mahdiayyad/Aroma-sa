<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('account.dashboard', [
            'user'          => $user,
            'wishlistCount' => $user->wishlistItems()->count(),
        ]);
    }
}
