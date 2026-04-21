<?php

namespace Tests\Unit;

use App\Support\FiscalCertificateService;
use App\Support\FiscalNfceXmlService;
use App\Support\FiscalNfceTransmissionService;
use App\Support\FiscalWebserviceResolverService;
use RuntimeException;
use ReflectionClass;
use Tests\TestCase;

class FiscalNfceTransmissionServiceTest extends TestCase
{
    public function test_build_soap_envelope_keeps_signature_without_default_namespace_prefix(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $buildBatchXml = $reflection->getMethod('buildBatchXml');
        $buildBatchXml->setAccessible(true);
        $buildSoapEnvelope = $reflection->getMethod('buildSoapEnvelope');
        $buildSoapEnvelope->setAccessible(true);

        $signedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe52260458493190000106650010000000051018297110" versao="4.00"><ide><cUF>52</cUF></ide></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=123|3|2</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>abc</SignatureValue></Signature></NFe>
XML;

        $lotXml = $buildBatchXml->invoke($service, $signedXml, 18863);
        $soapEnvelope = $buildSoapEnvelope->invoke($service, $lotXml, '5200258');

        $this->assertStringNotContainsString('xmlns:default=', $soapEnvelope);
        $this->assertStringNotContainsString('<default:Signature', $soapEnvelope);
        $this->assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $soapEnvelope);
    }

    public function test_parse_authorization_response_prefixes_cstat_in_error_message(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseAuthorizationResponse');
        $method->setAccessible(true);

        $response = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
    <tpAmb>2</tpAmb>
    <verAplic>GO2026001</verAplic>
    <cStat>225</cStat>
    <xMotivo>Rejeicao: Falha no Schema XML do lote de NFe</xMotivo>
    <cUF>52</cUF>
    <dhRecbto>2026-04-16T18:10:00-03:00</dhRecbto>
    <infRec>
        <nRec>522600000000001</nRec>
        <tMed>1</tMed>
    </infRec>
</retEnviNFe>
XML;

        $parsed = $method->invoke($service, $response);

        $this->assertSame('erro_transmissao', $parsed['status']);
        $this->assertSame(
            'cStat 225 - Rejeicao: Falha no Schema XML do lote de NFe',
            $parsed['message']
        );
        $this->assertSame('522600000000001', $parsed['receipt']);
    }

    public function test_assert_batch_preserves_signed_xml_or_fail_detects_changes_before_soap(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertBatchPreservesSignedXmlOrFail');
        $method->setAccessible(true);

        $signedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe1" versao="4.00"><ide><cUF>52</cUF></ide></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=123|3|2</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>abc</SignatureValue></Signature></NFe>
XML;

        $differentLotXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<enviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><idLote>000000000018863</idLote><indSinc>1</indSinc><NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe2" versao="4.00"><ide><cUF>52</cUF></ide></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=999|3|2</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>xyz</SignatureValue></Signature></NFe></enviNFe>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('alterado durante a montagem do lote SOAP');

        $method->invoke($service, $signedXml, $differentLotXml, []);
    }

    public function test_assert_signed_xml_structure_or_fail_blocks_incomplete_destination_group(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertSignedXmlStructureOrFail');
        $method->setAccessible(true);

        $signedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe1" versao="4.00"><ide><cUF>52</cUF><mod>65</mod><tpImp>4</tpImp></ide><dest><indIEDest>9</indIEDest></dest></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=123|2|2|1|HASH</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>abc</SignatureValue></Signature></NFe>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nao informou CPF, CNPJ ou idEstrangeiro');

        $method->invoke($service, $signedXml);
    }

    public function test_assert_signed_xml_structure_or_fail_accepts_fiscal_coupon_with_cpf_only(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertSignedXmlStructureOrFail');
        $method->setAccessible(true);

        $signedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe1" versao="4.00"><ide><cUF>52</cUF><mod>65</mod><tpImp>4</tpImp></ide><dest><CPF>12345678901</CPF><indIEDest>9</indIEDest></dest></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=123|2|2|1|HASH</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>abc</SignatureValue></Signature></NFe>
XML;

        $method->invoke($service, $signedXml);

        $this->assertTrue(true);
    }

    public function test_assert_signed_xml_structure_or_fail_still_requires_name_when_destination_has_address(): void
    {
        $service = new FiscalNfceTransmissionService(
            new FiscalCertificateService(),
            new FiscalWebserviceResolverService(),
            new FiscalNfceXmlService(new FiscalWebserviceResolverService()),
        );

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('assertSignedXmlStructureOrFail');
        $method->setAccessible(true);

        $signedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe1" versao="4.00"><ide><cUF>52</cUF><mod>65</mod><tpImp>4</tpImp></ide><dest><CPF>12345678901</CPF><enderDest><xLgr>RUA TESTE</xLgr><cMun>5200258</cMun></enderDest><indIEDest>9</indIEDest></dest></infNFe><infNFeSupl><qrCode>https://exemplo.invalid?p=123|2|2|1|HASH</qrCode><urlChave>https://exemplo.invalid/consulta</urlChave></infNFeSupl><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></SignedInfo><SignatureValue>abc</SignatureValue></Signature></NFe>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nao informou o nome do destinatario');

        $method->invoke($service, $signedXml);
    }
}
