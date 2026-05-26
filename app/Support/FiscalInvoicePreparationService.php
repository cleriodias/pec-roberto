<?php

namespace App\Support;

use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\VendaPagamento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiscalInvoicePreparationService
{
    public function __construct(
        private readonly FiscalCertificateService $fiscalCertificateService,
        private readonly FiscalNfceXmlService $fiscalNfceXmlService,
        private readonly FiscalMunicipalityCodeService $fiscalMunicipalityCodeService,
        private readonly FiscalProductTaxService $fiscalProductTaxService = new FiscalProductTaxService(),
    ) {
    }

    public function prepareForPayment(VendaPagamento $payment, ?array $consumer = null): ?NotaFiscal
    {
        $payment->loadMissing([
            'vendas.produto.categoriaFiscal',
            'vendas.produto.grupoNcm',
            'vendas.produto.excecoesFiscais',
            'vendas.unidade',
        ]);

        if ($payment->vendas->isEmpty()) {
            return null;
        }

        $firstSale = $payment->vendas->first();
        $unitId = (int) ($firstSale->id_unidade ?? 0);

        if ($unitId <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($payment, $unitId, $consumer) {
                $config = null;
                $invoice = NotaFiscal::query()
                    ->where('tb4_id', $payment->tb4_id)
                    ->lockForUpdate()
                    ->first();

                if ($invoice) {
                    $config = ConfiguracaoFiscal::query()
                        ->where('tb2_id', $unitId)
                        ->first();
                } else {
                    $config = ConfiguracaoFiscal::query()
                        ->where('tb2_id', $unitId)
                        ->lockForUpdate()
                        ->first();

                    if ($config && ! $config->tb26_geracao_automatica_ativa) {
                        return null;
                    }

                    // Revalida a existencia da nota apos obter o lock da configuracao:
                    // se outra transacao acabou de criar a nota, nao devemos consumir nova numeracao.
                    $invoice = NotaFiscal::query()
                        ->where('tb4_id', $payment->tb4_id)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $this->supportsAutomaticFiscalGenerationForPaymentType($payment->tipo_pagamento)) {
                    if (! $invoice) {
                        return null;
                    }

                    if (in_array((string) $invoice->tb27_status, ['emitida', 'cancelada'], true)) {
                        return $invoice;
                    }

                    $message = $this->buildNonFiscalPaymentMessage($payment->tipo_pagamento);

                    $invoice->fill([
                        'tb27_status' => 'erro_validacao',
                        'tb27_erros' => [$message],
                        'tb27_ultima_tentativa_em' => now(),
                        'tb27_mensagem' => $message,
                    ]);
                    $invoice->save();

                    return $invoice;
                }

                $nextNumber = $invoice?->tb27_numero;
                $serie = $invoice?->tb27_serie;
                $modelo = $invoice?->tb27_modelo ?? 'nfce';
                $ambiente = $config?->tb26_ambiente ?? 'homologacao';

                if ($config && $nextNumber === null && ($config->tb26_emitir_nfce || $config->tb26_emitir_nfe)) {
                    $nextNumber = (int) $config->tb26_proximo_numero;
                    $serie = $config->tb26_serie;
                    $modelo = $config->tb26_emitir_nfce ? 'nfce' : ($config->tb26_emitir_nfe ? 'nfe' : 'nfce');

                    $config->update([
                        'tb26_proximo_numero' => $nextNumber + 1,
                    ]);
                }

                [$eligibleSales, $excludedItems] = $this->splitSalesForFiscal($payment);
                $consumerPayload = $this->resolveConsumerPayload($invoice, $consumer);
                $payload = $this->buildPayload(
                    $payment,
                    $config,
                    $modelo,
                    $ambiente,
                    $serie,
                    $nextNumber,
                    $consumerPayload,
                    $eligibleSales,
                    $excludedItems,
                );
                $errors = $this->validatePayload($payment, $config, $modelo, $consumerPayload, $eligibleSales, $excludedItems);
                $status = $this->resolveStatus($config, $errors);
                $signedXml = null;
                $accessKey = null;

                if ($errors === [] && $config && $modelo === 'nfce') {
                    try {
                        $certificateData = $this->fiscalCertificateService->loadCertificateForConfiguration($config);
                        $xmlPayload = $this->fiscalNfceXmlService->buildSignedXml(
                            $invoice ?? new NotaFiscal([
                                'tb27_modelo' => $modelo,
                                'tb27_serie' => $serie,
                                'tb27_numero' => $nextNumber,
                            ]),
                            $payment,
                            $eligibleSales,
                            $consumerPayload,
                            $config,
                            $certificateData,
                        );

                        $signedXml = $xmlPayload['xml'];
                        $accessKey = $xmlPayload['access_key'];
                        $status = 'xml_assinado';
                    } catch (RuntimeException $exception) {
                        $errors[] = $exception->getMessage();
                        $status = 'erro_validacao';
                    }
                }

                if (! $invoice) {
                    $invoice = new NotaFiscal([
                        'tb4_id' => $payment->tb4_id,
                    ]);
                }

                $invoice->fill([
                    'tb2_id' => $unitId,
                    'tb26_id' => $config?->tb26_id,
                    'tb27_modelo' => $modelo,
                    'tb27_ambiente' => $ambiente,
                    'tb27_serie' => $serie,
                    'tb27_numero' => $nextNumber,
                    'tb27_status' => $status,
                    'tb27_payload' => $payload,
                    'tb27_erros' => $errors,
                    'tb27_chave_acesso' => $accessKey,
                    'tb27_xml_envio' => $signedXml,
                    'tb27_ultima_tentativa_em' => now(),
                    'tb27_mensagem' => $this->buildStatusMessage($status, $errors, $payload),
                ]);
                $invoice->save();

                return $invoice;
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isLockWaitTimeout($exception)) {
                throw new RuntimeException(
                    'Outra operacao fiscal da mesma loja ainda esta em andamento. Aguarde alguns segundos e tente novamente.',
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    public function reprocessPendingInvoicesForUnit(int $unitId): int
    {
        $invoices = NotaFiscal::query()
            ->where('tb2_id', $unitId)
            ->whereIn('tb27_status', [
                'pendente_configuracao',
                'erro_validacao',
                'pendente_emissao',
                'xml_assinado',
                'erro_transmissao',
            ])
            ->with(
                'pagamento.vendas.produto.categoriaFiscal',
                'pagamento.vendas.produto.grupoNcm',
                'pagamento.vendas.produto.excecoesFiscais',
                'pagamento.vendas.unidade',
            )
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->pagamento) {
                $this->prepareForPayment($invoice->pagamento);
            }
        }

        return $invoices->count();
    }

    private function buildPayload(
        VendaPagamento $payment,
        ?ConfiguracaoFiscal $config,
        string $modelo,
        string $ambiente,
        ?string $serie,
        ?int $numero,
        ?array $consumer,
        $eligibleSales,
        array $excludedItems,
    ): array {
        $sales = $payment->vendas;
        $unit = $sales->first()?->unidade;
        $documentTotal = round((float) $eligibleSales->sum('valor_total'), 2);
        $consumer = $this->normalizeConsumerPayload($consumer);

        return [
            'pagamento_id' => (int) $payment->tb4_id,
            'modelo' => $modelo,
            'ambiente' => $ambiente,
            'serie' => $serie,
            'numero' => $numero,
            'tipo_pagamento' => $payment->tipo_pagamento,
            'valor_total_venda' => round((float) $payment->valor_total, 2),
            'valor_total_documento' => $documentTotal,
            'itens_excluidos_qtd' => count($excludedItems),
            'consumer' => $consumer,
            'emitente' => [
                'unit_id' => (int) ($unit?->tb2_id ?? 0),
                'nome_unidade' => $unit?->tb2_nome,
                'cnpj' => $unit?->tb2_cnpj,
                'configuracao' => [
                    'razao_social' => $config?->tb26_razao_social,
                    'nome_fantasia' => $config?->tb26_nome_fantasia,
                    'ie' => $config?->tb26_ie,
                    'crt' => $config?->tb26_crt,
                    'logradouro' => $config?->tb26_logradouro,
                    'numero' => $config?->tb26_numero,
                    'bairro' => $config?->tb26_bairro,
                    'municipio' => $config?->tb26_municipio,
                    'codigo_municipio' => $config?->tb26_codigo_municipio,
                    'uf' => $config?->tb26_uf,
                    'cep' => $config?->tb26_cep,
                    'certificado_nome' => $config?->tb26_certificado_nome,
                    'certificado_cnpj' => $config?->tb26_certificado_cnpj,
                ],
            ],
            'itens' => $eligibleSales->map(function ($sale) {
                /** @var Produto|null $product */
                $product = $sale->produto;
                $taxData = $product
                    ? $this->fiscalProductTaxService->resolve($product)
                    : [];

                return [
                    'produto_id' => (int) $sale->tb1_id,
                    'descricao' => $sale->produto_nome,
                    'quantidade' => (int) $sale->quantidade,
                    'valor_unitario' => round((float) $sale->valor_unitario, 2),
                    'valor_total' => round((float) $sale->valor_total, 2),
                    'categoria_fiscal_id' => $taxData['categoria_fiscal_id'] ?? null,
                    'categoria_fiscal_nome' => $taxData['categoria_nome'] ?? null,
                    'grupo_ncm_id' => $taxData['grupo_ncm_id'] ?? null,
                    'grupo_ncm_nome' => $taxData['grupo_ncm_nome'] ?? null,
                    'origem_dados_fiscais' => $taxData['source'] ?? null,
                    'ncm' => $taxData['ncm'] ?? null,
                    'cest' => $taxData['cest'] ?? null,
                    'cfop' => $taxData['cfop'] ?? null,
                    'origem' => $taxData['origem'] ?? null,
                    'csosn' => $taxData['csosn'] ?? null,
                    'cst' => $taxData['cst'] ?? null,
                    'aliquota_icms' => $taxData['aliquota_icms'] ?? null,
                    'cst_ibscbs' => $taxData['cst_ibscbs'] ?? null,
                    'cclasstrib' => $taxData['cclasstrib'] ?? null,
                    'ind_doacao' => (bool) ($taxData['ind_doacao'] ?? false),
                    'aliquota_ibs_uf' => $taxData['aliquota_ibs_uf'] ?? null,
                    'aliquota_ibs_mun' => $taxData['aliquota_ibs_mun'] ?? null,
                    'aliquota_cbs' => $taxData['aliquota_cbs'] ?? null,
                    'aliquota_is' => $taxData['aliquota_is'] ?? null,
                    'unidade_comercial' => $taxData['unidade_comercial'] ?? null,
                    'unidade_tributavel' => $taxData['unidade_tributavel'] ?? null,
                    'codigo_barras' => $product?->tb1_codbar,
                    'rtc_emissao_apta' => $this->fiscalProductTaxService->hasRtcTaxData($taxData),
                ];
            })->values()->all(),
            'itens_excluidos' => $excludedItems,
        ];
    }

    private function validatePayload(
        VendaPagamento $payment,
        ?ConfiguracaoFiscal $config,
        string $modelo,
        ?array $consumer,
        $eligibleSales,
        array $excludedItems,
    ): array
    {
        $errors = [];

        if (! $config) {
            return ['Configure a emissao fiscal da unidade antes de gerar a nota.'];
        }

        $requiredConfigFields = [
            'tb26_serie' => 'Serie fiscal',
            'tb26_razao_social' => 'Razao social',
            'tb26_ie' => 'Inscricao estadual',
            'tb26_crt' => 'CRT',
            'tb26_logradouro' => 'Logradouro',
            'tb26_numero' => 'Numero',
            'tb26_bairro' => 'Bairro',
            'tb26_codigo_municipio' => 'Codigo do municipio IBGE',
            'tb26_municipio' => 'Municipio',
            'tb26_uf' => 'UF',
            'tb26_cep' => 'CEP',
            'tb26_certificado_tipo' => 'Tipo de certificado',
            'tb26_certificado_nome' => 'Nome do certificado',
            'tb26_certificado_cnpj' => 'CNPJ do certificado',
            'tb26_certificado_arquivo' => 'Arquivo do certificado A1',
        ];

        foreach ($requiredConfigFields as $field => $label) {
            if (blank($config->{$field})) {
                $errors[] = sprintf('%s nao configurado na unidade.', $label);
            }
        }

        if ($this->fiscalCertificateService->resolveConfigurationPassword($config) === null) {
            $errors[] = 'Senha do certificado nao configurada na unidade ou ilegivel neste ambiente.';
        }

        if (! $config->tb26_emitir_nfe && ! $config->tb26_emitir_nfce) {
            $errors[] = 'Selecione se a unidade vai emitir NF-e, NFC-e ou ambas.';
        }

        if ($config->tb26_certificado_valido_ate && $config->tb26_certificado_valido_ate->isPast()) {
            $errors[] = 'O certificado digital da loja esta vencido.';
        }

        if ($modelo === 'nfe') {
            $errors[] = 'NF-e modelo 55 ainda nao pode ser gerada automaticamente porque a venda atual nao armazena destinatario fiscal.';
        }

        if ($consumer !== null) {
            $consumerType = $this->resolveConsumerType($consumer);
            $document = $this->onlyDigits($consumer['document'] ?? null);
            $cep = $this->onlyDigits($consumer['cep'] ?? null);
            $cityCode = $this->onlyDigits($consumer['city_code'] ?? null);
            $state = strtoupper(trim((string) ($consumer['state'] ?? '')));

            if (! in_array($consumerType, ['cupom_fiscal', 'consumidor'], true)) {
                $errors[] = 'Tipo de identificacao do consumidor invalido para a nota fiscal.';
            }

            if ($consumerType === 'consumidor' && trim((string) ($consumer['name'] ?? '')) === '') {
                $errors[] = 'Nome do consumidor nao informado para a NF Consumidor.';
            }

            if ($consumerType === 'cupom_fiscal' && strlen($document) !== 11) {
                $errors[] = 'Documento do consumidor invalido. Informe um CPF com 11 digitos para o cupom fiscal.';
            }

            if ($consumerType === 'consumidor' && ! in_array(strlen($document), [11, 14], true)) {
                $errors[] = 'Documento do consumidor invalido. Informe CPF com 11 digitos ou CNPJ com 14 digitos.';
            }

            if ($consumerType === 'consumidor' && trim((string) ($consumer['street'] ?? '')) === '') {
                $errors[] = 'Logradouro do consumidor nao informado para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && trim((string) ($consumer['number'] ?? '')) === '') {
                $errors[] = 'Numero do endereco do consumidor nao informado para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && trim((string) ($consumer['neighborhood'] ?? '')) === '') {
                $errors[] = 'Bairro do consumidor nao informado para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && trim((string) ($consumer['city'] ?? '')) === '') {
                $errors[] = 'Municipio do consumidor nao informado para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && strlen($cep) !== 8) {
                $errors[] = 'CEP do consumidor invalido para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && strlen($cityCode) !== 7) {
                $errors[] = 'Codigo do municipio IBGE do consumidor invalido para a NF Consumidor.';
            }

            if ($consumerType === 'consumidor' && strlen($state) !== 2) {
                $errors[] = 'UF do consumidor invalida para a NF Consumidor.';
            }

            if (
                $consumerType === 'consumidor'
                && strlen($state) === 2
                && strlen($cityCode) === 7
                && ! $this->fiscalMunicipalityCodeService->matchesUf($state, $cityCode)
            ) {
                $expectedPrefix = $this->fiscalMunicipalityCodeService->expectedPrefixForUf($state);

                $errors[] = sprintf(
                    'O codigo do municipio IBGE %s nao pertence a UF %s do consumidor. Use um codigo iniciado por %s.',
                    $cityCode !== '' ? $cityCode : '--',
                    $state !== '' ? $state : '--',
                    $expectedPrefix ?? '--'
                );
            }
        }

        $unitCnpj = $this->onlyDigits($payment->vendas->first()?->unidade?->tb2_cnpj);
        $certificateCnpj = $this->onlyDigits($config->tb26_certificado_cnpj);

        if ($unitCnpj && $certificateCnpj && substr($unitCnpj, 0, 8) !== substr($certificateCnpj, 0, 8)) {
            $errors[] = 'O CNPJ do certificado nao pertence ao mesmo CNPJ base da loja da venda.';
        }

        if ($config->tb26_emitir_nfce) {
            if (blank($config->tb26_csc_id)) {
                $errors[] = 'CSC ID nao configurado para NFC-e.';
            }

            if (blank($config->tb26_csc)) {
                $errors[] = 'CSC nao configurado para NFC-e.';
            }
        }

        if (
            filled($config->tb26_uf)
            && filled($config->tb26_codigo_municipio)
            && ! $this->fiscalMunicipalityCodeService->matchesUf($config->tb26_uf, $config->tb26_codigo_municipio)
        ) {
            $expectedPrefix = $this->fiscalMunicipalityCodeService->expectedPrefixForUf($config->tb26_uf);

            $errors[] = sprintf(
                'O codigo do municipio IBGE %s nao pertence a UF %s informada na configuracao fiscal. Use um codigo iniciado por %s.',
                (string) $config->tb26_codigo_municipio,
                (string) $config->tb26_uf,
                $expectedPrefix ?? '--'
            );
        }

        if (! $this->isValidStateRegistration($config->tb26_ie)) {
            $errors[] = 'A inscricao estadual da unidade esta invalida para emissao fiscal. Informe apenas digitos ou ISENTO.';
        }

        if ($eligibleSales->isEmpty()) {
            $excludedSummary = collect($excludedItems)
                ->map(fn (array $item) => sprintf('%d (%s)', (int) $item['produto_id'], (string) $item['descricao']))
                ->implode(', ');

            $errors[] = $excludedSummary !== ''
                ? sprintf(
                    'Nenhum item da venda possui dados fiscais minimos para gerar a nota. Itens excluidos: %s.',
                    $excludedSummary
                )
                : 'Nenhum item da venda possui dados fiscais minimos para gerar a nota.';
        }

        foreach ($excludedItems as $excludedItem) {
            $errors[] = sprintf(
                'Produto %d (%s) bloqueou a emissao fiscal: %s.',
                (int) $excludedItem['produto_id'],
                (string) $excludedItem['descricao'],
                implode(', ', $excludedItem['campos_faltantes'] ?? [])
            );
        }

        foreach ($eligibleSales as $sale) {
            /** @var Produto|null $product */
            $product = $sale->produto;
            $productId = (int) $sale->tb1_id;
            $productName = $sale->produto_nome;

            if (! $product) {
                $errors[] = sprintf('Produto %d nao encontrado para a emissao fiscal.', $productId);
                continue;
            }

            $taxData = $this->fiscalProductTaxService->resolve($product);
            $missingFields = $this->fiscalProductTaxService->missingRequiredFields($taxData);

            if (($taxData['source'] ?? null) === 'sem_categoria') {
                $missingFields[] = 'Categoria fiscal';
            }

            if (($taxData['source'] ?? null) !== 'produto_legado' && blank($taxData['grupo_ncm_id'] ?? null)) {
                $missingFields[] = 'Grupo NCM';
            }

            if (! (bool) ($taxData['category_active'] ?? false)) {
                $missingFields[] = 'Categoria fiscal ativa';
            }

            if (! (bool) ($taxData['grupo_ncm_active'] ?? false)) {
                $missingFields[] = 'Grupo NCM ativo';
            }

            if (! (bool) ($taxData['category_current'] ?? false)) {
                $missingFields[] = 'Categoria fiscal vigente';
            }

            if ($missingFields !== []) {
                $errors[] = sprintf(
                    'Produto %d (%s) sem cadastro fiscal completo: %s.',
                    $productId,
                    $productName,
                    implode(', ', $missingFields)
                );
            }
        }

        return array_values(array_unique($errors));
    }

    private function resolveConsumerPayload(?NotaFiscal $invoice, ?array $consumer): ?array
    {
        if (is_array($consumer)) {
            return $this->normalizeConsumerPayload($consumer);
        }

        $payload = is_array($invoice?->tb27_payload) ? $invoice->tb27_payload : [];
        $storedConsumer = $payload['consumer'] ?? null;

        return is_array($storedConsumer) ? $this->normalizeConsumerPayload($storedConsumer) : null;
    }

    private function normalizeConsumerPayload(?array $consumer): ?array
    {
        if (! is_array($consumer)) {
            return null;
        }

        $normalized = [
            'type' => $this->resolveConsumerType($consumer),
            'name' => trim((string) ($consumer['name'] ?? '')),
            'document' => $this->onlyDigits($consumer['document'] ?? null),
            'cep' => $this->onlyDigits($consumer['cep'] ?? null),
            'street' => trim((string) ($consumer['street'] ?? '')),
            'number' => trim((string) ($consumer['number'] ?? '')),
            'complement' => trim((string) ($consumer['complement'] ?? '')),
            'neighborhood' => trim((string) ($consumer['neighborhood'] ?? '')),
            'city' => trim((string) ($consumer['city'] ?? '')),
            'city_code' => $this->onlyDigits($consumer['city_code'] ?? null),
            'state' => strtoupper(trim((string) ($consumer['state'] ?? ''))),
        ];

        if ($normalized['type'] === 'cupom_fiscal') {
            $normalized['name'] = '';
            $normalized['cep'] = null;
            $normalized['street'] = '';
            $normalized['number'] = '';
            $normalized['complement'] = '';
            $normalized['neighborhood'] = '';
            $normalized['city'] = '';
            $normalized['city_code'] = null;
            $normalized['state'] = '';
        }

        return $normalized;
    }

    private function resolveConsumerType(array $consumer): string
    {
        $declaredType = trim((string) ($consumer['type'] ?? ''));

        if (in_array($declaredType, ['cupom_fiscal', 'consumidor'], true)) {
            return $declaredType;
        }

        $document = $this->onlyDigits($consumer['document'] ?? null);
        $name = trim((string) ($consumer['name'] ?? ''));

        if ($document && $name === '') {
            return 'cupom_fiscal';
        }

        return 'consumidor';
    }

    private function resolveStatus(?ConfiguracaoFiscal $config, array $errors): string
    {
        if (! $config) {
            return 'pendente_configuracao';
        }

        if ($errors !== []) {
            return 'erro_validacao';
        }

        return 'pendente_emissao';
    }

    private function buildStatusMessage(string $status, array $errors, array $payload): string
    {
        $excludedCount = (int) ($payload['itens_excluidos_qtd'] ?? 0);

        return match ($status) {
            'xml_assinado' => $excludedCount > 0
                ? sprintf(
                    'XML fiscal assinado localmente e aguardando transmissao para a SEFAZ. %d item(ns) sem cadastro fiscal minimo ficaram fora da nota.',
                    $excludedCount
                )
                : 'XML fiscal assinado localmente e aguardando transmissao para a SEFAZ.',
            'pendente_emissao' => $excludedCount > 0
                ? sprintf(
                    'Nota preparada e aguardando integracao com a SEFAZ. %d item(ns) sem cadastro fiscal minimo ficaram fora da nota.',
                    $excludedCount
                )
                : 'Nota preparada e aguardando integracao com a SEFAZ.',
            'erro_validacao' => $errors[0] ?? 'A nota possui pendencias fiscais.',
            default => 'A unidade ainda nao possui configuracao fiscal suficiente para emitir.',
        };
    }

    private function splitSalesForFiscal(VendaPagamento $payment): array
    {
        $eligibleSales = collect();
        $excludedItems = [];

        foreach ($payment->vendas as $sale) {
            /** @var Produto|null $product */
            $product = $sale->produto;
            $missingFields = [];

            if (! $product) {
                $missingFields = ['Produto nao encontrado'];
            } else {
                $taxData = $this->fiscalProductTaxService->resolve($product);
                $missingFields = $this->fiscalProductTaxService->missingRequiredFields($taxData);

                if (($taxData['source'] ?? null) === 'sem_categoria') {
                    array_unshift($missingFields, 'Categoria fiscal');
                }

                if (($taxData['source'] ?? null) !== 'produto_legado' && blank($taxData['grupo_ncm_id'] ?? null)) {
                    $missingFields[] = 'Grupo NCM';
                }

                if (! (bool) ($taxData['category_active'] ?? false)) {
                    $missingFields[] = 'Categoria fiscal ativa';
                }

                if (! (bool) ($taxData['grupo_ncm_active'] ?? false)) {
                    $missingFields[] = 'Grupo NCM ativo';
                }

                if (! (bool) ($taxData['category_current'] ?? false)) {
                    $missingFields[] = 'Categoria fiscal vigente';
                }
            }

            if ($missingFields === []) {
                $eligibleSales->push($sale);
                continue;
            }

            $excludedItems[] = [
                'produto_id' => (int) $sale->tb1_id,
                'descricao' => (string) $sale->produto_nome,
                'motivo' => sprintf(
                    'Item fora da nota por falta de: %s.',
                    implode(', ', $missingFields)
                ),
                'campos_faltantes' => $missingFields,
                'valor_total' => round((float) $sale->valor_total, 2),
            ];
        }

        return [$eligibleSales, $excludedItems];
    }

    private function onlyDigits(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : $value;
    }

    private function isValidStateRegistration(?string $value): bool
    {
        $value = strtoupper(trim((string) $value));

        if ($value === 'ISENTO') {
            return true;
        }

        $digits = $this->onlyDigits($value);

        return $digits !== null && strlen($digits) >= 2 && strlen($digits) <= 14;
    }

    private function isLockWaitTimeout(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'lock wait timeout exceeded')
            || str_contains($message, 'deadlock found')
            || in_array((string) $exception->getCode(), ['1205', '1213'], true);
    }

    private function supportsAutomaticFiscalGenerationForPaymentType(?string $paymentType): bool
    {
        return in_array((string) $paymentType, [
            'dinheiro',
            'pix',
            'cartao_credito',
            'cartao_debito',
            'dinheiro_cartao_credito',
            'dinheiro_cartao_debito',
            'maquina',
        ], true);
    }

    private function buildNonFiscalPaymentMessage(?string $paymentType): string
    {
        $label = match ((string) $paymentType) {
            'vale' => 'Vale',
            'refeicao' => 'Refeicao',
            'faturar' => 'Faturar',
            'pix' => 'PiX',
            default => strtoupper(str_replace('_', ' ', trim((string) $paymentType))) ?: 'NAO INFORMADO',
        };

        return sprintf(
            'Pagamento %s e apenas controle interno e nao gera nota fiscal automatica.',
            $label
        );
    }
}
