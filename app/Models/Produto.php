<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'tb1_produto';

    protected $primaryKey = 'tb1_id';

    protected $fillable = [
        'tb1_nome',
        'tb1_vlr_custo',
        'tb1_vlr_venda',
        'tb1_codbar',
        'tb1_tipo',
        'tb1_status',
        'tb1_favorito',
        'tb1_vr_credit',
    ];

    protected $casts = [
        'tb1_vlr_custo' => 'float',
        'tb1_vlr_venda' => 'float',
        'tb1_tipo' => 'integer',
        'tb1_status' => 'integer',
        'tb1_favorito' => 'boolean',
        'tb1_vr_credit' => 'boolean',
    ];
}

