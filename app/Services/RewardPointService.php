<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\User;
use App\Support\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * The single gate for every points balance change — nothing else in the
 * app should write to users.loyalty_points directly. Centralizes the
 * points<->SAR conversion (config('aroma.rewards')) so it's never
 * hardcoded at a call site, and makes every change auditable via
 * point_transactions (balance_after is snapshotted on each row).
 */
class RewardPointService extends BaseService
{
    public function credit(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): PointTransaction
    {
        return $this->write($user->id, abs($points), $type, $reference, $description);
    }

    /**
     * @return PointTransaction|null null when the user doesn't have enough
     *         points to cover the debit — the caller decides how to react
     *         (e.g. reject a redemption); never lets a balance go negative.
     */
    public function debit(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): ?PointTransaction
    {
        return $this->write($user->id, -abs($points), $type, $reference, $description, false);
    }

    public function balance(User $user): int
    {
        return (int) $user->fresh()->loyalty_points;
    }

    public function toSar(int $points): float
    {
        $perSar = max(1, (int) config('aroma.rewards.points_per_sar', 10));

        return round($points / $perSar, 2);
    }

    public function toPoints(float $sar): int
    {
        $perSar = max(1, (int) config('aroma.rewards.points_per_sar', 10));

        return (int) round($sar * $perSar);
    }

    /**
     * Atomic write: lock the user row, compute the new balance, insert the
     * ledger row with that balance snapshotted, then persist it onto the
     * denormalized users.loyalty_points column — all inside one transaction
     * so the ledger and the running total can never drift apart. A debit
     * that would take the balance below zero is refused (returns null)
     * rather than clamped, so a caller never silently under-charges.
     */
    private function write(int $userId, int $signedPoints, string $type, ?Model $reference, ?string $description, bool $allowNegative = true): ?PointTransaction
    {
        return $this->transaction(function () use ($userId, $signedPoints, $type, $reference, $description, $allowNegative) {
            $locked = User::whereKey($userId)->lockForUpdate()->first();
            $newBalance = $locked->loyalty_points + $signedPoints;

            if ($newBalance < 0) {
                if (! $allowNegative) {
                    return null;
                }
                $newBalance = 0;
            }

            $locked->update(['loyalty_points' => $newBalance]);

            return PointTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'points' => $signedPoints,
                'balance_after' => $newBalance,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->getKey() : null,
                'description' => $description,
            ]);
        });
    }
}
