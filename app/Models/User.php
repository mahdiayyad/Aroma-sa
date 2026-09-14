<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'gender',
        'dob',
        'locale',
        'avatar',
        'provider',
        'provider_id',
        'role',
        // Admin-only fields (set exclusively by Admin\CustomerController /
        // Api\Admin\CustomerController, both gated behind ['auth','admin']
        // route middleware and — for 'role' specifically — the extra
        // admin-only check in CustomerController::update). Without these in
        // $fillable, Eloquent's mass-assignment guard was silently dropping
        // every admin loyalty-points/active-toggle save — a real, verified
        // bug (caught by a test asserting the value actually persisted),
        // not a hypothetical one. No customer-facing controller references
        // either field, so this doesn't widen what a shopper can self-set.
        'is_active',
        'loyalty_points',
    ];

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_STAFF = 'staff';
    public const ROLE_ADMIN = 'admin';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'dob'               => 'date',
        'loyalty_points'    => 'integer',
        'is_active'         => 'boolean',
    ];

    /* Relationships ------------------------------------------------------- */

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /** Products the user has wishlisted. */
    public function wishlistedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** The user who referred this account, if any (frozen at registration). */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    /** This user's own successful referrals (as the referrer). */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /* Helpers ------------------------------------------------------------- */

    public function hasWishlisted(int $productId): bool
    {
        return $this->wishlistItems()->where('product_id', $productId)->exists();
    }

    /** Staff and admins may reach the back-office. */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STAFF], true);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
