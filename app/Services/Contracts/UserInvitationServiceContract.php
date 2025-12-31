<?php

namespace App\Services\Contracts;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User Invitation Service Contract
 *
 * Defines the interface for user invitation and onboarding operations.
 *
 * @package App\Services\Contracts
 */
interface UserInvitationServiceContract
{
    /**
     * Invite a single user
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $data User data containing email, roles
     * @return array Invitation result
     *
     * @throws \App\Exceptions\UserException
     */
    public function invite(Authenticatable $actor, array $data): array;

    /**
     * Bulk invite multiple users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $invitations Array of invitation data
     * @return int Count of invited users
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkInvite(Authenticatable $actor, array $invitations): int;

    /**
     * Send invitation email
     *
     * @param Invitation $invitation The invitation
     * @return bool Success status
     *
     * @throws \App\Exceptions\UserException
     */
    public function sendInvitationEmail(Invitation $invitation): bool;

    /**
     * Verify invitation token
     *
     * @param string $token The invitation token
     * @return Invitation The invitation model
     *
     * @throws \App\Exceptions\UserException
     */
    public function verifyInvitationToken(string $token): Invitation;

    /**
     * Accept invitation and create user account
     *
     * @param string $token The invitation token
     * @param array $userData User details (username, password, etc)
     * @return User The newly created user
     *
     * @throws \App\Exceptions\UserException
     */
    public function acceptInvitation(string $token, array $userData): User;
}
