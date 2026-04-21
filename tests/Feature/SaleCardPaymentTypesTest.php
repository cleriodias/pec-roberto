<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Support\FiscalInvoicePreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SaleCardPaymentTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(FiscalInvoicePreparationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('prepareForPayment')->andReturn(null);
        });
    }

    public function test_card_credit_sale_stores_card_subtype_and_keeps_sale_rows_as_maquina(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct('Pao frances', 2.50);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->postJson(route('sales.store'), [
                'tipo_pago' => 'cartao_credito',
                'items' => [
                    [
                        'product_id' => $product->tb1_id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('sale.tipo_pago', 'cartao_credito')
            ->assertJsonPath('sale.payment.tipo_pagamento', 'cartao_credito');

        $this->assertDatabaseHas('tb4_vendas_pg', [
            'tipo_pagamento' => 'cartao_credito',
            'valor_total' => 5.00,
            'dois_pgto' => 0,
        ]);

        $this->assertDatabaseHas('tb3_vendas', [
            'tb1_id' => $product->tb1_id,
            'tipo_pago' => 'maquina',
            'status_pago' => 1,
            'id_unidade' => $unit->tb2_id,
        ]);
    }

    public function test_cash_sale_with_card_complement_stores_selected_card_subtype_on_payment(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct('Cafe coado', 10.00);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->postJson(route('sales.store'), [
                'tipo_pago' => 'dinheiro',
                'valor_pago' => 6.00,
                'card_type' => 'cartao_debito',
                'items' => [
                    [
                        'product_id' => $product->tb1_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('sale.tipo_pago', 'dinheiro_cartao_debito')
            ->assertJsonPath('sale.payment.tipo_pagamento', 'dinheiro_cartao_debito')
            ->assertJsonPath('sale.payment.valor_pago', 6.0)
            ->assertJsonPath('sale.payment.dois_pgto', 4.0);

        $this->assertDatabaseHas('tb4_vendas_pg', [
            'tipo_pagamento' => 'dinheiro_cartao_debito',
            'valor_total' => 10.00,
            'valor_pago' => 6.00,
            'dois_pgto' => 4.00,
        ]);

        $this->assertDatabaseHas('tb3_vendas', [
            'tb1_id' => $product->tb1_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => 1,
            'id_unidade' => $unit->tb2_id,
        ]);
    }

    public function test_cash_sale_with_card_complement_requires_card_type(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct('Leite', 8.00);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->postJson(route('sales.store'), [
                'tipo_pago' => 'dinheiro',
                'valor_pago' => 3.00,
                'items' => [
                    [
                        'product_id' => $product->tb1_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['card_type']);
    }

    private function makeUnit(string $name): Unidade
    {
        return Unidade::create([
            'tb2_nome' => $name,
            'tb2_endereco' => 'Endereco ' . $name,
            'tb2_cep' => '72900-000',
            'tb2_fone' => '(61) 99999-9999',
            'tb2_cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'tb2_localizacao' => 'https://maps.example.com/' . fake()->slug(),
        ]);
    }

    private function makeUser(string $name, int $role, Unidade $unit): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'funcao' => $role,
            'funcao_original' => $role,
            'tb2_id' => $unit->tb2_id,
        ]);

        $user->units()->sync([$unit->tb2_id]);

        return $user;
    }

    private function makeProduct(string $name, float $salePrice): Produto
    {
        return Produto::create([
            'tb1_nome' => $name,
            'tb1_vlr_custo' => 0,
            'tb1_vlr_venda' => $salePrice,
            'tb1_codbar' => fake()->unique()->numerify('############'),
            'tb1_tipo' => 0,
            'tb1_status' => 1,
        ]);
    }

    private function activeSessionPayload(Unidade $unit, int $role): array
    {
        return [
            'active_unit' => [
                'id' => $unit->tb2_id,
                'name' => $unit->tb2_nome,
                'address' => $unit->tb2_endereco,
                'cnpj' => $unit->tb2_cnpj,
            ],
            'active_role' => $role,
        ];
    }
}
