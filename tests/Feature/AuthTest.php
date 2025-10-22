<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['user' => ['id','email','name'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'testuser@example.com']);
    }

    public function test_login_returns_token()
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user','token']);
    }

    public function test_user_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    public function test_logout_revokes_token()
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/logout')
            ->assertStatus(200);

        // Subsequent call should be unauthorized
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user')
            ->assertStatus(401);
    }
}
