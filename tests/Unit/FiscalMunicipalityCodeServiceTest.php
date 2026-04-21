<?php

namespace Tests\Unit;

use App\Support\FiscalMunicipalityCodeService;
use Tests\TestCase;

class FiscalMunicipalityCodeServiceTest extends TestCase
{
    public function test_matches_uf_with_ibge_municipality_prefix(): void
    {
        $service = new FiscalMunicipalityCodeService();

        $this->assertTrue($service->matchesUf('GO', '5200258'));
        $this->assertFalse($service->matchesUf('GO', '5300108'));
        $this->assertSame('52', $service->expectedPrefixForUf('go'));
    }
}
