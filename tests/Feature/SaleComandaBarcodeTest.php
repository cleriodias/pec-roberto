<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleComandaBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lanchonete_can_add_weighted_barcode_item_to_comanda(): void
    {
        $unit = $this->makeUnit('Loja Balanca');
        $user = $this->makeUser('Lanchonete', 4, $unit);
        $product = $this->makeProduct(12, 'Queijo Fatiado', '7890000000012', 18.90, 1);

        $response = $this
            ->actingAs($user)
            ->withSession($this->activeSessionPayload($unit, 4))
            ->postJson(route('sales.comandas.add-item', ['codigo' => 3000]), [
                'product_id' => $product->tb1_id,
                'quantity' => 1,
                'barcode' => '2001200004567',
                'access_user_id' => $user->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('items.0.product_id', 12)
            ->assertJsonPath('items.0.price', 4.56)
            ->assertJsonPath('items.0.line_id', 'product-12-price-4.56');

        $this->assertDatabaseHas('tb3_vendas', [
            'id_comanda' => 3000,
            'tb1_id' => 12,
            'valor_unitario' => 4.56,
            'quantidade' => 1,
            'id_unidade' => $unit->tb2_id,
            'id_lanc' => $user->id,
            'status' => 0,
        ]);
    }

    public function test_update_comanda_item_uses_line_id_to_keep_same_product_prices_separate(): void
    {
        $unit = $this->makeUnit('Loja Comanda');
        $user = $this->makeUser('Lanchonete', 4, $unit);
        $product = $this->makeProduct(12, 'Mussarela', '7890000000098', 10.00, 1);

        Venda::create([
            'tb1_id' => $product->tb1_id,
            'id_comanda' => 3000,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 4.56,
            'quantidade' => 1,
            'valor_total' => 4.56,
            'data_hora' => now(),
            'id_user_caixa' => null,
            'id_user_vale' => null,
            'id_lanc' => $user->id,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'faturar',
            'status_pago' => false,
            'status' => 0,
        ]);

        Venda::create([
            'tb1_id' => $product->tb1_id,
            'id_comanda' => 3000,
            'produto_nome' => $product->tb1_nome,
            'valor_unitario' => 7.89,
            'quantidade' => 2,
            'valor_total' => 15.78,
            'data_hora' => now(),
            'id_user_caixa' => null,
            'id_user_vale' => null,
            'id_lanc' => $user->id,
            'id_unidade' => $unit->tb2_id,
            'tipo_pago' => 'faturar',
            'status_pago' => false,
            'status' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession($this->activeSessionPayload($unit, 4))
            ->putJson(route('sales.comandas.update-item', [
                'codigo' => 3000,
                'productId' => 'product-12-price-4.56',
            ]), [
                'quantity' => 3,
                'access_user_id' => $user->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'line_id' => 'product-12-price-4.56',
                'product_id' => 12,
                'price' => 4.56,
                'quantity' => 3,
            ])
            ->assertJsonFragment([
                'line_id' => 'product-12-price-7.89',
                'product_id' => 12,
                'price' => 7.89,
                'quantity' => 2,
            ]);

        $this->assertDatabaseHas('tb3_vendas', [
            'id_comanda' => 3000,
            'tb1_id' => 12,
            'valor_unitario' => 4.56,
            'quantidade' => 3,
        ]);

        $this->assertDatabaseHas('tb3_vendas', [
            'id_comanda' => 3000,
            'tb1_id' => 12,
            'valor_unitario' => 7.89,
            'quantidade' => 2,
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

    private function makeProduct(
        int $id,
        string $name,
        string $barcode,
        float $salePrice,
        int $type = 0,
    ): Produto {
        return Produto::create([
            'tb1_id' => $id,
            'tb1_nome' => $name,
            'tb1_vlr_custo' => 0,
            'tb1_vlr_venda' => $salePrice,
            'tb1_codbar' => $barcode,
            'tb1_tipo' => $type,
            'tb1_status' => 1,
            'tb1_vr_credit' => false,
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
