<?php

namespace Tests\Feature\Account;

use App\Mail\EmailCodeMail;
use App\Models\EmailCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Profile → "Email & password sign-in": add (confirmed by an emailed code) and change. */
class EmailPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_EMAIL = 'layla@example.com';
    private const NEW_PASSWORD = 'Passw0rd1';

    private function phoneUser(): User
    {
        return User::factory()->create(['email' => null, 'password' => null, 'phone' => '+966500000001']);
    }

    private function addPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => self::NEW_EMAIL,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);
    }

    /** Runs the "send code" step as $user and returns the emailed code. */
    private function requestCode(User $user, array $overrides = []): string
    {
        Mail::fake();
        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload($overrides))->assertOk();

        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function verify(User $user, string $code)
    {
        return $this->actingAs($user)->postJson(route('account.security.email.verify'), ['code' => $code]);
    }

    /* ---- The page -------------------------------------------------------------- */

    public function test_a_phone_only_account_is_offered_the_add_form(): void
    {
        $this->actingAs($this->phoneUser())->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee(__('email_auth.profile.title'))
            ->assertSee('id="emailAddPanel"', false)
            ->assertSee(route('account.security.email.send'), false)
            ->assertDontSee(route('account.security.password'), false);
    }

    public function test_an_account_with_a_password_is_offered_change_password_and_a_locked_email(): void
    {
        $user = User::factory()->create(['email' => 'has@example.com', 'password' => Hash::make(self::NEW_PASSWORD)]);

        $this->actingAs($user)->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee(route('account.security.password'), false)
            ->assertSee(__('email_auth.profile.email_locked_hint'))
            ->assertDontSee('id="emailAddPanel"', false)
            ->assertDontSee('name="email"', false);
    }

    /* ---- Adding email + password ------------------------------------------------ */

    public function test_a_code_is_emailed_and_nothing_is_saved_until_it_is_confirmed(): void
    {
        $user = $this->phoneUser();

        $this->requestCode($user);

        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) {
            return $mail->hasTo(self::NEW_EMAIL) && $mail->purpose === EmailCode::PURPOSE_ADD_CREDENTIALS;
        });
        $fresh = $user->fresh();
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->password);
    }

    public function test_the_right_code_saves_the_verified_email_and_hashed_password(): void
    {
        $user = $this->phoneUser();
        $code = $this->requestCode($user);

        $this->verify($user, $code)->assertOk()->assertJson(['redirect' => route('account.profile.edit')]);

        $fresh = $user->fresh();
        $this->assertSame(self::NEW_EMAIL, $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotSame(self::NEW_PASSWORD, $fresh->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertSame('+966500000001', $fresh->phone, 'phone sign-in is untouched');
    }

    public function test_the_new_credentials_then_work_for_email_sign_in(): void
    {
        $user = $this->phoneUser();
        $this->verify($user, $this->requestCode($user))->assertOk();
        auth()->logout();
        $this->flushSession();

        Mail::fake();
        $this->postJson(route('login.email'), ['email' => self::NEW_EMAIL, 'password' => self::NEW_PASSWORD])->assertOk();
        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->postJson(route('login.email.verify'), ['code' => $code])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_saves_nothing(): void
    {
        $user = $this->phoneUser();
        $code = $this->requestCode($user);

        $this->verify($user, $code === '000000' ? '111111' : '000000')->assertStatus(422);

        $this->assertNull($user->fresh()->email);
        $this->assertNull($user->fresh()->password);
    }

    public function test_verifying_without_asking_for_a_code_first_is_refused(): void
    {
        $this->verify($this->phoneUser(), '123456')->assertStatus(422)->assertJson(['message' => __('email_auth.profile.session_expired')]);
    }

    public function test_sending_validates_email_and_password(): void
    {
        Mail::fake();
        $user = $this->phoneUser();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload(['email' => 'taken@example.com']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload(['email' => 'nope']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload(['password' => 'short1', 'password_confirmation' => 'short1']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload(['password' => 'nodigitsHere', 'password_confirmation' => 'nodigitsHere']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload(['password_confirmation' => 'Different1']))
            ->assertStatus(422)->assertJsonValidationErrors('password');

        Mail::assertNothingSent();
    }

    public function test_an_address_claimed_by_someone_else_before_confirming_is_refused(): void
    {
        $user = $this->phoneUser();
        $code = $this->requestCode($user);

        User::factory()->create(['email' => self::NEW_EMAIL]);

        $this->verify($user, $code)->assertStatus(422);
        $this->assertNull($user->fresh()->email);
    }

    public function test_an_account_that_already_has_a_password_cannot_add_another(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::NEW_PASSWORD)]);
        Mail::fake();

        $this->actingAs($user)->postJson(route('account.security.email.send'), $this->addPayload())
            ->assertStatus(422)->assertJson(['message' => __('email_auth.profile.already_set')]);

        Mail::assertNothingSent();
    }

    public function test_another_accounts_pending_request_cannot_be_completed(): void
    {
        $owner = $this->phoneUser();
        $code = $this->requestCode($owner);
        $stranger = User::factory()->create(['email' => null, 'password' => null]);

        // Same session, different signed-in user: the pending request belongs to $owner.
        $this->verify($stranger, $code)->assertStatus(422);
        $this->assertNull($stranger->fresh()->password);
    }

    public function test_the_add_endpoints_need_a_signed_in_user(): void
    {
        $this->postJson(route('account.security.email.send'), $this->addPayload())->assertUnauthorized();
        $this->postJson(route('account.security.email.verify'), ['code' => '123456'])->assertUnauthorized();
    }

    /* ---- Changing the password ---------------------------------------------------- */

    private function withPassword(): User
    {
        return User::factory()->create(['email' => 'has@example.com', 'password' => Hash::make('OldPassw0rd')]);
    }

    public function test_the_password_can_be_changed_with_the_current_one(): void
    {
        $user = $this->withPassword();

        $this->actingAs($user)->put(route('account.security.password'), [
            'current_password' => 'OldPassw0rd', 'password' => 'NewPassw0rd1', 'password_confirmation' => 'NewPassw0rd1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassw0rd1', $user->fresh()->password));
    }

    public function test_a_wrong_current_password_changes_nothing(): void
    {
        $user = $this->withPassword();

        $this->actingAs($user)->put(route('account.security.password'), [
            'current_password' => 'NotIt12345', 'password' => 'NewPassw0rd1', 'password_confirmation' => 'NewPassw0rd1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassw0rd', $user->fresh()->password));
    }

    public function test_a_weak_new_password_is_refused(): void
    {
        $user = $this->withPassword();

        $this->actingAs($user)->put(route('account.security.password'), [
            'current_password' => 'OldPassw0rd', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('OldPassw0rd', $user->fresh()->password));
    }

    /* ---- Email stays put once it's a credential ------------------------------------ */

    public function test_the_plain_profile_form_cannot_change_the_email_of_an_account_with_a_password(): void
    {
        $user = $this->withPassword();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Renamed', 'email' => 'attacker@example.com'])->assertRedirect();

        $this->assertSame('has@example.com', $user->fresh()->email);
        $this->assertSame('Renamed', $user->fresh()->name);
    }

    public function test_an_account_without_a_password_can_still_edit_its_contact_email(): void
    {
        $user = $this->phoneUser();

        $this->actingAs($user)->put(route('account.profile.update'), ['name' => 'Layla', 'email' => 'contact@example.com'])->assertRedirect();

        $this->assertSame('contact@example.com', $user->fresh()->email);
    }
}
