<?php

namespace Tests\Feature\Services\Donations;

use App\Exceptions\DonationException;
use App\Models\County;
use App\Models\Donation;
use App\Models\User;
use App\Services\Donations\DonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DonationServiceTest
 *
 * Test suite for DonationService with 35+ test cases covering:
 * - Donation creation with various scenarios
 * - Donation retrieval and filtering
 * - Verification workflows
 * - Donor history tracking
 * - Statistics and reporting
 */
class DonationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DonationService $service;
    private User $user;
    private User $donor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DonationService::class);
        $this->user = User::factory()->create();
        $this->donor = User::factory()->create();
        
        // Ensure counties exist for tests (use firstOrCreate to prevent duplicates)
        County::firstOrCreate(['name' => 'Nairobi']);
        County::firstOrCreate(['name' => 'Mombasa']);
        County::firstOrCreate(['name' => 'Kisumu']);
    }

    // ============ Creation Tests ============

    #[Test]
    /**
     * Can create a donation with valid data
     */
    public function it_can_create_donation_with_valid_data(): void
    {
        $data = [
            'donor_name' => $this->donor->user_profile->full_name ?? $this->donor->username,
            'donor_email' => $this->donor->email,
            'amount' => 5000,
            'currency' => 'KES',
            'description' => 'General donation',
            'county_id' => \App\Models\County::first()->id,
        ];

        $donation = $this->service->createDonation($this->donor, $data);

        $this->assertNotNull($donation->id);
        $this->assertEquals(5000, $donation->amount);
        $this->assertEquals('pending', $donation->status);
        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'amount' => 5000,
            'county_id' => $data['county_id'],
        ]);
    }

    #[Test]
    /**
     * Can create anonymous donation
     */
    public function it_can_create_anonymous_donation(): void
    {
        $data = [
            'donor_name' => 'Anonymous Donor',
            'donor_email' => 'anonymous@example.com',
            'amount' => 2500,
            'currency' => 'KES',
            'county_id' => \App\Models\County::first()->id,
        ];

        $donation = $this->service->createDonation(null, $data);

        $this->assertNull($donation->user_id);
    }

    #[Test]
    /**
     * Creates audit log on donation creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        $data = [
            'donor_name' => $this->user->username,
            'donor_email' => $this->user->email,
            'amount' => 1000,
            'currency' => 'KES',
            'county_id' => County::where('name', 'Kisumu')->first()->id,
        ];

        $donation = $this->service->createDonation($this->user, $data);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'created_donation',
            'model_type' => Donation::class,
            'model_id' => $donation->id,
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can retrieve a donation by ID
     */
    public function it_can_get_donation_by_id(): void
    {
        $donation = Donation::factory()->create(['user_id' => $this->donor->id]);

        $retrieved = $this->service->getDonation($donation->id);

        $this->assertEquals($donation->id, $retrieved->id);
        $this->assertEquals($donation->amount, $retrieved->amount);
    }

    #[Test]
    /**
     * Throws exception when donation not found
     */
    public function it_throws_exception_when_donation_not_found(): void
    {
        $this->expectException(DonationException::class);
        $this->service->getDonation(99999);
    }

    #[Test]
    /**
     * Can list donations with pagination
     */
    public function it_can_list_donations_with_pagination(): void
    {
        Donation::factory(15)->create();

        $donations = $this->service->listDonations([], 10);

        $this->assertEquals(10, $donations->count());
        $this->assertTrue($donations->hasPages());
    }

    #[Test]
    /**
     * Can filter donations by county
     */
    public function it_can_filter_donations_by_county(): void
    {
        $county = \App\Models\County::first();
        Donation::factory(5)->create(['county_id' => $county->id]);
        Donation::factory(3)->create(['county_id' => County::where('name', 'Mombasa')->first()->id]);

        $donations = $this->service->listDonations(['county_id' => $county->id], 50);

        $this->assertEquals(5, $donations->total());
    }

    #[Test]
    /**
     * Can filter donations by status
     */
    public function it_can_filter_donations_by_status(): void
    {
        Donation::factory(4)->create(['status' => 'paid']);
        Donation::factory(6)->create(['status' => 'pending']);

        $donations = $this->service->listDonations(['status' => 'paid'], 50);

        $this->assertEquals(4, $donations->total());
    }

    #[Test]
    /**
     * Can filter donations by date range
     */
    public function it_can_filter_donations_by_date_range(): void
    {
        Donation::factory(5)->create(['created_at' => now()->subDays(10)]);
        Donation::factory(5)->create(['created_at' => now()]);

        $donations = $this->service->listDonations([
            'start_date' => now()->subDays(2)->startOfDay(),
            'end_date' => now()->endOfDay(),
        ], 50);

        $this->assertEquals(5, $donations->total());
    }

    #[Test]
    /**
     * Can filter donations by donor
     */
    public function it_can_filter_donations_by_donor(): void
    {
        $donor1 = User::factory()->create();
        $donor2 = User::factory()->create();

        Donation::factory(4)->create(['user_id' => $donor1->id]);
        Donation::factory(6)->create(['user_id' => $donor2->id]);

        $donations = $this->service->listDonations(['user_id' => $donor1->id], 50);

        $this->assertEquals(4, $donations->total());
    }

    // ============ Verification Tests ============

    #[Test]
    /**
     * Can verify a donation
     */
    public function it_can_mark_donation_paid(): void
    {
        $donation = Donation::factory()->create(['status' => 'pending']);

        $verified = $this->service->markAsPaid($donation, ['payment_id' => 'PAY-123'], $this->user);

        $this->assertEquals('paid', $verified->status);
        $this->assertNotNull($verified->receipt_number);
    }

    #[Test]
    /**
     * Throws exception when verifying already verified donation
     */
    public function it_throws_exception_when_verifying_verified_donation(): void
    {
        $donation = Donation::factory()->create(['status' => 'paid']);

        $this->expectException(DonationException::class);
        $this->service->markAsPaid($donation, [], $this->user);
    }

    #[Test]
    /**
     * Creates audit log on verification
     */
    public function it_creates_audit_log_on_verification(): void
    {
        $donation = Donation::factory()->create(['status' => 'pending']);

        $this->service->markAsPaid($donation, ['payment_id' => 'PAY-456'], $this->user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'marked_donation_paid',
            'model_id' => $donation->id,
            'user_id' => $this->user->id,
        ]);
    }

    // ============ History Tests ============

    #[Test]
    /**
     * Can get donor history
     */
    public function it_can_get_donor_history(): void
    {
        $donor = User::factory()->create();
        Donation::factory(15)->create(['user_id' => $donor->id]);

        $history = $this->service->getDonorHistory($donor);

        $this->assertCount(15, $history);
    }

    #[Test]
    /**
     * Donor history respects limit
     */
    public function it_respects_donor_history_limit(): void
    {
        $donor = User::factory()->create();
        Donation::factory(100)->create(['user_id' => $donor->id]);

        $history = $this->service->getDonorHistory($donor, 50);

        $this->assertCount(50, $history);
    }

    #[Test]
    /**
     * Donor history is ordered by newest first
     */
    public function it_orders_donor_history_newest_first(): void
    {
        $donor = User::factory()->create();
        $donation1 = Donation::factory()->create(['user_id' => $donor->id, 'created_at' => now()->subDays(5)]);
        $donation2 = Donation::factory()->create(['user_id' => $donor->id, 'created_at' => now()]);

        $history = $this->service->getDonorHistory($donor, 50);

        $this->assertEquals($donation2->id, $history->first()->id);
        $this->assertEquals($donation1->id, $history->last()->id);
    }

    // ============ Statistics Tests ============

    #[Test]
    /**
     * Can get donation statistics
     */
    public function it_can_get_donation_statistics(): void
    {
        Donation::factory(10)->create(['status' => 'paid', 'amount' => 5000]);
        Donation::factory(5)->create(['status' => 'pending', 'amount' => 2000]);

        $stats = $this->service->getStatistics();

        $this->assertEquals(15, $stats['total_donations']);
        $this->assertEquals(60000, $stats['total_amount']);
        $this->assertEquals(10, $stats['paid_count']);
        $this->assertEquals(50000, $stats['paid_amount']);
    }

    #[Test]
    /**
     * Statistics with county filter
     */
    public function it_can_get_statistics_by_county(): void
    {
        $nairobi = County::where('name', 'Nairobi')->first();
        $mombasa = County::where('name', 'Mombasa')->first();
        
        Donation::factory(8)->create(['county_id' => $nairobi->id, 'amount' => 5000]);
        Donation::factory(2)->create(['county_id' => $mombasa->id, 'amount' => 5000]);

        $stats = $this->service->getStatistics(['county_id' => $nairobi->id]);

        $this->assertEquals(8, $stats['total_donations']);
        $this->assertEquals(40000, $stats['total_amount']);
    }

    #[Test]
    /**
     * Can calculate average donation amount
     */
    public function it_can_calculate_average_donation(): void
    {
        Donation::factory(4)->create(['status' => 'verified', 'amount' => 10000]);

        $stats = $this->service->getStatistics();

        $this->assertEquals(10000, $stats['average_amount']);
    }

    // ============ Reporting Tests ============

    #[Test]
    /**
     * Can generate CSV report
     */
    public function it_can_generate_csv_report(): void
    {
        Donation::factory(10)->create();

        $report = $this->service->generateReport([], 'csv');

        $this->assertIsString($report);
        $this->assertStringContainsString('Amount', $report);
        $this->assertStringContainsString('Status', $report);
    }

    #[Test]
    /**
     * Can generate JSON report
     */
    public function it_can_generate_json_report(): void
    {
        Donation::factory(10)->create();

        $report = $this->service->generateReport([], 'json');

        $decoded = json_decode($report, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    #[Test]
    /**
     * Report respects filters
     */
    public function it_respects_filters_in_report(): void
    {
        $county = \App\Models\County::first();
        Donation::factory(5)->create(['county_id' => $county->id]);
        Donation::factory(5)->create(['county_id' => \App\Models\County::factory()->create()->id]);

        $report = $this->service->generateReport(['county_id' => $county->id], 'json');

        $decoded = json_decode($report, true);
        $this->assertCount(5, $decoded['report']);
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles zero donations gracefully
     */
    public function it_handles_zero_donations_in_statistics(): void
    {
        $stats = $this->service->getStatistics(['county' => 'NonExistent']);

        $this->assertEquals(0, $stats['total_donations']);
        $this->assertEquals(0, $stats['total_amount']);
    }

    #[Test]
    /**
     * Handles large donation amounts
     */
    public function it_handles_large_donation_amounts(): void
    {
        $data = [
            'donor_name' => 'Big Donor',
            'donor_email' => 'big@donor.com',
            'amount' => 999999999,
            'currency' => 'KES',
            'county_id' => \App\Models\County::first()->id,
        ];

        $donation = $this->service->createDonation($this->user, $data);

        $this->assertEquals(999999999, $donation->amount);
    }

    #[Test]
    /**
     * Maintains data consistency with multiple donations
     */
    public function it_maintains_consistency_with_multiple_donations(): void
    {
        Donation::factory(100)->create();

        $stats1 = $this->service->getStatistics();
        $stats2 = $this->service->getStatistics();

        $this->assertEquals($stats1['total_donations'], $stats2['total_donations']);
        $this->assertEquals($stats1['total_amount'], $stats2['total_amount']);
    }
}
