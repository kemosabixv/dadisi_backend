<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'is_public',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the value cast to the correct type.
     */
    public function getValueAttribute($value)
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Set the value, handling type conversion.
     */
    public function setValueAttribute($value)
    {
        if ($this->type === 'json' && is_array($value)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = (string) $value;
        }
    }

    /**
     * Get grace period days for expired subscriptions
     */
    public static function getGracePeriodDays(): int
    {
        $setting = self::where('key', 'subscription_grace_period_days')->first();
        return $setting ? (int) $setting->value : 14;
    }

    /**
     * Set grace period days
     */
    public static function setGracePeriodDays(int $days): self
    {
        return self::updateOrCreate(
            ['key' => 'subscription_grace_period_days'],
            [
                'value' => (string) $days,
                'group' => 'subscriptions',
                'type' => 'integer',
                'description' => 'Number of days to allow re-subscription after expiry',
                'is_public' => false,
            ]
        );
    }
}
