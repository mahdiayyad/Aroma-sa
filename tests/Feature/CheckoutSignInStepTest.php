<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksLocationLookup;
use Tests\TestCase;

/** The checkout "sign in / register / guest" step after the move to phone-only auth. */
class CheckoutSignInStepTest extends TestCase
{
    use RefreshDatabase;
    use MocksLocationLookup;

    private function withCart(): void
    {
        $product = Product::factory()->create(['base_price' => 100, 'stock_quantity' => 5]);
        $this->post('/cart', ['product_id' => $product->id, 'qty' => 1]);
    }

    public function test_the_step_offers_one_phone_number_card_and_guest_checkout(): void
    {
        $this->withCart();

        $this->get(route('checkout.start'))
            ->assertOk()
            ->assertSee(__('checkout.auth.phone_title'))
            ->assertSee(route('checkout.login'), false)
            ->assertSee(route('checkout.address'), false)          // continue as guest
            ->assertDontSee(route('checkout.register'), false);
    }

    public function test_signing_in_from_checkout_remembers_the_address_step(): void
    {
        $this->get(route('checkout.login'))->assertRedirect(route('login'));

        $this->assertSame(route('checkout.address'), session('url.intended'));
    }

    public function test_the_old_register_link_behaves_like_sign_in(): void
    {
        $this->get(route('checkout.register'))->assertRedirect(route('login'));

        $this->assertSame(route('checkout.address'), session('url.intended'));
    }

    public function test_a_signed_in_shopper_skips_the_step(): void
    {
        $this->withCart();

        $this->actingAs(User::factory()->create())->get(route('checkout.start'))->assertRedirect(route('checkout.address'));
    }
}
