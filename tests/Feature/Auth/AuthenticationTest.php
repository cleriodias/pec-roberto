<?php

namespace Tests\Feature\Auth;

use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $user = User::factory()->create([
            'email' => 'usuario.login@paoecafepremium.com.br',
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $user->units()->sync([$unit->tb2_id]);

        $response = $this->post('/login', [
            'username' => 'usuario.login',
            'password' => 'password',
            'unit_id' => $unit->tb2_id,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $user = User::factory()->create([
            'email' => 'usuario.senha@paoecafepremium.com.br',
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $unit->tb2_id,
        ]);
        $user->units()->sync([$unit->tb2_id]);

        $this->post('/login', [
            'username' => 'usuario.senha',
            'password' => 'wrong-password',
            'unit_id' => $unit->tb2_id,
        ]);

        $this->assertGuest();
    }

    public function test_login_units_returns_only_units_available_to_username(): void
    {
        $primaryUnit = $this->makeUnit('Loja A');
        $linkedUnit = $this->makeUnit('Loja B');
        $unlinkedUnit = $this->makeUnit('Loja C');
        $inactiveUnit = $this->makeUnit('Loja D', 0);

        $user = User::factory()->create([
            'email' => 'usuario.lojas@paoecafepremium.com.br',
            'funcao' => 1,
            'funcao_original' => 1,
            'tb2_id' => $primaryUnit->tb2_id,
        ]);
        $user->units()->sync([$linkedUnit->tb2_id, $inactiveUnit->tb2_id]);

        $response = $this->getJson(route('login.units', ['username' => 'usuario.lojas']));

        $response
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['tb2_id' => $primaryUnit->tb2_id, 'tb2_nome' => 'Loja A'])
            ->assertJsonFragment(['tb2_id' => $linkedUnit->tb2_id, 'tb2_nome' => 'Loja B'])
            ->assertJsonMissing(['tb2_id' => $unlinkedUnit->tb2_id])
            ->assertJsonMissing(['tb2_id' => $inactiveUnit->tb2_id]);
    }

    public function test_users_can_logout(): void
    {
        $unit = $this->makeUnit('Loja Centro');
        $user = User::factory()->create([
            'tb2_id' => $unit->tb2_id,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function makeUnit(string $name, int $status = 1): Unidade
    {
        return Unidade::create([
            'tb2_nome' => $name,
            'tb2_endereco' => 'Rua Teste, 123',
            'tb2_cep' => '00000-000',
            'tb2_fone' => '(11) 99999-9999',
            'tb2_cnpj' => '00.000.000/0001-00',
            'tb2_localizacao' => 'Teste',
            'tb2_status' => $status,
        ]);
    }
}
