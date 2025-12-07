<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalPreference extends Model
{
    protected $fillable = [
        'user_id',
        'renewal_type',
        'send_renewal_reminders',
        'reminder_days_before',
        'preferred_payment_method',
        'auto_switch_to_free_on_expiry',
        'notes',
    ];

    protected $casts = [
        'send_renewal_reminders' => 'boolean',
        'auto_switch_to_free_on_expiry' => 'boolean',
    ];

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if auto renewal is enabled
     */
    public function isAutoRenewal(): bool
    {
        return $this->renewal_type === 'automatic';
    }

    /**
     * Check if manual renewal is set
     */
    public function isManualRenewal(): bool
    {
        return $this->renewal_type === 'manual';
    }

    /**
     * Check if reminders are enabled
     */
    public function shouldSendReminders(): bool
    {
        return $this->send_renewal_reminders;
    }

    /**
     * Switch to automatic renewal
     */
    public function enableAutoRenewal(): void
    {
        $this->update(['renewal_type' => 'automatic']);
    }

    /**
     * Switch to manual renewal
     */
    public function enableManualRenewal(): void
    {
        $this->update(['renewal_type' => 'manual']);
    }

    /**
     * Update preferred payment method
     */
    public function setPreferredPaymentMethod(string $method): void
    {
        $this->update(['preferred_payment_method' => $method]);
    }
}
