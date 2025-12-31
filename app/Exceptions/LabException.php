<?php

namespace App\Exceptions;

/**
 * LabException
 *
 * Exception for lab-related operations
 */
class LabException extends \Exception
{
    public static function creationFailed(string $message = 'Lab creation failed'): static
    {
        return new static($message);
    }

    public static function updateFailed(string $message = 'Lab update failed'): static
    {
        return new static($message);
    }

    public static function deletionFailed(string $message = 'Lab deletion failed'): static
    {
        return new static($message);
    }

    public static function equipmentAdditionFailed(string $message = 'Equipment addition failed'): static
    {
        return new static($message);
    }

    public static function equipmentUpdateFailed(string $message = 'Equipment update failed'): static
    {
        return new static($message);
    }

    public static function equipmentRemovalFailed(string $message = 'Equipment removal failed'): static
    {
        return new static($message);
    }
}
