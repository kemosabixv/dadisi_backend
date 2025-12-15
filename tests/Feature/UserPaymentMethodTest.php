<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class UserPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_crud_payment_methods()
    {
        // create user
        $user = User::create([
            'username' => 'payer',
            'email' => 'payer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user, 'sanctum');

        // create payment method
        $resp = $this->postJson('/api/subscriptions/payment-methods', [
            'type' => 'phone_pattern',
            'identifier' => '254701234567',
            'label' => 'Primary phone',
            'is_primary' => true,
        ]);

        $resp->assertStatus(201)->assertJsonStructure(['success', 'data' => ['id', 'identifier']]);

        $id = $resp->json('data.id');

        // list methods
        $list = $this->getJson('/api/subscriptions/payment-methods');
        $list->assertStatus(200)->assertJsonFragment(['identifier' => '254701234567']);

        // update label
        $update = $this->putJson('/api/subscriptions/payment-methods/' . $id, [
            'label' => 'Updated label',
        ]);
        $update->assertStatus(200)->assertJsonFragment(['label' => 'Updated label']);

        // set primary via endpoint
        $setPrimary = $this->postJson('/api/subscriptions/payment-methods/' . $id . '/primary');
        $setPrimary->assertStatus(200)->assertJsonFragment(['is_primary' => true]);

        // delete
        $del = $this->deleteJson('/api/subscriptions/payment-methods/' . $id);
        $del->assertStatus(200)->assertJson(['success' => true]);
    }
}
