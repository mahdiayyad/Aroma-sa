<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGemini(string $reply = 'A rose oud would suit beautifully.'): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $reply]]]],
                ],
            ], 200),
        ]);
    }

    public function test_the_widget_renders_on_the_storefront(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertSee('aromaChatToggle', false)
            ->assertSee('aromaChatPanel', false)
            ->assertSee(__('assistant.name'));
    }

    public function test_it_answers_a_shopper(): void
    {
        $this->fakeGemini('Our amber blends are a lovely place to begin.');

        $this->postJson(route('assistant.chat'), ['message' => 'Help me pick a gift'])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('reply', 'Our amber blends are a lovely place to begin.');
    }

    public function test_conversation_history_is_remembered_in_the_session(): void
    {
        $this->fakeGemini('Certainly.');

        $this->postJson(route('assistant.chat'), ['message' => 'Do you gift wrap?'])->assertOk();

        $history = session('assistant.history');

        $this->assertCount(2, $history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Do you gift wrap?', $history[0]['text']);
        $this->assertSame('assistant', $history[1]['role']);
    }

    public function test_page_context_is_sent_to_the_model(): void
    {
        $this->fakeGemini();
        $product = Product::factory()->create(['name' => ['en' => 'Rose Oud', 'ar' => 'ورد عود']]);

        $this->postJson(route('assistant.chat'), [
            'message' => 'Is this good for a gift?',
            'context' => ['title' => 'Rose Oud', 'path' => '/en/product/'.$product->slug, 'product' => 'Rose Oud'],
        ])->assertOk();

        // The product the shopper is viewing must reach the system instruction.
        Http::assertSent(function ($request) {
            return str_contains(json_encode($request->data()), 'Rose Oud');
        });
    }

    public function test_it_degrades_gracefully_without_an_api_key(): void
    {
        config(['services.gemini.api_key' => '']);

        $this->postJson(route('assistant.chat'), ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJson(['ok' => false])
            ->assertJsonPath('reply', __('assistant.offline'));

        // A failed exchange must not be written into the thread.
        $this->assertEmpty(session('assistant.history', []));
    }

    public function test_a_gateway_failure_is_handled(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $this->postJson(route('assistant.chat'), ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('reply', __('assistant.error'));
    }

    public function test_a_quota_error_asks_the_shopper_to_retry(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded'],
        ], 429)]);

        $this->postJson(route('assistant.chat'), ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('reply', __('assistant.busy'));

        $this->assertEmpty(session('assistant.history', []));
    }

    public function test_the_message_is_validated(): void
    {
        $this->postJson(route('assistant.chat'), ['message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson(route('assistant.chat'), ['message' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_the_whatsapp_button_only_appears_once_a_number_is_configured(): void
    {
        config(['aroma.contact.whatsapp' => '']);
        $this->get('/ar')->assertOk()->assertDontSee('aromaWhatsApp', false);

        config(['aroma.contact.whatsapp' => '+966 50 000 0000']);
        $this->get('/ar')->assertOk()
            ->assertSee('aromaWhatsApp', false)
            ->assertSee('wa.me/966500000000', false); // normalised to digits
    }

    public function test_the_widget_is_not_rendered_in_the_admin(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('aromaChatToggle', false);
    }

    public function test_the_chat_offers_quick_actions(): void
    {
        config(['aroma.contact.whatsapp' => '+966 50 000 0000']);

        $response = $this->get('/ar')->assertOk();

        // 1) Browse products → storefront shop (home in the active locale).
        $response->assertSee('aroma-quick-action', false)
            ->assertSee(__('assistant.menu.products'))
            ->assertSee(url('/ar'), false);

        // 2) Customer service + 3) Returns → WhatsApp, each with its own prefill.
        $response->assertSee(__('assistant.menu.service'))
            ->assertSee(__('assistant.menu.returns'))
            ->assertSee('wa.me/966500000000', false)
            ->assertSee(rawurlencode(__('assistant.menu.returns_prefill')), false);
    }

    public function test_service_actions_fall_back_to_email_without_whatsapp(): void
    {
        config(['aroma.contact.whatsapp' => '', 'aroma.contact.email' => 'care@aroma.sa']);

        $this->get('/ar')->assertOk()
            ->assertSee(__('assistant.menu.service'))
            ->assertSee('mailto:care@aroma.sa', false)
            ->assertDontSee('wa.me', false);
    }

    public function test_the_conversation_can_be_reset(): void
    {
        $this->withSession(['assistant.history' => [['role' => 'user', 'text' => 'hi']]])
            ->postJson(route('assistant.reset'))
            ->assertOk();

        $this->assertEmpty(session('assistant.history', []));
    }
}
