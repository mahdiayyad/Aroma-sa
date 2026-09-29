<?php

namespace Tests\Feature\Account;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The mobile number is the sign-in credential, so it can only change by
 * proving the NEW number with a Tawked code (HTTP is faked throughout).
 */
class PhoneChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = '+966500000001';
    private const NEW = '+966500000002';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tawked.api_key' => 'tk_test_fake', 'services.tawked.base_url' => 'https://tawked.com']);
    }

    private function user(): User
    {
        return User::factory()->create(['phone' => self::OLD]);
    }

    private function tawkedStarts(): void
    {
        Http::fake(['tawked.com/v1/verify/start' => function () {
            return Http::response(['id' => (string) Str::uuid(), 'status' => 'pending', 'expires_at' => now()->addMinutes(5)->toIso8601String()], 201);
        }]);
    }

    private function pending(string $phone = self::NEW): PhoneVerification
    {
        return PhoneVerification::create([
            'phone' => $phone,
            'purpose' => PhoneVerification::PURPOSE_CHANGE_PHONE,
            'tawked_id' => 'ver-'.Str::random(6),
            'status' => PhoneVerification::STATUS_PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_the_profile_page_shows_the_number_read_only_with_a_change_action(): void
    {
        $this->actingAs($this->user())->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee(self::OLD)
            ->assertSee('data-otp-panel', false)
            ->assertSee(route('account.phone.send'), false)
            ->assertDontSee('name="phone"', false);
    }

    public function test_saving_the_profile_never_changes_the_phone(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Renamed', 'phone' => self::NEW,
        ])->assertRedirect();

        $this->assertSame(self::OLD, $user->fresh()->phone);
        $this->assertSame('Renamed', $user->fresh()->name);
    }

    public function test_a_code_is_sent_to_the_new_number_with_the_change_phone_purpose(): void
    {
        $this->tawkedStarts();

        $this->actingAs($this->user())->postJson(route('account.phone.send'), ['phone' => self::NEW])->assertOk();

        Http::assertSent(function (Request $request) {
            return $request['to'] === self::NEW && $request['reference'] === PhoneVerification::PURPOSE_CHANGE_PHONE;
        });
        $this->assertDatabaseHas('phone_verifications', ['phone' => self::NEW, 'purpose' => 'change_phone']);
    }

    public function test_sending_to_the_current_number_is_refused(): void
    {
        Http::fake();

        $this->actingAs($this->user())->postJson(route('account.phone.send'), ['phone' => self::OLD])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        Http::assertNothingSent();
    }

    public function test_a_correct_code_changes_the_number_and_marks_it_verified(): void
    {
        $user = $this->user();
        $this->pending();
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['verified' => true, 'status' => 'verified'], 200)]);

        $this->actingAs($user)->postJson(route('account.phone.verify'), ['phone' => self::NEW, 'code' => '123456'])
            ->assertOk()
            ->assertJson(['redirect' => route('account.profile.edit')]);

        $this->assertSame(self::NEW, $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_a_wrong_code_leaves_the_number_alone(): void
    {
        $user = $this->user();
        $this->pending();
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['verified' => false, 'status' => 'invalid_code'], 200)]);

        $this->actingAs($user)->postJson(route('account.phone.verify'), ['phone' => self::NEW, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(self::OLD, $user->fresh()->phone);
    }

    public function test_a_number_owned_by_another_account_cannot_be_claimed(): void
    {
        $user = $this->user();
        User::factory()->create(['phone' => self::NEW]);
        $this->pending();
        Http::fake(['tawked.com/v1/verify/check' => Http::response(['verified' => true, 'status' => 'verified'], 200)]);

        $this->actingAs($user)->postJson(route('account.phone.verify'), ['phone' => self::NEW, 'code' => '123456'])
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.change_phone.taken')]);

        $this->assertSame(self::OLD, $user->fresh()->phone);
    }

    public function test_a_login_code_cannot_be_used_to_change_the_number(): void
    {
        $user = $this->user();
        PhoneVerification::create([
            'phone' => self::NEW, 'purpose' => PhoneVerification::PURPOSE_LOGIN, 'tawked_id' => 'ver-login',
            'status' => PhoneVerification::STATUS_PENDING, 'expires_at' => now()->addMinutes(5),
        ]);
        Http::fake();

        $this->actingAs($user)->postJson(route('account.phone.verify'), ['phone' => self::NEW, 'code' => '123456'])
            ->assertStatus(422)
            ->assertJson(['message' => __('otp.errors.not_found')]);

        Http::assertNothingSent();
        $this->assertSame(self::OLD, $user->fresh()->phone);
    }

    public function test_guests_cannot_reach_the_phone_change_endpoints(): void
    {
        $this->postJson(route('account.phone.send'), ['phone' => self::NEW])->assertUnauthorized();
        $this->postJson(route('account.phone.verify'), ['phone' => self::NEW, 'code' => '123456'])->assertUnauthorized();
    }
}
