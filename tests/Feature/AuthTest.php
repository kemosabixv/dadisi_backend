<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_creates_user()
    {
        $payload = [
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/signup', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    public function test_login_establishes_session()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'email_verified']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/auth/user');
        $response->assertStatus(401);
    }

    public function test_logout_clears_session()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // Login to establish session
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(200);

        $this->assertAuthenticatedAs($user);

        // Logout
        $this->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // Session should be cleared
        $this->assertGuest();

        // Subsequent request should fail
        $this->getJson('/api/auth/user')
          ->assertStatus(401);
    }
}

