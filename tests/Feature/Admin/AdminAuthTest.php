<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_obtain_a_token(): void
    {
        User::factory()->create([
            'email'    => 'admin@aroma.sa',
            'password' => Hash::make('secret123'),
            'role'     => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/admin/login', ['email' => 'admin@aroma.sa', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_a_customer_cannot_obtain_an_admin_token(): void
    {
        User::factory()->create([
            'email'    => 'shopper@aroma.sa',
            'password' => Hash::make('secret123'),
            // role defaults to customer
        ]);

        $this->postJson('/api/admin/login', ['email' => 'shopper@aroma.sa', 'password' => 'secret123'])
            ->assertForbidden();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email'    => 'admin@aroma.sa',
            'password' => Hash::make('secret123'),
            'role'     => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/admin/login', ['email' => 'admin@aroma.sa', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized(); // 401
    }

    public function test_a_customer_token_is_forbidden_from_admin_routes(): void
    {
        Sanctum::actingAs(User::factory()->create()); // customer

        $this->getJson('/api/admin/dashboard')->assertForbidden(); // 403
    }

    public function test_admin_reaches_protected_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/api/admin/me')->assertOk()->assertJsonPath('data.role', 'admin');
        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonStructure(['revenue', 'orders_total', 'orders_pending', 'status_counts']);
    }
}
