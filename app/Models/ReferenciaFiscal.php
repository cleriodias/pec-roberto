<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenciaFiscal extends Model
{
    use HasFactory;

    protected $table = 'tb29_referencias_fiscais';

    protected $primaryKey = 'tb29_id';

    protected $fillable = [
        'tb29_descricao',
        'tb29_ncm',
        'tb29_cfop',
        'tb29_csosn',
        'tb29_cst',
        'tb29_cst_ibscbs',
        'tb29_cclasstrib',
        'tb29_aliquota_ibs_uf',
        'tb29_aliquota_ibs_mun',
        'tb29_aliquota_cbs',
        'tb29_aliquota_is',
    ];

    protected $casts = [
        'tb29_aliquota_ibs_uf' => 'float',
        'tb29_aliquota_ibs_mun' => 'float',
        'tb29_aliquota_cbs' => 'float',
        'tb29_aliquota_is' => 'float',
    ];
}
