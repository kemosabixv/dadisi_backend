<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RenewalReminder;
use App\Mail\RenewalReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendRenewalReminders extends Command
{
    protected $signature = 'renewals:send-reminders {--dueDays=0 : Only send reminders scheduled within N days from now}';

    protected $description = 'Send scheduled renewal reminders to users';

    public function handle()
    {
        $dueDays = (int) $this->option('dueDays');

        $query = RenewalReminder::where('is_sent', false)
            ->where('scheduled_at', '<=', now());

        if ($dueDays > 0) {
            $query->where('scheduled_at', '<=', now()->addDays($dueDays));
        }

        $reminders = $query->orderBy('scheduled_at')->get();

        $this->info('Found ' . $reminders->count() . ' reminders to process.');

        foreach ($reminders as $reminder) {
            try {
                $email = $reminder->metadata['email'] ?? $reminder->user->email ?? null;

                if (!$email) {
                    Log::warning('No email for renewal reminder', ['reminder_id' => $reminder->id]);
                    continue;
                }

                Mail::to($email)->queue(new RenewalReminderMail($reminder));

                $reminder->is_sent = true;
                $reminder->sent_at = now();
                $reminder->save();

                $this->info('Queued reminder ' . $reminder->id . ' to ' . $email);
            } catch (\Exception $e) {
                Log::error('Failed to send renewal reminder', ['id' => $reminder->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Done.');

        return 0;
    }
}
