<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

class GiftOptionsTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLocationLookup();
    }

    private array $validBilling = [
        'recipient_name' => 'Sara Al Qahtani',
        'email'          => 'sara@example.com',
        'phone'          => '+966500000000',
        'location_code'  => 'RAHA1234', // resolves to Riyadh, see MocksLocationLookup
    ];

    private array $validRecipient = [
        'recipient_name' => 'Layla Al Otaibi',
        'phone'          => '+966511111111',
        'location_code'  => 'JEDD5678', // resolves to Jeddah, see MocksLocationLookup
    ];

    private function seedCartAndAddress(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
    }

    public function test_the_gift_options_page_renders(): void
    {
        $this->seedCartAndAddress();

        $this->get(route('checkout.gift-options'))
            ->assertOk()
            ->assertSee(__('gift.toggle_title'));
    }

    public function test_it_redirects_to_address_when_no_billing_address_is_in_session(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();

        $this->get(route('checkout.gift-options'))
            ->assertRedirect(route('checkout.address'));
    }

    public function test_declining_the_gift_option_clears_gift_session_and_continues(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0'])
            ->assertRedirect(route('checkout.delivery'));

        $this->assertFalse(session('checkout.gift.is_gift'));
        // The recipient step must not have touched the billing-derived shipping address.
        $this->assertSame('Riyadh', session('checkout.shipping_address')['city']);
    }

    public function test_choosing_a_gift_replaces_the_shipping_address_with_the_recipient(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'   => '1',
            'recipient' => $this->validRecipient,
        ])->assertRedirect(route('checkout.delivery'))->assertSessionHasNoErrors();

        $this->assertSame('Jeddah', session('checkout.shipping_address')['city']);
        $this->assertSame('Layla Al Otaibi', session('checkout.shipping_address')['recipient_name']);
        $this->assertTrue(session('checkout.gift.is_gift'));
    }

    public function test_a_manual_recipient_address_replaces_the_shipping_address(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'   => '1',
            'recipient' => [
                'recipient_name'  => 'Layla Al Otaibi',
                'phone'           => '+966511111111',
                'method'          => \App\Models\Address::METHOD_MANUAL,
                'country'         => 'SA',
                'city'            => 'Jeddah',
                'district'        => 'Al Rawdah',
                'street_address'  => 'King Fahd Road',
                'building_number' => '1234',
            ],
        ])->assertRedirect(route('checkout.delivery'))->assertSessionHasNoErrors();

        $shipping = session('checkout.shipping_address');
        $this->assertSame(\App\Models\Address::METHOD_MANUAL, $shipping['method']);
        $this->assertSame('Jeddah', $shipping['city']);
        $this->assertSame('1234', $shipping['building_number']);
        $this->assertSame('Layla Al Otaibi', $shipping['recipient_name']);
    }

    public function test_a_manual_recipient_address_requires_the_building_number(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'   => '1',
            'recipient' => [
                'recipient_name' => 'Layla Al Otaibi',
                'phone'          => '+966511111111',
                'method'         => \App\Models\Address::METHOD_MANUAL,
                'country'        => 'SA',
                'city'           => 'Jeddah',
                'district'       => 'Al Rawdah',
                'street_address' => 'King Fahd Road',
            ],
        ])->assertSessionHasErrors('recipient.building_number');
    }

    public function test_gift_wrap_fee_is_pulled_from_config_not_the_client(): void
    {
        config(['aroma.gifting.wrap_fee' => 22.5]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'   => '1',
            'recipient' => $this->validRecipient,
            'gift_wrap' => '1',
        ])->assertRedirect(route('checkout.delivery'));

        $this->assertSame(22.5, session('checkout.gift.wrap_fee'));
    }

    public function test_an_anonymous_sender_has_no_from_name_or_signature(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'      => '1',
            'recipient'    => $this->validRecipient,
            'is_anonymous' => '1',
            'gift_from'    => 'Should be dropped',
        ])->assertRedirect(route('checkout.delivery'));

        $this->assertTrue(session('checkout.gift.is_anonymous'));
        $this->assertNull(session('checkout.gift.from'));
        $this->assertNull(session('checkout.gift.signature'));
    }

    public function test_a_drawn_signature_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        $this->seedCartAndAddress();

        // A minimal valid base64 PNG (a real signature pad would send a larger one).
        $png = base64_encode(UploadedFile::fake()->image('sig.png', 10, 10)->get());

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'              => '1',
            'recipient'            => $this->validRecipient,
            'gift_signature_data'  => 'data:image/png;base64,'.$png,
        ])->assertRedirect(route('checkout.delivery'))->assertSessionHasNoErrors();

        $path = session('checkout.gift.signature');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_resubmit_without_redrawing_keeps_the_previously_saved_signature(): void
    {
        Storage::fake('public');
        $this->seedCartAndAddress();

        $png = base64_encode(UploadedFile::fake()->image('sig.png', 10, 10)->get());

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'             => '1',
            'recipient'           => $this->validRecipient,
            'gift_signature_data' => 'data:image/png;base64,'.$png,
        ]);
        $firstPath = session('checkout.gift.signature');

        // Resubmitting (e.g. after tweaking the message) without touching the
        // signature pad must not wipe out the signature already on file.
        $this->post(route('checkout.gift-options.store'), [
            'is_gift'      => '1',
            'recipient'    => $this->validRecipient,
            'gift_message' => 'Updated message',
        ])->assertRedirect(route('checkout.delivery'));

        $this->assertSame($firstPath, session('checkout.gift.signature'));
    }

    public function test_a_selected_greeting_card_must_exist(): void
    {
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'          => '1',
            'recipient'        => $this->validRecipient,
            'greeting_card_id' => 999999,
        ])->assertSessionHasErrors('greeting_card_id');
    }

    public function test_a_valid_greeting_card_is_stored(): void
    {
        $card = GiftCard::create([
            'name'       => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
            'slug'       => 'birthday',
            'image'      => 'gift-cards/birthday.jpg',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'          => '1',
            'recipient'        => $this->validRecipient,
            'greeting_card_id' => $card->id,
        ])->assertRedirect(route('checkout.delivery'));

        $this->assertSame($card->id, session('checkout.gift.card_id'));
    }

    public function test_a_message_over_the_line_limit_is_rejected(): void
    {
        config(['aroma.gifting.message_max_lines' => 2]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'      => '1',
            'recipient'    => $this->validRecipient,
            'gift_message' => "line one\nline two\nline three",
        ])->assertSessionHasErrors('gift_message');
    }
}
