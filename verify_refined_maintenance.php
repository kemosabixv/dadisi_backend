<?php

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use App\Models\LabMaintenanceBlock;
use App\Models\SlotHold;
use App\Models\BookingSeries;
use App\Services\LabBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function log_msg($msg) {
    echo $msg . "\n";
    file_put_contents('verify.log', $msg . "\n", FILE_APPEND);
}

if (file_exists('verify.log')) {
    unlink('verify.log');
}

log_msg("Starting Refined Maintenance Verification...");

[$user, $lab] = (function() {
    $user = User::create([
        'username' => 'testuser_' . uniqid(),
        'email' => 'test_' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $lab = LabSpace::first() ?: LabSpace::create([
        'name' => 'Main Lab',
        'slug' => 'main-lab',
        'capacity' => 10,
        'is_available' => true,
    ]);

    return [$user, $lab];
})();

$service = app(LabBookingService::class);

// --- CASE 1: Rollover Confirmed only ---
log_msg("\n--- Testing Confirmed-Only Rollover ---");
$targetDate = Carbon::now()->addDays(30)->startOfDay()->addHours(10);

$confirmed = LabBooking::create([
    'user_id' => null,
    'guest_email' => 'guest@example.com',
    'lab_space_id' => $lab->id,
    'starts_at' => $targetDate->copy(),
    'ends_at' => $targetDate->copy()->addHours(2),
    'status' => LabBooking::STATUS_CONFIRMED,
    'purpose' => 'Confirmed Rollover',
    'title' => 'C1 Confirmed',
]);

$pending = LabBooking::create([
    'user_id' => null,
    'guest_email' => 'guest@example.com',
    'lab_space_id' => $lab->id,
    'starts_at' => $targetDate->copy()->addHours(3),
    'ends_at' => $targetDate->copy()->addHours(5),
    'status' => LabBooking::STATUS_PENDING,
    'purpose' => 'Pending Static',
    'title' => 'C1 Pending',
]);

$block = LabMaintenanceBlock::create([
    'lab_space_id' => $lab->id,
    'title' => 'Maintenance Title',
    'starts_at' => $targetDate->copy()->subHour(),
    'ends_at' => $targetDate->copy()->addHours(6),
    'reason' => 'Test Maintenance',
    'created_by' => $user->id,
]);

log_msg("Maintenance Block created: {$block->starts_at} to {$block->ends_at}");

$results = $service->rollOverBookings($block);

$confirmed->refresh();
$pending->refresh();

$confirmedOk = $confirmed->starts_at->gte($block->ends_at);
$pendingOk = $pending->starts_at->equalTo($targetDate->copy()->addHours(3));

if ($confirmedOk && $pendingOk) {
    log_msg("✅ SUCCESS: Only Confirmed booking was rolled over.");
} else {
    log_msg("❌ FAILURE: Rollover logic incorrect.");
}

// --- CASE 2: checkAvailability checks Maintenance ---
log_msg("\n--- Testing checkAvailability with Maintenance ---");
$isAvailable = $service->checkAvailability($lab->id, $targetDate->copy(), $targetDate->copy()->addHour());
if (!$isAvailable) {
    log_msg("✅ SUCCESS: checkAvailability correctly flagged maintenance overlap.");
} else {
    log_msg("❌ FAILURE: checkAvailability missed maintenance overlap.");
}

// --- CASE 3: Re-validation at confirmation ---
log_msg("\n--- Testing re-validation at confirmation ---");
$futureDate = Carbon::now()->addDays(35)->startOfDay()->addHours(10);

$holdRef = 'HOLD_' . uniqid();
log_msg("Creating hold: $holdRef");
$hold = SlotHold::create([
    'lab_space_id' => $lab->id,
    'user_id' => null,
    'guest_email' => 'guest@example.com',
    'reference' => $holdRef,
    'starts_at' => $futureDate->copy(),
    'ends_at' => $futureDate->copy()->addHour(),
    'expires_at' => Carbon::now()->addMinutes(15),
]);

log_msg("Creating series for hold");
$series = BookingSeries::create([
    'reference' => $holdRef,
    'user_id' => null,
    'lab_space_id' => $lab->id,
    'status' => 'pending',
    'type' => 'single',
    'total_hours' => 1,
    'metadata' => ['purpose' => 'Test Phase 2', 'title' => 'Phase 2', 'guest_email' => 'guest@example.com'],
]);

log_msg("Creating overlapping maintenance block");
LabMaintenanceBlock::create([
    'lab_space_id' => $lab->id,
    'title' => 'Late Maintenance',
    'starts_at' => $futureDate->copy()->subMinutes(30),
    'ends_at' => $futureDate->copy()->addMinutes(30),
    'reason' => 'Late Maintenance',
    'created_by' => $user->id,
]);

try {
    log_msg("Attempting to confirm hold...");
    $service->confirmBooking($holdRef);
    log_msg("❌ FAILURE: confirmBooking allowed overlap.");
} catch (\Exception $e) {
    log_msg("✅ SUCCESS: confirmBooking caught the conflict: " . $e->getMessage());
}

log_msg("\nRefined Maintenance Verification Finished.");
