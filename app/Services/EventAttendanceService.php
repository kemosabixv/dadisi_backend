<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOrder;
use App\Models\EventRegistration;
use App\Services\Contracts\EventAttendanceServiceContract;
use Illuminate\Support\Collection;

/**
 * EventAttendanceService
 *
 * Handles attendance tracking and ticket verification logic.
 */
class EventAttendanceService implements EventAttendanceServiceContract
{
    /**
     * @inheritDoc
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

    /**
     * @inheritDoc
     */
    public function scanTicket(Event $event, string $token): array
    {
        // 1. Try Registration (Free RSVP)
        $registration = EventRegistration::where('qr_code_token', $token)
            ->where('event_id', $event->id)
            ->with('user')
            ->first();

        if ($registration) {
            if ($registration->status === 'attended') {
                return [
                    'success' => false,
                    'status_code' => 409,
                    'message' => 'User already checked in.',
                    'attendee' => [
                        'name' => $registration->user?->name ?? 'Guest',
                        'type' => 'RSVP',
                        'time' => $registration->check_in_at,
                    ]
                ];
            }

            if ($registration->status !== 'confirmed') {
                return [
                    'success' => false,
                    'status_code' => 400,
                    'message' => 'Ticket is status: ' . $registration->status,
                ];
            }

            $registration->update([
                'status' => 'attended',
                'check_in_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'RSVP Check-in successful!',
                'attendee' => [
                    'name' => $registration->user?->name ?? 'Guest',
                    'type' => 'RSVP',
                    'time' => now(),
                ]
            ];
        }

        // 2. Try EventOrder (Paid Ticket)
        $order = EventOrder::where('qr_code_token', $token)
            ->where('event_id', $event->id)
            ->with('user')
            ->first();

        if ($order) {
            if ($order->checked_in_at) {
                return [
                    'success' => false,
                    'status_code' => 409,
                    'message' => 'Ticket already used.',
                    'attendee' => [
                        'name' => $order->user?->name ?? 'Guest',
                        'type' => 'Paid Ticket',
                        'time' => $order->checked_in_at,
                    ]
                ];
            }

            if ($order->status !== 'paid') {
                return [
                    'success' => false,
                    'status_code' => 400,
                    'message' => 'Ticket not paid (Status: ' . $order->status . ')',
                ];
            }

            $order->update([
                'checked_in_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Paid Ticket Check-in successful!',
                'attendee' => [
                    'name' => $order->user?->name ?? 'Guest',
                    'type' => 'Paid Ticket',
                    'time' => now(),
                ]
            ];
        }

        return [
            'success' => false,
            'status_code' => 404,
            'message' => 'Invalid ticket token.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function listAttendees(Event $event): Collection
    {
        $registrations = EventRegistration::where('event_id', $event->id)
            ->whereIn('status', ['confirmed', 'attended'])
            ->with('user')
            ->get()
            ->map(function($r) {
                return [
                    'id' => 'reg_' . $r->id,
                    'name' => $r->user?->name ?? 'Guest',
                    'email' => $r->user?->email,
                    'type' => 'RSVP',
                    'status' => $r->status,
                    'checked_in_at' => $r->check_in_at,
                ];
            });

        $orders = EventOrder::where('event_id', $event->id)
            ->where('status', 'paid')
            ->with('user')
            ->get()
            ->map(function($o) {
                return [
                    'id' => 'ord_' . $o->id,
                    'name' => $o->user?->name ?? 'Guest',
                    'email' => $o->user?->email,
                    'type' => 'Paid',
                    'status' => $o->checked_in_at ? 'attended' : 'confirmed',
                    'checked_in_at' => $o->checked_in_at,
                ];
            });

        return $registrations->concat($orders)->sortByDesc('checked_in_at')->values();
    }
}
