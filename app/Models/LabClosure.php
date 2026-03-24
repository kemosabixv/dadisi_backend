<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_space_id',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function labSpace(): BelongsTo
    {
        return $this->belongsTo(LabSpace::class);
    }
}
