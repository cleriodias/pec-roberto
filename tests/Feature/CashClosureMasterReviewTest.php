<?php

namespace Tests\Feature;

use App\Models\CashierClosure;
use App\Models\Expense;
use App\Models\Produto;
use App\Models\Supplier;
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
            ->where('records.0.closure.cash_amount', fn ($value) => (float) $value === 90.0)
            ->where('records.0.closure.card_amount', fn ($value) => (float) $value === 210.0)
            ->where('records.0.closure.original_cash_amount', fn ($value) => (float) $value === 100.0)
            ->where('records.0.closure.original_card_amount', fn ($value) => (float) $value === 200.0)
            ->where('records.0.closure.master_review.reviewed', true)
            ->where('records.0.closure.master_review.checked_by_name', 'Master')
        );
    }

    public function test_cash_closure_report_groups_small_card_complements_per_cashier_and_unit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Setor-10');
        $manager = $this->makeUser('Gerente', 1, $unit);
        $cashierA = $this->makeUser('Caixa A', 3, $unit);
        $cashierB = $this->makeUser('Caixa B', 3, $unit);
        $product = $this->makeProduct();

        $paymentA1 = VendaPagamento::create([
            'valor_total' => 10,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 9.50,
            'troco' => 0,
            'dois_pgto' => 0.50,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $paymentA2 = VendaPagamento::create([
            'valor_total' => 20,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 19.25,
            'troco' => 0,
            'dois_pgto' => 0.75,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $paymentA3 = VendaPagamento::create([
            'valor_total' => 15,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 13.80,
            'troco' => 0,
            'dois_pgto' => 1.20,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $paymentB1 = VendaPagamento::create([
            'valor_total' => 8,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 7.10,
            'troco' => 0,
            'dois_pgto' => 0.90,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        Venda::create([
            'tb4_id' => $paymentA1->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '101',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 10,
            'quantidade' => 1,
            'valor_total' => 10,
            'data_hora' => now()->subMinutes(20),
            'id_user_caixa' => $cashierA->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        Venda::create([
            'tb4_id' => $paymentA2->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '102',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 20,
            'quantidade' => 1,
            'valor_total' => 20,
            'data_hora' => now()->subMinutes(10),
            'id_user_caixa' => $cashierA->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Venda::create([
            'tb4_id' => $paymentA3->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '103',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 15,
            'quantidade' => 1,
            'valor_total' => 15,
            'data_hora' => now()->subMinutes(5),
            'id_user_caixa' => $cashierA->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        Venda::create([
            'tb4_id' => $paymentB1->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '201',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 8,
            'quantidade' => 1,
            'valor_total' => 8,
            'data_hora' => now()->subMinutes(2),
            'id_user_caixa' => $cashierB->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $response = $this
            ->actingAs($manager)
            ->get(route('reports.cash.closure', ['date' => '2026-04-04']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashClosure')
            ->has('records', 2)
            ->where('records.0.cashier_name', 'Caixa A')
            ->where('records.0.small_card_complements.total', fn ($value) => (float) $value === 1.25)
            ->has('records.0.small_card_complements.items', 2)
            ->where('records.0.small_card_complements.items', function ($items) use ($paymentA1, $paymentA2) {
                $receiptIds = collect($items)->pluck('receipt.id')->sort()->values()->all();

                return $receiptIds === collect([$paymentA1->tb4_id, $paymentA2->tb4_id])->sort()->values()->all();
            })
            ->where('records.1.cashier_name', 'Caixa B')
            ->where('records.1.small_card_complements.total', fn ($value) => (float) $value === 0.9)
            ->has('records.1.small_card_complements.items', 1)
            ->where('records.1.small_card_complements.items.0.receipt.id', $paymentB1->tb4_id)
        );
    }

    public function test_manager_can_close_cash_closure_with_system_values_from_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $unit = $this->makeUnit('Setor-30');
        $manager = $this->makeUser('Gerente', 1, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct();
        $supplier = $this->makeSupplier();

        $payment = VendaPagamento::create([
            'valor_total' => 150,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 150,
            'troco' => 0,
            'dois_pgto' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '501',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 150,
            'quantidade' => 1,
            'valor_total' => 150,
            'data_hora' => now(),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Expense::create([
            'supplier_id' => $supplier->id,
            'unit_id' => $unit->tb2_id,
            'user_id' => $cashier->id,
            'expense_date' => '2026-04-08',
            'amount' => 15,
            'notes' => 'Despesa do caixa',
        ]);

        $response = $this
            ->actingAs($manager)
            ->postJson(route('reports.cash.closure.close'), [
                'cashier_id' => $cashier->id,
                'unit_id' => $unit->tb2_id,
                'closed_date' => '2026-04-08',
                'mode' => 'system',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Caixa fechado com os valores do sistema.');

        $this->assertDatabaseHas('cashier_closures', [
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'closed_date' => '2026-04-08',
            'cash_amount' => 110.00,
            'card_amount' => 25.00,
        ]);
    }

    public function test_master_can_close_cash_closure_zeroed_from_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $unit = $this->makeUnit('Setor-31');
        $master = $this->makeUser('Master', 0, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct();

        $payment = VendaPagamento::create([
            'valor_total' => 80,
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
            'id_comanda' => '601',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 80,
            'quantidade' => 1,
            'valor_total' => 80,
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

        $response = $this
            ->actingAs($master)
            ->postJson(route('reports.cash.closure.close'), [
                'cashier_id' => $cashier->id,
                'unit_id' => $unit->tb2_id,
                'closed_date' => '2026-04-08',
                'mode' => 'zeroed',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Caixa fechado com valores zerados.');

        $this->assertDatabaseHas('cashier_closures', [
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'closed_date' => '2026-04-08',
            'cash_amount' => 0.00,
            'card_amount' => 0.00,
        ]);
    }

    public function test_manager_cannot_close_cash_closure_from_unmanaged_unit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $managerUnit = $this->makeUnit('Setor-32');
        $otherUnit = $this->makeUnit('Setor-33');
        $manager = $this->makeUser('Gerente', 1, $managerUnit);
        $cashier = $this->makeUser('Caixa', 3, $otherUnit);
        $product = $this->makeProduct();

        $payment = VendaPagamento::create([
            'valor_total' => 90,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 90,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '701',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 90,
            'quantidade' => 1,
            'valor_total' => 90,
            'data_hora' => now(),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $otherUnit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($manager)
            ->postJson(route('reports.cash.closure.close'), [
                'cashier_id' => $cashier->id,
                'unit_id' => $otherUnit->tb2_id,
                'closed_date' => '2026-04-08',
                'mode' => 'system',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('cashier_closures', [
            'user_id' => $cashier->id,
            'unit_id' => $otherUnit->tb2_id,
            'closed_date' => '2026-04-08',
        ]);
    }

    public function test_cash_closure_report_deducts_expenses_from_cash_conference_base(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $unit = $this->makeUnit('Setor-20');
        $manager = $this->makeUser('Gerente', 1, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $otherCashier = $this->makeUser('Outro Caixa', 3, $unit);
        $product = $this->makeProduct();
        $supplier = $this->makeSupplier();

        $payment = VendaPagamento::create([
            'valor_total' => 100,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 100,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '301',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 100,
            'quantidade' => 1,
            'valor_total' => 100,
            'data_hora' => now(),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CashierClosure::create([
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'unit_name' => $unit->tb2_nome,
            'cash_amount' => 80,
            'card_amount' => 0,
            'closed_date' => '2026-04-08',
            'closed_at' => now(),
        ]);

        Expense::create([
            'supplier_id' => $supplier->id,
            'unit_id' => $unit->tb2_id,
            'user_id' => $cashier->id,
            'expense_date' => '2026-04-08',
            'amount' => 20,
            'notes' => 'Gasto do caixa',
        ]);

        Expense::create([
            'supplier_id' => $supplier->id,
            'unit_id' => $unit->tb2_id,
            'user_id' => $otherCashier->id,
            'expense_date' => '2026-04-08',
            'amount' => 15,
            'notes' => 'Gasto de outro caixa',
        ]);

        $response = $this
            ->actingAs($manager)
            ->get(route('reports.cash.closure', ['date' => '2026-04-08']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashClosure')
            ->has('records', 1)
            ->where('records.0.cashier_name', 'Caixa')
            ->where('records.0.expense_total', fn ($value) => (float) $value === 20.0)
            ->has('records.0.expense_details', 1)
            ->where('records.0.expense_details.0.supplier', 'Fornecedor teste')
            ->where('records.0.expense_details.0.amount', fn ($value) => (float) $value === 20.0)
            ->where('records.0.expense_details.0.notes', 'Gasto do caixa')
            ->where('records.0.conference_base_cash', fn ($value) => (float) $value === 80.0)
            ->where('records.0.conference_base_total', fn ($value) => (float) $value === 80.0)
            ->where('records.0.closure.differences.cash', fn ($value) => (float) $value === 0.0)
            ->where('records.0.closure.differences.total', fn ($value) => (float) $value === 0.0)
        );
    }

    public function test_cash_discrepancies_report_ignores_shortage_when_expense_balances_cash(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $unit = $this->makeUnit('Setor-21');
        $manager = $this->makeUser('Gerente', 1, $unit);
        $cashier = $this->makeUser('Caixa', 3, $unit);
        $product = $this->makeProduct();
        $supplier = $this->makeSupplier();

        $payment = VendaPagamento::create([
            'valor_total' => 100,
            'tipo_pagamento' => 'dinheiro',
            'valor_pago' => 100,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => '401',
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 100,
            'quantidade' => 1,
            'valor_total' => 100,
            'data_hora' => now(),
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_lanc' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'dinheiro',
            'status_pago' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CashierClosure::create([
            'user_id' => $cashier->id,
            'unit_id' => $unit->tb2_id,
            'unit_name' => $unit->tb2_nome,
            'cash_amount' => 80,
            'card_amount' => 0,
            'closed_date' => '2026-04-08',
            'closed_at' => now(),
        ]);

        Expense::create([
            'supplier_id' => $supplier->id,
            'unit_id' => $unit->tb2_id,
            'user_id' => $cashier->id,
            'expense_date' => '2026-04-08',
            'amount' => 20,
            'notes' => 'Gasto do caixa',
        ]);

        $response = $this
            ->actingAs($manager)
            ->get(route('reports.cash.discrepancies', ['date' => '2026-04-08']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/CashDiscrepancies')
            ->has('records', 0)
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

    private function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Fornecedor teste',
            'dispute' => false,
            'access_code' => '1234',
        ]);
    }
}
