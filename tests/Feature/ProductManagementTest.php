<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    public function test_prepare_product_data_generates_sem_prefixed_barcode_for_new_balance_product(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $product = new Produto([
            'tb1_id' => 30,
            'tb1_codbar' => '',
            'tb1_tipo' => 1,
        ]);

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 30,
            'tb1_nome' => 'Biscoito de Queijo',
            'tb1_tipo' => 1,
        ], $product);

        $this->assertSame('SEM-30', $result['tb1_codbar']);
    }

    public function test_prepare_product_data_keeps_existing_internal_barcode_for_balance_product(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $product = new Produto([
            'tb1_id' => 30,
            'tb1_nome' => 'Biscoito de Queijo',
            'tb1_vlr_custo' => 3,
            'tb1_vlr_venda' => 4,
            'tb1_codbar' => 'SEM-30',
            'tb1_tipo' => 1,
            'tb1_status' => 1,
            'tb1_vr_credit' => true,
        ]);
        $product->exists = true;

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 30,
            'tb1_nome' => 'Biscoito de Queijo Atualizado',
            'tb1_tipo' => 1,
        ], $product);

        $this->assertSame('SEM-30', $result['tb1_codbar']);
        $this->assertArrayNotHasKey('tb1_id', $result);
    }

    public function test_sub_manager_can_change_product_prices(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $ensurePriceEditingIsAuthorized = $reflection->getMethod('ensurePriceEditingIsAuthorized');
        $ensurePriceEditingIsAuthorized->setAccessible(true);

        $product = new Produto([
            'tb1_vlr_custo' => 3,
            'tb1_vlr_venda' => 5,
        ]);

        $user = new User([
            'funcao' => 2,
            'funcao_original' => 2,
        ]);

        $ensurePriceEditingIsAuthorized->invoke($controller, [
            'tb1_vlr_custo' => 4,
            'tb1_vlr_venda' => 6,
        ], $product, $user);

        $this->assertTrue(true);
    }

    public function test_cashier_cannot_change_product_prices(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $ensurePriceEditingIsAuthorized = $reflection->getMethod('ensurePriceEditingIsAuthorized');
        $ensurePriceEditingIsAuthorized->setAccessible(true);

        $product = new Produto([
            'tb1_vlr_custo' => 3,
            'tb1_vlr_venda' => 5,
        ]);

        $user = new User([
            'funcao' => 3,
            'funcao_original' => 3,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $ensurePriceEditingIsAuthorized->invoke($controller, [
                'tb1_vlr_custo' => 4,
                'tb1_vlr_venda' => 6,
            ], $product, $user);
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof ValidationException) {
                $this->assertSame(
                    'Apenas Master, Gerente e Sub-Gerente podem alterar o valor de custo.',
                    $previous->errors()['tb1_vlr_custo'][0] ?? null
                );
                $this->assertSame(
                    'Apenas Master, Gerente e Sub-Gerente podem alterar o valor de venda.',
                    $previous->errors()['tb1_vlr_venda'][0] ?? null
                );
            }

            throw $previous ?? $exception;
        }
    }
}
