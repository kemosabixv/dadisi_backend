<?php

namespace App\Services\Contracts;

use App\DTOs\CreateLabSpaceDTO;
use App\DTOs\UpdateLabSpaceDTO;
use App\DTOs\CreateLabBookingDTO;
use App\Models\LabSpace;
use App\Models\LabBooking;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

interface LabManagementServiceContract
{
    public function createLabSpace(Authenticatable $creator, CreateLabSpaceDTO $dto): LabSpace;
    public function updateLabSpace(Authenticatable $actor, LabSpace $lab, UpdateLabSpaceDTO $dto): LabSpace;
    public function deleteLabSpace(Authenticatable $actor, LabSpace $lab): bool;
    public function listLabSpaces(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getLabSpacesByCounty(string $county): \Illuminate\Database\Eloquent\Collection;
}
