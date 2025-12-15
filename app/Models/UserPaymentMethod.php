<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_payment_methods';

    protected $fillable = [
        'user_id',
        'type',
        'identifier',
        'data',
        'is_primary',
        'is_active',
        'label',
    ];

    protected $casts = [
        'data' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
