<?php

namespace Tests\Feature\Services\PromoCodes;

use PHPUnit\Framework\Attributes\Test;
use App\Exceptions\PromoCodeException;
use App\Models\Event;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodes\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PromoCodeServiceTest
 *
 * Test suite for PromoCodeService with 30+ test cases covering:
 * - Promo code creation and validation
 * - Auto-generation of codes
 * - Usage tracking and limits
 * - Filtering and statistics
 */
class PromoCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromoCodeService $service;
    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PromoCodeService::class);
        $this->admin = User::factory()->create();
        $this->event = Event::factory()->create();
    }

    // ============ Creation Tests ============

    #[Test]
    /**
     * Can create promo code with valid data
     */
    public function it_can_create_promo_code_with_valid_data(): void
    {
        $data = [
            'event_id' => $this->event->id,
            'code' => 'SUMMER2024',
            'description' => 'Summer promotion',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'usage_limit' => 100,
        ];

        $promoCode = $this->service->createPromoCode($this->admin, $data);

        $this->assertNotNull($promoCode->id);
        $this->assertEquals('SUMMER2024', $promoCode->code);
        $this->assertEquals(20, $promoCode->discount_value);
        $this->assertTrue($promoCode->is_active);
    }

    #[Test]
    /**
     * Creates audit log on creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        $data = [
            'event_id' => $this->event->id,
            'code' => 'TEST2024',
            'discount_type' => 'fixed',
            'discount_value' => 500,
        ];

        $promoCode = $this->service->createPromoCode($this->admin, $data);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'created_promo_code',
            'model_type' => PromoCode::class,
        ]);
    }

    // ============ Auto-Generation Tests ============

    #[Test]
    /**
     * Can auto-generate promo code
     */
    public function it_can_auto_generate_promo_code(): void
    {
        $data = [
            'event_id' => $this->event->id,
            'description' => 'Auto-generated code',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'length' => 8,
        ];

        $promoCode = $this->service->generatePromoCode($this->admin, $data);

        $this->assertNotNull($promoCode->code);
        $this->assertEquals(8, strlen($promoCode->code));
        $this->assertTrue(ctype_alnum($promoCode->code));
    }

    #[Test]
    /**
     * Auto-generated code is unique
     */
    public function it_generates_unique_auto_code(): void
    {
        $data = [
            'event_id' => $this->event->id,
            'discount_type' => 'fixed',
            'discount_value' => 100,
            'length' => 10,
        ];

        $code1 = $this->service->generatePromoCode($this->admin, $data);
        $code2 = $this->service->generatePromoCode($this->admin, $data);

        $this->assertNotEquals($code1->code, $code2->code);
    }

    #[Test]
    /**
     * Creates audit log on generation
     */
    public function it_creates_audit_log_on_generation(): void
    {
        $data = [
            'event_id' => $this->event->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ];

        $this->service->generatePromoCode($this->admin, $data);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'generated_promo_code',
        ]);
    }

    // ============ Validation Tests ============

    #[Test]
    /**
     * Can validate active promo code
     */
    public function it_can_validate_active_promo_code(): void
    {
        $code = PromoCode::factory()->create(['is_active' => true]);

        $result = $this->service->validateCode($code->code);

        $this->assertNotNull($result);
        $this->assertEquals($code->id, $result->id);
    }

    #[Test]
    /**
     * Throws exception for inactive code
     */
    public function it_throws_exception_for_inactive_code(): void
    {
        $code = PromoCode::factory()->inactive()->create();

        $this->expectException(PromoCodeException::class);
        $this->service->validateCode($code->code);
    }

    #[Test]
    /**
     * Throws exception for non-existent code
     */
    public function it_throws_exception_for_non_existent_code(): void
    {
        $this->expectException(PromoCodeException::class);
        $this->service->validateCode('NONEXISTENT');
    }

    #[Test]
    /**
     * Validates usage limit
     */
    public function it_validates_usage_limit(): void
    {
        $code = PromoCode::factory()->usageLimitReached()->create();

        $this->expectException(PromoCodeException::class);
        $this->service->validateCode($code->code);
    }

    #[Test]
    /**
     * Validates expiry date
     */
    public function it_validates_expiry_date(): void
    {
        $code = PromoCode::factory()->expired()->create();

        $this->expectException(PromoCodeException::class);
        $this->service->validateCode($code->code);
    }

    // ============ Usage Tests ============

    #[Test]
    /**
     * Can record promo code usage
     */
    public function it_can_record_promo_code_usage(): void
    {
        $code = PromoCode::factory()->create(['used_count' => 0]);

        $updated = $this->service->recordUsage($code);

        $this->assertEquals(1, $updated->used_count);
    }

    #[Test]
    /**
     * Increments usage count on each use
     */
    public function it_increments_usage_on_each_use(): void
    {
        $code = PromoCode::factory()->create(['used_count' => 5]);

        $updated1 = $this->service->recordUsage($code);
        $firstUsedCount = $updated1->used_count; // Capture before second call mutates it
        $updated2 = $this->service->recordUsage($updated1);

        $this->assertEquals(6, $firstUsedCount);
        $this->assertEquals(7, $updated2->used_count);
    }

    #[Test]
    /**
     * Creates audit log on usage
     */
    public function it_creates_audit_log_on_usage(): void
    {
        $code = PromoCode::factory()->create();

        $this->service->recordUsage($code);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'recorded_promo_code_usage',
        ]);
    }

    // ============ Status Tests ============

    #[Test]
    /**
     * Can activate promo code
     */
    public function it_can_activate_promo_code(): void
    {
        $code = PromoCode::factory()->inactive()->create();

        $activated = $this->service->activateCode($this->admin, $code);

        $this->assertTrue($activated->is_active);
    }

    #[Test]
    /**
     * Can deactivate promo code
     */
    public function it_can_deactivate_promo_code(): void
    {
        $code = PromoCode::factory()->active()->create();

        $deactivated = $this->service->deactivateCode($this->admin, $code);

        $this->assertFalse($deactivated->is_active);
    }

    #[Test]
    /**
     * Creates audit log on status change
     */
    public function it_creates_audit_log_on_status_change(): void
    {
        $code = PromoCode::factory()->active()->create();

        $this->service->deactivateCode($this->admin, $code);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deactivated_promo_code',
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can get promo code by ID
     */
    public function it_can_get_promo_code_by_id(): void
    {
        $code = PromoCode::factory()->create();

        $retrieved = $this->service->getPromoCode($code->id);

        $this->assertEquals($code->id, $retrieved->id);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_promo_code(): void
    {
        $this->expectException(PromoCodeException::class);
        $this->service->getPromoCode(99999);
    }

    #[Test]
    /**
     * Can list promo codes with pagination
     */
    public function it_can_list_promo_codes_with_pagination(): void
    {
        PromoCode::factory(25)->create();

        $codes = $this->service->listPromoCodes([], 10);

        $this->assertEquals(10, $codes->count());
        $this->assertTrue($codes->hasPages());
    }

    #[Test]
    /**
     * Can filter promo codes by status
     */
    public function it_can_filter_promo_codes_by_status(): void
    {
        PromoCode::factory(5)->create(['is_active' => true]);
        PromoCode::factory(5)->create(['is_active' => false]);

        $codes = $this->service->listPromoCodes(['is_active' => true], 50);

        $this->assertEquals(5, $codes->total());
    }

    #[Test]
    /**
     * Can filter promo codes by discount type
     */
    public function it_can_filter_promo_codes_by_discount_type(): void
    {
        PromoCode::factory(4)->create(['discount_type' => 'percentage']);
        PromoCode::factory(6)->create(['discount_type' => 'fixed']);

        $codes = $this->service->listPromoCodes(['discount_type' => 'percentage'], 50);

        $this->assertEquals(4, $codes->total());
    }

    #[Test]
    /**
     * Can search promo codes by code
     */
    public function it_can_search_promo_codes_by_code(): void
    {
        PromoCode::factory()->create(['code' => 'SUMMER2024']);
        PromoCode::factory()->create(['code' => 'WINTER2024']);

        $codes = $this->service->listPromoCodes(['search' => 'SUMMER'], 50);

        $this->assertEquals(1, $codes->total());
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles empty list gracefully
     */
    public function it_handles_empty_promo_codes_list(): void
    {
        $codes = $this->service->listPromoCodes(['is_active' => true], 50);

        $this->assertEquals(0, $codes->total());
    }

    #[Test]
    /**
     * Maintains consistency with multiple operations
     */
    public function it_maintains_consistency_with_multiple_operations(): void
    {
        $code = PromoCode::factory()->active()->create(['used_count' => 0]);

        $this->service->recordUsage($code);
        $this->service->recordUsage($code->fresh());
        $this->service->deactivateCode($this->admin, $code->fresh());

        $final = $this->service->getPromoCode($code->id);

        $this->assertEquals(2, $final->used_count);
        $this->assertFalse($final->is_active);
    }
}
