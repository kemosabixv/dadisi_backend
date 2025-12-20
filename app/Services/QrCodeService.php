<?php

namespace App\Services;

use App\Models\Registration;
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
    public function generateQrCode(Registration $registration): string
    {
        $token = $registration->qr_code_token ?? $this->generateQrToken();
        
        if (!$registration->qr_code_token) {
            $registration->update(['qr_code_token' => $token]);
        }

        $qrImage = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->generate($token);

        $path = 'events/tickets/qr-' . $registration->confirmation_code . '.png';
        Storage::disk('public')->put($path, $qrImage);

        $registration->update(['qr_code_path' => $path]);

        return $path;
    }

    /**
     * Get attendance stats for an event.
     */
    public function getAttendanceStats(Event $event): array
    {
        $total = $event->registrations()->whereIn('status', ['confirmed', 'attended'])->count();
        $attended = $event->registrations()->where('status', 'attended')->count();
        
        return [
            'total_registered' => $total,
            'attended' => $attended,
            'remaining' => $total - $attended,
            'percentage' => $total > 0 ? round(($attended / $total) * 100, 2) : 0,
        ];
    }
}
