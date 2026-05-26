<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoExcecaoFiscal extends Model
{
    use HasFactory;

    protected $table = 'tb31_produto_excecoes_fiscais';

    protected $primaryKey = 'tb31_id';

    protected $fillable = [
        'tb1_id',
        'tb31_ativo',
        'tb31_motivo_excecao',
        'tb31_data_inicio_vigencia',
        'tb31_data_fim_vigencia',
        'tb31_ncm',
        'tb31_cest',
        'tb31_cclass_trib',
        'tb31_cst_ibs',
        'tb31_cst_cbs',
        'tb31_aliquota_ibs_uf',
        'tb31_aliquota_ibs_municipio',
        'tb31_aliquota_cbs',
        'tb31_aliquota_is',
        'tb31_cfop_venda_interna',
        'tb31_cfop_venda_interestadual',
        'tb31_cfop_consumo_local',
        'tb31_cfop_entrega',
        'tb31_csosn',
        'tb31_cst_icms',
        'tb31_cst_pis',
        'tb31_cst_cofins',
        'tb31_aliquota_icms',
        'tb31_aliquota_pis',
        'tb31_aliquota_cofins',
        'tb31_observacao_fiscal',
    ];

    protected $casts = [
        'tb31_ativo' => 'boolean',
        'tb31_data_inicio_vigencia' => 'date',
        'tb31_data_fim_vigencia' => 'date',
        'tb31_aliquota_ibs_uf' => 'float',
        'tb31_aliquota_ibs_municipio' => 'float',
        'tb31_aliquota_cbs' => 'float',
        'tb31_aliquota_is' => 'float',
        'tb31_aliquota_icms' => 'float',
        'tb31_aliquota_pis' => 'float',
        'tb31_aliquota_cofins' => 'float',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'tb1_id', 'tb1_id');
    }
}
