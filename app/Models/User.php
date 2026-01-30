<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'funcao',
        'funcao_original',
        'hr_ini',
        'hr_fim',
        'salario',
        'vr_cred',
        'tb2_id',
        'cod_acesso',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'funcao' => 'integer',
            'funcao_original' => 'integer',
            'hr_ini' => 'string',
            'hr_fim' => 'string',
            'salario' => 'float',
            'vr_cred' => 'float',
            'tb2_id' => 'integer',
            'cod_acesso' => 'string',
        ];
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'tb2_unidade_user', 'user_id', 'tb2_id')->withTimestamps();
    }

    public function primaryUnit(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'tb2_id', 'tb2_id');
    }
}
