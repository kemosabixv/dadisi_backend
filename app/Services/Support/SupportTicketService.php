<?php

namespace App\Services\Support;

use App\Exceptions\SupportTicketException;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketResponse;
use App\Services\Contracts\SupportTicketServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportTicketService implements SupportTicketServiceContract
{
    public function createTicket(Authenticatable $actor, array $data): SupportTicket
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                $ticket = SupportTicket::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'priority' => $data['priority'] ?? 'medium',
                    'status' => 'open',
                ]);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'created_support_ticket',
                    'model_type' => SupportTicket::class,
                    'model_id' => $ticket->id,
                    'changes' => $ticket->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $ticket;
            });
        } catch (\Exception $e) {
            throw SupportTicketException::creationFailed($e->getMessage());
        }
    }

    public function listTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SupportTicket::with(['user', 'assignee']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('subject', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getTicketDetail(int $id): SupportTicket
    {
        try {
            return SupportTicket::with(['user', 'assignee', 'responses.user'])->findOrFail($id);
        } catch (\Exception $e) {
            throw SupportTicketException::notFound($id);
        }
    }

    public function getTicket(int $id): SupportTicket
    {
        return $this->getTicketDetail($id);
    }

    public function updateTicket(Authenticatable $actor, SupportTicket|int $ticket, array $data): SupportTicket
    {
        try {
            $ticket = $ticket instanceof SupportTicket ? $ticket : SupportTicket::findOrFail($ticket);
            
            $ticket->update($data);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_support_ticket',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'changes' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed($e->getMessage());
        }
    }

    public function addResponse(Authenticatable $actor, int $ticketId, string $message, array $attachments = [], bool $isInternal = false): SupportTicketResponse
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);

            return DB::transaction(function () use ($actor, $ticket, $message, $attachments, $isInternal) {
                $response = SupportTicketResponse::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $actor->getAuthIdentifier(),
                    'message' => $message,
                    'attachments' => $attachments,
                    'is_internal' => $isInternal,
                ]);

                // Update ticket status to pending if responded by staff, or open if by user
                if (!$isInternal) {
                    $newStatus = $actor->getAuthIdentifier() === $ticket->user_id ? 'open' : 'pending';
                    $ticket->update(['status' => $newStatus]);
                }

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'added_support_response',
                    'model_type' => SupportTicket::class,
                    'model_id' => $ticket->id,
                    'changes' => ['response_id' => $response->id],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $response;
            });
        } catch (\Exception $e) {
            throw new SupportTicketException("Failed to add response: " . $e->getMessage());
        }
    }

    public function assignTicket(Authenticatable $actor, int $ticketId, int|Authenticatable $assigneeId): SupportTicket
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            $assigneeId = $assigneeId instanceof Authenticatable ? $assigneeId->getAuthIdentifier() : $assigneeId;
            
            $ticket->update(['assigned_to' => $assigneeId]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'assigned_support_ticket',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'changes' => ['assigned_to' => $assigneeId],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed("Assignment failed: " . $e->getMessage());
        }
    }

    public function updateStatus(Authenticatable $actor, int $ticketId, string $status): SupportTicket
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            $oldStatus = $ticket->status;
            $ticket->update(['status' => $status]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_support_status',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'changes' => ['old' => $oldStatus, 'new' => $status],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed($e->getMessage());
        }
    }

    public function resolveTicket(Authenticatable $actor, SupportTicket|int $ticket, ?string $notes = null): SupportTicket
    {
        try {
            $ticket = $ticket instanceof SupportTicket ? $ticket : SupportTicket::findOrFail($ticket);
            
            if ($ticket->status !== 'open' && $ticket->status !== 'pending') {
                 throw new \Exception("Only open or pending tickets can be resolved.");
            }

            $ticket->update([
                'status' => 'resolved',
                'resolution_notes' => $notes,
                'resolved_by' => $actor->getAuthIdentifier(),
                'resolved_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'resolved_support_ticket',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'changes' => ['notes' => $notes],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed("Resolution failed: " . $e->getMessage());
        }
    }

    public function reopenTicket(Authenticatable $actor, SupportTicket|int $ticket, string $reason): SupportTicket
    {
        try {
            $ticket = $ticket instanceof SupportTicket ? $ticket : SupportTicket::findOrFail($ticket);
            
            $ticket->update([
                'status' => 'open',
                'reopen_reason' => $reason,
                'reopened_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'reopened_support_ticket',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'changes' => ['reason' => $reason],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed("Reopen failed: " . $e->getMessage());
        }
    }

    public function closeTicket(Authenticatable $actor, int $ticketId): SupportTicket
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'closed_support_ticket',
                'model_type' => SupportTicket::class,
                'model_id' => $ticket->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $ticket;
        } catch (\Exception $e) {
            throw SupportTicketException::updateFailed("Closure failed: " . $e->getMessage());
        }
    }
}
