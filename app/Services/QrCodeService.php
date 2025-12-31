<?php

namespace App\Services;

use App\Models\EventRegistration;
use App\Models\Event;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    /**
     * Generate a unique secure token for the QR code.
     */
    public function generateQrToken(): string
    {
        return 'TKT-' . Str::random(40);
    }

    /**
     * Generate the QR code image and return the storage path.
     */
    public function generateQrCode(EventRegistration $registration): string
    {
        $token = $registration->qr_code_token ?? $this->generateQrToken();
        
        if (!$registration->qr_code_token) {
            $registration->update(['qr_code_token' => $token]);
        }

        $qrImage = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('H')
            ->generate($token);

        $path = 'events/tickets/qr-' . $registration->confirmation_code . '.svg';
        Storage::disk('public')->put($path, $qrImage);

        $registration->update(['qr_code_path' => $path]);

        return $path;
    }

    /**
     * Get attendance stats for an event.
     */
    public function getAttendanceStats(Event $event): array
    {
        $regTotal = $event->registrations()->whereIn('status', ['confirmed', 'attended'])->count();
        $regAttended = $event->registrations()->where('status', 'attended')->count();

        $orderTotal = $event->orders()->where('status', 'paid')->count();
        $orderAttended = $event->orders()->whereNotNull('checked_in_at')->count();
        
        $total = $regTotal + $orderTotal;
        $attended = $regAttended + $orderAttended;

        return [
            'total_registered' => $total,
            'attended' => $attended,
            'remaining' => $total - $attended,
            'percentage' => $total > 0 ? round(($attended / $total) * 100, 2) : 0,
            'breakdown' => [
                'registrations' => [
                    'total' => $regTotal,
                    'attended' => $regAttended,
                ],
                'orders' => [
                    'total' => $orderTotal,
                    'attended' => $orderAttended,
                ]
            ]
        ];
    }
}
