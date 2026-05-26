<?php

namespace App\Support;

use App\Models\CategoriaFiscal;
use App\Models\GrupoNcm;
use App\Models\Produto;
use App\Models\ProdutoExcecaoFiscal;
use Carbon\Carbon;

class FiscalProductTaxService
{
    public const OPERATION_VENDA_INTERNA = 'venda_interna';
    public const OPERATION_VENDA_INTERESTADUAL = 'venda_interestadual';
    public const OPERATION_CONSUMO_LOCAL = 'consumo_local';
    public const OPERATION_ENTREGA = 'entrega';

    public function resolve(Produto $product, string $operation = self::OPERATION_VENDA_INTERNA, ?Carbon $date = null): array
    {
        $date ??= Carbon::today();
        $hasCategoryAttribute = array_key_exists('tb30_categoria_fiscal_id', $product->getAttributes());
        $hasCategoryBinding = $hasCategoryAttribute || $product->relationLoaded('categoriaFiscal');
        $category = $product->relationLoaded('categoriaFiscal')
            ? $product->categoriaFiscal
            : ($hasCategoryAttribute ? $product->categoriaFiscal()->first() : null);
        $hasGrupoNcmAttribute = array_key_exists('tb33_grupo_ncm_id', $product->getAttributes());
        $hasGrupoNcmBinding = $hasGrupoNcmAttribute || $product->relationLoaded('grupoNcm');
        $grupoNcm = $product->relationLoaded('grupoNcm')
            ? $product->grupoNcm
            : ($hasGrupoNcmAttribute ? $product->grupoNcm()->first() : null);
        $exception = $this->activeException($product, $date);
        $base = $category instanceof CategoriaFiscal
            ? $this->categoryPayload($category, $operation, $date)
            : $this->legacyProductPayload($product, $operation, $hasCategoryBinding);

        if ($grupoNcm instanceof GrupoNcm) {
            $base = array_merge($base, $this->grupoNcmPayload($grupoNcm));
        } elseif ($hasGrupoNcmBinding) {
            $base['grupo_ncm_active'] = false;
            $base['grupo_ncm_id'] = null;
            $base['grupo_ncm_nome'] = null;
        }

        if (filled($product->tb1_ncm_proprio ?? null)) {
            $base['ncm'] = $this->digits($product->tb1_ncm_proprio, 8);
        }

        if ($exception instanceof ProdutoExcecaoFiscal) {
            $base = array_merge($base, $this->exceptionPayload($exception, $operation));
            $base['source'] = 'excecao_produto';
            $base['exception_id'] = (int) $exception->tb31_id;
        }

        $base['produto_id'] = (int) $product->tb1_id;
        $base['categoria_fiscal_id'] = $category?->tb30_id ? (int) $category->tb30_id : null;
        $base['categoria_nome'] = $category?->tb30_nome;
        $base['grupo_ncm_id'] = $grupoNcm?->tb33_id ? (int) $grupoNcm->tb33_id : ($base['grupo_ncm_id'] ?? null);
        $base['grupo_ncm_nome'] = $grupoNcm?->tb33_nome ?? ($base['grupo_ncm_nome'] ?? null);
        $base['usa_excecao_fiscal'] = (bool) ($product->tb1_usa_excecao_fiscal ?? false);
        $base['operation'] = $operation;

        return $base;
    }

    public function missingRequiredFields(array $taxData): array
    {
        $missing = [];

        foreach ([
            'ncm' => 'NCM',
            'cfop' => 'CFOP',
            'unidade_comercial' => 'Unidade comercial',
            'unidade_tributavel' => 'Unidade tributavel',
        ] as $field => $label) {
            if (blank($taxData[$field] ?? null)) {
                $missing[] = $label;
            }
        }

        if (blank($taxData['csosn'] ?? null) && blank($taxData['cst'] ?? null)) {
            $missing[] = 'CSOSN/CST';
        }

        return $missing;
    }

    public function hasRtcTaxData(array $taxData): bool
    {
        return filled($taxData['cst_ibscbs'] ?? null)
            && filled($taxData['cclasstrib'] ?? null)
            && ($taxData['aliquota_ibs_uf'] ?? null) !== null
            && ($taxData['aliquota_ibs_mun'] ?? null) !== null
            && ($taxData['aliquota_cbs'] ?? null) !== null;
    }

    private function categoryPayload(CategoriaFiscal $category, string $operation, Carbon $date): array
    {
        $isVigente = $this->isCurrent($category->tb30_data_inicio_vigencia, $category->tb30_data_fim_vigencia, $date);

        return [
            'source' => 'categoria_fiscal',
            'category_active' => (bool) $category->tb30_ativo,
            'category_current' => $isVigente,
            'grupo_ncm_active' => true,
            'ncm' => $this->digits($category->tb30_ncm_padrao, 8),
            'cest' => $this->digits($category->tb30_cest, 7),
            'cfop' => $this->resolveCfop([
                self::OPERATION_VENDA_INTERNA => $category->tb30_cfop_venda_interna,
                self::OPERATION_VENDA_INTERESTADUAL => $category->tb30_cfop_venda_interestadual,
                self::OPERATION_CONSUMO_LOCAL => $category->tb30_cfop_consumo_local,
                self::OPERATION_ENTREGA => $category->tb30_cfop_entrega,
            ], $operation),
            'origem' => $this->originCode($category->tb30_origem_mercadoria),
            'csosn' => $this->digits($category->tb30_csosn, 4),
            'cst' => $this->digits($category->tb30_cst_icms, 3),
            'cst_pis' => $this->digits($category->tb30_cst_pis, 3),
            'cst_cofins' => $this->digits($category->tb30_cst_cofins, 3),
            'aliquota_icms' => $this->decimal($category->tb30_aliquota_icms, 2, 0),
            'aliquota_pis' => $this->decimal($category->tb30_aliquota_pis, 4),
            'aliquota_cofins' => $this->decimal($category->tb30_aliquota_cofins, 4),
            'cst_ibscbs' => $this->digits($category->tb30_cst_ibs ?: $category->tb30_cst_cbs, 3),
            'cst_ibs' => $this->digits($category->tb30_cst_ibs, 3),
            'cst_cbs' => $this->digits($category->tb30_cst_cbs, 3),
            'cclasstrib' => $this->digits($category->tb30_cclass_trib, 6),
            'aliquota_ibs_uf' => $this->decimal($category->tb30_aliquota_ibs_uf, 4),
            'aliquota_ibs_mun' => $this->decimal($category->tb30_aliquota_ibs_municipio, 4),
            'aliquota_cbs' => $this->decimal($category->tb30_aliquota_cbs, 4),
            'aliquota_is' => $this->decimal($category->tb30_aliquota_is, 4),
            'observacao_fiscal' => $category->tb30_observacao_fiscal,
            'unidade_comercial' => 'UN',
            'unidade_tributavel' => 'UN',
            'ind_doacao' => false,
        ];
    }

    private function grupoNcmPayload(GrupoNcm $grupoNcm): array
    {
        return [
            'grupo_ncm_active' => (bool) $grupoNcm->tb33_ativo,
            'grupo_ncm_id' => (int) $grupoNcm->tb33_id,
            'grupo_ncm_nome' => $grupoNcm->tb33_nome,
            'ncm' => $this->digits($grupoNcm->tb33_ncm, 8),
            'cest' => $this->digits($grupoNcm->tb33_cest, 7),
            'cclasstrib' => $this->digits($grupoNcm->tb33_cclass_trib, 6),
            'observacao_ncm' => $grupoNcm->tb33_observacao_fiscal,
        ];
    }

    private function legacyProductPayload(Produto $product, string $operation, bool $hasCategoryAttribute): array
    {
        return [
            'source' => $hasCategoryAttribute ? 'sem_categoria' : 'produto_legado',
            'category_active' => ! $hasCategoryAttribute,
            'category_current' => ! $hasCategoryAttribute,
            'grupo_ncm_active' => ! $hasCategoryAttribute,
            'ncm' => $this->digits($product->tb1_ncm, 8),
            'cest' => $this->digits($product->tb1_cest, 7),
            'cfop' => $this->digits($product->tb1_cfop, 4),
            'origem' => $product->tb1_origem ?? 0,
            'csosn' => $this->digits($product->tb1_csosn, 4),
            'cst' => $this->digits($product->tb1_cst, 3),
            'cst_pis' => null,
            'cst_cofins' => null,
            'aliquota_icms' => $this->decimal($product->tb1_aliquota_icms, 2, 0),
            'aliquota_pis' => null,
            'aliquota_cofins' => null,
            'cst_ibscbs' => $this->digits($product->tb1_cst_ibscbs, 3),
            'cst_ibs' => $this->digits($product->tb1_cst_ibscbs, 3),
            'cst_cbs' => $this->digits($product->tb1_cst_ibscbs, 3),
            'cclasstrib' => $this->digits($product->tb1_cclasstrib, 6),
            'aliquota_ibs_uf' => $this->decimal($product->tb1_aliquota_ibs_uf, 4),
            'aliquota_ibs_mun' => $this->decimal($product->tb1_aliquota_ibs_mun, 4),
            'aliquota_cbs' => $this->decimal($product->tb1_aliquota_cbs, 4),
            'aliquota_is' => $this->decimal($product->tb1_aliquota_is, 4),
            'observacao_fiscal' => $product->tb1_cff_nt,
            'unidade_comercial' => $product->tb1_unidade_comercial ?: 'UN',
            'unidade_tributavel' => $product->tb1_unidade_tributavel ?: 'UN',
            'ind_doacao' => (bool) ($product->tb1_ind_doacao ?? false),
        ];
    }

    private function exceptionPayload(ProdutoExcecaoFiscal $exception, string $operation): array
    {
        return array_filter([
            'ncm' => $this->digits($exception->tb31_ncm, 8),
            'cest' => $this->digits($exception->tb31_cest, 7),
            'cfop' => $this->resolveCfop([
                self::OPERATION_VENDA_INTERNA => $exception->tb31_cfop_venda_interna,
                self::OPERATION_VENDA_INTERESTADUAL => $exception->tb31_cfop_venda_interestadual,
                self::OPERATION_CONSUMO_LOCAL => $exception->tb31_cfop_consumo_local,
                self::OPERATION_ENTREGA => $exception->tb31_cfop_entrega,
            ], $operation),
            'csosn' => $this->digits($exception->tb31_csosn, 4),
            'cst' => $this->digits($exception->tb31_cst_icms, 3),
            'cst_pis' => $this->digits($exception->tb31_cst_pis, 3),
            'cst_cofins' => $this->digits($exception->tb31_cst_cofins, 3),
            'aliquota_icms' => $this->decimal($exception->tb31_aliquota_icms, 2),
            'aliquota_pis' => $this->decimal($exception->tb31_aliquota_pis, 4),
            'aliquota_cofins' => $this->decimal($exception->tb31_aliquota_cofins, 4),
            'cst_ibscbs' => $this->digits($exception->tb31_cst_ibs ?: $exception->tb31_cst_cbs, 3),
            'cst_ibs' => $this->digits($exception->tb31_cst_ibs, 3),
            'cst_cbs' => $this->digits($exception->tb31_cst_cbs, 3),
            'cclasstrib' => $this->digits($exception->tb31_cclass_trib, 6),
            'aliquota_ibs_uf' => $this->decimal($exception->tb31_aliquota_ibs_uf, 4),
            'aliquota_ibs_mun' => $this->decimal($exception->tb31_aliquota_ibs_municipio, 4),
            'aliquota_cbs' => $this->decimal($exception->tb31_aliquota_cbs, 4),
            'aliquota_is' => $this->decimal($exception->tb31_aliquota_is, 4),
            'observacao_fiscal' => $exception->tb31_observacao_fiscal,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function activeException(Produto $product, Carbon $date): ?ProdutoExcecaoFiscal
    {
        if (! (bool) ($product->tb1_usa_excecao_fiscal ?? false)) {
            return null;
        }

        $exceptions = $product->relationLoaded('excecoesFiscais')
            ? $product->excecoesFiscais
            : $product->excecoesFiscais()->get();

        return $exceptions
            ->filter(fn (ProdutoExcecaoFiscal $exception) => $exception->tb31_ativo && $this->isCurrent(
                $exception->tb31_data_inicio_vigencia,
                $exception->tb31_data_fim_vigencia,
                $date
            ))
            ->sortByDesc('tb31_id')
            ->first();
    }

    private function resolveCfop(array $values, string $operation): ?string
    {
        return $this->digits($values[$operation] ?? $values[self::OPERATION_VENDA_INTERNA] ?? null, 4);
    }

    private function isCurrent(mixed $start, mixed $end, Carbon $date): bool
    {
        $startDate = $start ? Carbon::parse($start)->startOfDay() : null;
        $endDate = $end ? Carbon::parse($end)->endOfDay() : null;

        return ($startDate === null || $startDate->lte($date))
            && ($endDate === null || $endDate->gte($date));
    }

    private function originCode(?string $origin): int
    {
        return match ($origin) {
            CategoriaFiscal::ORIGEM_REVENDA => 0,
            CategoriaFiscal::ORIGEM_PREPARO_MONTAGEM => 0,
            default => 0,
        };
    }

    private function digits(mixed $value, int $size): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return mb_substr($digits, 0, $size);
    }

    private function decimal(mixed $value, int $scale, ?float $default = null): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return round((float) $value, $scale);
    }
}
