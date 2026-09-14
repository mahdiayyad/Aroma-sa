<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\View\View;

class ReferralController extends Controller
{
    private ReferralService $referrals;

    public function __construct(ReferralService $referrals)
    {
        $this->referrals = $referrals;
    }

    public function index(): View
    {
        $user = auth()->user();

        return view('account.referrals.index', [
            'user' => $user,
            'stats' => $this->referrals->stats($user),
            'referrals' => $user->referralsMade()->with('referred')->latest()->paginate(10, ['*'], 'referrals_page'),
            'pointTransactions' => $user->pointTransactions()->latest()->paginate(10, ['*'], 'points_page'),
        ]);
    }
}
