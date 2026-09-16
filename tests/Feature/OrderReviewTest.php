<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

class OrderReviewTest extends TestCase
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

    private function seedCartAndAddress(int $qty = 1): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => $qty])->assertRedirect();
        $this->post(route('checkout.address.store'), ['billing_address' => $this->validBilling]);
    }

    public function test_the_order_review_page_renders_for_a_non_gift_order(): void
    {
        $this->seedCartAndAddress();
        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $this->get(route('checkout.order-review'))
            ->assertOk()
            ->assertSee(__('checkout.order_review.not_a_gift'))
            ->assertSee('Riyadh'); // billing/shipping address, since no gift replaced it
    }

    public function test_the_order_review_page_shows_gift_and_recipient_details(): void
    {
        $card = GiftCard::create([
            'name' => ['en' => 'Birthday', 'ar' => 'عيد ميلاد'],
            'slug' => 'birthday',
            'image' => 'gift-cards/birthday.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->seedCartAndAddress();
        $this->post(route('checkout.gift-options.store'), [
            'is_gift'          => '1',
            'recipient'        => $this->validRecipient,
            'greeting_card_id' => $card->id,
            'gift_message'     => 'Happy birthday!',
            'gift_from'        => 'Sara',
        ]);
        $this->get(route('checkout.order-review'))
            ->assertOk()
            ->assertSee('Jeddah') // the recipient's address, not the billing one
            ->assertSee('Happy birthday!')
            ->assertSee(__('delivery.notice'));
    }

    public function test_the_displayed_total_includes_the_gift_wrap_fee(): void
    {
        config(['aroma.gifting.wrap_fee' => 15.00]);
        $this->seedCartAndAddress();

        $this->post(route('checkout.gift-options.store'), [
            'is_gift'   => '1',
            'recipient' => $this->validRecipient,
            'gift_wrap' => '1',
        ]);

        // Cart subtotal is 200 (1 x 200); total must be subtotal + wrap fee.
        $this->get(route('checkout.order-review'))
            ->assertOk()
            ->assertViewHas('totals', function ($totals) {
                return $totals['gift_wrap_fee'] === 15.0 && $totals['total_amount'] === 215.0;
            });
    }

    public function test_it_redirects_to_address_when_no_billing_address_is_in_session(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();

        $this->get(route('checkout.order-review'))
            ->assertRedirect(route('checkout.address'));
    }

    public function test_a_pinned_location_shows_as_a_map_link_not_a_blank_address(): void
    {
        $product = Product::factory()->create(['base_price' => 200, 'stock_quantity' => 10]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1])->assertRedirect();

        $this->post(route('checkout.address.store'), ['billing_address' => [
            'recipient_name' => 'Geo Test',
            'email'          => 'geo@example.com',
            'phone'          => '+966500000000',
            // Strings, matching a real form submission.
            'latitude'       => '24.7136',
            'longitude'      => '46.6753',
        ]])->assertSessionHasNoErrors();

        $this->post(route('checkout.gift-options.store'), ['is_gift' => '0']);

        $this->get(route('checkout.order-review'))
            ->assertOk()
            ->assertSee(__('location.map.pinned_label'))
            ->assertSee('google.com/maps?q=24.7136,46.6753', false);
    }
}
