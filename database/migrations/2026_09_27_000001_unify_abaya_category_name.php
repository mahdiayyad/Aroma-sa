<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

/**
 * Data-only fix: local and production had drifted onto different English
 * names for the same category (e.g. "Abaya", "ABAYA") — this makes it
 * "Abayas" everywhere a migration runs, local and production alike, so the
 * two stop disagreeing. Matches by content (English name starts with
 * "abaya", case-insensitive) rather than by slug/id, so it corrects
 * whichever spelling each environment currently has instead of assuming one.
 * Arabic name is untouched — only the English name was reported as
 * conflicting. Idempotent: running it again on an environment that's
 * already "Abayas" changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Category::query()->get()->each(function (Category $category) {
            $name = (string) ($category->getTranslations('name')['en'] ?? '');

            if (stripos($name, 'abaya') === 0 && $name !== 'Abayas') {
                $category->setTranslation('name', 'en', 'Abayas')->save();
            }
        });
    }

    /**
     * Not reversible: each environment's original (already-inconsistent)
     * name isn't recorded anywhere, so there's nothing correct to roll back to.
     */
    public function down(): void
    {
    }
};
