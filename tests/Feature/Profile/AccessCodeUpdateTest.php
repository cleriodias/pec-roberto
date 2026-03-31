<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessCodeUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_code_can_be_updated_from_profile(): void
    {
        $user = User::factory()->create([
            'cod_acesso' => '1234',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/profile/access-code', [
                'cod_acesso' => '4321',
                'cod_acesso_confirmation' => '4321',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('4321', $user->refresh()->cod_acesso);
    }

    public function test_access_code_must_have_exactly_four_digits(): void
    {
        $user = User::factory()->create([
            'cod_acesso' => '1234',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/profile/access-code', [
                'cod_acesso' => '12a',
                'cod_acesso_confirmation' => '12a',
            ]);

        $response
            ->assertSessionHasErrors('cod_acesso')
            ->assertRedirect('/profile');

        $this->assertSame('1234', $user->refresh()->cod_acesso);
    }
}
