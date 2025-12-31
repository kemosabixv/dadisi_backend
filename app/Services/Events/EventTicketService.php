<?php

namespace App\Services\Events;

use App\Exceptions\EventTicketException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\Contracts\EventTicketServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EventTicketService
 *
 * Handles management of event ticket tiers.
 */
class EventTicketService implements EventTicketServiceContract
{
    /**
     * Create a new ticket tier for an event
     */
    public function createTicket(Authenticatable $actor, Event $event, array $data): Ticket
    {
        try {
            return DB::transaction(function () use ($actor, $event, $data) {
                $data['event_id'] = $event->id;
                
                // If quantity is set, initialize available to same value
                if (isset($data['quantity'])) {
                    $data['available'] = $data['quantity'];
                }

                $ticket = Ticket::create($data);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'created_ticket_tier',
                    'model_type' => Ticket::class,
                    'model_id' => $ticket->id,
                    'changes' => [
                        'event_id' => $event->id,
                        'name' => $ticket->name,
                        'price' => $ticket->price,
                        'quantity' => $ticket->quantity,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info("Ticket tier created: {$ticket->name} for event ID: {$event->id}", [
                    'actor_id' => $actor->getAuthIdentifier(),
                    'ticket_id' => $ticket->id,
                ]);

                return $ticket;
            });
        } catch (\Exception $e) {
            Log::error('Ticket tier creation failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventTicketException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update an existing ticket tier
     */
    public function updateTicket(Authenticatable $actor, Ticket $ticket, array $data): Ticket
    {
        try {
            return DB::transaction(function () use ($actor, $ticket, $data) {
                $oldValues = $ticket->toArray();

                // If quantity is increased, increase available as well
                if (isset($data['quantity']) && $data['quantity'] != $ticket->quantity) {
                    $difference = $data['quantity'] - $ticket->quantity;
                    $data['available'] = max(0, $ticket->available + $difference);
                    
                    // Sanity check: available cannot exceed quantity
                    $data['available'] = min($data['available'], $data['quantity']);
                }

                $ticket->update($data);

                $changes = [];
                foreach ($data as $key => $value) {
                    if (array_key_exists($key, $oldValues) && $oldValues[$key] !== $value) {
                        $changes[$key] = [
                            'old' => $oldValues[$key],
                            'new' => $value,
                        ];
                    }
                }

                if (!empty($changes)) {
                    AuditLog::create([
                        'user_id' => $actor->getAuthIdentifier(),
                        'action' => 'updated_ticket_tier',
                        'model_type' => Ticket::class,
                        'model_id' => $ticket->id,
                        'changes' => $changes,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }

                return $ticket->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Ticket tier update failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            throw EventTicketException::updateFailed($e->getMessage());
        }
    }

    /**
     * Delete a ticket tier
     */
    public function deleteTicket(Authenticatable $actor, Ticket $ticket): bool
    {
        try {
            // Check if there are registrations for this ticket
            if ($ticket->registrations()->exists()) {
                throw new \Exception("Cannot delete ticket tier with existing registrations.");
            }

            $ticket->delete();

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'deleted_ticket_tier',
                'model_type' => Ticket::class,
                'model_id' => $ticket->id,
                'changes' => ['name' => $ticket->name],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Ticket tier deletion failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            throw EventTicketException::closureFailed($e->getMessage());
        }
    }

    /**
     * List all ticket tiers for an event
     */
    public function listEventTickets(Event $event, bool $activeOnly = true): Collection
    {
        $query = $event->tickets();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('price', 'asc')->get();
    }

    /**
     * Get a specific ticket by ID
     */
    public function getById(int $id): Ticket
    {
        try {
            return Ticket::with('event')->findOrFail($id);
        } catch (\Exception $e) {
            throw EventTicketException::notFound($id);
        }
    }

    /**
     * Get statistics for ticket sales/availability
     */
    public function getTicketStats(Ticket $ticket): array
    {
        $sold = $ticket->registrations()->where('status', 'confirmed')->count();
        
        return [
            'ticket_id' => $ticket->id,
            'name' => $ticket->name,
            'total_quantity' => $ticket->quantity,
            'sold_count' => $sold,
            'available_count' => $ticket->available,
            'price' => $ticket->price,
            'currency' => $ticket->currency,
            'is_active' => (bool)$ticket->is_active,
        ];
    }
}
