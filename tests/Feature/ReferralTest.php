<?php

namespace Tests\Feature;

use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\RewardPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Layla Ahmed',
            'email'                 => 'layla@example.com',
            'password'              => 'Passw0rd1',
            'password_confirmation' => 'Passw0rd1',
        ], $overrides);
    }

    /* ---- Every new customer gets a code ------------------------------------ */

    public function test_every_registered_customer_receives_a_unique_referral_code(): void
    {
        $this->post('/register', $this->validPayload())->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'layla@example.com')->first();
        $this->assertNotNull($user->referral_code);
        $this->assertStringStartsWith('AROMA-', $user->referral_code);
    }

    public function test_referral_codes_are_unique_even_for_customers_sharing_a_first_name(): void
    {
        $service = app(ReferralService::class);
        $a = User::factory()->create(['name' => 'Sara Al Qahtani', 'referral_code' => null]);
        $b = User::factory()->create(['name' => 'Sara Al Otaibi', 'referral_code' => null]);

        $codeA = $service->generateCode($a);
        $a->forceFill(['referral_code' => $codeA])->save();
        $codeB = $service->generateCode($b);

        $this->assertNotSame($codeA, $codeB);
    }

    public function test_referral_code_generation_handles_arabic_only_names(): void
    {
        $service = app(ReferralService::class);
        $user = User::factory()->create(['name' => 'سارة القحطاني', 'referral_code' => null]);

        $code = $service->generateCode($user);

        $this->assertStringStartsWith('AROMA-', $code);
        $this->assertMatchesRegularExpression('/^AROMA-[A-Z0-9]+$/', $code);
    }

    /* ---- Valid referral rewards both sides ---------------------------------- */

    public function test_a_valid_referral_code_rewards_both_customers(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'AROMA-MAHDI']);

        $this->post('/register', $this->validPayload(['referral_code' => 'aroma-mahdi']))
            ->assertRedirect(route('account.dashboard'));

        $newUser = User::where('email', 'layla@example.com')->first();

        $this->assertSame($referrer->id, $newUser->referred_by_user_id);
        $this->assertEquals(100, $newUser->loyalty_points);
        $this->assertEquals(100, $referrer->fresh()->loyalty_points);

        $this->assertDatabaseHas('referrals', ['referrer_id' => $referrer->id, 'referred_id' => $newUser->id, 'status' => 'rewarded']);
        $this->assertDatabaseHas('point_transactions', ['user_id' => $newUser->id, 'type' => PointTransaction::TYPE_REFERRAL_SIGNUP_BONUS, 'points' => 100]);
        $this->assertDatabaseHas('point_transactions', ['user_id' => $referrer->id, 'type' => PointTransaction::TYPE_REFERRAL_REWARD, 'points' => 100]);
    }

    /* ---- Invalid/unknown code never blocks registration --------------------- */

    public function test_an_invalid_referral_code_does_not_block_registration(): void
    {
        $this->post('/register', $this->validPayload(['referral_code' => 'NOT-A-REAL-CODE']))
            ->assertRedirect(route('account.dashboard'));

        $newUser = User::where('email', 'layla@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertNull($newUser->referred_by_user_id);
        $this->assertEquals(0, $newUser->loyalty_points);
    }

    /* ---- Self-referral is structurally rejected ------------------------------ */

    public function test_self_referral_grants_no_reward(): void
    {
        $service = app(ReferralService::class);
        $user = User::factory()->create(['referral_code' => 'AROMA-SELF']);

        $service->applyReferral($user, 'AROMA-SELF');

        $this->assertEquals(0, $user->fresh()->loyalty_points);
        $this->assertDatabaseCount('referrals', 0);
    }

    /* ---- A user can only ever be referred once ------------------------------- */

    public function test_a_user_cannot_be_referred_twice(): void
    {
        $service = app(ReferralService::class);
        $referrerA = User::factory()->create(['referral_code' => 'AROMA-AAAA']);
        $referrerB = User::factory()->create(['referral_code' => 'AROMA-BBBB']);
        $newUser = User::factory()->create(['referral_code' => null]);

        $service->applyReferral($newUser, 'AROMA-AAAA');
        $service->applyReferral($newUser, 'AROMA-BBBB'); // attempted second referral

        $this->assertDatabaseCount('referrals', 1);
        $this->assertSame($referrerA->id, $newUser->fresh()->referred_by_user_id);
        $this->assertEquals(100, $referrerA->fresh()->loyalty_points);
        $this->assertEquals(0, $referrerB->fresh()->loyalty_points); // never credited
    }

    /* ---- Duplicate/replayed applyReferral calls never double-reward --------- */

    public function test_calling_apply_referral_twice_for_the_same_pair_only_rewards_once(): void
    {
        $service = app(ReferralService::class);
        $referrer = User::factory()->create(['referral_code' => 'AROMA-ONCE']);
        $newUser = User::factory()->create(['referral_code' => null]);

        $service->applyReferral($newUser, 'AROMA-ONCE');
        $service->applyReferral($newUser, 'AROMA-ONCE'); // simulates a retried/duplicated request

        $this->assertDatabaseCount('referrals', 1);
        $this->assertDatabaseCount('point_transactions', 2); // one credit each side, not four
        $this->assertEquals(100, $referrer->fresh()->loyalty_points);
        $this->assertEquals(100, $newUser->fresh()->loyalty_points);
    }

    /* ---- Points ledger is the source of truth -------------------------------- */

    public function test_reward_point_service_credit_and_debit_maintain_an_auditable_ledger(): void
    {
        $service = app(RewardPointService::class);
        $user = User::factory()->create(['loyalty_points' => 0]);

        $tx1 = $service->credit($user, 50, PointTransaction::TYPE_PROMOTIONAL_REWARD, null, 'Welcome gift');
        $this->assertEquals(50, $tx1->balance_after);
        $this->assertEquals(50, $service->balance($user));

        $tx2 = $service->debit($user, 20, PointTransaction::TYPE_REDEMPTION, null, 'Redeemed at checkout');
        $this->assertNotNull($tx2);
        $this->assertEquals(30, $tx2->balance_after);
        $this->assertEquals(30, $service->balance($user));

        // Can't overdraw.
        $this->assertNull($service->debit($user, 1000, PointTransaction::TYPE_REDEMPTION));
        $this->assertEquals(30, $service->balance($user));

        $this->assertDatabaseCount('point_transactions', 2);
    }

    public function test_points_to_sar_conversion_uses_the_centralized_config(): void
    {
        config(['aroma.rewards.points_per_sar' => 10]);
        $service = app(RewardPointService::class);

        $this->assertEquals(10.0, $service->toSar(100));
        $this->assertEquals(100, $service->toPoints(10));
    }

    /* ---- Referral link prefills the registration form ------------------------ */

    public function test_the_referral_query_param_prefills_the_registration_form(): void
    {
        User::factory()->create(['referral_code' => 'AROMA-LINKED']);

        $this->get('/register?ref=AROMA-LINKED')->assertOk()->assertSee('AROMA-LINKED');
    }

    /* ---- Admin points adjustments are ledgered, not raw column writes -------- */

    public function test_admin_loyalty_point_edits_create_a_ledger_entry(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['loyalty_points' => 50]);

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'role' => User::ROLE_CUSTOMER,
            'loyalty_points' => 80,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertEquals(80, $customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $customer->id, 'type' => PointTransaction::TYPE_ADMIN_ADJUSTMENT, 'points' => 30, 'balance_after' => 80,
        ]);
    }
}
