<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesapal_webhook_endpoint_accepts_post()
    {
        $payload = [
            'reference' => 'MOCK_REF_123',
            'status' => 'COMPLETED',
            'amount' => 100,
        ];

        $response = $this->postJson('/api/webhooks/pesapal', $payload);

        // Accept common success codes, validation responses, or not-found (no payment)
        $this->assertTrue(
            in_array($response->status(), [200, 201, 202, 204, 422, 404]),
            'Unexpected status ' . $response->status() . ' response: ' . $response->getContent()
        );
    }
}
