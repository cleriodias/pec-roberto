<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    public function test_prepare_product_data_uses_product_id_as_barcode_for_new_balance_product(): void
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

        $this->assertSame('30', $result['tb1_codbar']);
    }

    public function test_prepare_product_data_keeps_product_id_as_barcode_for_existing_balance_product(): void
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
            'tb1_codbar' => '30',
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

        $this->assertSame('30', $result['tb1_codbar']);
        $this->assertArrayNotHasKey('tb1_id', $result);
    }

    public function test_prepare_product_data_uses_product_id_as_barcode_when_product_has_no_barcode(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 3200,
            'tb1_nome' => 'Pudim',
            'tb1_tipo' => 0,
            'sem_codigo_barras' => true,
            'tb1_codbar' => '',
        ]);

        $this->assertSame('3200', $result['tb1_codbar']);
        $this->assertArrayNotHasKey('sem_codigo_barras', $result);
    }

    public function test_prepare_product_data_keeps_informed_barcode_when_product_has_own_barcode(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 3201,
            'tb1_nome' => 'Bolo',
            'tb1_tipo' => 0,
            'sem_codigo_barras' => false,
            'tb1_codbar' => '7891234567890',
        ]);

        $this->assertSame('7891234567890', $result['tb1_codbar']);
        $this->assertArrayNotHasKey('sem_codigo_barras', $result);
    }

    public function test_prepare_product_data_keeps_quantity_for_non_production_product(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 3202,
            'tb1_nome' => 'Suco de Laranja',
            'tb1_tipo' => 0,
            'tb1_qtd' => '12',
            'tb1_codbar' => '7891234567000',
        ]);

        $this->assertSame(12, $result['tb1_qtd']);
    }

    public function test_validate_product_allows_empty_payload_without_required_errors(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $validateProduct = $reflection->getMethod('validateProduct');
        $validateProduct->setAccessible(true);

        $request = Request::create('/products', 'POST', []);

        $result = $validateProduct->invoke($controller, $request);

        $this->assertSame([], $result);
    }

    public function test_prepare_product_data_applies_safe_defaults_when_fields_are_empty(): void
    {
        $controller = new ProductController();
        $reflection = new ReflectionClass($controller);
        $prepareProductData = $reflection->getMethod('prepareProductData');
        $prepareProductData->setAccessible(true);

        $result = $prepareProductData->invoke($controller, [
            'tb1_id' => 3203,
            'tb1_codbar' => '',
            'tb1_vlr_custo' => null,
            'tb1_vlr_venda' => null,
            'tb1_qtd' => null,
            'tb1_status' => null,
        ]);

        $this->assertSame('', $result['tb1_nome']);
        $this->assertSame('3203', $result['tb1_codbar']);
        $this->assertSame(0.0, $result['tb1_vlr_custo']);
        $this->assertSame(0.0, $result['tb1_vlr_venda']);
        $this->assertSame(0, $result['tb1_tipo']);
        $this->assertSame(1, $result['tb1_status']);
        $this->assertSame(0, $result['tb1_qtd']);
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
