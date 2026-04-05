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

class SalesReportHojeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hoje_report_returns_only_ten_latest_records_from_current_day_and_unit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unitA = $this->makeUnit('Loja A');
        $unitB = $this->makeUnit('Loja B');
        $cashierA = $this->makeUser('Caixa A', 3, $unitA);
        $cashierB = $this->makeUser('Caixa B', 3, $unitB);
        $product = $this->makeProduct();

        $this->createReceipt($unitB, $cashierB, $product, 99.90, '2026-04-04 08:00:00', 2001);
        $this->createReceipt($unitA, $cashierA, $product, 88.80, '2026-04-03 08:00:00', 2002);

        $includedPayments = [];

        foreach (range(1, 12) as $index) {
            $includedPayments[] = $this->createReceipt(
                $unitA,
                $cashierA,
                $product,
                10 + $index,
                sprintf('2026-04-04 09:%02d:00', $index),
                3000 + $index,
            );
        }

        $response = $this->actingAs($cashierA)->get(route('reports.hoje'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Hoje')
            ->where('unit.id', $unitA->tb2_id)
            ->where('reportDate', '04/04/2026')
            ->has('records', 10)
            ->where('records.0.id', $includedPayments[11]->tb4_id)
            ->where('records.9.id', $includedPayments[2]->tb4_id)
        );
    }

    public function test_hoje_report_filters_by_value_and_time_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Loja A');
        $cashier = $this->makeUser('Caixa A', 3, $unit);
        $product = $this->makeProduct();

        $matchingPayment = $this->createReceipt($unit, $cashier, $product, 25.50, '2026-04-04 10:15:00', 4001);
        $this->createReceipt($unit, $cashier, $product, 25.50, '2026-04-04 10:35:00', 4002);
        $this->createReceipt($unit, $cashier, $product, 30.00, '2026-04-04 10:18:00', 4003);
        $this->createReceipt($unit, $cashier, $product, 25.50, '2026-04-03 10:15:00', 4004);

        $response = $this->actingAs($cashier)->get(route('reports.hoje', [
            'valor' => '25,50',
            'hora' => '10:20',
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Hoje')
            ->where('filters.valor', '25,50')
            ->where('filters.hora', '10:20')
            ->has('records', 1)
            ->where('records.0.id', $matchingPayment->tb4_id)
        );
    }

    public function test_hoje_report_filters_by_receipt_number(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Loja A');
        $cashier = $this->makeUser('Caixa A', 3, $unit);
        $product = $this->makeProduct();

        $matchingPayment = $this->createReceipt($unit, $cashier, $product, 18.75, '2026-04-04 11:00:00', 5001);
        $this->createReceipt($unit, $cashier, $product, 18.75, '2026-04-04 11:05:00', 5002);

        $response = $this->actingAs($cashier)->get(route('reports.hoje', [
            'cupom' => $matchingPayment->tb4_id,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Hoje')
            ->where('filters.cupom', (string) $matchingPayment->tb4_id)
            ->has('records', 1)
            ->where('records.0.id', $matchingPayment->tb4_id)
        );
    }

    public function test_hoje_report_filters_by_comanda_number(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00'));

        $unit = $this->makeUnit('Loja A');
        $cashier = $this->makeUser('Caixa A', 3, $unit);
        $product = $this->makeProduct();

        $matchingPayment = $this->createReceipt($unit, $cashier, $product, 19.90, '2026-04-04 11:00:00', 6001);
        $this->createReceipt($unit, $cashier, $product, 19.90, '2026-04-04 11:05:00', 6002);

        $response = $this->actingAs($cashier)->get(route('reports.hoje', [
            'comanda' => 6001,
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Hoje')
            ->where('filters.comanda', '6001')
            ->has('records', 1)
            ->where('records.0.id', $matchingPayment->tb4_id)
            ->where('records.0.comanda', '6001')
        );
    }

    private function createReceipt(
        Unidade $unit,
        User $cashier,
        Produto $product,
        float $total,
        string $dateTime,
        int $comanda,
    ): VendaPagamento {
        $time = Carbon::parse($dateTime);

        $payment = VendaPagamento::create([
            'valor_total' => $total,
            'tipo_pagamento' => 'maquina',
            'valor_pago' => null,
            'troco' => 0,
            'dois_pgto' => 0,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        Venda::create([
            'tb4_id' => $payment->tb4_id,
            'tb1_id' => $product->tb1_id,
            'id_comanda' => $comanda,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => $total,
            'quantidade' => 1,
            'valor_total' => $total,
            'data_hora' => $time,
            'id_user_caixa' => $cashier->id,
            'id_user_vale' => null,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'maquina',
            'status_pago' => true,
            'status' => 1,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return $payment;
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

    private function makeUser(string $name, int $role, Unidade $primaryUnit): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'funcao' => $role,
            'funcao_original' => $role,
            'tb2_id' => $primaryUnit->tb2_id,
        ]);
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
