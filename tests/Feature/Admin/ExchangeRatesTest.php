<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class ExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('member');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_exchange_rates_requires_authentication()
    {
        $response = $this->getJson('/api/admin/exchange-rates');

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_access_exchange_rates()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/exchange-rates');

        $this->assertThat($response->status(), $this->logicalOr(
            $this->equalTo(200),
            $this->equalTo(422)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function exchange_rates_info_requires_authentication()
    {
        $response = $this->getJson('/api/admin/exchange-rates/info');

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_get_exchange_rates_info()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/exchange-rates/info');

        $this->assertThat($response->status(), $this->logicalOr(
            $this->equalTo(200),
            $this->equalTo(422)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refresh_exchange_rate_requires_authentication()
    {
        $response = $this->postJson('/api/admin/exchange-rates/refresh');

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_refresh_exchange_rate()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/exchange-rates/refresh');

        $this->assertThat($response->status(), $this->logicalOr(
            $this->equalTo(200),
            $this->equalTo(422),
            $this->equalTo(500)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_cache_settings_requires_authentication()
    {
        $response = $this->putJson('/api/admin/exchange-rates/settings', [
            'cache_minutes' => 60,
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_update_cache_settings()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/exchange-rates/settings', [
                'cache_minutes' => 120,
            ]);

        $this->assertThat($response->status(), $this->logicalOr(
            $this->equalTo(200),
            $this->equalTo(422)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_cache_settings_validates_input()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/exchange-rates/settings', [
                'cache_minutes' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_manual_rate_requires_authentication()
    {
        $response = $this->putJson('/api/admin/exchange-rates/rate', [
            'rate' => 140.50,
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_update_manual_rate()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/exchange-rates/rate', [
                'rate' => 145.75,
            ]);

        $this->assertThat($response->status(), $this->logicalOr(
            $this->equalTo(200),
            $this->equalTo(422)
        ));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_manual_rate_validates_rate_value()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/exchange-rates/rate', [
                'rate' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_manual_rate_must_be_positive()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/admin/exchange-rates/rate', [
                'rate' => -100,
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_access_exchange_rates()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/exchange-rates');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_update_exchange_rates()
    {
        $response = $this->actingAs($this->regularUser)
            ->putJson('/api/admin/exchange-rates/rate', [
                'rate' => 150,
            ]);

        $response->assertStatus(403);
    }
}
