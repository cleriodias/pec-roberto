<?php

namespace Tests\Unit;

use App\Models\ConfiguracaoFiscal;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaPagamento;
use App\Support\FiscalCertificateService;
use App\Support\FiscalMunicipalityCodeService;
use App\Support\FiscalNfceXmlService;
use App\Support\FiscalInvoicePreparationService;
use App\Support\FiscalWebserviceResolverService;
use ReflectionClass;
use Tests\TestCase;

class FiscalInvoicePreparationServiceTest extends TestCase
{
    public function test_validate_payload_requires_unit_configuration(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, null, 'nfce', null, $eligibleSales, $excludedItems);

        $this->assertSame(
            ['Configure a emissao fiscal da unidade antes de gerar a nota.'],
            $errors
        );
    }

    public function test_validate_payload_accepts_complete_configuration_and_product(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        $config = $this->makeConfiguration();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', null, $eligibleSales, $excludedItems);

        $this->assertSame([], $errors);
    }

    public function test_validate_payload_accepts_fiscal_coupon_with_cpf_only(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        $config = $this->makeConfiguration();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', [
            'type' => 'cupom_fiscal',
            'document' => '12345678901',
        ], $eligibleSales, $excludedItems);

        $this->assertSame([], $errors);
    }

    public function test_validate_payload_requires_full_address_for_nf_consumidor(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        $config = $this->makeConfiguration();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', [
            'type' => 'consumidor',
            'name' => 'RODRIGO TESTE',
            'document' => '12345678901',
        ], $eligibleSales, $excludedItems);

        $this->assertContains('Logradouro do consumidor nao informado para a NF Consumidor.', $errors);
        $this->assertContains('CEP do consumidor invalido para a NF Consumidor.', $errors);
    }

    public function test_validate_payload_flags_when_all_items_are_without_minimum_tax_fields(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct([
            'tb1_ncm' => null,
            'tb1_cfop' => null,
            'tb1_csosn' => null,
            'tb1_cst' => null,
        ]);
        $config = $this->makeConfiguration();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', null, $eligibleSales, $excludedItems);

        $productError = collect($errors)->first(fn (string $error) => str_contains($error, 'Nenhum item da venda possui dados fiscais minimos'));

        $this->assertNotNull($productError);
        $this->assertStringContainsString('COXINHA', $productError);
    }

    public function test_validate_payload_flags_certificate_from_another_store(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct([], '11.222.333/0001-44');
        $config = $this->makeConfiguration([
            'tb26_certificado_cnpj' => '99888777000166',
        ]);
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', null, $eligibleSales, $excludedItems);

        $this->assertContains(
            'O CNPJ do certificado nao pertence ao mesmo CNPJ base da loja da venda.',
            $errors
        );
    }

    public function test_validate_payload_flags_municipality_code_from_another_uf(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        $config = $this->makeConfiguration([
            'tb26_uf' => 'GO',
            'tb26_codigo_municipio' => '5300108',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
        ]);
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', null, $eligibleSales, $excludedItems);

        $this->assertContains(
            'O codigo do municipio IBGE 5300108 nao pertence a UF GO informada na configuracao fiscal. Use um codigo iniciado por 52.',
            $errors
        );
    }

    public function test_validate_payload_flags_invalid_state_registration(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $validatePayload = $reflection->getMethod('validatePayload');
        $validatePayload->setAccessible(true);

        $payment = $this->makePaymentWithFiscalProduct();
        $config = $this->makeConfiguration([
            'tb26_ie' => 'ABCD',
        ]);
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $errors = $validatePayload->invoke($service, $payment, $config, 'nfce', null, $eligibleSales, $excludedItems);

        $this->assertContains(
            'A inscricao estadual da unidade esta invalida para emissao fiscal. Informe apenas digitos ou ISENTO.',
            $errors
        );
    }

    public function test_supports_automatic_fiscal_generation_only_for_cash_and_card_payments(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $supportsMethod = $reflection->getMethod('supportsAutomaticFiscalGenerationForPaymentType');
        $supportsMethod->setAccessible(true);

        $this->assertTrue($supportsMethod->invoke($service, 'dinheiro'));
        $this->assertTrue($supportsMethod->invoke($service, 'cartao_credito'));
        $this->assertTrue($supportsMethod->invoke($service, 'cartao_debito'));
        $this->assertTrue($supportsMethod->invoke($service, 'dinheiro_cartao_credito'));
        $this->assertTrue($supportsMethod->invoke($service, 'dinheiro_cartao_debito'));
        $this->assertTrue($supportsMethod->invoke($service, 'maquina'));

        $this->assertFalse($supportsMethod->invoke($service, 'vale'));
        $this->assertFalse($supportsMethod->invoke($service, 'refeicao'));
        $this->assertFalse($supportsMethod->invoke($service, 'faturar'));
    }

    public function test_build_non_fiscal_payment_message_marks_internal_control_payments(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $messageMethod = $reflection->getMethod('buildNonFiscalPaymentMessage');
        $messageMethod->setAccessible(true);

        $this->assertSame(
            'Pagamento Vale e apenas controle interno e nao gera nota fiscal automatica.',
            $messageMethod->invoke($service, 'vale')
        );
        $this->assertSame(
            'Pagamento Refeicao e apenas controle interno e nao gera nota fiscal automatica.',
            $messageMethod->invoke($service, 'refeicao')
        );
        $this->assertSame(
            'Pagamento Faturar e apenas controle interno e nao gera nota fiscal automatica.',
            $messageMethod->invoke($service, 'faturar')
        );
    }

    private function makePaymentWithFiscalProduct(
        array $productOverrides = [],
        string $unitCnpj = '11.222.333/0001-44',
    ): VendaPagamento
    {
        $product = new Produto(array_merge([
            'tb1_id' => 10,
            'tb1_nome' => 'COXINHA',
            'tb1_codbar' => '7891234567890',
            'tb1_ncm' => '19059090',
            'tb1_cest' => '1704901',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_cst' => null,
            'tb1_aliquota_icms' => 0,
        ], $productOverrides));

        $sale = new Venda([
            'tb1_id' => 10,
            'produto_nome' => 'COXINHA',
            'quantidade' => 2,
            'valor_unitario' => 7.5,
            'valor_total' => 15,
        ]);
        $sale->setRelation('produto', $product);
        $sale->setRelation('unidade', new \App\Models\Unidade([
            'tb2_id' => 1,
            'tb2_nome' => 'LOJA TESTE',
            'tb2_cnpj' => $unitCnpj,
        ]));

        $payment = new VendaPagamento([
            'tb4_id' => 99,
            'valor_total' => 15,
            'tipo_pagamento' => 'dinheiro',
        ]);
        $payment->setRelation('vendas', collect([$sale]));

        return $payment;
    }

    public function test_build_payload_keeps_only_items_with_minimum_tax_data(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $buildPayload = $reflection->getMethod('buildPayload');
        $buildPayload->setAccessible(true);

        $eligibleProduct = new Produto([
            'tb1_id' => 10,
            'tb1_nome' => 'COXINHA',
            'tb1_codbar' => '7891234567890',
            'tb1_ncm' => '19059090',
            'tb1_cest' => '1704901',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_cst' => null,
            'tb1_aliquota_icms' => 0,
        ]);

        $excludedProduct = new Produto([
            'tb1_id' => 11,
            'tb1_nome' => 'REFRIGERANTE',
            'tb1_codbar' => '7890000000000',
            'tb1_ncm' => null,
            'tb1_cest' => null,
            'tb1_cfop' => null,
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => null,
            'tb1_cst' => null,
            'tb1_aliquota_icms' => 0,
        ]);

        $unit = new \App\Models\Unidade([
            'tb2_id' => 1,
            'tb2_nome' => 'LOJA TESTE',
            'tb2_cnpj' => '11.222.333/0001-44',
        ]);

        $eligibleSale = new Venda([
            'tb1_id' => 10,
            'produto_nome' => 'COXINHA',
            'quantidade' => 2,
            'valor_unitario' => 7.5,
            'valor_total' => 15,
        ]);
        $eligibleSale->setRelation('produto', $eligibleProduct);
        $eligibleSale->setRelation('unidade', $unit);

        $excludedSale = new Venda([
            'tb1_id' => 11,
            'produto_nome' => 'REFRIGERANTE',
            'quantidade' => 1,
            'valor_unitario' => 10,
            'valor_total' => 10,
        ]);
        $excludedSale->setRelation('produto', $excludedProduct);
        $excludedSale->setRelation('unidade', $unit);

        $payment = new VendaPagamento([
            'tb4_id' => 99,
            'valor_total' => 25,
            'tipo_pagamento' => 'dinheiro',
        ]);
        $payment->setRelation('vendas', collect([$eligibleSale, $excludedSale]));

        $config = $this->makeConfiguration();
        [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment, $service);

        $payload = $buildPayload->invoke(
            $service,
            $payment,
            $config,
            'nfce',
            'homologacao',
            '1',
            1,
            null,
            $eligibleSales,
            $excludedItems,
        );

        $this->assertCount(1, $payload['itens']);
        $this->assertSame('COXINHA', $payload['itens'][0]['descricao']);
        $this->assertSame(1, $payload['itens_excluidos_qtd']);
        $this->assertSame('REFRIGERANTE', $payload['itens_excluidos'][0]['descricao']);
        $this->assertSame(15.0, $payload['valor_total_documento']);
        $this->assertSame(25.0, $payload['valor_total_venda']);
    }

    public function test_resolve_consumer_payload_normalizes_fiscal_coupon_type(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveConsumerPayload');
        $method->setAccessible(true);

        $consumer = $method->invoke($service, null, [
            'type' => 'cupom_fiscal',
            'document' => '123.456.789-01',
            'name' => 'IGNORAR',
            'cep' => '72920-076',
        ]);

        $this->assertSame('cupom_fiscal', $consumer['type']);
        $this->assertSame('12345678901', $consumer['document']);
        $this->assertSame('', $consumer['name']);
        $this->assertNull($consumer['cep']);
    }

    private function splitSalesForFiscal(VendaPagamento $payment, FiscalInvoicePreparationService $service): array
    {
        $reflection = new ReflectionClass($service);
        $splitMethod = $reflection->getMethod('splitSalesForFiscal');
        $splitMethod->setAccessible(true);

        return $splitMethod->invoke($service, $payment);
    }

    private function makeConfiguration(array $overrides = []): ConfiguracaoFiscal
    {
        $attributes = array_merge([
            'tb2_id' => 1,
            'tb26_emitir_nfce' => true,
            'tb26_emitir_nfe' => false,
            'tb26_ambiente' => 'homologacao',
            'tb26_serie' => '1',
            'tb26_proximo_numero' => 1,
            'tb26_crt' => 1,
            'tb26_csc_id' => '1',
            'tb26_csc' => 'TOKENCSC',
            'tb26_certificado_tipo' => 'A1',
            'tb26_certificado_nome' => 'CERTIFICADO LOJA TESTE',
            'tb26_certificado_cnpj' => '11222333000144',
            'tb26_certificado_arquivo' => 'private/fiscal-certificados/1/certificado.pfx',
            'tb26_certificado_senha' => 'segredo',
            'tb26_certificado_senha_compartilhada' => 'segredo',
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_ie' => '123456789',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_codigo_municipio' => '5300108',
            'tb26_municipio' => 'BRASILIA',
            'tb26_uf' => 'DF',
            'tb26_cep' => '70000000',
        ], $overrides);

        $configuration = new ConfiguracaoFiscal();
        $configuration->setRawAttributes($attributes, true);

        return $configuration;
    }

    private function makeService(): FiscalInvoicePreparationService
    {
        return new FiscalInvoicePreparationService(
            new FiscalCertificateService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
            new FiscalMunicipalityCodeService(),
        );
    }
}
