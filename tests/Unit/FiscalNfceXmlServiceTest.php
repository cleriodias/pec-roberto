<?php

namespace Tests\Unit;

use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\VendaPagamento;
use App\Support\FiscalNfceXmlService;
use App\Support\FiscalWebserviceResolverService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use App\Models\Venda;
use ReflectionClass;
use Tests\TestCase;

class FiscalNfceXmlServiceTest extends TestCase
{
    public function test_append_supplemental_info_adds_qrcode_and_url_chave(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('appendSupplementalInfo');
        $method->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $nfe = $document->createElementNS('http://www.portalfiscal.inf.br/nfe', 'NFe');
        $document->appendChild($nfe);

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
            'tb26_csc_id' => '1',
            'tb26_csc' => 'ABC123',
        ]);

        $method->invoke(
            $service,
            $document,
            $nfe,
            $config,
            '52260411222333000144650010000012341000012345',
            [
                'qr_code_url' => 'https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe',
                'consultation_url' => 'https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa',
            ],
        );

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<infNFeSupl>', $xml);
        $this->assertStringContainsString(
            '<qrCode>https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe?p=52260411222333000144650010000012341000012345|2|2|1|C92BCAC16BD47A307A3922A655F169F2644FF60E</qrCode>',
            $xml
        );
        $this->assertStringContainsString(
            '<urlChave>https://nfewebhomolog.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa</urlChave>',
            $xml
        );
    }

    public function test_append_emitter_and_items_normalize_numeric_fields_for_schema(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendEmitter = $reflection->getMethod('appendEmitter');
        $appendEmitter->setAccessible(true);
        $appendItems = $reflection->getMethod('appendItems');
        $appendItems->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $document->createElement('infNFe');
        $document->appendChild($infNfe);

        $config = new ConfiguracaoFiscal([
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_codigo_municipio' => '52.087-07',
            'tb26_municipio' => 'GOIANIA',
            'tb26_uf' => 'GO',
            'tb26_cep' => '74.000-000',
            'tb26_ie' => '12.345.678-9',
            'tb26_crt' => 1,
        ]);

        $product = new Produto([
            'tb1_ncm' => '19.05.9090',
            'tb1_cest' => '17.049.01',
            'tb1_cfop' => '5.102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => '7891234567890',
        ]);

        $sale = new Venda([
            'tb1_id' => 10,
            'produto_nome' => 'COXINHA',
            'quantidade' => 1,
            'valor_unitario' => 7.5,
            'valor_total' => 7.5,
        ]);
        $sale->setRelation('produto', $product);

        $appendEmitter->invoke($service, $document, $infNfe, $config, '11222333000144');
        $appendItems->invoke($service, $document, $infNfe, collect([$sale]), 1, $config);

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<cMun>5208707</cMun>', $xml);
        $this->assertStringContainsString('<CEP>74000000</CEP>', $xml);
        $this->assertStringContainsString('<IE>123456789</IE>', $xml);
        $this->assertStringContainsString('<NCM>19059090</NCM>', $xml);
        $this->assertStringContainsString('<CEST>1704901</CEST>', $xml);
        $this->assertStringContainsString('<CFOP>5102</CFOP>', $xml);
        $this->assertStringNotContainsString('<CNAE>', $xml);
    }

    public function test_append_emitter_only_includes_cnae_when_municipal_registration_exists(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendEmitter = $reflection->getMethod('appendEmitter');
        $appendEmitter->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $document->createElement('infNFe');
        $document->appendChild($infNfe);

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'producao',
        ]);

        $config = new ConfiguracaoFiscal([
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_codigo_municipio' => '5208707',
            'tb26_municipio' => 'GOIANIA',
            'tb26_uf' => 'GO',
            'tb26_cep' => '74000000',
            'tb26_ie' => '123456789',
            'tb26_im' => '998877',
            'tb26_cnae' => '4721102',
            'tb26_crt' => 1,
        ]);

        $appendEmitter->invoke($service, $document, $infNfe, $config, '11222333000144');

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<IM>998877</IM>', $xml);
        $this->assertStringContainsString('<CNAE>4721102</CNAE>', $xml);
    }

    public function test_append_emitter_escapes_ampersand_in_company_names(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendEmitter = $reflection->getMethod('appendEmitter');
        $appendEmitter->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $document->createElement('infNFe');
        $document->appendChild($infNfe);

        $config = new ConfiguracaoFiscal([
            'tb26_razao_social' => 'PAO & CAFE PADARIA LTDA',
            'tb26_nome_fantasia' => 'PAO & CAFE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_codigo_municipio' => '5200258',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
            'tb26_uf' => 'GO',
            'tb26_cep' => '72920539',
            'tb26_ie' => '202165639',
            'tb26_crt' => 1,
        ]);

        $appendEmitter->invoke($service, $document, $infNfe, $config, '58493190000106');

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<xNome>PAO &amp; CAFE PADARIA LTDA</xNome>', $xml);
        $this->assertStringContainsString('<xFant>PAO &amp; CAFE</xFant>', $xml);
    }

    public function test_append_items_limits_unit_values_to_four_decimal_places(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendItems = $reflection->getMethod('appendItems');
        $appendItems->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $document->createElement('infNFe');
        $document->appendChild($infNfe);

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'producao',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 10,
            'produto_nome' => 'COXINHA',
            'quantidade' => 1,
            'valor_unitario' => 7.123456789,
            'valor_total' => 7.12,
        ]);
        $sale->setRelation('produto', $product);

        $appendItems->invoke($service, $document, $infNfe, collect([$sale]), 1, $config);

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<vUnCom>7.1235</vUnCom>', $xml);
        $this->assertStringContainsString('<vUnTrib>7.1235</vUnTrib>', $xml);
    }

    public function test_append_items_uses_homologation_description_on_first_item(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendItems = $reflection->getMethod('appendItems');
        $appendItems->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $document->createElement('infNFe');
        $document->appendChild($infNfe);

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 10,
            'produto_nome' => 'COXINHA',
            'quantidade' => 1,
            'valor_unitario' => 7.5,
            'valor_total' => 7.5,
        ]);
        $sale->setRelation('produto', $product);

        $appendItems->invoke($service, $document, $infNfe, collect([$sale]), 1, $config);

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringContainsString(
            '<xProd>NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xProd>',
            $xml
        );
    }

    public function test_append_rtc_tax_group_includes_required_gross_tax_fields(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('appendRtcTaxGroup');
        $method->setAccessible(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $imposto = $document->createElement('imposto');
        $document->appendChild($imposto);

        $method->invoke($service, $document, $imposto, [
            'CST' => '000',
            'cClassTrib' => '000001',
            'indDoacao' => false,
            'vBC' => '12.00',
            'pIBSUF' => '0.1000',
            'vIBSUF' => '0.01',
            'pIBSMun' => '0.0000',
            'vIBSMun' => '0.00',
            'vIBS' => '0.01',
            'pCBS' => '0.9000',
            'vCBS' => '0.11',
            'vIS' => '0.00',
        ]);

        $xml = $document->saveXML();

        $this->assertNotFalse($xml);
        $this->assertStringNotContainsString('<vIS>', $xml);
        $this->assertStringContainsString('<gIBSUF><pIBSUF>0.1000</pIBSUF><vIBSUF>0.01</vIBSUF></gIBSUF>', $xml);
        $this->assertStringContainsString('<gIBSMun><pIBSMun>0.0000</pIBSMun><vIBSMun>0.00</vIBSMun></gIBSMun>', $xml);
        $this->assertStringContainsString('<gCBS><pCBS>0.9000</pCBS><vCBS>0.11</vCBS></gCBS>', $xml);
    }

    public function test_append_payment_uses_official_codes_for_card_and_splits_cash_with_card(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());
        $reflection = new ReflectionClass($service);
        $appendPayment = $reflection->getMethod('appendPayment');
        $appendPayment->setAccessible(true);

        $legacyMachineDocument = new DOMDocument('1.0', 'UTF-8');
        $legacyMachineInfNfe = $legacyMachineDocument->createElement('infNFe');
        $legacyMachineDocument->appendChild($legacyMachineInfNfe);

        $legacyMachinePayment = new VendaPagamento([
            'tipo_pagamento' => 'maquina',
            'valor_total' => 4.2,
            'troco' => 0,
        ]);

        $appendPayment->invoke($service, $legacyMachineDocument, $legacyMachineInfNfe, $legacyMachinePayment, 4.2);

        $legacyMachineXml = $legacyMachineDocument->saveXML();

        $this->assertNotFalse($legacyMachineXml);
        $this->assertStringContainsString('<tPag>03</tPag>', $legacyMachineXml);
        $this->assertStringNotContainsString('<xPag>', $legacyMachineXml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $legacyMachineXml);

        $creditDocument = new DOMDocument('1.0', 'UTF-8');
        $creditInfNfe = $creditDocument->createElement('infNFe');
        $creditDocument->appendChild($creditInfNfe);

        $creditPayment = new VendaPagamento([
            'tipo_pagamento' => 'cartao_credito',
            'valor_total' => 4.2,
            'troco' => 0,
        ]);

        $appendPayment->invoke($service, $creditDocument, $creditInfNfe, $creditPayment, 4.2);

        $creditXml = $creditDocument->saveXML();

        $this->assertNotFalse($creditXml);
        $this->assertStringContainsString('<tPag>03</tPag>', $creditXml);
        $this->assertStringNotContainsString('<xPag>', $creditXml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $creditXml);

        $debitDocument = new DOMDocument('1.0', 'UTF-8');
        $debitInfNfe = $debitDocument->createElement('infNFe');
        $debitDocument->appendChild($debitInfNfe);

        $debitPayment = new VendaPagamento([
            'tipo_pagamento' => 'cartao_debito',
            'valor_total' => 4.2,
            'troco' => 0,
        ]);

        $appendPayment->invoke($service, $debitDocument, $debitInfNfe, $debitPayment, 4.2);

        $debitXml = $debitDocument->saveXML();

        $this->assertNotFalse($debitXml);
        $this->assertStringContainsString('<tPag>04</tPag>', $debitXml);
        $this->assertStringNotContainsString('<xPag>', $debitXml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $debitXml);

        $mixedDocument = new DOMDocument('1.0', 'UTF-8');
        $mixedInfNfe = $mixedDocument->createElement('infNFe');
        $mixedDocument->appendChild($mixedInfNfe);

        $mixedPayment = new VendaPagamento([
            'tipo_pagamento' => 'dinheiro_cartao_debito',
            'valor_total' => 10.0,
            'valor_pago' => 6.0,
            'troco' => 0,
            'dois_pgto' => 4.0,
        ]);

        $appendPayment->invoke($service, $mixedDocument, $mixedInfNfe, $mixedPayment, 10.0);

        $mixedXml = $mixedDocument->saveXML();

        $this->assertNotFalse($mixedXml);
        $this->assertStringContainsString('<tPag>01</tPag>', $mixedXml);
        $this->assertStringContainsString('<vPag>6.00</vPag>', $mixedXml);
        $this->assertStringContainsString('<tPag>04</tPag>', $mixedXml);
        $this->assertStringContainsString('<vPag>4.00</vPag>', $mixedXml);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $mixedXml);

        $valeDocument = new DOMDocument('1.0', 'UTF-8');
        $valeInfNfe = $valeDocument->createElement('infNFe');
        $valeDocument->appendChild($valeInfNfe);

        $valePayment = new VendaPagamento([
            'tipo_pagamento' => 'vale',
            'valor_total' => 4.2,
            'troco' => 0,
        ]);

        $appendPayment->invoke($service, $valeDocument, $valeInfNfe, $valePayment, 4.2);

        $valeXml = $valeDocument->saveXML();

        $this->assertNotFalse($valeXml);
        $this->assertStringContainsString('<tPag>10</tPag>', $valeXml);
        $this->assertStringNotContainsString('<xPag>', $valeXml);
        $this->assertStringNotContainsString('<card>', $valeXml);
    }

    public function test_build_signed_xml_keeps_official_root_order_with_supplemental_info_before_signature(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
            'tb26_uf' => 'GO',
            'tb26_codigo_municipio' => '5200258',
            'tb26_serie' => '1',
            'tb26_crt' => 1,
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
            'tb26_cep' => '72920076',
            'tb26_ie' => '123456789',
            'tb26_csc_id' => '1',
            'tb26_csc' => 'ABC123',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 1,
            'id_unidade' => 3,
            'produto_nome' => 'PAO DE SAL',
            'quantidade' => 2,
            'valor_unitario' => 1.5,
            'valor_total' => 3.0,
        ]);
        $sale->setRelation('produto', $product);
        $sale->setRelation('unidade', new Unidade([
            'tb2_id' => 3,
            'tb2_nome' => 'BARRAGEM 1',
            'tb2_cnpj' => '62074471000156',
        ]));

        $sales = collect([$sale]);

        $payment = new VendaPagamento([
            'tb4_id' => 999,
            'tipo_pagamento' => 'dinheiro',
            'valor_total' => 3.0,
            'troco' => 0,
        ]);
        $payment->setRelation('vendas', $sales);

        $invoice = new NotaFiscal([
            'tb27_modelo' => 'nfce',
            'tb27_serie' => '1',
            'tb27_numero' => 1,
        ]);

        $certificateData = $this->createCertificateData();

        $result = $service->buildSignedXml($invoice, $payment, $sales, null, $config, $certificateData);

        $document = new DOMDocument();
        $loaded = @$document->loadXML($result['xml']);

        $this->assertTrue($loaded);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $this->assertSame(0.0, $xpath->evaluate('count(/nfe:NFe/nfe:infNFe/nfe:dest)'));
        $this->assertSame(
            '4',
            (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:ide/nfe:tpImp)')
        );
        $this->assertSame(
            'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
            (string) $xpath->evaluate('string(/nfe:NFe/ds:Signature/ds:SignedInfo/ds:SignatureMethod/@Algorithm)')
        );
        $this->assertSame(
            'http://www.w3.org/2000/09/xmldsig#sha1',
            (string) $xpath->evaluate('string(/nfe:NFe/ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestMethod/@Algorithm)')
        );

        $rootChildren = [];

        foreach ($document->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $rootChildren[] = $child->localName;
            }
        }

        $this->assertSame(['infNFe', 'infNFeSupl', 'Signature'], $rootChildren);

        $this->assertSame('http://www.w3.org/2000/09/xmldsig#', $document->getElementsByTagName('Signature')->item(0)?->namespaceURI);
        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/ds:Signature/ds:SignedInfo)'));
        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/ds:Signature/ds:SignedInfo/ds:Reference)'));
        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/ds:Signature/ds:SignatureValue)'));
        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate)'));
    }

    public function test_build_signed_xml_adds_destination_when_consumer_is_informed(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
            'tb26_uf' => 'GO',
            'tb26_codigo_municipio' => '5200258',
            'tb26_serie' => '1',
            'tb26_crt' => 1,
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
            'tb26_cep' => '72920076',
            'tb26_ie' => '123456789',
            'tb26_csc_id' => '1',
            'tb26_csc' => 'ABC123',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 1,
            'id_unidade' => 3,
            'produto_nome' => 'PAO DE SAL',
            'quantidade' => 2,
            'valor_unitario' => 1.5,
            'valor_total' => 3.0,
        ]);
        $sale->setRelation('produto', $product);
        $sale->setRelation('unidade', new Unidade([
            'tb2_id' => 3,
            'tb2_nome' => 'BARRAGEM 1',
            'tb2_cnpj' => '62074471000156',
        ]));

        $sales = collect([$sale]);

        $payment = new VendaPagamento([
            'tb4_id' => 1002,
            'tipo_pagamento' => 'dinheiro',
            'valor_total' => 3.0,
            'troco' => 0,
        ]);
        $payment->setRelation('vendas', $sales);

        $invoice = new NotaFiscal([
            'tb27_modelo' => 'nfce',
            'tb27_serie' => '1',
            'tb27_numero' => 2,
        ]);

        $certificateData = $this->createCertificateData();
        $consumer = [
            'name' => 'RODRIGO TESTE',
            'document' => '12345678901',
            'cep' => '72920076',
            'street' => 'RUA DO CONSUMIDOR',
            'number' => '123',
            'complement' => 'CASA',
            'neighborhood' => 'CENTRO',
            'city' => 'AGUAS LINDAS DE GOIAS',
            'city_code' => '5200258',
            'state' => 'GO',
        ];

        $result = $service->buildSignedXml($invoice, $payment, $sales, $consumer, $config, $certificateData);

        $document = new DOMDocument();
        $loaded = @$document->loadXML($result['xml']);

        $this->assertTrue($loaded);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/nfe:infNFe/nfe:dest)'));
        $this->assertSame('12345678901', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:CPF)'));
        $this->assertSame('RODRIGO TESTE', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:xNome)'));
        $this->assertSame('5200258', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:enderDest/nfe:cMun)'));
        $this->assertSame('RUA DO CONSUMIDOR', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:enderDest/nfe:xLgr)'));
        $this->assertSame('9', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:indIEDest)'));

        $destinationChildren = [];

        foreach ($xpath->query('/nfe:NFe/nfe:infNFe/nfe:dest/*') as $child) {
            $destinationChildren[] = $child->localName;
        }

        $this->assertSame(['CPF', 'xNome', 'enderDest', 'indIEDest'], $destinationChildren);
    }

    public function test_build_signed_xml_adds_fiscal_coupon_destination_with_cpf_only(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
            'tb26_uf' => 'GO',
            'tb26_codigo_municipio' => '5200258',
            'tb26_serie' => '1',
            'tb26_crt' => 1,
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
            'tb26_cep' => '72920076',
            'tb26_ie' => '123456789',
            'tb26_csc_id' => '1',
            'tb26_csc' => 'ABC123',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 1,
            'id_unidade' => 3,
            'produto_nome' => 'PAO DE SAL',
            'quantidade' => 2,
            'valor_unitario' => 1.5,
            'valor_total' => 3.0,
        ]);
        $sale->setRelation('produto', $product);
        $sale->setRelation('unidade', new Unidade([
            'tb2_id' => 3,
            'tb2_nome' => 'BARRAGEM 1',
            'tb2_cnpj' => '62074471000156',
        ]));

        $sales = collect([$sale]);

        $payment = new VendaPagamento([
            'tb4_id' => 1003,
            'tipo_pagamento' => 'dinheiro',
            'valor_total' => 3.0,
            'troco' => 0,
        ]);
        $payment->setRelation('vendas', $sales);

        $invoice = new NotaFiscal([
            'tb27_modelo' => 'nfce',
            'tb27_serie' => '1',
            'tb27_numero' => 3,
        ]);

        $certificateData = $this->createCertificateData();
        $consumer = [
            'type' => 'cupom_fiscal',
            'document' => '12345678901',
        ];

        $result = $service->buildSignedXml($invoice, $payment, $sales, $consumer, $config, $certificateData);

        $document = new DOMDocument();
        $loaded = @$document->loadXML($result['xml']);

        $this->assertTrue($loaded);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $this->assertSame(1.0, $xpath->evaluate('count(/nfe:NFe/nfe:infNFe/nfe:dest)'));
        $this->assertSame('12345678901', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:CPF)'));
        $this->assertSame(0.0, $xpath->evaluate('count(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:xNome)'));
        $this->assertSame(0.0, $xpath->evaluate('count(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:enderDest)'));
        $this->assertSame('9', (string) $xpath->evaluate('string(/nfe:NFe/nfe:infNFe/nfe:dest/nfe:indIEDest)'));

        $destinationChildren = [];

        foreach ($xpath->query('/nfe:NFe/nfe:infNFe/nfe:dest/*') as $child) {
            $destinationChildren[] = $child->localName;
        }

        $this->assertSame(['CPF', 'indIEDest'], $destinationChildren);
    }

    public function test_assert_signed_xml_is_locally_valid_reports_reference_mismatch_for_tampered_xml(): void
    {
        $service = new FiscalNfceXmlService(new FiscalWebserviceResolverService());

        $config = new ConfiguracaoFiscal([
            'tb26_ambiente' => 'homologacao',
            'tb26_uf' => 'GO',
            'tb26_codigo_municipio' => '5200258',
            'tb26_serie' => '1',
            'tb26_crt' => 1,
            'tb26_razao_social' => 'EMPRESA TESTE LTDA',
            'tb26_nome_fantasia' => 'EMPRESA TESTE',
            'tb26_logradouro' => 'RUA 1',
            'tb26_numero' => '10',
            'tb26_bairro' => 'CENTRO',
            'tb26_municipio' => 'AGUAS LINDAS DE GOIAS',
            'tb26_cep' => '72920076',
            'tb26_ie' => '123456789',
            'tb26_csc_id' => '1',
            'tb26_csc' => 'ABC123',
        ]);

        $product = new Produto([
            'tb1_ncm' => '19059090',
            'tb1_cfop' => '5102',
            'tb1_unidade_comercial' => 'UN',
            'tb1_unidade_tributavel' => 'UN',
            'tb1_origem' => 0,
            'tb1_csosn' => '102',
            'tb1_codbar' => null,
        ]);

        $sale = new Venda([
            'tb1_id' => 1,
            'id_unidade' => 3,
            'produto_nome' => 'PAO DE SAL',
            'quantidade' => 2,
            'valor_unitario' => 1.5,
            'valor_total' => 3.0,
        ]);
        $sale->setRelation('produto', $product);
        $sale->setRelation('unidade', new Unidade([
            'tb2_id' => 3,
            'tb2_nome' => 'BARRAGEM 1',
            'tb2_cnpj' => '62074471000156',
        ]));

        $sales = collect([$sale]);

        $payment = new VendaPagamento([
            'tb4_id' => 1001,
            'tipo_pagamento' => 'dinheiro',
            'valor_total' => 3.0,
            'troco' => 0,
        ]);
        $payment->setRelation('vendas', $sales);

        $invoice = new NotaFiscal([
            'tb27_modelo' => 'nfce',
            'tb27_serie' => '1',
            'tb27_numero' => 1,
        ]);

        $certificateData = $this->createCertificateData();
        $result = $service->buildSignedXml($invoice, $payment, $sales, null, $config, $certificateData);
        $tamperedXml = str_replace('PAO DE SAL', 'PAO DOCE', $result['xml']);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertSignedXmlIsLocallyValid');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DigestValue/Reference');

        $method->invoke($service, $tamperedXml, $certificateData);
    }

    private function createCertificateData(): array
    {
        if (! function_exists('openssl_pkey_new') || ! function_exists('openssl_csr_new') || ! function_exists('openssl_csr_sign')) {
            $this->markTestSkipped('OpenSSL nao esta disponivel neste ambiente para gerar um certificado temporario de teste.');
        }

        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($privateKey === false) {
            $this->markTestSkipped('O ambiente atual nao permitiu gerar uma chave RSA temporaria para o teste de assinatura.');
        }

        $csr = openssl_csr_new([
            'commonName' => 'TESTE NFC-E',
            'organizationName' => 'PEC',
            'countryName' => 'BR',
        ], $privateKey);

        if ($csr === false) {
            $this->markTestSkipped('O ambiente atual nao permitiu gerar um CSR temporario para o teste de assinatura.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 1);

        if ($certificate === false) {
            $this->markTestSkipped('O ambiente atual nao permitiu assinar um certificado temporario para o teste de assinatura.');
        }

        $certificatePem = '';
        $privateKeyPem = '';

        $this->assertTrue(openssl_x509_export($certificate, $certificatePem));
        $this->assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        return [
            'certificate_pem' => $certificatePem,
            'private_key_pem' => $privateKeyPem,
            'public_certificate_base64' => trim(str_replace([
                '-----BEGIN CERTIFICATE-----',
                '-----END CERTIFICATE-----',
                "\r",
                "\n",
            ], '', $certificatePem)),
        ];
    }
}
