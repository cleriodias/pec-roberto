<?php

namespace Database\Seeders;

use App\Models\CategoriaFiscal;
use Illuminate\Database\Seeder;

class CategoriaFiscalSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            CategoriaFiscal::updateOrCreate(
                ['tb30_codigo' => $category['tb30_codigo']],
                array_merge($category, [
                    'tb30_ativo' => false,
                    'tb30_aplica_balcao' => true,
                    'tb30_aplica_delivery' => true,
                    'tb30_aplica_consumo_local' => true,
                    'tb30_permite_excecao_produto' => true,
                ])
            );
        }
    }

    private function categories(): array
    {
        return [
            [
                'tb30_codigo' => 'PANIFICACAO_PROPRIA',
                'tb30_nome' => 'PANIFICACAO PROPRIA',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_FABRICACAO_PROPRIA,
                'tb30_descricao' => 'Produtos de fabricacao propria da padaria.',
            ],
            [
                'tb30_codigo' => 'CONFEITARIA_PROPRIA',
                'tb30_nome' => 'CONFEITARIA PROPRIA',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_FABRICACAO_PROPRIA,
                'tb30_descricao' => 'Produtos de confeitaria feitos na loja.',
            ],
            [
                'tb30_codigo' => 'SALGADOS_PROPRIOS',
                'tb30_nome' => 'SALGADOS PROPRIOS',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_FABRICACAO_PROPRIA,
                'tb30_descricao' => 'Salgados de producao propria.',
            ],
            [
                'tb30_codigo' => 'BEBIDAS_PREPARADAS',
                'tb30_nome' => 'BEBIDAS PREPARADAS',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_PREPARO_MONTAGEM,
                'tb30_descricao' => 'Bebidas preparadas ou finalizadas na loja.',
            ],
            [
                'tb30_codigo' => 'LANCHES_MONTAGEM',
                'tb30_nome' => 'LANCHES E MONTAGEM',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_PREPARO_MONTAGEM,
                'tb30_descricao' => 'Lanches e itens montados no atendimento.',
            ],
            [
                'tb30_codigo' => 'REVENDA_ALIMENTICIA_IND',
                'tb30_nome' => 'REVENDA ALIMENTICIA INDUSTRIALIZADA',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_REVENDA,
                'tb30_descricao' => 'Alimentos industrializados para revenda.',
            ],
            [
                'tb30_codigo' => 'REVENDA_BEBIDAS',
                'tb30_nome' => 'REVENDA DE BEBIDAS',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_REVENDA,
                'tb30_descricao' => 'Bebidas industrializadas para revenda.',
            ],
            [
                'tb30_codigo' => 'REVENDA_REFRIG_CONGELADA',
                'tb30_nome' => 'REVENDA REFRIGERADA OU CONGELADA',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_REVENDA,
                'tb30_descricao' => 'Produtos refrigerados ou congelados para revenda.',
            ],
            [
                'tb30_codigo' => 'FRIOS_LATICINIOS_FRACIONADOS',
                'tb30_nome' => 'FRIOS E LATICINIOS FRACIONADOS',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_PREPARO_MONTAGEM,
                'tb30_descricao' => 'Frios e laticinios fracionados ou pesados na loja.',
            ],
            [
                'tb30_codigo' => 'ANALISE_FISCAL_INDIVIDUAL',
                'tb30_nome' => 'ANALISE FISCAL INDIVIDUAL',
                'tb30_origem_mercadoria' => CategoriaFiscal::ORIGEM_REVENDA,
                'tb30_descricao' => 'Categoria temporaria para produtos que exigem revisao fiscal individual.',
            ],
        ];
    }
}
