<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoNcm extends Model
{
    use HasFactory;

    protected $table = 'tb33_grupos_ncm';

    protected $primaryKey = 'tb33_id';

    protected $fillable = [
        'tb33_codigo',
        'tb33_nome',
        'tb33_descricao',
        'tb33_ncm',
        'tb33_cest',
        'tb33_cclass_trib',
        'tb33_ativo',
        'tb33_observacao_fiscal',
    ];

    protected $casts = [
        'tb33_ativo' => 'boolean',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'tb33_grupo_ncm_id', 'tb33_id');
    }
}
