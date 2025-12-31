<?php

namespace App\Services\Users;

use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Contracts\UserInvitationServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * User Invitation Service
 *
 * Handles user invitation and onboarding workflows including
 * dedicated invitation tracking and on-demand account creation.
 *
 * @package App\Services\Users
 */
class UserInvitationService implements UserInvitationServiceContract
{
    /**
     * Invite a single user (contract method)
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $data User data containing email, roles
     * @return array Invitation result
     *
     * @throws \App\Exceptions\UserException
     */
    public function invite(Authenticatable $actor, array $data): array
    {
        $invitation = $this->createInvitation(
            $actor,
            $data['email'],
            $data['roles'] ?? []
        );

        return [
            'invitation' => $invitation,
            'email' => $invitation->email,
            'token' => $invitation->token,
            'expires_at' => $invitation->expires_at,
        ];
    }

    /**
     * Bulk invite multiple users (contract method)
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $invitations Array of invitation data
     * @return int Count of invited users
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkInvite(Authenticatable $actor, array $invitations): int
    {
        $count = 0;
        foreach ($invitations as $data) {
            $this->invite($actor, $data);
            $count++;
        }

        return $count;
    }

    /**
     * Create an invitation record
     *
     * @param Authenticatable $actor The user performing the action
     * @param string $email User's email
     * @param array $roles Optional array of role names to assign
     * @return Invitation The created invitation
     *
     * @throws \App\Exceptions\UserException
     */
    private function createInvitation(Authenticatable $actor, string $email, array $roles = []): Invitation
    {
        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            throw new \App\Exceptions\UserException("User with this email already exists", 422);
        }

        return DB::transaction(function () use ($actor, $email, $roles) {
            try {
                // Check for existing pending invitation
                Invitation::where('email', $email)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->delete();

                // Create invitation
                $invitation = Invitation::create([
                    'email' => $email,
                    'token' => Str::random(64),
                    'roles' => $roles,
                    'inviter_id' => $actor->getAuthIdentifier(),
                    'expires_at' => now()->addDays(2),
                ]);

                // Log audit trail
                $this->logAudit($actor, 'create_invitation', $invitation);

                Log::info("Invitation created for {$email} by {$actor->getAuthIdentifier()} with roles: " . implode(',', $roles));

                // Send invitation email
                try {
                    $this->sendInvitationEmail($invitation);
                } catch (\Exception $e) {
                    Log::warning("Failed to send invitation email to {$email}: {$e->getMessage()}");
                }

                return $invitation;
            } catch (\App\Exceptions\UserException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error("Failed to create invitation: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to invite user", 422, $e);
            }
        });
    }

    /**
     * Send invitation email to user
     *
     * @param Invitation $invitation The invitation record
     * @return bool Success status
     *
     * @throws \App\Exceptions\UserException
     */
    public function sendInvitationEmail(Invitation $invitation): bool
    {
        try {
            // TODO: Implement email sending via Mailable with signed URL or token link
            Log::info("Invitation email would be sent to {$invitation->email} with token {$invitation->token}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send invitation email: {$e->getMessage()}");
            throw new \App\Exceptions\UserException("Failed to send invitation email", 422, $e);
        }
    }

    /**
     * Verify invitation token
     *
     * @param string $token The invitation token
     * @return Invitation The invitation associated with the token
     *
     * @throws \App\Exceptions\UserException
     */
    public function verifyInvitationToken(string $token): Invitation
    {
        try {
            $invitation = Invitation::where('token', $token)->firstOrFail();

            if ($invitation->isAccepted()) {
                throw new \App\Exceptions\UserException("Invitation already accepted", 422);
            }

            if ($invitation->isExpired()) {
                throw new \App\Exceptions\UserException("Invitation has expired", 422);
            }

            return $invitation;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \App\Exceptions\UserException("Invalid invitation token", 422, $e);
        } catch (\App\Exceptions\UserException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Failed to verify invitation token: {$e->getMessage()}");
            throw new \App\Exceptions\UserException("Failed to verify invitation", 422, $e);
        }
    }

    /**
     * Accept invitation and create user account
     *
     * @param string $token
     * @param array $userData
     * @return User
     *
     * @throws \App\Exceptions\UserException
     */
    public function acceptInvitation(string $token, array $userData): User
    {
        return DB::transaction(function () use ($token, $userData) {
            try {
                $invitation = $this->verifyInvitationToken($token);

                // Create user
                $user = User::create([
                    'email' => $invitation->email,
                    'username' => $userData['username'],
                    'password' => Hash::make($userData['password']),
                    'email_verified_at' => now(), // Implicitly verified via token
                ]);

                // Assign roles
                if (!empty($invitation->roles)) {
                    $user->syncRoles($invitation->roles);
                }

                // Mark invitation as accepted
                $invitation->update(['accepted_at' => now()]);

                // Log audit trail
                $this->logAudit($user, 'accept_invitation', $invitation);

                Log::info("Invitation accepted by {$user->email}, user account created with ID {$user->id}");

                return $user;
            } catch (\App\Exceptions\UserException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error("Failed to accept invitation: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to create account from invitation", 422, $e);
            }
        });
    }

    /**
     * Log audit trail
     *
     * @param Authenticatable $actor The user performing the action
     * @param string $action The action performed
     * @param mixed $entity The affected entity
     * @return void
     */
    private function logAudit(Authenticatable $actor, string $action, $entity): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => $action,
                'model_type' => get_class($entity),
                'model_id' => $entity->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log audit: {$e->getMessage()}");
        }
    }
}
