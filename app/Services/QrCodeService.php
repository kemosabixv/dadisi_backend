<?php

namespace App\Services;

use App\Models\EventOrder;
use App\Services\Media\MediaService;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

        // 1. Generate QR SVG
        $qrImage = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('H')
            ->generate($token);

        // 2. Save to temporary file for MediaService
        $tempPath = tempnam(sys_get_temp_dir(), 'qr_');
        file_put_contents($tempPath, $qrImage);

        try {
            /** @var MediaService $mediaService */
            $mediaService = app(MediaService::class);
            
            // 3. Register in CAS/R2
            $media = $mediaService->registerFile(
                $registration->user ?? User::first(), // Fallback to a system user if guest
                $tempPath,
                'qr-' . $registration->confirmation_code . '.svg',
                [
                    'visibility' => 'public',
                    'root_type' => 'public',
                    'path' => ['tickets', $registration->confirmation_code],
                ]
            );

            // 4. Update registration
            $registration->update([
                'qr_code_path' => $media->url,
                'qr_code_media_id' => $media->id,
            ]);

            return $media->url;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Generate the QR code image for an order and return the storage path.
     */
    public function generateQrCodeForOrder(EventOrder $order): string
    {
        $token = $order->qr_code_token ?? $order->generateQrToken();
        
        if (!$order->qr_code_token) {
            $order->update(['qr_code_token' => $token]);
        }

        $qrImage = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('H')
            ->generate($token);

        $tempPath = tempnam(sys_get_temp_dir(), 'qr_o_');
        file_put_contents($tempPath, $qrImage);

        try {
            /** @var MediaService $mediaService */
            $mediaService = app(MediaService::class);
            
            $media = $mediaService->registerFile(
                $order->user ?? User::first(),
                $tempPath,
                'qr-order-' . $order->reference . '.svg',
                [
                    'visibility' => 'public',
                    'root_type' => 'public',
                    'path' => ['tickets', $order->reference],
                ]
            );

            $order->update([
                'qr_code_path' => $media->url,
                'qr_code_media_id' => $media->id,
            ]);

            return $media->url;
        } finally {
            @unlink($tempPath);
        }
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
