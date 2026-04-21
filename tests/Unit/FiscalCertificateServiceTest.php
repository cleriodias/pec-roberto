<?php

namespace Tests\Unit;

use App\Models\ConfiguracaoFiscal;
use App\Support\FiscalCertificateService;
use Illuminate\Support\Facades\Crypt;
use ReflectionClass;
use Tests\TestCase;

class FiscalCertificateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/fiscal-certificados/teste-certificado.pfx'));
        @rmdir(storage_path('app/private/fiscal-certificados'));

        parent::tearDown();
    }

    public function test_extracts_certificate_name_from_subject(): void
    {
        $service = new FiscalCertificateService();

        $name = $service->extractCertificateName([
            'subject' => [
                'CN' => 'EMPRESA TESTE LTDA',
            ],
        ]);

        $this->assertSame('EMPRESA TESTE LTDA', $name);
    }

    public function test_extracts_certificate_cnpj_from_serial_number(): void
    {
        $service = new FiscalCertificateService();

        $cnpj = $service->extractCertificateCnpj([
            'subject' => [
                'serialNumber' => 'CNPJ:11.222.333/0001-44',
            ],
        ]);

        $this->assertSame('11222333000144', $cnpj);
    }

    public function test_extracts_certificate_cnpj_from_subject_alt_name(): void
    {
        $service = new FiscalCertificateService();

        $cnpj = $service->extractCertificateCnpj([
            'extensions' => [
                'subjectAltName' => 'otherName:2.16.76.1.3.3=11222333000144',
            ],
        ]);

        $this->assertSame('11222333000144', $cnpj);
    }

    public function test_resolves_current_and_legacy_certificate_storage_paths(): void
    {
        $directory = storage_path('app/private/fiscal-certificados');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . 'teste-certificado.pfx';
        file_put_contents($filePath, 'certificado-teste');

        $service = new FiscalCertificateService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveStorageAbsolutePath');
        $method->setAccessible(true);

        $currentPath = $method->invoke($service, 'fiscal-certificados/teste-certificado.pfx');
        $legacyPath = $method->invoke($service, 'private/fiscal-certificados/teste-certificado.pfx');

        $expectedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
        $resolvedCurrentPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $currentPath);
        $resolvedLegacyPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $legacyPath);

        $this->assertSame($expectedPath, $resolvedCurrentPath);
        $this->assertSame($expectedPath, $resolvedLegacyPath);
    }

    public function test_prefers_shared_certificate_password_without_app_key_dependency(): void
    {
        $service = new FiscalCertificateService();
        $configuration = new ConfiguracaoFiscal();
        $configuration->setRawAttributes([
            'tb26_certificado_senha_compartilhada' => 'segredo-compartilhado',
            'tb26_certificado_senha' => Crypt::encryptString('segredo-antigo'),
        ], true);

        $details = $service->resolveConfigurationPasswordDetails($configuration);

        $this->assertTrue($details['has_password']);
        $this->assertTrue($details['readable']);
        $this->assertSame('shared', $details['source']);
        $this->assertSame('segredo-compartilhado', $details['password']);
    }

    public function test_reads_legacy_encrypted_certificate_password_when_shared_is_missing(): void
    {
        $service = new FiscalCertificateService();
        $configuration = new ConfiguracaoFiscal();
        $configuration->setRawAttributes([
            'tb26_certificado_senha' => Crypt::encryptString('segredo-antigo'),
        ], true);

        $details = $service->resolveConfigurationPasswordDetails($configuration);

        $this->assertTrue($details['has_password']);
        $this->assertTrue($details['readable']);
        $this->assertSame('legacy_encrypted', $details['source']);
        $this->assertSame('segredo-antigo', $details['password']);
    }
}
