<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_not_available(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertNotFound();
    }

    public function test_profile_information_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Original User',
            'email' => 'original@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response->assertNotFound();

        $user->refresh();

        $this->assertSame('Original User', $user->name);
        $this->assertSame('original@example.com', $user->email);
    }

    public function test_user_account_cannot_be_deleted_from_profile(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertNotFound();

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_link_is_not_displayed_in_navigation(): void
    {
        $user = User::factory()->create([
            'name' => 'Fernando Silva',
            'email' => 'fernando@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Fernando Silva')
            ->assertDontSee('Perfil')
            ->assertDontSee('fernando@example.com');
    }

    public function test_authenticated_password_cannot_be_updated_from_profile(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertNotFound();

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
