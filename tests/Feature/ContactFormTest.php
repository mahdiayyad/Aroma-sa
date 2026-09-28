<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private array $validSubmission = [
        'name' => 'Sara Al Qahtani',
        'email' => 'sara@example.com',
        'phone' => '+966500000000',
        'topic' => 'general',
        'message' => 'I have a question about my order.',
    ];

    public function test_a_valid_submission_sends_the_mail_and_succeeds(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), $this->validSubmission)
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo(config('aroma.contact.email'));
        });
    }

    public function test_a_json_request_gets_a_json_success_response(): void
    {
        Mail::fake();

        $this->postJson(route('contact.send'), $this->validSubmission)
            ->assertOk()
            ->assertJson(['message' => __('contact.form.success')]);
    }

    public function test_a_mail_transport_failure_is_reported_not_silently_lost(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('Connection could not be established'));

        $this->postJson(route('contact.send'), $this->validSubmission)
            ->assertStatus(500)
            ->assertJson(['message' => __('contact.form.errors.generic')]);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), [])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

        Mail::assertNothingSent();
    }

    public function test_the_honeypot_field_must_be_empty(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), $this->validSubmission + ['website' => 'http://spam.example'])
            ->assertSessionHasErrors('website');

        Mail::assertNothingSent();
    }
}
