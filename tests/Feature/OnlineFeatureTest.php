<?php

namespace Tests\Feature;

use App\Models\OnlineUser;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnlineFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_lanchonete_only_sees_cashier_profiles_from_same_active_unit(): void
    {
        $unitA = $this->makeUnit('Loja A');
        $unitB = $this->makeUnit('Loja B');

        $viewer = $this->makeUser('Lanchonete', 4, $unitA);
        $sameUnitSubManager = $this->makeUser('SubCaixa', 2, $unitA);
        $sameUnitCashier = $this->makeUser('CaixaLoja', 3, $unitA);
        $sameUnitManager = $this->makeUser('GerenteLoja', 1, $unitA, [$unitA]);
        $otherUnitCashier = $this->makeUser('CaixaFora', 3, $unitB);
        $master = $this->makeUser('Master', 0, $unitB, [$unitA, $unitB]);

        $this->makePresence($sameUnitSubManager, 2, $unitA);
        $this->makePresence($sameUnitCashier, 3, $unitA);
        $this->makePresence($sameUnitManager, 1, $unitA);
        $this->makePresence($otherUnitCashier, 3, $unitB);
        $this->makePresence($master, 0, $unitB);

        $response = $this
            ->actingAs($viewer)
            ->withSession($this->activeSessionPayload($unitA, 4))
            ->get(route('online.snapshot'));

        $response->assertOk();

        $users = collect($response->json('onlineUsers'));

        $this->assertSame(
            [$sameUnitCashier->id, $sameUnitSubManager->id],
            $users->pluck('id')->all()
        );
    }

    public function test_cashier_sees_master_all_managers_and_same_unit_users(): void
    {
        $unitA = $this->makeUnit('Loja A');
        $unitB = $this->makeUnit('Loja B');

        $viewer = $this->makeUser('CaixaBase', 3, $unitA);
        $master = $this->makeUser('Master', 0, $unitB, [$unitA, $unitB]);
        $managerA = $this->makeUser('GerenteA', 1, $unitA, [$unitA]);
        $managerB = $this->makeUser('GerenteB', 1, $unitB, [$unitB]);
        $subManagerA = $this->makeUser('SubA', 2, $unitA);
        $lanchoneteA = $this->makeUser('LanchoneteA', 4, $unitA);
        $cashierB = $this->makeUser('CaixaB', 3, $unitB);

        $this->makePresence($master, 0, $unitB);
        $this->makePresence($managerA, 1, $unitA);
        $this->makePresence($managerB, 1, $unitB);
        $this->makePresence($subManagerA, 2, $unitA);
        $this->makePresence($lanchoneteA, 4, $unitA);
        $this->makePresence($cashierB, 3, $unitB);

        $response = $this
            ->actingAs($viewer)
            ->withSession($this->activeSessionPayload($unitA, 3))
            ->get(route('online.snapshot'));

        $response->assertOk();

        $users = collect($response->json('onlineUsers'));

        $this->assertTrue($users->pluck('id')->contains($master->id));
        $this->assertTrue($users->pluck('id')->contains($managerA->id));
        $this->assertTrue($users->pluck('id')->contains($managerB->id));
        $this->assertTrue($users->pluck('id')->contains($subManagerA->id));
        $this->assertTrue($users->pluck('id')->contains($lanchoneteA->id));
        $this->assertFalse($users->pluck('id')->contains($cashierB->id));
    }

    public function test_lanchonete_cannot_message_master_but_can_message_cashier_of_same_unit(): void
    {
        $unitA = $this->makeUnit('Loja A');
        $unitB = $this->makeUnit('Loja B');

        $viewer = $this->makeUser('Lanchonete', 4, $unitA);
        $master = $this->makeUser('Master', 0, $unitB, [$unitA, $unitB]);
        $cashier = $this->makeUser('CaixaLoja', 3, $unitA);

        $this->makePresence($master, 0, $unitB);
        $this->makePresence($cashier, 3, $unitA);

        $session = $this->activeSessionPayload($unitA, 4);

        $this->actingAs($viewer)
            ->withSession($session)
            ->post(route('online.messages.store'), [
                'recipient_user_id' => $master->id,
                'message' => 'Mensagem nao permitida',
            ])
            ->assertSessionHasErrors();

        $response = $this->actingAs($viewer)
            ->withSession($session)
            ->post(route('online.messages.store'), [
                'recipient_user_id' => $cashier->id,
                'message' => '[b]Pode atender o caixa?[/b]',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tb22_chat_mensagens', [
            'sender_id' => $viewer->id,
            'recipient_id' => $cashier->id,
            'message' => '[b]Pode atender o caixa?[/b]',
        ]);
    }

    public function test_funcionario_and_cliente_profiles_cannot_log_in(): void
    {
        $unit = $this->makeUnit('Loja Login');

        foreach ([5 => 'funcionario', 6 => 'cliente'] as $role => $username) {
            User::factory()->create([
                'name' => ucfirst($username),
                'email' => $username . '@paoecafe83.com.br',
                'password' => Hash::make('1234'),
                'funcao' => $role,
                'funcao_original' => $role,
                'tb2_id' => $unit->tb2_id,
            ])->units()->sync([$unit->tb2_id]);

            $this->post(route('login'), [
                'username' => $username,
                'password' => '1234',
                'unit_id' => $unit->tb2_id,
            ])->assertSessionHasErrors('username');

            $this->assertGuest();
        }
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

    private function makePresence(User $user, int $role, Unidade $unit): OnlineUser
    {
        return OnlineUser::create([
            'user_id' => $user->id,
            'session_id' => 'sessao-' . $user->id . '-' . fake()->unique()->numerify('###'),
            'active_role' => $role,
            'active_unit_id' => $unit->tb2_id,
            'last_seen_at' => now(),
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
