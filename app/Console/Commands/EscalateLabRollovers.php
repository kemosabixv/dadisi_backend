<?php

namespace App\Console\Commands;

use App\Models\LabBooking;
use App\Models\MaintenanceBlockRollover;
use App\Notifications\BookingRolloverEscalatedNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscalateLabRollovers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:escalate-rollovers {--hours=48 : Hours to wait before escalation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Escalate maintenance rollovers that have not been resolved by users within the specified timeframe.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);

        $pendingRollovers = MaintenanceBlockRollover::where('status', MaintenanceBlockRollover::STATUS_PENDING_USER)
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($pendingRollovers->isEmpty()) {
            $this->info('No rollovers to escalate.');
            return 0;
        }

        $this->info("Found {$pendingRollovers->count()} rollovers to escalate.");

        // Get admins to notify
        $admins = User::role(['super_admin', 'admin'])->get();

        foreach ($pendingRollovers as $rollover) {
            DB::transaction(function () use ($rollover, $admins) {
                // Update rollover status
                $rollover->update([
                    'status' => MaintenanceBlockRollover::STATUS_ESCALATED,
                    'notes' => ($rollover->notes ? $rollover->notes . "\n" : "") . "Automatically escalated after {$this->option('hours')}h of inactivity.",
                ]);

                // We don't change the LabBooking status, it remains 'pending_user_resolution'
                // notify admins
                foreach ($admins as $admin) {
                    $admin->notify(new BookingRolloverEscalatedNotification($rollover));
                }
            });

            $this->line("Escalated rollover for booking #{$rollover->original_booking_id}");
        }

        $this->info('Escalation complete.');
        return 0;
    }
}
