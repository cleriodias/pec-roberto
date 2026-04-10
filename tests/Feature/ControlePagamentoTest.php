<?php

namespace Tests\Feature;

use App\Models\ControlePagamento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ControlePagamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_open_payment_control_screen(): void
    {
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('settings.payment-control'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ControlePagamentos')
            ->where('paymentControls', [])
        );
    }

    public function test_admin_can_store_monthly_payment_control_with_calculated_end_date(): void
    {
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('settings.payment-control.store'), [
                'descricao' => 'Aluguel da loja',
                'frequencia' => 'mensal',
                'dia_semana' => null,
                'dia_mes' => 31,
                'valor_total' => 3000,
                'quantidade_parcelas' => 3,
                'data_inicio' => '31/01/26',
            ]);

        $response->assertRedirect(route('settings.payment-control'));

        $this->assertDatabaseHas('tb24_controle_pagamentos', [
            'descricao' => 'Aluguel da loja',
            'frequencia' => 'mensal',
            'dia_semana' => null,
            'dia_mes' => 31,
            'valor_total' => 3000.00,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 1000.00,
            'data_inicio' => '2026-01-31',
            'data_fim' => '2026-03-31',
        ]);
    }

    public function test_weekly_payment_control_requires_start_date_matching_selected_weekday(): void
    {
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('settings.payment-control'))
            ->post(route('settings.payment-control.store'), [
                'descricao' => 'Fornecedor semanal',
                'frequencia' => 'semanal',
                'dia_semana' => 5,
                'dia_mes' => null,
                'valor_total' => 100,
                'quantidade_parcelas' => 2,
                'data_inicio' => '06/04/26',
            ]);

        $response
            ->assertRedirect(route('settings.payment-control'))
            ->assertSessionHasErrors(['dia_semana']);

        $this->assertSame(0, ControlePagamento::count());
    }

    public function test_index_projects_timeline_with_vencido_por_vencer_e_nao_venceu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00'));

        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
        ]);

        ControlePagamento::create([
            'descricao' => 'Servico semanal',
            'frequencia' => 'semanal',
            'dia_semana' => 4,
            'dia_mes' => null,
            'valor_total' => 400,
            'quantidade_parcelas' => 4,
            'valor_parcela' => 100,
            'data_inicio' => '2026-04-03',
            'data_fim' => '2026-04-24',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('settings.payment-control'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ControlePagamentos')
            ->where('timelineReferenceDate', '2026-04-09')
            ->has('paymentControls', 1)
            ->where('paymentControls.0.timeline.summary.vencido', 1)
            ->where('paymentControls.0.timeline.summary.por_vencer', 2)
            ->where('paymentControls.0.timeline.summary.nao_venceu', 1)
            ->where('paymentControls.0.timeline.records.0.status', 'vencido')
            ->where('paymentControls.0.timeline.records.1.status', 'por_vencer')
            ->where('paymentControls.0.timeline.records.3.status', 'nao_venceu')
        );
    }
}
