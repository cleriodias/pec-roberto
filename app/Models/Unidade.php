<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Unidade extends Model
{
    use HasFactory;

    protected $table = 'tb2_unidades';

    protected $primaryKey = 'tb2_id';

    protected $fillable = [
        'tb2_nome',
        'tb2_endereco',
        'tb2_cep',
        'tb2_fone',
        'tb2_cnpj',
        'tb2_localizacao',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tb2_unidade_user', 'tb2_id', 'user_id')->withTimestamps();
    }
}
