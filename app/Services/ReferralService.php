<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Support\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Generates referral codes and applies them at registration. The reward
 * operation (crediting both sides) is atomic and idempotent: the whole
 * thing runs in one transaction, and referrals.unique(referred_id) means a
 * replayed or concurrently-duplicated registration request can insert the
 * Referral row at most once — a second attempt throws a unique-constraint
 * violation, caught here and treated as "already processed", never a
 * second reward.
 */
class ReferralService extends BaseService
{
    private RewardPointService $points;

    public function __construct(RewardPointService $points)
    {
        $this->points = $points;
    }

    /**
     * "AROMA-" + a short code derived from the user's name where possible
     * (e.g. AROMA-MAHDI), falling back to a random alphanumeric suffix when
     * the name has no usable Latin characters (common for Arabic-only
     * names, which the obvious "slugify the name" approach silently breaks
     * on) or when the derived code collides. Generate-and-check-uniqueness,
     * the same shape as HandlesAdminForms::uniqueSlug().
     */
    public function generateCode(User $user): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Str::before($user->name, ' ') ?: $user->name));
        $base = substr($base, 0, 8);

        if (strlen($base) < 3) {
            $base = $this->randomSuffix();
        }

        $candidate = 'AROMA-'.$base;
        $attempts = 0;

        while (User::where('referral_code', $candidate)->exists()) {
            $candidate = 'AROMA-'.$base.$this->randomSuffix(2);
            if (++$attempts > 20) {
                $candidate = 'AROMA-'.$this->randomSuffix();
            }
        }

        return $candidate;
    }

    private function randomSuffix(int $length = 6): string
    {
        return strtoupper(Str::random($length));
    }

    /**
     * Non-blocking by design: an unrecognized/invalid code never fails
     * registration — it's simply not applied. Losing an entire signup over
     * a mistyped referral code would be far worse UX than silently
     * skipping the reward.
     */
    public function applyReferral(User $newUser, ?string $code): void
    {
        $code = $code ? strtoupper(trim($code)) : null;

        if (! $code) {
            return;
        }

        $referrer = User::where('referral_code', $code)->first();

        // Structurally can't self-refer at registration (the referred user
        // doesn't exist yet when they type in a code), but guarded anyway
        // in case this method is ever reused outside that flow.
        if (! $referrer || $referrer->id === $newUser->id) {
            return;
        }

        $this->transaction(function () use ($referrer, $newUser) {
            try {
                $referral = Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $newUser->id,
                    'status' => Referral::STATUS_REWARDED,
                    'rewarded_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // unique(referred_id) tripped — this user was already
                // referred (a genuine race: two concurrent requests for the
                // same new account). Already processed, nothing to do.
                return;
            }

            // update() would silently no-op here: referred_by_user_id is
            // deliberately absent from User::$fillable (never settable via
            // any request), so the mass-assignment guard would drop it.
            $newUser->forceFill(['referred_by_user_id' => $referrer->id])->save();

            $signupBonus = (int) config('aroma.rewards.referral_signup_bonus', 100);
            $referrerReward = (int) config('aroma.rewards.referral_reward', 100);

            $this->points->credit(
                $newUser, $signupBonus, PointTransaction::TYPE_REFERRAL_SIGNUP_BONUS, $referral,
                __('referral.ledger.signup_bonus', ['code' => $referrer->referral_code])
            );
            $this->points->credit(
                $referrer, $referrerReward, PointTransaction::TYPE_REFERRAL_REWARD, $referral,
                __('referral.ledger.referral_reward', ['name' => $newUser->name])
            );
        });
    }

    /** @return array{total:int,successful:int,pending:int,points_earned:int,balance:int} */
    public function stats(User $user): array
    {
        $referrals = $user->referralsMade();

        return [
            'total' => (clone $referrals)->count(),
            'successful' => (clone $referrals)->where('status', Referral::STATUS_REWARDED)->count(),
            'pending' => (clone $referrals)->where('status', Referral::STATUS_PENDING)->count(),
            'points_earned' => (int) $user->pointTransactions()->where('type', PointTransaction::TYPE_REFERRAL_REWARD)->sum('points'),
            'balance' => $this->points->balance($user),
        ];
    }
}
