<?php

namespace Tests\Unit\Services;

use App\Models\EventRegistration;
use App\Models\Event;
use App\Models\User;
use App\Models\Ticket;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QrCodeService $qrCodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qrCodeService = new QrCodeService();
        Storage::fake('public');
    }

    public function test_it_generates_qr_token()
    {
        $token = $this->qrCodeService->generateQrToken();
        $this->assertStringStartsWith('TKT-', $token);
        $this->assertEquals(44, strlen($token)); // 'TKT-' (4) + Str::random(40)
    }

    public function test_it_generates_qr_code_image()
    {
        // Setup dependencies
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);
        
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'confirmation_code' => 'TEST-CONF-123',
            'status' => 'confirmed',
        ]);

        $path = $this->qrCodeService->generateQrCode($registration);

        // Assert path is returned and stored in db
        $this->assertEquals('events/tickets/qr-TEST-CONF-123.svg', $path);
        $this->assertEquals($path, $registration->fresh()->qr_code_path);
        $this->assertNotNull($registration->fresh()->qr_code_token);

        // Assert file exists in storage
        Storage::disk('public')->assertExists($path);
    }

    public function test_it_reuses_existing_token()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);
        
        $token = 'TKT-EXISTING-TOKEN-123';
        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'confirmation_code' => 'TEST-CONF-456',
            'status' => 'confirmed',
            'qr_code_token' => $token,
        ]);

        $this->qrCodeService->generateQrCode($registration);
        
        $this->assertEquals($token, $registration->fresh()->qr_code_token);
    }
}
