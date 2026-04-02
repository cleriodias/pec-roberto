<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $table = 'tb18_chamados';

    protected $fillable = [
        'user_id',
        'unit_id',
        'title',
        'description',
        'video_path',
        'video_original_name',
        'video_mime_type',
        'video_size',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'unit_id' => 'integer',
        'video_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unit_id', 'tb2_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SupportTicketInteraction::class);
    }
}
