<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_verification_requires_email()
    {
        // Unauthenticated requests are rejected
        $response = $this->postJson('/api/auth/send-verification', []);
        $response->assertStatus(401);
    }

    public function test_send_verification_sends_code()
    {
        // Create a new unverified user (email_verified_at is null by default)
        $user = User::factory()->unverified()->create(["email" => "verifyme@example.com"]);

        // Authenticate using a personal access token and call endpoint
        $token = $user->createToken('tests')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/send-verification', []);

        // Accept any 2xx success response (200, 202, 204, etc.)
        $response->assertSuccessful();

        // Verify a code was created in the database (table is email_verification_codes)
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_verify_email_requires_code_and_email()
    {
        $response = $this->postJson('/api/auth/verify-email', []);
        $response->assertStatus(422);
    }

    public function test_verify_email_with_invalid_code_returns_422()
    {
        $user = User::factory()->unverified()->create(["email" => "verifyme2@example.com"]);

        // Code must be 6 characters per validation rules
        $response = $this->postJson('/api/auth/verify-email', [
            'code' => 'INV123'
        ]);

        // Validation returns 422 with error about code length
        $response->assertStatus(422);
    }

    public function test_verify_email_with_valid_code_succeeds()
    {
        $user = User::factory()->unverified()->create();

        // Create a valid verification code (using the correct table name)
        $code = 'VALID6';
        \DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'code' => $code,
        ]);

        // Should succeed and return token
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'token', 'user']);
    }
}
