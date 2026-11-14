<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mechanic extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'email', 'user_id', 'gender_id', 'mechanic_type_id', 'phone', 'commission_rate', 'is_active'];

    protected $casts = [
        'commission_rate' => 'decimal:2',
    ];

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function mechanicType(): BelongsTo
    {
        return $this->belongsTo(MechanicType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
