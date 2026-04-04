<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketInteraction;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_support_ticket_with_recording(): void
    {
        Storage::fake('local');

        [$unit, $user] = $this->makeUserWithUnit(5);

        $response = $this
            ->actingAs($user)
            ->post(route('support.tickets.store'), [
                'title' => 'Erro no fechamento',
                'description' => 'Video mostrando a falha',
                'recording_file' => UploadedFile::fake()->create('gravacao.webm', 512, 'video/webm'),
            ]);

        $response->assertRedirect(route('support.tickets.index'));
        $this->assertDatabaseCount('tb18_chamados', 1);

        $ticket = SupportTicket::first();

        $this->assertNotNull($ticket);
        $this->assertSame($unit->tb2_id, $ticket->unit_id);
        $this->assertTrue(Storage::disk('local')->exists($ticket->video_path));
    }

    public function test_author_and_master_can_watch_video_but_other_users_cannot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('support-tickets/teste.webm', 'video-teste');

        [$unit, $author] = $this->makeUserWithUnit(5);
        [, $master] = $this->makeUserWithUnit(0);
        [, $otherUser] = $this->makeUserWithUnit(5);

        $ticket = SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Chamado com video',
            'description' => 'Descricao teste',
            'video_path' => 'support-tickets/teste.webm',
            'video_original_name' => 'teste.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 11,
            'status' => 'aberto',
        ]);

        $this->actingAs($author)->get(route('support.tickets.video', $ticket))->assertOk();
        $this->actingAs($master)->get(route('support.tickets.video', $ticket))->assertOk();
        $this->actingAs($otherUser)->get(route('support.tickets.video', $ticket))->assertForbidden();
    }

    public function test_master_can_register_interaction_with_images(): void
    {
        Storage::fake('local');

        [$unit, $author] = $this->makeUserWithUnit(5);
        [, $master] = $this->makeUserWithUnit(0);

        $ticket = SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Chamado',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/base.webm',
            'video_original_name' => 'base.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 11,
            'status' => 'aberto',
        ]);

        Storage::disk('local')->put('support-tickets/base.webm', 'video-base');

        $response = $this
            ->actingAs($master)
            ->post(route('support.tickets.reply', $ticket), [
                'message' => 'Analise realizada.',
                'images' => [
                    UploadedFile::fake()->image('print-1.png'),
                    UploadedFile::fake()->image('print-2.jpg'),
                ],
            ]);

        $response->assertRedirect(route('support.tickets.index'));
        $this->assertDatabaseCount('tb19_chamado_interacoes', 1);
        $this->assertDatabaseCount('tb20_chamado_anexos', 2);

        $attachment = SupportTicketAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertTrue(Storage::disk('local')->exists($attachment->file_path));
    }

    public function test_only_master_can_change_status_and_delete_ticket(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('support-tickets/base.webm', 'video-base');
        Storage::disk('local')->put('support-ticket-images/img.png', 'img-base');

        [$unit, $author] = $this->makeUserWithUnit(5);
        [, $master] = $this->makeUserWithUnit(0);

        $ticket = SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Chamado',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/base.webm',
            'video_original_name' => 'base.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 11,
            'status' => 'aberto',
        ]);

        $interaction = SupportTicketInteraction::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $master->id,
            'author_name' => $master->name,
            'message' => 'Print da validacao',
        ]);

        $interaction->attachments()->create([
            'file_path' => 'support-ticket-images/img.png',
            'original_name' => 'img.png',
            'mime_type' => 'image/png',
            'file_size' => 10,
        ]);

        $this
            ->actingAs($author)
            ->put(route('support.tickets.update-status', $ticket), ['status' => 'resolvido'])
            ->assertForbidden();

        $this
            ->actingAs($master)
            ->put(route('support.tickets.update-status', $ticket), ['status' => 'resolvido'])
            ->assertRedirect(route('support.tickets.index'));

        $this->assertDatabaseHas('tb18_chamados', [
            'id' => $ticket->id,
            'status' => 'resolvido',
        ]);

        $this->actingAs($author)->delete(route('support.tickets.destroy', $ticket))->assertForbidden();

        $this->actingAs($master)->delete(route('support.tickets.destroy', $ticket))->assertRedirect(route('support.tickets.index'));

        $this->assertDatabaseMissing('tb18_chamados', ['id' => $ticket->id]);
        $this->assertFalse(Storage::disk('local')->exists('support-tickets/base.webm'));
        $this->assertFalse(Storage::disk('local')->exists('support-ticket-images/img.png'));
    }

    public function test_master_sees_pending_counts_of_all_tickets_in_menu_summary(): void
    {
        [$unitA, $master] = $this->makeUserWithUnit(0);
        [$unitB, $author] = $this->makeUserWithUnit(5);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unitA->tb2_id,
            'title' => 'Chamado aberto',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/a.webm',
            'video_original_name' => 'a.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'aberto',
        ]);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unitB->tb2_id,
            'title' => 'Chamado em analise',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/b.webm',
            'video_original_name' => 'b.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'em_analise',
        ]);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unitB->tb2_id,
            'title' => 'Chamado aguardando',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/c.webm',
            'video_original_name' => 'c.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'aguardando_usuario',
        ]);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unitB->tb2_id,
            'title' => 'Chamado resolvido',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/d.webm',
            'video_original_name' => 'd.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'resolvido',
        ]);

        $this
            ->actingAs($master)
            ->get(route('support.tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/TicketIndex')
                ->where('supportTicketsMenu.can_view', true)
                ->where('supportTicketsMenu.counts.aberto', 1)
                ->where('supportTicketsMenu.counts.em_analise', 1)
                ->where('supportTicketsMenu.counts.aguardando_usuario', 1));
    }

    public function test_requester_sees_only_own_pending_counts_in_menu_summary(): void
    {
        [$unit, $author] = $this->makeUserWithUnit(5);
        [, $otherUser] = $this->makeUserWithUnit(5);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Meu chamado aberto',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/e.webm',
            'video_original_name' => 'e.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'aberto',
        ]);

        SupportTicket::create([
            'user_id' => $author->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Meu chamado aguardando',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/f.webm',
            'video_original_name' => 'f.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'aguardando_usuario',
        ]);

        SupportTicket::create([
            'user_id' => $otherUser->id,
            'unit_id' => $unit->tb2_id,
            'title' => 'Chamado de outro usuario',
            'description' => 'Descricao',
            'video_path' => 'support-tickets/g.webm',
            'video_original_name' => 'g.webm',
            'video_mime_type' => 'video/webm',
            'video_size' => 10,
            'status' => 'em_analise',
        ]);

        $this
            ->actingAs($author)
            ->get(route('support.tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/TicketIndex')
                ->where('supportTicketsMenu.can_view', true)
                ->where('supportTicketsMenu.counts.aberto', 1)
                ->where('supportTicketsMenu.counts.em_analise', 0)
                ->where('supportTicketsMenu.counts.aguardando_usuario', 1));
    }

    public function test_user_without_ticket_does_not_receive_menu_indicator_permission(): void
    {
        [, $user] = $this->makeUserWithUnit(5);

        $this
            ->actingAs($user)
            ->get(route('support.tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/TicketIndex')
                ->where('supportTicketsMenu.can_view', false)
                ->where('supportTicketsMenu.counts.aberto', 0)
                ->where('supportTicketsMenu.counts.em_analise', 0)
                ->where('supportTicketsMenu.counts.aguardando_usuario', 0));
    }

    private function makeUserWithUnit(int $role): array
    {
        $unit = Unidade::create([
            'tb2_nome' => 'Unidade Teste ' . fake()->unique()->word(),
            'tb2_endereco' => 'Endereco Teste',
            'tb2_cep' => '72900-000',
            'tb2_fone' => '(61) 99999-9999',
            'tb2_cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'tb2_localizacao' => 'https://maps.example.com',
        ]);

        $user = User::factory()->create([
            'tb2_id' => $unit->tb2_id,
            'funcao' => $role,
            'funcao_original' => $role,
        ]);

        return [$unit, $user];
    }
}
