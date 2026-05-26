<?php

namespace Tests\Unit;

use App\Models\CategoriaFiscal;
use App\Models\GrupoNcm;
use App\Models\Produto;
use App\Models\ProdutoExcecaoFiscal;
use App\Support\FiscalProductTaxService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FiscalProductTaxServiceTest extends TestCase
{
    public function test_resolves_tax_data_from_product_category(): void
    {
        $product = new Produto([
            'tb1_id' => 10,
            'tb30_categoria_fiscal_id' => 5,
            'tb33_grupo_ncm_id' => 7,
            'tb1_ncm_proprio' => null,
            'tb1_usa_excecao_fiscal' => false,
        ]);
        $product->setRelation('categoriaFiscal', $this->category());
        $product->setRelation('grupoNcm', $this->grupoNcm());

        $taxData = (new FiscalProductTaxService())->resolve($product);

        $this->assertSame('categoria_fiscal', $taxData['source']);
        $this->assertSame('21011200', $taxData['ncm']);
        $this->assertSame('BEBIDAS CAFE', $taxData['grupo_ncm_nome']);
        $this->assertSame('5102', $taxData['cfop']);
        $this->assertSame('102', $taxData['csosn']);
        $this->assertSame('000001', $taxData['cclasstrib']);
        $this->assertSame([], (new FiscalProductTaxService())->missingRequiredFields($taxData));
    }

    public function test_active_product_exception_overrides_category_values(): void
    {
        $product = new Produto([
            'tb1_id' => 10,
            'tb30_categoria_fiscal_id' => 5,
            'tb33_grupo_ncm_id' => 7,
            'tb1_usa_excecao_fiscal' => true,
        ]);
        $product->setRelation('categoriaFiscal', $this->category());
        $product->setRelation('grupoNcm', $this->grupoNcm());
        $product->setRelation('excecoesFiscais', new Collection([
            new ProdutoExcecaoFiscal([
                'tb31_id' => 1,
                'tb31_ativo' => true,
                'tb31_ncm' => '22021000',
                'tb31_cfop_venda_interna' => '5405',
                'tb31_csosn' => '400',
            ]),
        ]));

        $taxData = (new FiscalProductTaxService())->resolve($product);

        $this->assertSame('excecao_produto', $taxData['source']);
        $this->assertSame('22021000', $taxData['ncm']);
        $this->assertSame('5405', $taxData['cfop']);
        $this->assertSame('400', $taxData['csosn']);
        $this->assertSame('000001', $taxData['cclasstrib']);
    }

    public function test_product_with_loaded_null_category_is_marked_as_without_category(): void
    {
        $product = new Produto([
            'tb1_id' => 10,
            'tb30_categoria_fiscal_id' => null,
            'tb33_grupo_ncm_id' => null,
            'tb1_usa_excecao_fiscal' => false,
        ]);
        $product->setRelation('categoriaFiscal', null);
        $product->setRelation('grupoNcm', null);

        $taxData = (new FiscalProductTaxService())->resolve($product);

        $this->assertSame('sem_categoria', $taxData['source']);
        $this->assertFalse($taxData['category_active']);
        $this->assertFalse($taxData['category_current']);
    }

    public function test_resolves_loaded_category_and_group_even_when_foreign_keys_are_not_selected(): void
    {
        $product = new Produto([
            'tb1_id' => 10,
            'tb1_usa_excecao_fiscal' => false,
        ]);
        $product->setRelation('categoriaFiscal', $this->category());
        $product->setRelation('grupoNcm', $this->grupoNcm());

        $taxData = (new FiscalProductTaxService())->resolve($product);

        $this->assertSame('categoria_fiscal', $taxData['source']);
        $this->assertSame(5, $taxData['categoria_fiscal_id']);
        $this->assertSame(7, $taxData['grupo_ncm_id']);
        $this->assertSame('21011200', $taxData['ncm']);
        $this->assertSame([], (new FiscalProductTaxService())->missingRequiredFields($taxData));
    }

    private function category(): CategoriaFiscal
    {
        $category = new CategoriaFiscal();
        $category->setRawAttributes([
            'tb30_id' => 5,
            'tb30_nome' => 'PANIFICACAO PROPRIA',
            'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_FABRICACAO_PROPRIA,
            'tb30_ativo' => true,
            'tb30_ncm_padrao' => '19059090',
            'tb30_cest' => '1704901',
            'tb30_cfop_venda_interna' => '5102',
            'tb30_csosn' => '102',
            'tb30_cst_icms' => '102',
            'tb30_cst_ibs' => '000',
            'tb30_cst_cbs' => '000',
            'tb30_cclass_trib' => '000001',
            'tb30_aliquota_ibs_uf' => 0.1,
            'tb30_aliquota_ibs_municipio' => 0,
            'tb30_aliquota_cbs' => 0.9,
        ], true);

        return $category;
    }

    private function grupoNcm(): GrupoNcm
    {
        $grupoNcm = new GrupoNcm();
        $grupoNcm->setRawAttributes([
            'tb33_id' => 7,
            'tb33_nome' => 'BEBIDAS CAFE',
            'tb33_ncm' => '21011200',
            'tb33_cest' => null,
            'tb33_cclass_trib' => '000001',
            'tb33_ativo' => true,
        ], true);

        return $grupoNcm;
    }
}
