<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\County;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\MemberProfile;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LabBookingSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Truncate existing booking data to ensure idempotency
        LabBooking::query()->delete();
        AttendanceLog::query()->delete();

        $labs = LabSpace::all();
        if ($labs->isEmpty()) {
            $this->command->warn('No lab spaces found. Skipping lab bookings seeding.');
            return;
        }

        $counties = County::all();
        $plans = Plan::all();
        
        // Ensure we have some users with profiles and plans
        $users = $this->ensureUsersWithProfiles($counties, $plans);

        $now = Carbon::now();
        $totalBookings = 0;

        foreach ($labs as $lab) {
            // Seed past bookings (reduced)
            for ($i = 0; $i < 3; $i++) {
                $this->createRandomBooking($lab, $users, $now->copy()->subDays(rand(1, 30)), true);
                $totalBookings++;
            }

            // Seed future bookings (reduced)
            for ($i = 0; $i < 2; $i++) {
                $this->createRandomBooking($lab, $users, $now->copy()->addDays(rand(0, 30)), false);
                $totalBookings++;
            }
        }

        $this->command->info("Created {$totalBookings} lab bookings with associated profiles, plans, and attendance logs.");
    }

    private function ensureUsersWithProfiles($counties, $plans)
    {
        $users = User::whereDoesntHave('roles', function($q) {
            $q->where('name', 'super_admin');
        })->take(20)->get();

        if ($users->count() < 10) {
            // Create more if needed
            for ($i = 0; $i < 10; $i++) {
                $user = User::create([
                    'username' => 'member' . rand(100, 999),
                    'email' => 'member' . rand(100, 999) . '@example.com',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('member');
                $users->push($user);
            }
        }

        foreach ($users as $user) {
            if (!$user->memberProfile) {
                MemberProfile::create([
                    'user_id' => $user->id,
                    'first_name' => 'Member',
                    'last_name' => (string) $user->id,
                    'phone_number' => '+2547' . rand(10000000, 99999999),
                    'county_id' => $counties->random()->id ?? 1,
                    'plan_id' => $plans->random()->id ?? null,
                    'terms_accepted' => true,
                ]);
            }
        }

        return $users;
    }

    private function createRandomBooking($lab, $users, $date, $isPast)
    {
        $isGuest = rand(0, 10) > 6; // 40% guest
        $user = $isGuest ? null : $users->random();
        
        $startHour = rand(8, 18);
        $duration = rand(1, 4);
        
        $startsAt = $date->copy()->setTime($startHour, 0);
        $endsAt = $startsAt->copy()->addHours($duration);

        $status = $this->getRandomStatus($isPast);

        $booking = LabBooking::create([
            'lab_space_id' => $lab->id,
            'user_id' => $user?->id,
            'guest_name' => $isGuest ? 'Guest ' . Str::random(5) : null,
            'guest_email' => $isGuest ? 'guest' . rand(100, 999) . '@example.com' : null,
            'title' => 'Laboratory Usage Session',
            'purpose' => 'Research and experimentation for project ' . Str::random(3),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_type' => 'hourly',
            'status' => $status,
            'quota_consumed' => !$isGuest && rand(0, 1),
            'booking_reference' => 'LB-' . strtoupper(Str::random(8)),
            'total_price' => $isGuest ? ($lab->hourly_rate * $duration) : 0,
        ]);

        // Generate Attendance Logs for completed or no-show
        if ($status === LabBooking::STATUS_COMPLETED || $status === LabBooking::STATUS_NO_SHOW) {
            $this->createAttendanceLog($booking);
        }
    }

    private function getRandomStatus($isPast)
    {
        if ($isPast) {
            $options = [
                LabBooking::STATUS_COMPLETED,
                LabBooking::STATUS_COMPLETED,
                LabBooking::STATUS_COMPLETED,
                LabBooking::STATUS_NO_SHOW,
                LabBooking::STATUS_CANCELLED
            ];
            return $options[array_rand($options)];
        } else {
            $options = [
                LabBooking::STATUS_CONFIRMED,
                LabBooking::STATUS_CONFIRMED,
                LabBooking::STATUS_PENDING,
                LabBooking::STATUS_CANCELLED
            ];
            return $options[array_rand($options)];
        }
    }

    private function createAttendanceLog(LabBooking $booking)
    {
        $status = $booking->status === LabBooking::STATUS_COMPLETED ? AttendanceLog::STATUS_ATTENDED : AttendanceLog::STATUS_NO_SHOW;
        
        AttendanceLog::create([
            'booking_id' => $booking->id,
            'lab_id' => $booking->lab_space_id,
            'user_id' => $booking->user_id,
            'status' => $status,
            'check_in_time' => $status === AttendanceLog::STATUS_ATTENDED ? $booking->starts_at->copy()->addMinutes(rand(0, 15)) : null,
            'slot_start_time' => $booking->starts_at,
            'marked_by_id' => User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->first()?->id,
            'notes' => $status === AttendanceLog::STATUS_NO_SHOW ? 'User did not show up' : 'Session completed successfully',
        ]);
    }
}
