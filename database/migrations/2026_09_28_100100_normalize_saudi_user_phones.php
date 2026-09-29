<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The mobile number is now the sign-in credential and OTP login looks accounts
 * up by exact match on the canonical "+9665XXXXXXXX" form. Numbers entered
 * through the app's phone field were already E.164, but anything written any
 * other way ("05…", "966 5…", spaces) would never match, silently locking that
 * customer out. This canonicalises Saudi-looking values in place.
 *
 * Non-Saudi and unparseable numbers are left untouched (they can't receive a
 * Tawked code either way), and a value is skipped — and logged — if
 * canonicalising it would collide with another account's number on the unique
 * index, so a human can decide which account is the real owner.
 */
class NormalizeSaudiUserPhones extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('phone')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $canonical = Phone::normalizeSaudi($user->phone);

                if ($canonical === null || $canonical === $user->phone) {
                    continue;
                }

                $taken = DB::table('users')->where('phone', $canonical)->where('id', '!=', $user->id)->exists();

                if ($taken) {
                    Log::warning('Phone normalisation skipped: canonical number belongs to another account', [
                        'user_id' => $user->id,
                        'phone' => Phone::mask($user->phone),
                    ]);

                    continue;
                }

                DB::table('users')->where('id', $user->id)->update(['phone' => $canonical]);
            }
        });
    }

    /** Data-only change; the original spellings aren't recorded, so nothing to restore. */
    public function down(): void
    {
    }
}
