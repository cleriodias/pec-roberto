<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'tb1_produto';

    protected $primaryKey = 'tb1_id';

    protected $fillable = [
        'tb1_id',
        'tb1_nome',
        'tb1_vlr_custo',
        'tb1_vlr_venda',
        'tb1_codbar',
        'tb1_ncm',
        'tb1_cest',
        'tb1_cfop',
        'tb1_unidade_comercial',
        'tb1_unidade_tributavel',
        'tb1_origem',
        'tb1_csosn',
        'tb1_cst',
        'tb1_aliquota_icms',
        'tb1_cst_ibscbs',
        'tb1_cclasstrib',
        'tb1_cff_nt',
        'tb1_ind_doacao',
        'tb1_aliquota_ibs_uf',
        'tb1_aliquota_ibs_mun',
        'tb1_aliquota_cbs',
        'tb1_aliquota_is',
        'tb1_tipo',
        'tb30_categoria_fiscal_id',
        'tb33_grupo_ncm_id',
        'tb1_ncm_proprio',
        'tb1_usa_excecao_fiscal',
        'tb1_responsavel_ultima_alteracao',
        'tb1_qtd',
        'tb1_status',
        'tb1_favorito',
        'tb1_vr_credit',
    ];

    protected $casts = [
        'tb1_vlr_custo' => 'float',
        'tb1_vlr_venda' => 'float',
        'tb1_origem' => 'integer',
        'tb1_aliquota_icms' => 'float',
        'tb1_ind_doacao' => 'boolean',
        'tb1_aliquota_ibs_uf' => 'float',
        'tb1_aliquota_ibs_mun' => 'float',
        'tb1_aliquota_cbs' => 'float',
        'tb1_aliquota_is' => 'float',
        'tb1_tipo' => 'integer',
        'tb30_categoria_fiscal_id' => 'integer',
        'tb33_grupo_ncm_id' => 'integer',
        'tb1_usa_excecao_fiscal' => 'boolean',
        'tb1_responsavel_ultima_alteracao' => 'integer',
        'tb1_qtd' => 'integer',
        'tb1_status' => 'integer',
        'tb1_favorito' => 'boolean',
        'tb1_vr_credit' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class, 'product_id', 'tb1_id');
    }

    public function categoriaFiscal(): BelongsTo
    {
        return $this->belongsTo(CategoriaFiscal::class, 'tb30_categoria_fiscal_id', 'tb30_id');
    }

    public function grupoNcm(): BelongsTo
    {
        return $this->belongsTo(GrupoNcm::class, 'tb33_grupo_ncm_id', 'tb33_id');
    }

    public function excecoesFiscais(): HasMany
    {
        return $this->hasMany(ProdutoExcecaoFiscal::class, 'tb1_id', 'tb1_id');
    }

    public function excecaoFiscalAtual(): HasOne
    {
        return $this->hasOne(ProdutoExcecaoFiscal::class, 'tb1_id', 'tb1_id')
            ->where('tb31_ativo', true)
            ->latest('tb31_id');
    }

    public function historicosFiscais(): HasMany
    {
        return $this->hasMany(CategoriaFiscalHistorico::class, 'tb1_id', 'tb1_id');
    }
}
