<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaFiscal extends Model
{
    use HasFactory;

    public const ORIGEM_FABRICACAO_PROPRIA = 'FABRICACAO_PROPRIA';
    public const ORIGEM_REVENDA = 'REVENDA';
    public const ORIGEM_PREPARO_MONTAGEM = 'PREPARO_MONTAGEM';

    public const ORIGENS = [
        self::ORIGEM_FABRICACAO_PROPRIA,
        self::ORIGEM_REVENDA,
        self::ORIGEM_PREPARO_MONTAGEM,
    ];

    protected $table = 'tb30_categorias_fiscais';

    protected $primaryKey = 'tb30_id';

    protected $fillable = [
        'tb30_codigo',
        'tb30_nome',
        'tb30_descricao',
        'tb30_origem_mercadoria',
        'tb30_ativo',
        'tb30_data_inicio_vigencia',
        'tb30_data_fim_vigencia',
        'tb30_ncm_padrao',
        'tb30_cest',
        'tb30_cclass_trib',
        'tb30_cst_ibs',
        'tb30_cst_cbs',
        'tb30_aliquota_ibs_uf',
        'tb30_aliquota_ibs_municipio',
        'tb30_aliquota_cbs',
        'tb30_aliquota_is',
        'tb30_cfop_venda_interna',
        'tb30_cfop_venda_interestadual',
        'tb30_cfop_consumo_local',
        'tb30_cfop_entrega',
        'tb30_csosn',
        'tb30_cst_icms',
        'tb30_cst_pis',
        'tb30_cst_cofins',
        'tb30_aliquota_icms',
        'tb30_aliquota_pis',
        'tb30_aliquota_cofins',
        'tb30_regra_icms',
        'tb30_natureza_receita',
        'tb30_aplica_balcao',
        'tb30_aplica_delivery',
        'tb30_aplica_consumo_local',
        'tb30_permite_excecao_produto',
        'tb30_observacao_fiscal',
    ];

    protected $casts = [
        'tb30_ativo' => 'boolean',
        'tb30_data_inicio_vigencia' => 'date',
        'tb30_data_fim_vigencia' => 'date',
        'tb30_aliquota_ibs_uf' => 'float',
        'tb30_aliquota_ibs_municipio' => 'float',
        'tb30_aliquota_cbs' => 'float',
        'tb30_aliquota_is' => 'float',
        'tb30_aliquota_icms' => 'float',
        'tb30_aliquota_pis' => 'float',
        'tb30_aliquota_cofins' => 'float',
        'tb30_aplica_balcao' => 'boolean',
        'tb30_aplica_delivery' => 'boolean',
        'tb30_aplica_consumo_local' => 'boolean',
        'tb30_permite_excecao_produto' => 'boolean',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'tb30_categoria_fiscal_id', 'tb30_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(CategoriaFiscalHistorico::class, 'tb30_categoria_fiscal_id', 'tb30_id');
    }
}
