<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * A shared referral link (?ref=AROMA-XXXXXX) lands on the sign-in or sign-up
 * page; the code has to survive the verify → "your name" pages that follow so
 * the new-account step can prefill it. Anything that isn't a plain code is ignored.
 */
trait StashesReferralCode
{
    private function stashReferralCode(Request $request): void
    {
        $ref = trim((string) $request->query('ref'));

        if ($ref !== '' && preg_match('/^[A-Za-z0-9_-]{1,50}$/', $ref) === 1) {
            session(['referral_code_prefill' => $ref]);
        }
    }
}
