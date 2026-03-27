<?php

namespace App\Services;

use App\Models\DataDestructionCommand;
use App\Models\UserDataRetentionSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing data destruction commands and retention policies
 * 
 * This service provides a unified interface for:
 * - Managing command execution (run, dry-run, sync)
 * - Querying command status and metadata
 * - Updating retention policies
 * - Admin operations (enable/disable commands)
 */
class DataDestructionCommandManager
{
    /**
     * Get all data destruction commands with status
     */
    public function getAllCommands(): Collection
    {
        return DataDestructionCommand::all()->map(function ($command) {
            return [
                'id' => $command->id,
                'command_name' => $command->command_name,
                'data_type' => $command->data_type,
                'is_enabled' => $command->is_enabled,
                'frequency' => $command->frequency,
                'last_run_at' => $command->last_run_at?->diffForHumans(),
                'affected_records_count' => $command->affected_records_count,
                'is_critical' => $command->is_critical,
                'supports_dry_run' => $command->supports_dry_run,
                'supports_sync' => $command->supports_sync,
                'description' => $command->description,
            ];
        });
    }

    /**
     * Get commands grouped by data type
     */
    public function getCommandsByDataType(string $dataType): Collection
    {
        return DataDestructionCommand::where('data_type', $dataType)
            ->where('is_enabled', true)
            ->get();
    }

    /**
     * Get critical commands that need monitoring
     */
    public function getCriticalCommands(): Collection
    {
        return DataDestructionCommand::critical()->enabled()->get();
    }

    /**
     * Run a data destruction command (with options)
     * 
     * Options:
     * - sync: Run synchronously instead of queueing
     * - dry-run: Show what would happen without making changes
     * - force: Skip confirmation prompts
     */
    public function runCommand(string $commandName, array $options = []): array
    {
        $command = DataDestructionCommand::where('command_name', $commandName)->firstOrFail();

        if (!$command->is_enabled) {
            throw new \Exception("Command '{$commandName}' is disabled");
        }

        try {
            Log::info('Running data destruction command', [
                'command' => $commandName,
                'options' => $options,
                'user' => auth()?->id(),
            ]);

            $exitCode = Artisan::call($commandName, $options);

            $output = Artisan::output();

            // Update command record
            if (!($options['--dry-run'] ?? false)) {
                $this->extractAndUpdateStats($command, $output);
            }

            return [
                'success' => $exitCode === 0,
                'command' => $commandName,
                'message' => $output,
                'exit_code' => $exitCode,
            ];

        } catch (\Exception $e) {
            Log::error('Data destruction command failed', [
                'command' => $commandName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($command->is_critical) {
                // Alert notification could be sent here
                Log::critical('CRITICAL: Data destruction command failed', [
                    'command_name' => $commandName,
                    'data_type' => $command->data_type,
                ]);
            }

            return [
                'success' => false,
                'command' => $commandName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Estimate what would be deleted (dry-run)
     */
    public function estimateImpact(string $commandName): array
    {
        $command = DataDestructionCommand::where('command_name', $commandName)->firstOrFail();

        if (!$command->supports_dry_run) {
            throw new \Exception("Command '{$commandName}' does not support dry-run");
        }

        try {
            $exitCode = Artisan::call($commandName, [
                '--dry-run' => true,
                '--sync' => true,
            ]);
            
            return [
                'command' => $commandName,
                'dry_run_output' => Artisan::output(),
                'success' => $exitCode === 0,
            ];
        } catch (\Exception $e) {
            return [
                'command' => $commandName,
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }

    /**
     * Get retention setting for a data type
     */
    public function getRetentionDays(string $dataType): int
    {
        $setting = UserDataRetentionSetting::where('data_type', $dataType)->first();
        return $setting?->retention_days ?? 90; // Default 90 days
    }

    /**
     * Get retention setting object
     */
    public function getRetentionSetting(string $dataType): ?UserDataRetentionSetting
    {
        return UserDataRetentionSetting::where('data_type', $dataType)->first();
    }

    /**
     * Update retention policy
     */
    public function updateRetention(string $dataType, int $days): void
    {
        if ($days < 1) {
            throw new \Exception('Retention days must be at least 1');
        }

        UserDataRetentionSetting::where('data_type', $dataType)
            ->update(['retention_days' => $days]);

        Log::info('Retention policy updated', [
            'data_type' => $dataType,
            'retention_days' => $days,
            'user' => auth()?->id(),
        ]);
    }

    /**
     * Enable/disable a command
     */
    public function toggleCommand(string $commandName, bool $enabled): void
    {
        DataDestructionCommand::where('command_name', $commandName)
            ->update(['is_enabled' => $enabled]);

        Log::info('Data destruction command toggled', [
            'command' => $commandName,
            'enabled' => $enabled,
            'user' => auth()?->id(),
        ]);
    }

    /**
     * Enable a command
     */
    public function enableCommand(string $commandName): void
    {
        $this->toggleCommand($commandName, true);
    }

    /**
     * Disable a command
     */
    public function disableCommand(string $commandName): void
    {
        $this->toggleCommand($commandName, false);
    }

    /**
     * Get all commands metrics
     */
    public function getMetrics(): array
    {
        return DataDestructionCommand::getMetrics();
    }

    /**
     * Get command details
     */
    public function getCommand(string $commandName): ?DataDestructionCommand
    {
        return DataDestructionCommand::where('command_name', $commandName)->first();
    }

    /**
     * Extract statistics from command output and update record
     */
    private function extractAndUpdateStats(DataDestructionCommand $command, string $output): void
    {
        // Try to extract record count from output (simple regex)
        // This is a fallback - ideally the command returns structured data
        $count = 0;

        if (preg_match('/deleted (\d+) records?/i', $output, $matches)) {
            $count = (int) $matches[1];
        } elseif (preg_match('/(\d+) record[s]? removed/i', $output, $matches)) {
            $count = (int) $matches[1];
        }

        $command->markAsRun($count);
    }

    /**
     * Get all retention settings
     */
    public function getAllRetentionSettings(): Collection
    {
        return UserDataRetentionSetting::enabled()->get();
    }

    /**
     * Check if a data type is being cleaned up automatically
     */
    public function hasCleanupCommand(string $dataType): bool
    {
        return DataDestructionCommand::where('data_type', $dataType)
            ->where('is_enabled', true)
            ->exists();
    }
}
