<?php

namespace Tests\Feature;

use App\Models\CashierClosure;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashClosureMasterReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_master_can_update_second_cash_closure_review(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Setor-10');
        $master = $this->makeUser('Master', 0, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $closure = CashierClosure::create([
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'unit_name' => $unit->tb2_nome,
            'cash_amount' => 100,
            'card_amount' => 200,
            'closed_date' => '2026-04-04',
            'closed_at' => now(),
        ]);

        $response = $this
            ->actingAs($master)
            ->patchJson(route('reports.cash.closure.master-review', $closure), [
                'cash_amount' => 110.50,
                'card_amount' => 198.25,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Conferencia do Master atualizada com sucesso.');

        $this->assertDatabaseHas('cashier_closures', [
            'id' => $closure->id,
            'master_cash_amount' => 110.50,
            'master_card_amount' => 198.25,
            'master_checked_by' => $master->id,
        ]);
    }

    public function test_cash_closure_report_uses_master_review_values_when_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Setor-10');
        $manager = $this->makeUser('Gerente', 1, $unit);
        $master = $this->makeUser('Master', 0, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct();

        $payment = VendaPagamento::create([
            'valor_total' => 300,
            'tipo_pagamento' => 'maquina',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => null,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 300,
            'quantidade' => 1,
            'valor_total' => 300,
            'data_hora' => now(),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'maquina',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CashierClosure::create([
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'unit_name' => $unit->tb2_nome,
            'cash_amount' => 100,
            'card_amount' => 200,
            'master_cash_amount' => 90,
            'master_card_amount' => 210,
            'master_checked_by' => $master->id,
            'master_checked_at' => now(),
            'closed_date' => '2026-04-04',
            'closed_at' => now(),
        ]);

        $response = $this
            ->actingAs($manager)
            ->get(route('reports.cash.closure', ['date' => '2026-04-04']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashClosure')
            ->has('records', 1)
            ->where('records.0.closure.cash_amount', 90.0)
            ->where('records.0.closure.card_amount', 210.0)
            ->where('records.0.closure.original_cash_amount', 100.0)
            ->where('records.0.closure.original_card_amount', 200.0)
            ->where('records.0.closure.master_review.reviewed', true)
            ->where('records.0.closure.master_review.checked_by_name', 'Master')
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
}
