<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unit_id',
        'unit_name',
        'cash_amount',
        'card_amount',
        'closed_date',
        'closed_at',
    ];

    protected $casts = [
        'cash_amount' => 'float',
        'card_amount' => 'float',
        'closed_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
