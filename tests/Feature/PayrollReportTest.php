<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\SalaryAdvance;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_payroll_consolidated_rows_excluding_clients(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $productA = $this->makeProduct('Cafe');
        $productB = $this->makeProduct('Pao');

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'funcao' => 1,
            'funcao_original' => 1,
            'salario' => 0,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

        $employee = User::factory()->create([
            'name' => 'Bruno',
            'email' => 'bruno@example.com',
            'funcao' => 5,
            'funcao_original' => 5,
            'salario' => 2000,
            'tb2_id' => $unit->tb2_id,
        ]);
        $employee->units()->sync([$unit->tb2_id]);

        $client = User::factory()->create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'funcao' => 6,
            'funcao_original' => 6,
            'salario' => 999,
            'tb2_id' => $unit->tb2_id,
        ]);
        $client->units()->sync([$unit->tb2_id]);

        SalaryAdvance::create([
            'user_id' => $employee->id,
            'unit_id' => $unit->tb2_id,
            'advance_date' => '2026-04-05',
            'amount' => 100,
            'reason' => 'Primeiro adiantamento',
        ]);

        SalaryAdvance::create([
            'user_id' => $employee->id,
            'unit_id' => $unit->tb2_id,
            'advance_date' => '2026-04-10',
            'amount' => 50,
            'reason' => 'Segundo adiantamento',
        ]);

        SalaryAdvance::create([
            'user_id' => $client->id,
            'unit_id' => $unit->tb2_id,
            'advance_date' => '2026-04-12',
            'amount' => 999,
            'reason' => 'Nao deve entrar',
        ]);

        $employeePayment = VendaPagamento::create([
            'valor_total' => 25,
            'tipo_pagamento' => 'vale',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
        ]);

        Venda::create([
            'tb4_id' => $employeePayment->tb4_id,
            'tb1_id' => $productA->tb1_id,
            'id_comanda' => null,
            'produto_nome' => 'Cafe',
            'valor_unitario' => 10,
            'quantidade' => 1,
            'valor_total' => 10,
            'data_hora' => '2026-04-08 09:00:00',
            'id_user_caixa' => $admin->id,
            'id_user_vale' => $employee->id,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'vale',
            'status_pago' => true,
            'status' => 1,
        ]);

        Venda::create([
            'tb4_id' => $employeePayment->tb4_id,
            'tb1_id' => $productB->tb1_id,
            'id_comanda' => null,
            'produto_nome' => 'Pao',
            'valor_unitario' => 15,
            'quantidade' => 1,
            'valor_total' => 15,
            'data_hora' => '2026-04-08 09:00:00',
            'id_user_caixa' => $admin->id,
            'id_user_vale' => $employee->id,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'vale',
            'status_pago' => true,
            'status' => 1,
        ]);

        $clientPayment = VendaPagamento::create([
            'valor_total' => 60,
            'tipo_pagamento' => 'vale',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
        ]);

        Venda::create([
            'tb4_id' => $clientPayment->tb4_id,
            'tb1_id' => $productA->tb1_id,
            'id_comanda' => null,
            'produto_nome' => 'Cafe Cliente',
            'valor_unitario' => 60,
            'quantidade' => 1,
            'valor_total' => 60,
            'data_hora' => '2026-04-09 10:00:00',
            'id_user_caixa' => $admin->id,
            'id_user_vale' => $client->id,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'vale',
            'status_pago' => true,
            'status' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('settings.payroll', [
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/FolhaPagamento')
            ->where('summary.employees_count', 2)
            ->where('summary.salary_total', 2000.0)
            ->where('summary.advances_total', 150.0)
            ->where('summary.vales_total', 25.0)
            ->where('summary.balance_total', 1825.0)
            ->has('rows', 2)
            ->where('rows.0.name', 'Admin')
            ->where('rows.0.salary', 0.0)
            ->where('rows.0.advances_total', 0.0)
            ->where('rows.0.vales_total', 0.0)
            ->where('rows.0.balance', 0.0)
            ->where('rows.1.name', 'Bruno')
            ->where('rows.1.salary', 2000.0)
            ->where('rows.1.advances_total', 150.0)
            ->where('rows.1.vales_total', 25.0)
            ->where('rows.1.balance', 1825.0)
            ->where('rows.1.detail.advances_count', 2)
            ->where('rows.1.detail.vales_count', 1)
            ->where('rows.1.detail.vales.0.total', 25.0)
        );
    }

    public function test_non_admin_cannot_access_payroll_screen(): void
    {
        $unit = $this->makeUnit('Loja Operacao');

        $subManager = User::factory()->create([
            'funcao' => 2,
            'funcao_original' => 2,
            'tb2_id' => $unit->tb2_id,
        ]);
        $subManager->units()->sync([$unit->tb2_id]);

        $this->actingAs($subManager)
            ->get(route('settings.payroll'))
            ->assertForbidden();
    }

    public function test_admin_can_filter_payroll_by_role(): void
    {
        $unit = $this->makeUnit('Loja Perfil');

        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

        $manager = User::factory()->create([
            'name' => 'Gerente Perfil',
            'email' => 'gerente.perfil@example.com',
            'funcao' => 1,
            'funcao_original' => 1,
            'salario' => 3500,
            'tb2_id' => $unit->tb2_id,
        ]);
        $manager->units()->sync([$unit->tb2_id]);

        $cashier = User::factory()->create([
            'name' => 'Caixa Perfil',
            'email' => 'caixa.perfil@example.com',
            'funcao' => 3,
            'funcao_original' => 3,
            'salario' => 1800,
            'tb2_id' => $unit->tb2_id,
        ]);
        $cashier->units()->sync([$unit->tb2_id]);

        $response = $this
            ->actingAs($admin)
            ->get(route('settings.payroll', [
                'role' => 3,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/FolhaPagamento')
            ->where('selectedRole', 3)
            ->where('summary.employees_count', 1)
            ->has('rows', 1)
            ->where('rows.0.name', 'Caixa Perfil')
            ->where('rows.0.role_label', 'Caixa')
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
            'tb2_status' => 1,
        ]);
    }

    private function makeProduct(string $name): Produto
    {
        return Produto::create([
            'tb1_nome' => $name,
            'tb1_vlr_custo' => 5,
            'tb1_vlr_venda' => 10,
            'tb1_codbar' => fake()->unique()->ean13(),
            'tb1_tipo' => 0,
            'tb1_status' => 1,
        ]);
    }
}
