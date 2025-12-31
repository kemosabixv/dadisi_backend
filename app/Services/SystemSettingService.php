<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Services\Contracts\SystemSettingServiceContract;
use Illuminate\Support\Collection;

/**
 * SystemSettingService
 *
 * Implements business logic for managing global system settings.
 */
class SystemSettingService implements SystemSettingServiceContract
{
    /**
     * @inheritDoc
     */
    public function getSettings(?string $group = null): Collection
    {
        $query = SystemSetting::query();

        if ($group) {
            $query->where('group', $group);
        }

        return $query->get()->mapWithKeys(function ($item) {
            return [$item->key => $item->value];
        });
    }

    /**
     * @inheritDoc
     */
    public function updateSettings(array $settings, ?int $userId = null): array
    {
        $updatedSettings = [];

        foreach ($settings as $key => $value) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);

            if (!$setting->exists) {
                // Infer group and type for new settings
                $setting->group = explode('.', $key)[0] ?? 'general';
                $setting->type = $this->inferType($value);
            }

            $setting->value = $value;
            if ($userId) {
                $setting->updated_by = $userId;
            }
            $setting->save();

            $updatedSettings[$key] = $setting->value;
        }

        return $updatedSettings;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        $setting = SystemSetting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $userId = null): void
    {
        $this->updateSettings([$key => $value], $userId);
    }

    /**
     * Infer the setting type from its value
     *
     * @param mixed $value
     * @return string
     */
    protected function inferType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_numeric($value)) {
            // Check if it's float or int
            return floor((float)$value) == (float)$value && !str_contains((string)$value, '.') ? 'integer' : 'float';
        }

        if (is_array($value)) {
            return 'json';
        }

        return 'string';
    }
}
