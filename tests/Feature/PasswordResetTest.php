<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\User;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('OldPass123!'),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_password_reset_link_email()
    {
        $response = $this->postJson('/api/auth/password/email', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'We have emailed your password reset link!']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_password_reset_link_with_invalid_email()
    {
        $response = $this->postJson('/api/auth/password/email', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should still return 200 for security reasons (not revealing if email exists)
        $response->assertStatus(200)
            ->assertJson(['message' => 'We have emailed your password reset link!']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_password_reset_requires_email()
    {
        $response = $this->postJson('/api/auth/password/email', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reset_password_with_valid_token()
    {
        // Generate a valid password reset token
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NewPass456!@#',
            'password_confirmation' => 'NewPass456!@#',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password reset successful.']);

        // Verify password was changed
        $this->user->refresh();
        $this->assertTrue(\Hash::check('NewPass456!@#', $this->user->password));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reset_password_with_invalid_token()
    {
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'token' => 'invalid-token',
            'password' => 'NewPass456!@#',
            'password_confirmation' => 'NewPass456!@#',
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Invalid password reset request.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reset_password_requires_password_confirmation()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NewPass456!@#',
            'password_confirmation' => 'DifferentPass456!@#',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reset_password_requires_strong_password()
    {
        $token = Password::createToken($this->user);

        // Test without uppercase
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'newpass456!@#',
            'password_confirmation' => 'newpass456!@#',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_password_requires_authentication()
    {
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456!@#',
            'new_password_confirmation' => 'NewPass456!@#',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_change_password()
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/password/change', [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456!@#',
            'new_password_confirmation' => 'NewPass456!@#',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password changed successfully.']);

        // Verify password was changed
        $this->user->refresh();
        $this->assertTrue(\Hash::check('NewPass456!@#', $this->user->password));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_password_with_wrong_current_password()
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/password/change', [
            'current_password' => 'WrongPass123!',
            'new_password' => 'NewPass456!@#',
            'new_password_confirmation' => 'NewPass456!@#',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Current password is incorrect.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_password_cannot_reuse_same_password()
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/password/change', [
            'current_password' => 'OldPass123!',
            'new_password' => 'OldPass123!',
            'new_password_confirmation' => 'OldPass123!',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'New password cannot be the same as the old password.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function change_password_requires_strong_password()
    {
        // Password without special character
        $response = $this->actingAs($this->user)->postJson('/api/auth/password/change', [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456',
            'new_password_confirmation' => 'NewPass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }
}
