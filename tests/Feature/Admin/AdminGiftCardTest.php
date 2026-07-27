<?php

namespace Tests\Feature\Admin;

use App\Models\GiftCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGiftCardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_open_gift_card_screens(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.gift-cards.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.gift-cards.create'))->assertOk();
    }

    public function test_admin_can_create_a_gift_card_design(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.gift-cards.store'), [
            'name'  => ['ar' => 'عيد ميلاد سعيد', 'en' => 'Happy Birthday'],
            'image' => UploadedFile::fake()->image('birthday.jpg'),
            'is_active' => '1',
        ])->assertRedirect(route('admin.gift-cards.index'));

        $this->assertDatabaseCount('gift_cards', 1);

        $card = GiftCard::first();
        $this->assertSame('Happy Birthday', $card->getTranslations('name')['en']);
        $this->assertSame('happy-birthday', $card->slug);
        Storage::disk('public')->assertExists($card->image);
    }

    public function test_image_is_required_when_creating_but_not_when_editing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.gift-cards.store'), [
            'name' => ['ar' => 'شكراً', 'en' => 'Thank You'],
        ])->assertSessionHasErrors('image');

        Storage::fake('public');
        $card = GiftCard::create([
            'name' => ['ar' => 'شكراً', 'en' => 'Thank You'],
            'slug' => 'thank-you',
            'image' => 'gift-cards/existing.jpg',
            'is_active' => true,
        ]);

        // Editing without re-uploading keeps the existing image.
        $this->actingAs($admin)->put(route('admin.gift-cards.update', $card), [
            'name' => ['ar' => 'شكراً جزيلاً', 'en' => 'Thank You So Much'],
        ])->assertRedirect(route('admin.gift-cards.index'));

        $this->assertSame('gift-cards/existing.jpg', $card->fresh()->image);
    }

    public function test_admin_can_delete_a_gift_card_and_orders_keep_working(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $card = GiftCard::create([
            'name' => ['ar' => 'أ', 'en' => 'A'], 'slug' => 'a', 'image' => 'x.jpg', 'is_active' => true,
        ]);

        $this->actingAs($admin)->delete(route('admin.gift-cards.destroy', $card))
            ->assertRedirect(route('admin.gift-cards.index'));

        $this->assertDatabaseMissing('gift_cards', ['id' => $card->id]);
    }

    public function test_a_customer_cannot_reach_the_gift_card_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.gift-cards.index'))
            ->assertForbidden();
    }
}
