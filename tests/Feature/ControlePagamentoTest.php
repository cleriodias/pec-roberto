<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ControlePagamento;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ControlePagamentoTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveUnit(array $overrides = []): Unidade
    {
        return Unidade::query()->create(array_merge([
            'tb2_nome' => 'Loja Centro',
            'tb2_endereco' => 'Rua A, 10',
            'tb2_cep' => '01000-000',
            'tb2_fone' => '11999999999',
            'tb2_cnpj' => '12.345.678/0001-99',
            'tb2_localizacao' => 'Centro',
            'tb2_status' => 1,
        ], $overrides));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_open_payment_control_screen(): void
    {
        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

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
        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

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
            'user_id' => $admin->id,
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
        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

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

        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $admin->units()->sync([$unit->tb2_id]);

        ControlePagamento::create([
            'user_id' => $admin->id,
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

    public function test_admin_sees_only_own_payment_controls(): void
    {
        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $otherAdmin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);

        $admin->units()->sync([$unit->tb2_id]);
        $otherAdmin->units()->sync([$unit->tb2_id]);

        ControlePagamento::create([
            'user_id' => $admin->id,
            'descricao' => 'Controle proprio',
            'frequencia' => 'quinzenal',
            'dia_semana' => null,
            'dia_mes' => null,
            'valor_total' => 200,
            'quantidade_parcelas' => 2,
            'valor_parcela' => 100,
            'data_inicio' => '2026-04-01',
            'data_fim' => '2026-04-15',
        ]);

        ControlePagamento::create([
            'user_id' => $otherAdmin->id,
            'descricao' => 'Controle de outro usuario',
            'frequencia' => 'quinzenal',
            'dia_semana' => null,
            'dia_mes' => null,
            'valor_total' => 300,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 100,
            'data_inicio' => '2026-04-02',
            'data_fim' => '2026-04-30',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('settings.payment-control'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ControlePagamentos')
            ->has('paymentControls', 1)
            ->where('paymentControls.0.descricao', 'Controle proprio')
        );
    }

    public function test_admin_cannot_delete_payment_control_from_another_user(): void
    {
        $unit = $this->createActiveUnit();
        $admin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $otherAdmin = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);

        $admin->units()->sync([$unit->tb2_id]);
        $otherAdmin->units()->sync([$unit->tb2_id]);

        $paymentControl = ControlePagamento::create([
            'user_id' => $otherAdmin->id,
            'descricao' => 'Controle protegido',
            'frequencia' => 'quinzenal',
            'dia_semana' => null,
            'dia_mes' => null,
            'valor_total' => 150,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 50,
            'data_inicio' => '2026-04-01',
            'data_fim' => '2026-04-29',
        ]);

        $this->actingAs($admin)
            ->delete(route('settings.payment-control.destroy', $paymentControl))
            ->assertForbidden();

        $this->assertDatabaseHas('tb24_controle_pagamentos', [
            'id' => $paymentControl->id,
        ]);
    }

    public function test_login_creates_system_chat_summary_for_owner_payment_controls(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 08:00:00'));

        $unit = $this->createActiveUnit();
        $manager = User::factory()->create([
            'name' => 'Gerente Teste',
            'email' => 'gerente@paoecafe83.com.br',
            'password' => 'password',
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $manager->units()->sync([$unit->tb2_id]);

        ControlePagamento::create([
            'user_id' => $manager->id,
            'descricao' => 'Fornecedor de hoje',
            'frequencia' => 'quinzenal',
            'dia_semana' => null,
            'dia_mes' => null,
            'valor_total' => 300,
            'quantidade_parcelas' => 3,
            'valor_parcela' => 100,
            'data_inicio' => '2026-04-10',
            'data_fim' => '2026-05-08',
        ]);

        ControlePagamento::create([
            'user_id' => $manager->id,
            'descricao' => 'Servico em 3 dias',
            'frequencia' => 'semanal',
            'dia_semana' => 1,
            'dia_mes' => null,
            'valor_total' => 200,
            'quantidade_parcelas' => 1,
            'valor_parcela' => 200,
            'data_inicio' => '2026-04-13',
            'data_fim' => '2026-04-13',
        ]);

        $otherManager = User::factory()->create([
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $otherManager->units()->sync([$unit->tb2_id]);

        ControlePagamento::create([
            'user_id' => $otherManager->id,
            'descricao' => 'Pagamento de outro usuario',
            'frequencia' => 'quinzenal',
            'dia_semana' => null,
            'dia_mes' => null,
            'valor_total' => 500,
            'quantidade_parcelas' => 1,
            'valor_parcela' => 500,
            'data_inicio' => '2026-04-10',
            'data_fim' => '2026-04-10',
        ]);

        $response = $this->post(route('login'), [
            'username' => 'gerente',
            'password' => 'password',
            'unit_id' => $unit->tb2_id,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $systemUser = User::query()->where('email', 'sistema.chat@pec.local')->first();

        $this->assertNotNull($systemUser);
        $this->assertDatabaseHas('tb22_chat_mensagens', [
            'sender_id' => $systemUser->id,
            'recipient_id' => $manager->id,
        ]);

        $message = ChatMessage::query()
            ->where('sender_id', $systemUser->id)
            ->where('recipient_id', $manager->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Resumo de pagamentos', $message->message);
        $this->assertStringContainsString('Hoje: 1 pendencia(s)', $message->message);
        $this->assertStringContainsString('Proximos 3 dias: 1 pendencia(s)', $message->message);
        $this->assertStringContainsString('Fornecedor de hoje', $message->message);
        $this->assertStringContainsString('Servico em 3 dias', $message->message);
        $this->assertStringNotContainsString('Pagamento de outro usuario', $message->message);
        $this->assertStringContainsString('[link=/settings/controle-pagamentos]Abrir Controle de Pagamentos[/link]', $message->message);
        $this->assertStringContainsString('Link direto:', $message->message);
        $this->assertStringContainsString('/settings/controle-pagamentos', $message->message);
    }
}
