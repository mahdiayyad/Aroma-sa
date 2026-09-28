<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeSaudiUserPhonesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        require_once database_path('migrations/2026_09_28_100100_normalize_saudi_user_phones.php');
        (new \NormalizeSaudiUserPhones())->up();
    }

    public function test_legacy_spellings_are_canonicalised_so_otp_login_can_find_them(): void
    {
        $a = User::factory()->create(['phone' => '0570574471']);
        $b = User::factory()->create(['phone' => '966 57 057 4472']);
        $c = User::factory()->create(['phone' => '+966570574473']); // already canonical

        $this->runMigration();

        $this->assertSame('+966570574471', $a->fresh()->phone);
        $this->assertSame('+966570574472', $b->fresh()->phone);
        $this->assertSame('+966570574473', $c->fresh()->phone);
    }

    public function test_non_saudi_and_empty_numbers_are_left_alone(): void
    {
        $uae = User::factory()->create(['phone' => '+971501234567']);
        $none = User::factory()->create(['phone' => null]);

        $this->runMigration();

        $this->assertSame('+971501234567', $uae->fresh()->phone);
        $this->assertNull($none->fresh()->phone);
    }

    public function test_a_spelling_that_would_collide_with_another_account_is_skipped_not_merged(): void
    {
        $owner = User::factory()->create(['phone' => '+966570574471']);
        $duplicate = User::factory()->create(['phone' => '0570574471']);

        $this->runMigration();

        $this->assertSame('+966570574471', $owner->fresh()->phone);
        $this->assertSame('0570574471', $duplicate->fresh()->phone);
    }
}
