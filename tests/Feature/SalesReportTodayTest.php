<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesReportTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_report_without_unit_id_keeps_all_units_even_with_active_session_unit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 10:00:00'));

        $unitA = $this->makeUnit('Loja A');
        $unitB = $this->makeUnit('Loja B');
        $manager = $this->makeUser('Gerente', 1, $unitA, [$unitA, $unitB]);
        $cashierA = $this->makeUser('Caixa A', 3, $unitA);
        $cashierB = $this->makeUser('Caixa B', 3, $unitB);
        $product = $this->makeProduct();

        $paymentA = VendaPagamento::create([
            'valor_total' => 10,
            'tipo_pagamento' => 'maquina',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentB = VendaPagamento::create([
            'valor_total' => 20,
            'tipo_pagamento' => 'maquina',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $paymentA->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => 1001,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 10,
            'quantidade' => 1,
            'valor_total' => 10,
            'data_hora' => now(),
            'id_user_caixa' => $cashierA->id,
            'id_user_vale' => null,
            'id_unidade' => $unitA->tb2_id,
            'tipo_pago' => 'maquina',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $paymentB->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => 1002,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 20,
            'quantidade' => 1,
            'valor_total' => 20,
            'data_hora' => now(),
            'id_user_caixa' => $cashierB->id,
            'id_user_vale' => null,
            'id_unidade' => $unitB->tb2_id,
            'tipo_pago' => 'maquina',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($manager)
            ->withSession($this->activeSessionPayload($unitA))
            ->get(route('reports.sales.today'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/SalesToday')
            ->where('selectedUnitId', null)
            ->where('selectedUnit.name', 'Todas as unidades')
            ->where('totals.maquina', 30.0)
            ->has('details.maquina', 2)
        );
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

    private function makeUser(string $name, int $role, Unidade $primaryUnit, array $allowedUnits = []): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'funcao' => $role,
            'funcao_original' => $role,
            'tb2_id' => $primaryUnit->tb2_id,
        ]);

        $unitIds = collect($allowedUnits)
            ->prepend($primaryUnit)
            ->map(fn ($unit) => $unit instanceof Unidade ? $unit->tb2_id : (int) $unit)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $user->units()->sync($unitIds);

        return $user;
    }

    private function makeProduct(): Produto
    {
        return Produto::create([
            'tb1_nome' => 'Produto teste',
            'tb1_vlr_custo' => 5,
            'tb1_vlr_venda' => 10,
            'tb1_codbar' => fake()->unique()->numerify('############'),
            'tb1_tipo' => 0,
            'tb1_status' => 1,
        ]);
    }

    private function activeSessionPayload(Unidade $unit): array
    {
        return [
            'active_unit' => [
                'id' => $unit->tb2_id,
                'name' => $unit->tb2_nome,
                'address' => $unit->tb2_endereco,
                'cnpj' => $unit->tb2_cnpj,
            ],
            'active_role' => 1,
        ];
    }
}
