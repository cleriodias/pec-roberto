<?php

namespace Database\Seeders;

use App\Models\GrupoNcm;
use Illuminate\Database\Seeder;

class GrupoNcmSeeder extends Seeder
{
    private const DEFAULT_OBSERVATION = 'Grupo NCM ativado com NCM aproximado para operacao inicial. Validar NCM, CEST e cClassTrib com contador, XML do fornecedor e tabelas oficiais vigentes antes do uso definitivo.';

    public function run(): void
    {
        foreach ($this->groups() as $group) {
            GrupoNcm::updateOrCreate(
                ['tb33_codigo' => $group['tb33_codigo']],
                array_merge([
                    'tb33_ativo' => true,
                    'tb33_cest' => null,
                    'tb33_cclass_trib' => '000001',
                    'tb33_observacao_fiscal' => self::DEFAULT_OBSERVATION,
                ], $group)
            );
        }
    }

    private function groups(): array
    {
        return [
            [
                'tb33_codigo' => 'FAB_PAO_COMUM',
                'tb33_nome' => 'PAES COMUNS FABRICACAO PROPRIA',
                'tb33_descricao' => 'Pao frances, pao de sal, pao careca e paes comuns de fabricacao propria.',
                'tb33_ncm' => '19059090',
            ],
            [
                'tb33_codigo' => 'FAB_PAO_FORMA',
                'tb33_nome' => 'PAO DE FORMA FABRICACAO PROPRIA',
                'tb33_descricao' => 'Pao de forma tradicional, integral ou fatiado produzido na padaria.',
                'tb33_ncm' => '19059010',
            ],
            [
                'tb33_codigo' => 'FAB_PAO_QUEIJO',
                'tb33_nome' => 'PAO DE QUEIJO FABRICACAO PROPRIA',
                'tb33_descricao' => 'Pao de queijo assado, mini pao de queijo ou pao de queijo congelado produzido internamente.',
                'tb33_ncm' => '19059090',
            ],
            [
                'tb33_codigo' => 'FAB_BOLOS_SIMPLES',
                'tb33_nome' => 'BOLOS SIMPLES FABRICACAO PROPRIA',
                'tb33_descricao' => 'Bolo de milho, bolo de cenoura sem cobertura, bolo comum e bolo caseiro.',
                'tb33_ncm' => '19059090',
            ],
            [
                'tb33_codigo' => 'FAB_CONFEITARIA',
                'tb33_nome' => 'CONFEITARIA PROPRIA',
                'tb33_descricao' => 'Tortas, doces de vitrine, sonhos, roscas doces, cucas e bolos recheados.',
                'tb33_ncm' => '19059090',
            ],
            [
                'tb33_codigo' => 'FAB_SALGADOS_ASSADOS',
                'tb33_nome' => 'SALGADOS ASSADOS PROPRIOS',
                'tb33_descricao' => 'Esfirra, enroladinho, empada, quiche, pizza assada ou pre-assada.',
                'tb33_ncm' => '19059090',
            ],
            [
                'tb33_codigo' => 'FAB_SALGADOS_FRITOS',
                'tb33_nome' => 'SALGADOS FRITOS PROPRIOS',
                'tb33_descricao' => 'Coxinha, risole, bolinha de queijo, quibe e outros salgados fritos.',
                'tb33_ncm' => '19059090',
                'tb33_observacao_fiscal' => $this->producaoObservation(),
            ],
            [
                'tb33_codigo' => 'PREP_LANCHES_MONTAGEM',
                'tb33_nome' => 'LANCHES E MONTAGEM',
                'tb33_descricao' => 'Misto quente, sanduiche, hamburguer montado, tapioca recheada e itens montados no atendimento.',
                'tb33_ncm' => '19059090',
                'tb33_observacao_fiscal' => $this->producaoObservation(),
            ],
            [
                'tb33_codigo' => 'PREP_BEBIDAS_QUENTES',
                'tb33_nome' => 'BEBIDAS QUENTES PREPARADAS',
                'tb33_descricao' => 'Cafe coado, cappuccino, chocolate quente, cafe com leite e pingado.',
                'tb33_ncm' => '22029900',
                'tb33_observacao_fiscal' => $this->preparoObservation(),
            ],
            [
                'tb33_codigo' => 'PREP_SUCOS_VITAMINAS',
                'tb33_nome' => 'SUCOS E VITAMINAS PREPARADOS',
                'tb33_descricao' => 'Suco natural, vitamina, laranja espremida e bebidas preparadas na hora.',
                'tb33_ncm' => '20098990',
                'tb33_observacao_fiscal' => $this->preparoObservation(),
            ],
            [
                'tb33_codigo' => 'REV_BISCOITOS_BOLACHAS',
                'tb33_nome' => 'BISCOITOS E BOLACHAS REVENDA',
                'tb33_descricao' => 'Biscoito industrializado, bolacha recheada, cream cracker e similares para revenda.',
                'tb33_ncm' => '19053100',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_BEBIDAS_INDUSTRIALIZ',
                'tb33_nome' => 'BEBIDAS INDUSTRIALIZADAS REVENDA',
                'tb33_descricao' => 'Refrigerante, agua, suco pronto, energetico e bebidas compradas prontas para revenda.',
                'tb33_ncm' => '22029900',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_LATICINIOS_FRIOS',
                'tb33_nome' => 'FRIOS E LATICINIOS REVENDA',
                'tb33_descricao' => 'Queijo, presunto, mussarela, requeijao, leite e similares para revenda.',
                'tb33_ncm' => '04069090',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_CONGELADOS',
                'tb33_nome' => 'CONGELADOS REVENDA',
                'tb33_descricao' => 'Pao de queijo congelado comprado, salgados congelados, massas prontas e similares.',
                'tb33_ncm' => '19059090',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'ANALISE_INDIVIDUAL',
                'tb33_nome' => 'ANALISE FISCAL INDIVIDUAL',
                'tb33_descricao' => 'Produtos com composicao mista, kits, cestas, novidades ou itens que exigem revisao individual.',
                'tb33_ncm' => '21069090',
                'tb33_observacao_fiscal' => 'Grupo ativado com NCM generico aproximado apenas para triagem cadastral. Revisar individualmente com contador antes de emitir nota.',
            ],
            [
                'tb33_codigo' => 'REV_ARROZ',
                'tb33_nome' => 'ARROZ REVENDA',
                'tb33_descricao' => 'Arroz branco, parboilizado, integral e outros tipos. Usar NCM do XML do fornecedor.',
                'tb33_ncm' => '10063021',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_FEIJAO',
                'tb33_nome' => 'FEIJAO REVENDA',
                'tb33_descricao' => 'Feijao carioca, preto, fradinho e outras especies. Usar NCM do XML do fornecedor.',
                'tb33_ncm' => '07133399',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_ACUCAR',
                'tb33_nome' => 'ACUCAR REVENDA',
                'tb33_descricao' => 'Acucar cristal ou refinado comum. Validar apresentacoes como demerara, mascavo, organico e similares.',
                'tb33_ncm' => '17019900',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_CAFE',
                'tb33_nome' => 'CAFE TORRADO REVENDA',
                'tb33_descricao' => 'Cafe torrado nao descafeinado, em po ou em grao. Validar cafes soluveis, capsulas e misturas.',
                'tb33_ncm' => '09012100',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_OLEO_VEGETAL',
                'tb33_nome' => 'OLEOS VEGETAIS REVENDA',
                'tb33_descricao' => 'Oleo de soja, milho, girassol, canola e similares. Usar NCM do XML do fornecedor.',
                'tb33_ncm' => '15079011',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_FARINHA_TRIGO',
                'tb33_nome' => 'FARINHA DE TRIGO REVENDA',
                'tb33_descricao' => 'Farinha de trigo comum. Validar misturas prontas, farinha com fermento e preparacoes.',
                'tb33_ncm' => '11010010',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_FARINHAS_OUTRAS',
                'tb33_nome' => 'FARINHAS DIVERSAS REVENDA',
                'tb33_descricao' => 'Farinha de mandioca, milho, aveia, rosca, tapioca e similares. Usar NCM do XML do fornecedor.',
                'tb33_ncm' => '11062000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_MASSAS_SECAS',
                'tb33_nome' => 'MASSAS ALIMENTICIAS REVENDA',
                'tb33_descricao' => 'Macarrao, espaguete, massa com ovos, massa instantanea e similares.',
                'tb33_ncm' => '19021900',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_MOLHOS_COND',
                'tb33_nome' => 'MOLHOS E CONDIMENTOS REVENDA',
                'tb33_descricao' => 'Molho de tomate, ketchup, maionese, mostarda, temperos e condimentos.',
                'tb33_ncm' => '21039021',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_ENLATADOS',
                'tb33_nome' => 'ENLATADOS E CONSERVAS REVENDA',
                'tb33_descricao' => 'Milho, ervilha, sardinha, atum, extrato, seleta, azeitona e conservas em geral.',
                'tb33_ncm' => '20059900',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_BISCOITOS_MERCEARIA',
                'tb33_nome' => 'BISCOITOS E BOLACHAS MERCEARIA',
                'tb33_descricao' => 'Biscoito doce, salgado, recheado, wafer, cookies e similares industrializados.',
                'tb33_ncm' => '19053100',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_DOCES_BALAS',
                'tb33_nome' => 'DOCES BALAS E GOMAS REVENDA',
                'tb33_descricao' => 'Bala, pirulito, goma, doce embalado, sobremesa pronta e similares.',
                'tb33_ncm' => '17049020',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_CHOCOLATES',
                'tb33_nome' => 'CHOCOLATES REVENDA',
                'tb33_descricao' => 'Barras, bombons, achocolatado, creme de chocolate e similares.',
                'tb33_ncm' => '18069000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_LEITE',
                'tb33_nome' => 'LEITE REVENDA',
                'tb33_descricao' => 'Leite UHT integral, semidesnatado, desnatado, zero lactose e similares.',
                'tb33_ncm' => '04012010',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_LATICINIOS',
                'tb33_nome' => 'LATICINIOS REVENDA',
                'tb33_descricao' => 'Iogurte, manteiga, requeijao, creme de leite, leite condensado, queijo embalado e similares.',
                'tb33_ncm' => '04039000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_FRIOS_EMBUTIDOS',
                'tb33_nome' => 'FRIOS E EMBUTIDOS REVENDA',
                'tb33_descricao' => 'Presunto, mortadela, salame, peito de peru, salsicha, linguica e similares.',
                'tb33_ncm' => '16010000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_BEBIDAS_AGUA',
                'tb33_nome' => 'AGUA MINERAL REVENDA',
                'tb33_descricao' => 'Agua mineral ou natural sem acucar nem aromatizante. Validar agua saborizada ou gaseificada.',
                'tb33_ncm' => '22011000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_BEBIDAS_DOCES',
                'tb33_nome' => 'BEBIDAS PRONTAS REVENDA',
                'tb33_descricao' => 'Refrigerante, suco pronto, energetico, isotonico, cha pronto e similares.',
                'tb33_ncm' => '22029900',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_HIGIENE_PESSOAL',
                'tb33_nome' => 'HIGIENE PESSOAL REVENDA',
                'tb33_descricao' => 'Sabonete, papel higienico, creme dental, shampoo, absorvente e similares.',
                'tb33_ncm' => '34011190',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_LIMPEZA',
                'tb33_nome' => 'LIMPEZA DOMESTICA REVENDA',
                'tb33_descricao' => 'Detergente, desinfetante, agua sanitaria, sabao, amaciante e similares.',
                'tb33_ncm' => '34022000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_DESCARTAVEIS',
                'tb33_nome' => 'DESCARTAVEIS REVENDA',
                'tb33_descricao' => 'Copo, prato, talher, guardanapo, embalagem, sacola e descartaveis em geral.',
                'tb33_ncm' => '39241000',
                'tb33_observacao_fiscal' => $this->revendaObservation(),
            ],
            [
                'tb33_codigo' => 'REV_MERCEARIA_ANALISE',
                'tb33_nome' => 'MERCEARIA ANALISE INDIVIDUAL',
                'tb33_descricao' => 'Produto novo, kit, composicao duvidosa ou item sem XML confiavel.',
                'tb33_ncm' => '21069090',
                'tb33_observacao_fiscal' => 'Grupo ativado com NCM generico aproximado apenas para triagem cadastral. Revisar individualmente com contador e XML do fornecedor antes de emitir nota.',
            ],
        ];
    }

    private function producaoObservation(): string
    {
        return 'Grupo NCM ativado com NCM aproximado para producao propria. Validar composicao, preparo, CEST e cClassTrib com contador antes do uso definitivo.';
    }

    private function preparoObservation(): string
    {
        return 'Grupo NCM ativado com NCM aproximado para item preparado na loja. Validar se a operacao sera tratada como produto, preparo ou servico e confirmar NCM/cClassTrib com contador.';
    }

    private function revendaObservation(): string
    {
        return 'Grupo NCM ativado com NCM aproximado para mercadoria de revenda. Validar NCM, CEST e cClassTrib com XML do fornecedor, contador e tabelas oficiais vigentes antes do uso definitivo.';
    }
}
