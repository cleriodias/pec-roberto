<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleRefeicaoRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_refeicao_sale_is_blocked_when_it_exceeds_weekday_daily_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00'));

        $unit = $this->makeUnit('Loja Centro');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $employee = $this->makeUser('Clerio', 3, $unit, 200);
        $product = $this->makeProduct('Almoco Executivo', 8.00, true);

        Venda::create([
            'tb4_id' => null,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => null,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 5.00,
            'quantidade' => 1,
            'valor_total' => 5.00,
            'data_hora' => now()->copy()->setTime(9, 0),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => $employee->id,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'refeicao',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->copy()->setTime(9, 0),
            'updated_at' => now()->copy()->setTime(9, 0),
        ]);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->postJson(route('sales.store'), [
                'tipo_pago' => 'vale',
                'vale_type' => 'refeicao',
                'vale_user_id' => $employee->id,
                'items' => [
                    [
                        'product_id' => $product->tb1_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vale_user_id']);

        $this->assertStringContainsString(
            'A compra ultrapassa o limite diario de refeicao para Clerio.',
            $response->json('errors.vale_user_id.0')
        );
        $this->assertStringContainsString(
            'Disponivel hoje: R$ 7,00',
            $response->json('errors.vale_user_id.0')
        );
    }

    public function test_refeicao_sale_allows_higher_limit_on_sunday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 12:00:00'));

        $unit = $this->makeUnit('Loja Centro');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $employee = $this->makeUser('Clerio', 3, $unit, 200);
        $product = $this->makeProduct('Prato Domingo', 20.00, true);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->postJson(route('sales.store'), [
                'tipo_pago' => 'vale',
                'vale_type' => 'refeicao',
                'vale_user_id' => $employee->id,
                'items' => [
                    [
                        'product_id' => $product->tb1_id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('sale.total', 20.0)
            ->assertJsonPath('sale.tipo_pago', 'refeicao')
            ->assertJsonPath('sale.vale_user_name', 'Clerio');
    }

    public function test_user_search_returns_refeicao_daily_usage_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00'));

        $unit = $this->makeUnit('Loja Centro');
        $otherUnit = $this->makeUnit('Loja Sul');
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $employee = $this->makeUser('Clerio', 3, $unit, 200);
        $otherEmployee = $this->makeUser('Clerio Externo', 3, $otherUnit, 200);
        $product = $this->makeProduct('Prato Feito', 6.00, true);

        Venda::create([
            'tb4_id' => null,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => null,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 6.00,
            'quantidade' => 1,
            'valor_total' => 6.00,
            'data_hora' => now()->copy()->setTime(10, 0),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => $employee->id,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'refeicao',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->copy()->setTime(10, 0),
            'updated_at' => now()->copy()->setTime(10, 0),
        ]);

        $response = $this
            ->actingAs($cashier)
            ->withSession($this->activeSessionPayload($unit, 3))
            ->getJson(route('users.search', ['q' => 'cler']));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Clerio',
                'refeicao_daily_limit' => 12.0,
                'refeicao_daily_used' => 6.0,
                'refeicao_daily_remaining' => 6.0,
            ])
            ->assertJsonMissing([
                'name' => $otherEmployee->name,
            ]);
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

    private function makeUser(string $name, int $role, Unidade $unit, float $vrCred = 0): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'funcao' => $role,
            'funcao_original' => $role,
            'tb2_id' => $unit->tb2_id,
            'vr_cred' => $vrCred,
        ]);

        $user->units()->sync([$unit->tb2_id]);

        return $user;
    }

    private function makeProduct(string $name, float $salePrice, bool $vrEligible): Produto
    {
        return Produto::create([
            'tb1_nome' => $name,
            'tb1_vlr_custo' => 0,
            'tb1_vlr_venda' => $salePrice,
            'tb1_codbar' => fake()->unique()->numerify('############'),
            'tb1_tipo' => 0,
            'tb1_status' => 1,
            'tb1_vr_credit' => $vrEligible,
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
