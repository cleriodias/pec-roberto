<?php

namespace Tests\Feature;

use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_user_updates_funcao_and_funcao_original_and_phone(): void
    {
        $unit = $this->makeUnit('Loja Gestao');

        $manager = User::factory()->create([
            'name' => 'Master Gestao',
            'email' => 'master.gestao@example.com',
            'funcao' => 0,
            'funcao_original' => 0,
            'tb2_id' => $unit->tb2_id,
        ]);
        $manager->units()->sync([$unit->tb2_id]);

        $targetUser = User::factory()->create([
            'name' => 'Usuario Teste',
            'email' => 'usuario.teste@example.com',
            'funcao' => 5,
            'funcao_original' => 5,
            'tb2_id' => $unit->tb2_id,
            'hr_ini' => '08:00',
            'hr_fim' => '17:00',
            'salario' => 1518.00,
            'vr_cred' => 350.00,
        ]);
        $targetUser->units()->sync([$unit->tb2_id]);

        $response = $this
            ->actingAs($manager)
            ->put(route('users.update', ['user' => $targetUser->id]), [
                'name' => 'Usuario Ajustado',
                'email' => 'usuario.ajustado@example.com',
                'phone' => '(62) 99999-8888',
                'funcao' => 3,
                'hr_ini' => '09:00',
                'hr_fim' => '18:00',
                'salario' => 2100.50,
                'vr_cred' => 420.75,
                'payment_day' => 10,
                'tb2_id' => [$unit->tb2_id],
            ]);

        $response->assertRedirect(route('users.show', ['user' => $targetUser->id]));

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Usuario Ajustado',
            'email' => 'usuario.ajustado@example.com',
            'phone' => '62999998888',
            'funcao' => 3,
            'funcao_original' => 3,
            'tb2_id' => $unit->tb2_id,
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
}
