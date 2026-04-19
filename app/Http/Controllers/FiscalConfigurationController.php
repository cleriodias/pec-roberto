<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaPagamento;
use App\Support\FiscalCertificateService;
use App\Support\FiscalInvoicePreparationService;
use App\Support\FiscalMunicipalityCodeService;
use App\Support\FiscalNfceTransmissionService;
use App\Support\FiscalWebserviceResolverService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use App\Support\ManagementScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class FiscalConfigurationController extends Controller
{
    public function index(
        Request $request,
        FiscalCertificateService $fiscalCertificateService,
        FiscalWebserviceResolverService $fiscalWebserviceResolverService,
    ): Response
    {
        $user = $request->user();
        $this->ensureAdmin($user);
        $units = collect();
        $selectedUnitId = (int) $request->query('unit_id', 0);
        $lastStep = 'inicializar tela fiscal';

        try {
            $lastStep = 'carregar unidades gerenciadas';
            $units = ManagementScope::managedUnits($user, ['tb2_id', 'tb2_nome', 'tb2_cnpj'])
                ->map(fn (Unidade $unit) => [
                    'id' => (int) $unit->tb2_id,
                    'name' => $unit->tb2_nome,
                    'cnpj' => $unit->tb2_cnpj,
                ])
                ->values();

            if ($selectedUnitId <= 0) {
                $selectedUnitId = (int) ($units->first()['id'] ?? 0);
            }

            if ($selectedUnitId > 0 && ! ManagementScope::canManageUnit($user, $selectedUnitId)) {
                abort(403, 'Acesso negado.');
            }

            if (! $this->fiscalTablesAreAvailable()) {
                return $this->renderFiscalConfigPage(
                    $units,
                    $selectedUnitId,
                    null,
                    $this->defaultFiscalConfigurationPayload($selectedUnitId),
                    collect(),
                    null,
                    $this->defaultConfigurationDiagnostics($selectedUnitId),
                    'As tabelas fiscais ainda nao estao disponiveis neste ambiente. Execute as migrations fiscais do deploy antes de usar esta tela.',
                    null,
                );
            }

            $lastStep = 'carregar configuracao fiscal da unidade';
            $configuration = $selectedUnitId > 0
                ? ConfiguracaoFiscal::query()->where('tb2_id', $selectedUnitId)->first()
                : null;

            $lastStep = 'carregar dados da unidade';
            $unit = $selectedUnitId > 0
                ? Unidade::query()->find($selectedUnitId)
                : null;

            $invoiceLoadWarning = null;
            $invoices = collect();

            if ($selectedUnitId > 0) {
                try {
                    $lastStep = 'carregar ultimas notas fiscais da unidade';
                    $invoices = NotaFiscal::query()
                        ->where('tb2_id', $selectedUnitId)
                        ->with([
                            'pagamento.vendas.unidade:tb2_id,tb2_nome,tb2_endereco,tb2_cnpj',
                            'pagamento.vendas.caixa:id,name',
                            'pagamento.vendas.valeUser:id,name',
                            'configuracaoFiscal:tb26_id,tb26_razao_social,tb26_nome_fantasia,tb26_ie,tb26_logradouro,tb26_numero,tb26_complemento,tb26_bairro,tb26_municipio,tb26_uf,tb26_cep',
                        ])
                        ->orderByDesc('tb27_id')
                        ->limit(15)
                        ->get([
                            'tb27_id',
                            'tb4_id',
                            'tb27_modelo',
                            'tb27_ambiente',
                            'tb27_serie',
                            'tb27_numero',
                            'tb27_status',
                            'tb27_mensagem',
                            'tb27_chave_acesso',
                            'tb27_protocolo',
                            'tb27_recibo',
                            'tb27_xml_envio',
                            'tb27_emitida_em',
                            'created_at',
                        ])
                        ->map(fn (NotaFiscal $invoice) => $this->buildInvoiceListPayload($invoice))
                        ->values();
                } catch (Throwable) {
                    $invoiceLoadWarning = 'Nao foi possivel carregar todas as notas fiscais desta unidade neste ambiente. A tela foi mantida aberta sem derrubar a configuracao.';
                    $invoices = collect();
                }
            }

            $resolvedEndpoints = null;

            if ($configuration && filled($configuration->tb26_uf) && filled($configuration->tb26_ambiente)) {
                try {
                    $lastStep = 'resolver endpoints da SEFAZ para a unidade';
                    $resolvedEndpoints = $fiscalWebserviceResolverService->resolveNfceEndpoints(
                        (string) $configuration->tb26_uf,
                        (string) $configuration->tb26_ambiente,
                    );
                } catch (RuntimeException $exception) {
                    $resolvedEndpoints = [
                        'error' => $exception->getMessage(),
                    ];
                }
            }

            $lastStep = 'montar payload da configuracao fiscal para a tela';
            return $this->renderFiscalConfigPage(
                $units,
                $selectedUnitId,
                $unit,
                $this->buildFiscalConfigurationPayload($configuration, $unit, $selectedUnitId, $fiscalCertificateService),
                $invoices,
                $resolvedEndpoints,
                $this->buildConfigurationDiagnostics($configuration, $selectedUnitId, $fiscalCertificateService),
                null,
                $invoiceLoadWarning,
            );
        } catch (Throwable $exception) {
            report($exception);

            $fallbackDiagnostics = $this->buildRawConfigurationDiagnostics($selectedUnitId);
            $fallbackDiagnostics['loading_error'] = sprintf(
                'Etapa: %s | Detalhe: %s',
                $lastStep,
                $this->buildSafeExceptionMessage($exception)
            );

            return $this->renderFiscalConfigPage(
                $units,
                $selectedUnitId,
                null,
                $this->defaultFiscalConfigurationPayload($selectedUnitId),
                collect(),
                null,
                $fallbackDiagnostics,
                sprintf(
                    'O ambiente de producao retornou um erro interno ao montar os dados fiscais. Etapa: %s. Detalhe tecnico: %s',
                    $lastStep,
                    $this->buildSafeExceptionMessage($exception)
                ),
                null,
            );
        }
    }

    public function update(
        Request $request,
        FiscalCertificateService $fiscalCertificateService,
        FiscalMunicipalityCodeService $fiscalMunicipalityCodeService,
    ): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        $data = $request->validate([
            'tb2_id' => ['required', 'integer', 'exists:tb2_unidades,tb2_id'],
            'tb26_emitir_nfe' => ['nullable', 'boolean'],
            'tb26_emitir_nfce' => ['nullable', 'boolean'],
            'tb26_geracao_automatica_ativa' => ['nullable', 'boolean'],
            'tb26_ambiente' => ['required', Rule::in(['homologacao', 'producao'])],
            'tb26_serie' => ['required', 'string', 'max:10'],
            'tb26_proximo_numero' => ['required', 'integer', 'min:1'],
            'tb26_crt' => ['nullable', Rule::in(['1', '2', '3', 1, 2, 3])],
            'tb26_csc_id' => ['nullable', 'string', 'max:36'],
            'tb26_csc' => ['nullable', 'string', 'max:255'],
            'tb26_certificado_tipo' => ['nullable', Rule::in(['A1', 'A3'])],
            'tb26_certificado_nome' => ['nullable', 'string', 'max:255'],
            'tb26_certificado_cnpj' => ['nullable', 'string', 'max:18'],
            'tb26_certificado_arquivo_upload' => [
                'nullable',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower((string) ($value->getClientOriginalExtension() ?: $value->extension()));

                    if (! in_array($extension, ['pfx', 'p12'], true)) {
                        $fail('O campo certificado deve ser um arquivo do tipo: pfx, p12.');
                    }
                },
            ],
            'tb26_certificado_senha' => ['nullable', 'string', 'max:255'],
            'remover_certificado' => ['nullable', 'boolean'],
            'tb26_razao_social' => ['nullable', 'string', 'max:60'],
            'tb26_nome_fantasia' => ['nullable', 'string', 'max:60'],
            'tb26_ie' => ['nullable', 'string', 'max:20'],
            'tb26_im' => ['nullable', 'string', 'max:20'],
            'tb26_cnae' => ['nullable', 'string', 'max:10'],
            'tb26_logradouro' => ['nullable', 'string', 'max:60'],
            'tb26_numero' => ['nullable', 'string', 'max:60'],
            'tb26_complemento' => ['nullable', 'string', 'max:60'],
            'tb26_bairro' => ['nullable', 'string', 'max:60'],
            'tb26_codigo_municipio' => ['nullable', 'string', 'max:7'],
            'tb26_municipio' => ['nullable', 'string', 'max:60'],
            'tb26_uf' => ['nullable', 'string', 'size:2'],
            'tb26_cep' => ['nullable', 'string', 'max:8'],
            'tb26_telefone' => ['nullable', 'string', 'max:20'],
            'tb26_email' => ['nullable', 'email', 'max:255'],
        ]);

        $unitId = (int) $data['tb2_id'];

        try {
            if (! ManagementScope::canManageUnit($user, $unitId)) {
                abort(403, 'Acesso negado.');
            }

            $unit = Unidade::query()->findOrFail($unitId);

            $configuration = ConfiguracaoFiscal::query()->firstOrNew([
                'tb2_id' => $unitId,
            ]);

            if ($request->boolean('remover_certificado') && filled($configuration->tb26_certificado_arquivo)) {
                Storage::delete($configuration->tb26_certificado_arquivo);
                $configuration->tb26_certificado_arquivo = null;
                $configuration->tb26_certificado_senha = null;
                $configuration->tb26_certificado_senha_compartilhada = null;
            }

            if ($request->hasFile('tb26_certificado_arquivo_upload')) {
                if (filled($configuration->tb26_certificado_arquivo)) {
                    Storage::delete($configuration->tb26_certificado_arquivo);
                }

                $file = $request->file('tb26_certificado_arquivo_upload');
                $password = trim((string) ($data['tb26_certificado_senha'] ?? ''));

                if ($password === '' && ! $fiscalCertificateService->hasStoredPassword($configuration)) {
                    throw ValidationException::withMessages([
                        'tb26_certificado_senha' => 'Informe a senha do certificado para validar o arquivo enviado.',
                    ]);
                }

                $path = $file->storeAs(
                    'fiscal-certificados/' . $unitId,
                    'certificado-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension()
                );

                $configuration->tb26_certificado_arquivo = $path;

                try {
                    $inspection = $fiscalCertificateService->inspectStoredCertificate(
                        $path,
                        $password !== '' ? $password : (string) $fiscalCertificateService->resolveConfigurationPassword($configuration)
                    );
                } catch (RuntimeException $exception) {
                    Storage::delete($path);
                    $configuration->tb26_certificado_arquivo = null;

                    throw ValidationException::withMessages([
                        'tb26_certificado_arquivo_upload' => $exception->getMessage(),
                    ]);
                }

                $configuration->tb26_certificado_nome = $inspection['common_name'] ?? $configuration->tb26_certificado_nome;
                $configuration->tb26_certificado_cnpj = $inspection['cnpj'] ?? $configuration->tb26_certificado_cnpj;
                $configuration->tb26_certificado_valido_ate = isset($inspection['valid_to'])
                    ? now()->setTimestamp((int) $inspection['valid_to'])
                    : null;
            }

            $configuration->fill([
                'tb26_emitir_nfe' => (bool) ($data['tb26_emitir_nfe'] ?? false),
                'tb26_emitir_nfce' => (bool) ($data['tb26_emitir_nfce'] ?? false),
                'tb26_geracao_automatica_ativa' => (bool) ($data['tb26_geracao_automatica_ativa'] ?? true),
                'tb26_ambiente' => $data['tb26_ambiente'],
                'tb26_serie' => trim((string) $data['tb26_serie']),
                'tb26_proximo_numero' => (int) $data['tb26_proximo_numero'],
                'tb26_crt' => filled($data['tb26_crt'] ?? null) ? (int) $data['tb26_crt'] : null,
                'tb26_csc_id' => $this->nullableTrim($data['tb26_csc_id'] ?? null),
                'tb26_csc' => $this->nullableTrim($data['tb26_csc'] ?? null),
                'tb26_certificado_tipo' => $this->nullableTrim($data['tb26_certificado_tipo'] ?? null) ?? 'A1',
                'tb26_certificado_nome' => $this->nullableTrim($data['tb26_certificado_nome'] ?? null) ?? $configuration->tb26_certificado_nome,
                'tb26_certificado_cnpj' => $this->onlyDigits($data['tb26_certificado_cnpj'] ?? null) ?? $configuration->tb26_certificado_cnpj,
                'tb26_certificado_valido_ate' => $configuration->tb26_certificado_valido_ate,
                'tb26_razao_social' => $this->nullableTrim($data['tb26_razao_social'] ?? null),
                'tb26_nome_fantasia' => $this->nullableTrim($data['tb26_nome_fantasia'] ?? null),
                'tb26_ie' => $this->normalizeStateRegistration($data['tb26_ie'] ?? null),
                'tb26_im' => $this->nullableTrim($data['tb26_im'] ?? null),
                'tb26_cnae' => $this->onlyDigits($data['tb26_cnae'] ?? null),
                'tb26_logradouro' => $this->nullableTrim($data['tb26_logradouro'] ?? null),
                'tb26_numero' => $this->nullableTrim($data['tb26_numero'] ?? null),
                'tb26_complemento' => $this->nullableTrim($data['tb26_complemento'] ?? null),
                'tb26_bairro' => $this->nullableTrim($data['tb26_bairro'] ?? null),
                'tb26_codigo_municipio' => $this->onlyDigits($data['tb26_codigo_municipio'] ?? null),
                'tb26_municipio' => $this->nullableTrim($data['tb26_municipio'] ?? null),
                'tb26_uf' => strtoupper((string) ($data['tb26_uf'] ?? '')),
                'tb26_cep' => $this->onlyDigits($data['tb26_cep'] ?? null),
                'tb26_telefone' => $this->nullableTrim($data['tb26_telefone'] ?? null),
                'tb26_email' => $this->nullableTrim($data['tb26_email'] ?? null),
            ]);

            if ($request->filled('tb26_certificado_senha')) {
                $configuration->tb26_certificado_senha = null;
                $configuration->tb26_certificado_senha_compartilhada = $data['tb26_certificado_senha'];
            } elseif (! filled($configuration->getRawOriginal('tb26_certificado_senha_compartilhada'))) {
                $resolvedPassword = $fiscalCertificateService->resolveConfigurationPassword($configuration);

                if ($resolvedPassword !== null && $resolvedPassword !== '') {
                    $configuration->tb26_certificado_senha_compartilhada = $resolvedPassword;
                }
            }

            if (filled($configuration->getRawOriginal('tb26_certificado_senha_compartilhada'))) {
                $configuration->tb26_certificado_senha = null;
            }

            $this->ensureCertificateMatchesUnit($configuration, $unit);
            $this->ensureMunicipalityCodeMatchesUf($configuration, $fiscalMunicipalityCodeService);

            $configuration->save();

            return redirect()
                ->route('settings.fiscal', ['unit_id' => $unitId])
                ->with('success', 'Configuracao fiscal atualizada com sucesso.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('settings.fiscal', ['unit_id' => $unitId])
                ->with('error', sprintf(
                    'Nao foi possivel salvar a configuracao fiscal neste ambiente. Detalhe tecnico: %s',
                    $this->buildSafeExceptionMessage($exception)
                ));
        }
    }

    public function reprocess(
        Request $request,
        FiscalInvoicePreparationService $fiscalInvoicePreparationService,
    ): RedirectResponse {
        $user = $request->user();
        $this->ensureAdmin($user);

        $data = $request->validate([
            'tb2_id' => ['required', 'integer', 'exists:tb2_unidades,tb2_id'],
        ]);

        $unitId = (int) $data['tb2_id'];

        if (! ManagementScope::canManageUnit($user, $unitId)) {
            abort(403, 'Acesso negado.');
        }

        $count = $fiscalInvoicePreparationService->reprocessPendingInvoicesForUnit($unitId);

        return redirect()
            ->route('settings.fiscal', ['unit_id' => $unitId])
            ->with('success', sprintf('%d nota(s) fiscal(is) reprocessada(s) para a loja selecionada.', $count));
    }

    public function regenerateInvoice(
        Request $request,
        NotaFiscal $notaFiscal,
        FiscalInvoicePreparationService $fiscalInvoicePreparationService,
    ): RedirectResponse {
        $user = $request->user();
        $this->ensureAdmin($user);

        if (! ManagementScope::canManageUnit($user, (int) $notaFiscal->tb2_id)) {
            abort(403, 'Acesso negado.');
        }

        $notaFiscal->loadMissing('pagamento.vendas.produto', 'pagamento.vendas.unidade');

        if (! $notaFiscal->pagamento) {
            return redirect()
                ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                ->with('error', 'Nao foi encontrado o pagamento vinculado a esta nota fiscal.');
        }

        $invoice = $fiscalInvoicePreparationService->prepareForPayment($notaFiscal->pagamento);

        return redirect()
            ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
            ->with('success', sprintf(
                'Nota fiscal da venda %d regenerada com status %s.',
                (int) $notaFiscal->tb4_id,
                $invoice?->tb27_status ?? 'indefinido'
            ));
    }

    public function destroyInvoice(Request $request, NotaFiscal $notaFiscal): RedirectResponse
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        if (! ManagementScope::canManageUnit($user, (int) $notaFiscal->tb2_id)) {
            abort(403, 'Acesso negado.');
        }

        if (! $this->canDeletePreparedInvoice($notaFiscal)) {
            return redirect()
                ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                ->with('error', 'Somente notas preparadas que ainda nao foram transmitidas podem ser excluidas.');
        }

        $paymentId = (int) $notaFiscal->tb4_id;
        $unitId = (int) $notaFiscal->tb2_id;

        $notaFiscal->delete();

        return redirect()
            ->route('settings.fiscal', ['unit_id' => $unitId])
            ->with('success', sprintf(
                'Nota fiscal preparada da venda %d excluida com sucesso.',
                $paymentId
            ));
    }

    public function downloadXml(Request $request, NotaFiscal $notaFiscal)
    {
        $user = $request->user();
        $this->ensureAdmin($user);

        if (! ManagementScope::canManageUnit($user, (int) $notaFiscal->tb2_id)) {
            abort(403, 'Acesso negado.');
        }

        if (! filled($notaFiscal->tb27_xml_envio)) {
            abort(404, 'Nao existe XML assinado para esta nota.');
        }

        $filename = sprintf('nfce-loja-%d-venda-%d.xml', (int) $notaFiscal->tb2_id, (int) $notaFiscal->tb4_id);

        return response($notaFiscal->tb27_xml_envio, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function transmit(
        Request $request,
        NotaFiscal $notaFiscal,
        FiscalInvoicePreparationService $fiscalInvoicePreparationService,
        FiscalNfceTransmissionService $fiscalNfceTransmissionService,
    ): RedirectResponse {
        $user = $request->user();
        $this->ensureAdmin($user);

        if (! ManagementScope::canManageUnit($user, (int) $notaFiscal->tb2_id)) {
            abort(403, 'Acesso negado.');
        }

        try {
            $notaFiscal->loadMissing('pagamento.vendas.produto', 'pagamento.vendas.unidade');

            if (! $notaFiscal->pagamento) {
                return redirect()
                    ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                    ->with('error', 'Nao foi encontrado o pagamento vinculado a esta nota fiscal.');
            }

            $refreshedInvoice = $fiscalInvoicePreparationService->prepareForPayment($notaFiscal->pagamento);

            if (! $refreshedInvoice) {
                return redirect()
                    ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                    ->with('error', 'Esta forma de pagamento nao gera nota fiscal automatica para transmissao.');
            }

            if (! filled($refreshedInvoice->tb27_xml_envio)) {
                return redirect()
                    ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                    ->with('error', $refreshedInvoice->tb27_mensagem ?: 'A nota ainda nao possui XML assinado para transmissao.');
            }

            $fiscalNfceTransmissionService->transmit($refreshedInvoice);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('settings.fiscal', ['unit_id' => $notaFiscal->tb2_id])
            ->with('success', sprintf('Nota fiscal da venda %d enviada para a SEFAZ.', (int) $notaFiscal->tb4_id));
    }

    private function ensureAdmin($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403, 'Acesso negado.');
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function onlyDigits(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeStateRegistration(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if ($value === 'ISENTO') {
            return 'ISENTO';
        }

        return $this->onlyDigits($value);
    }

    private function ensureCertificateMatchesUnit(ConfiguracaoFiscal $configuration, Unidade $unit): void
    {
        $certificateCnpj = $this->onlyDigits($configuration->tb26_certificado_cnpj);
        $unitCnpj = $this->onlyDigits($unit->tb2_cnpj);

        if (! $certificateCnpj || ! $unitCnpj) {
            return;
        }

        $unitBase = substr($unitCnpj, 0, 8);
        $certificateBase = substr($certificateCnpj, 0, 8);

        if ($unitBase !== $certificateBase) {
            throw ValidationException::withMessages([
                'tb26_certificado_cnpj' => 'O certificado informado nao pertence ao mesmo CNPJ base da loja selecionada.',
            ]);
        }
    }

    private function ensureMunicipalityCodeMatchesUf(
        ConfiguracaoFiscal $configuration,
        FiscalMunicipalityCodeService $fiscalMunicipalityCodeService,
    ): void {
        if (! filled($configuration->tb26_uf) || ! filled($configuration->tb26_codigo_municipio)) {
            return;
        }

        if ($fiscalMunicipalityCodeService->matchesUf($configuration->tb26_uf, $configuration->tb26_codigo_municipio)) {
            return;
        }

        $expectedPrefix = $fiscalMunicipalityCodeService->expectedPrefixForUf($configuration->tb26_uf);

        throw ValidationException::withMessages([
            'tb26_codigo_municipio' => sprintf(
                'O codigo do municipio IBGE %s nao pertence a UF %s. Use um codigo iniciado por %s.',
                (string) $configuration->tb26_codigo_municipio,
                (string) $configuration->tb26_uf,
                $expectedPrefix ?? '--'
            ),
        ]);
    }

    private function buildReceiptPayload(VendaPagamento $payment): array
    {
        $sales = $payment->vendas->values();
        $firstSale = $sales->first();
        $saleDateTime = $firstSale?->data_hora ?? $payment->created_at;
        $receiptComanda = $this->resolveReceiptComanda($sales);

        return [
            'id' => $payment->tb4_id,
            'comanda' => $receiptComanda,
            'total' => round((float) $payment->valor_total, 2),
            'date_time' => $saleDateTime?->toIso8601String(),
            'tipo_pago' => $payment->tipo_pagamento,
            'cashier_name' => $firstSale?->caixa?->name ?? '---',
            'unit_name' => $firstSale?->unidade?->tb2_nome ?? '---',
            'unit_address' => $firstSale?->unidade?->tb2_endereco,
            'unit_cnpj' => $firstSale?->unidade?->tb2_cnpj,
            'vale_user_name' => $firstSale?->valeUser?->name,
            'vale_type' => in_array($payment->tipo_pagamento, ['vale', 'refeicao'], true)
                ? $payment->tipo_pagamento
                : null,
            'payment' => [
                'id' => $payment->tb4_id,
                'valor_total' => round((float) $payment->valor_total, 2),
                'valor_pago' => $payment->valor_pago !== null ? round((float) $payment->valor_pago, 2) : null,
                'troco' => $payment->troco !== null ? round((float) $payment->troco, 2) : null,
                'dois_pgto' => $payment->dois_pgto !== null ? round((float) $payment->dois_pgto, 2) : null,
            ],
            'items' => $sales
                ->map(fn (Venda $sale) => [
                    'id' => $sale->tb3_id,
                    'product_name' => $sale->produto_nome,
                    'quantity' => (int) $sale->quantidade,
                    'unit_price' => round((float) $sale->valor_unitario, 2),
                    'subtotal' => round((float) $sale->valor_total, 2),
                    'comanda' => $sale->id_comanda,
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveReceiptComanda($sales): ?string
    {
        $comanda = $sales
            ->pluck('id_comanda')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->unique()
            ->implode(', ');

        return $comanda !== '' ? $comanda : null;
    }

    private function buildFiscalReceiptPayload(NotaFiscal $invoice): ?array
    {
        $payment = $invoice->pagamento;

        if (! $payment) {
            return null;
        }

        $sales = $payment->vendas->values();
        $firstSale = $sales->first();
        $configuration = $invoice->configuracaoFiscal;
        $invoicePayload = $invoice->tb27_payload ?? [];
        $fiscalItems = collect($invoicePayload['itens'] ?? []);
        $excludedItems = collect($invoicePayload['itens_excluidos'] ?? []);
        $xmlData = $this->extractFiscalReceiptXmlData($invoice->tb27_xml_envio);
        $issueDateTime = $invoice->tb27_emitida_em ?? $firstSale?->data_hora ?? $invoice->created_at;
        $emitterName = $configuration?->tb26_nome_fantasia
            ?: $configuration?->tb26_razao_social
            ?: $firstSale?->unidade?->tb2_nome
            ?: 'EMITENTE NAO INFORMADO';
        $documentTotal = round((float) ($invoicePayload['valor_total_documento'] ?? $payment->valor_total), 2);
        $statusMessage = $this->augmentFiscalStatusMessage($invoice->tb27_mensagem, $xmlData);

        return [
            'title' => strtolower((string) $invoice->tb27_modelo) === 'nfce' ? 'DANFE NFC-e' : 'Documento fiscal',
            'subtitle' => 'Documento auxiliar da nota fiscal eletronica para impressao em 80mm',
            'payment_id' => (int) $invoice->tb4_id,
            'invoice_id' => (int) $invoice->tb27_id,
            'model_label' => strtoupper((string) $invoice->tb27_modelo) === 'NFCE' ? 'NFC-e' : strtoupper((string) $invoice->tb27_modelo),
            'environment' => $invoice->tb27_ambiente === 'producao' ? 'Producao' : 'Homologacao',
            'serie' => $invoice->tb27_serie,
            'number' => $invoice->tb27_numero,
            'status' => $invoice->tb27_status,
            'status_message' => $statusMessage,
            'issued_at' => $issueDateTime?->toIso8601String(),
            'emitter_name' => $emitterName,
            'emitter_legal_name' => $configuration?->tb26_razao_social,
            'emitter_document' => $firstSale?->unidade?->tb2_cnpj,
            'emitter_ie' => $configuration?->tb26_ie,
            'emitter_address' => $this->buildEmitterAddress($configuration),
            'consumer_name' => 'CONSUMIDOR NAO IDENTIFICADO',
            'payment_label' => $this->resolvePaymentLabel($payment->tipo_pagamento),
            'total' => $documentTotal,
            'amount_paid' => $documentTotal,
            'change' => null,
            'additional_payment' => null,
            'access_key' => $invoice->tb27_chave_acesso ?: ($xmlData['access_key'] ?? null),
            'protocol' => $invoice->tb27_protocolo,
            'receipt' => $invoice->tb27_recibo,
            'consulta_url' => $xmlData['consulta_url'] ?? null,
            'qr_code_data' => $xmlData['qr_code_data'] ?? null,
            'is_preview' => $invoice->tb27_status !== 'emitida',
            'excluded_items' => $excludedItems->values()->all(),
            'items' => $fiscalItems
                ->map(fn (array $item) => [
                    'id' => $item['produto_id'] ?? null,
                    'product_name' => $item['descricao'] ?? 'ITEM FISCAL',
                    'quantity' => (float) ($item['quantidade'] ?? 0),
                    'unit_price' => round((float) ($item['valor_unitario'] ?? 0), 2),
                    'subtotal' => round((float) ($item['valor_total'] ?? 0), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    private function buildEmitterAddress(?ConfiguracaoFiscal $configuration): ?string
    {
        if (! $configuration) {
            return null;
        }

        $lineOne = trim(implode(', ', array_filter([
            $configuration->tb26_logradouro,
            $configuration->tb26_numero,
            $configuration->tb26_complemento,
        ])));

        $lineTwo = trim(implode(' - ', array_filter([
            $configuration->tb26_bairro,
            trim(implode('/', array_filter([
                $configuration->tb26_municipio,
                $configuration->tb26_uf,
            ]))),
        ])));

        $parts = array_filter([
            $lineOne,
            $lineTwo,
            $configuration->tb26_cep ? 'CEP ' . $configuration->tb26_cep : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }

    private function extractFiscalReceiptXmlData(?string $xml): array
    {
        if (! filled($xml)) {
            return [];
        }

        if (! class_exists(DOMDocument::class) || ! class_exists(DOMXPath::class)) {
            return [];
        }

        $document = new DOMDocument();

        if (! @$document->loadXML((string) $xml)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $qrCodeData = $this->stringOrNull($xpath->evaluate('string(//nfe:infNFeSupl/nfe:qrCode)'));
        $queryString = $qrCodeData ? (string) parse_url($qrCodeData, PHP_URL_QUERY) : '';
        $payload = str_starts_with($queryString, 'p=') ? substr($queryString, 2) : $queryString;
        $payloadParts = $payload !== '' ? explode('|', $payload) : [];

        return [
            'access_key' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/@Id)'))
                ? preg_replace('/^NFe/', '', (string) $xpath->evaluate('string(//nfe:infNFe/@Id)'))
                : null,
            'qr_code_data' => $qrCodeData,
            'consulta_url' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFeSupl/nfe:urlChave)')),
            'mod' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/nfe:ide/nfe:mod)')),
            'tp_imp' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/nfe:ide/nfe:tpImp)')),
            'dest_present' => (bool) $xpath->evaluate('count(//nfe:infNFe/nfe:dest)'),
            'dest_name' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/nfe:dest/nfe:xNome)')),
            'dest_city_code' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/nfe:dest/nfe:enderDest/nfe:cMun)')),
            'dest_document' => $this->stringOrNull($xpath->evaluate('string(//nfe:infNFe/nfe:dest/nfe:CPF | //nfe:infNFe/nfe:dest/nfe:CNPJ | //nfe:infNFe/nfe:dest/nfe:idEstrangeiro)')),
            'card_present' => (bool) $xpath->evaluate('count(//nfe:infNFe/nfe:pag/nfe:detPag/nfe:card)'),
            'signature_present' => (bool) $xpath->evaluate('count(//ds:Signature)'),
            'csc_id' => $payloadParts[3] ?? null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function augmentFiscalStatusMessage(?string $message, array $xmlData = []): ?string
    {
        $message = $this->stringOrNull($message);

        if ($message === null || ! str_contains($message, 'cStat 462')) {
            return $message;
        }

        $cscId = $xmlData['csc_id'] ?? null;
        $suffix = sprintf(
            ' Confira o CSC ID%s cadastrado na SEFAZ para o ambiente atual da loja.',
            $cscId ? ' ' . $cscId : ''
        );

        return str_contains($message, $suffix) ? $message : $message . $suffix;
    }

    private function resolvePaymentLabel(?string $paymentType): string
    {
        return match ((string) $paymentType) {
            'dinheiro' => 'Dinheiro',
            'cartao_credito' => 'Cartao credito',
            'cartao_debito' => 'Cartao debito',
            'dinheiro_cartao_credito' => 'Dinheiro + Cartao credito',
            'dinheiro_cartao_debito' => 'Dinheiro + Cartao debito',
            'maquina' => 'Maquina',
            'vale' => 'Vale',
            'refeicao' => 'Refeicao',
            'faturar' => 'Faturar',
            default => strtoupper(str_replace('_', ' ', trim((string) $paymentType))) ?: 'Nao informado',
        };
    }

    private function buildInvoiceListPayload(NotaFiscal $invoice): array
    {
        $invoicePayload = is_array($invoice->tb27_payload) ? $invoice->tb27_payload : [];
        $xmlData = $this->extractFiscalReceiptXmlData($invoice->tb27_xml_envio);

        $payload = [
            'id' => (int) $invoice->tb27_id,
            'payment_id' => (int) $invoice->tb4_id,
            'modelo' => $invoice->tb27_modelo,
            'ambiente' => $invoice->tb27_ambiente,
            'serie' => $invoice->tb27_serie,
            'numero' => $invoice->tb27_numero,
            'status' => $invoice->tb27_status,
            'mensagem' => $this->augmentFiscalStatusMessage($invoice->tb27_mensagem, $xmlData),
            'chave_acesso' => $invoice->tb27_chave_acesso,
            'protocolo' => $invoice->tb27_protocolo,
            'recibo' => $invoice->tb27_recibo,
            'emitida_em' => optional($invoice->tb27_emitida_em)->format('d/m/y H:i'),
            'criada_em' => optional($invoice->created_at)->format('d/m/y H:i'),
            'total' => round((float) ($invoicePayload['valor_total_documento'] ?? $invoice->pagamento?->valor_total ?? 0), 2),
            'xml_disponivel' => filled($invoice->tb27_xml_envio),
            'pode_regenerar' => in_array($invoice->tb27_status, [
                'pendente_configuracao',
                'erro_validacao',
                'erro_transmissao',
                'pendente_emissao',
                'xml_assinado',
                'emitida',
            ], true),
            'pode_excluir' => $this->canDeletePreparedInvoice($invoice),
            'itens_excluidos_qtd' => (int) ($invoicePayload['itens_excluidos_qtd'] ?? 0),
            'xml_debug' => [
                'mod' => $xmlData['mod'] ?? null,
                'tp_imp' => $xmlData['tp_imp'] ?? null,
                'dest_present' => $xmlData['dest_present'] ?? false,
                'dest_document' => $xmlData['dest_document'] ?? null,
                'dest_name' => $xmlData['dest_name'] ?? null,
                'dest_city_code' => $xmlData['dest_city_code'] ?? null,
                'card_present' => $xmlData['card_present'] ?? false,
                'signature_present' => $xmlData['signature_present'] ?? false,
                'csc_id' => $xmlData['csc_id'] ?? null,
                'qr_code_data' => $xmlData['qr_code_data'] ?? null,
            ],
            'fiscal_receipt' => null,
        ];

        if (! $invoice->pagamento) {
            return $payload;
        }

        try {
            $payload['fiscal_receipt'] = $this->buildFiscalReceiptPayload($invoice);
        } catch (Throwable) {
            $payload['fiscal_receipt'] = null;
        }

        return $payload;
    }

    private function canDeletePreparedInvoice(NotaFiscal $invoice): bool
    {
        return ! in_array($invoice->tb27_status, ['emitida', 'cancelada'], true)
            && ! filled($invoice->tb27_protocolo)
            && ! filled($invoice->tb27_recibo);
    }

    private function fiscalTablesAreAvailable(): bool
    {
        try {
            return Schema::hasTable('tb26_configuracoes_fiscais')
                && Schema::hasTable('tb27_notas_fiscais');
        } catch (Throwable) {
            return false;
        }
    }

    private function renderFiscalConfigPage(
        $units,
        int $selectedUnitId,
        ?Unidade $unit,
        array $configuration,
        $invoices,
        ?array $resolvedEndpoints,
        array $configurationDiagnostics,
        ?string $fiscalUnavailableMessage,
        ?string $invoiceLoadWarning,
    ): Response {
        return Inertia::render('Settings/FiscalConfig', [
            'units' => $units,
            'selectedUnitId' => $selectedUnitId > 0 ? $selectedUnitId : null,
            'unit' => $unit ? [
                'id' => (int) $unit->tb2_id,
                'name' => $unit->tb2_nome,
                'cnpj' => $unit->tb2_cnpj,
                'endereco' => $unit->tb2_endereco,
                'cnpj_digits' => $this->onlyDigits($unit->tb2_cnpj),
            ] : null,
            'configuration' => $configuration,
            'resolvedEndpoints' => $resolvedEndpoints,
            'configurationDiagnostics' => $configurationDiagnostics,
            'invoices' => $invoices,
            'fiscalUnavailableMessage' => $fiscalUnavailableMessage,
            'invoiceLoadWarning' => $invoiceLoadWarning,
        ]);
    }

    private function defaultFiscalConfigurationPayload(int $selectedUnitId): array
    {
        return [
            'tb2_id' => $selectedUnitId > 0 ? $selectedUnitId : null,
            'tb26_emitir_nfe' => false,
            'tb26_emitir_nfce' => false,
            'tb26_geracao_automatica_ativa' => true,
            'tb26_ambiente' => 'homologacao',
            'tb26_serie' => '1',
            'tb26_proximo_numero' => 1,
            'tb26_crt' => '',
            'tb26_csc_id' => '',
            'tb26_csc' => '',
            'tb26_certificado_tipo' => 'A1',
            'tb26_certificado_nome' => '',
            'tb26_certificado_cnpj' => '',
            'tb26_certificado_arquivo' => '',
            'tb26_certificado_valido_ate' => '',
            'tb26_razao_social' => '',
            'tb26_nome_fantasia' => '',
            'tb26_ie' => '',
            'tb26_im' => '',
            'tb26_cnae' => '',
            'tb26_logradouro' => '',
            'tb26_numero' => '',
            'tb26_complemento' => '',
            'tb26_bairro' => '',
            'tb26_codigo_municipio' => '',
            'tb26_municipio' => '',
            'tb26_uf' => '',
            'tb26_cep' => '',
            'tb26_telefone' => '',
            'tb26_email' => '',
            'has_certificate_password' => false,
        ];
    }

    private function buildFiscalConfigurationPayload(
        ?ConfiguracaoFiscal $configuration,
        ?Unidade $unit,
        int $selectedUnitId,
        FiscalCertificateService $fiscalCertificateService,
    ): array {
        return [
            'tb2_id' => $selectedUnitId > 0 ? $selectedUnitId : null,
            'tb26_emitir_nfe' => (bool) ($configuration?->tb26_emitir_nfe ?? false),
            'tb26_emitir_nfce' => (bool) ($configuration?->tb26_emitir_nfce ?? false),
            'tb26_geracao_automatica_ativa' => (bool) ($configuration?->tb26_geracao_automatica_ativa ?? true),
            'tb26_ambiente' => $configuration?->tb26_ambiente ?? 'homologacao',
            'tb26_serie' => $configuration?->tb26_serie ?? '1',
            'tb26_proximo_numero' => (int) ($configuration?->tb26_proximo_numero ?? 1),
            'tb26_crt' => $configuration?->tb26_crt ? (string) $configuration->tb26_crt : '',
            'tb26_csc_id' => $configuration?->tb26_csc_id ?? '',
            'tb26_csc' => $configuration?->tb26_csc ?? '',
            'tb26_certificado_tipo' => $configuration?->tb26_certificado_tipo ?? 'A1',
            'tb26_certificado_nome' => $configuration?->tb26_certificado_nome ?? '',
            'tb26_certificado_cnpj' => $configuration?->tb26_certificado_cnpj ?? '',
            'tb26_certificado_arquivo' => $configuration?->tb26_certificado_arquivo ?? '',
            'tb26_certificado_valido_ate' => $configuration?->tb26_certificado_valido_ate
                ? $configuration->tb26_certificado_valido_ate->format('d/m/y H:i')
                : '',
            'tb26_razao_social' => $configuration?->tb26_razao_social ?? '',
            'tb26_nome_fantasia' => $configuration?->tb26_nome_fantasia ?? ($unit?->tb2_nome ?? ''),
            'tb26_ie' => $configuration?->tb26_ie ?? '',
            'tb26_im' => $configuration?->tb26_im ?? '',
            'tb26_cnae' => $configuration?->tb26_cnae ?? '',
            'tb26_logradouro' => $configuration?->tb26_logradouro ?? '',
            'tb26_numero' => $configuration?->tb26_numero ?? '',
            'tb26_complemento' => $configuration?->tb26_complemento ?? '',
            'tb26_bairro' => $configuration?->tb26_bairro ?? '',
            'tb26_codigo_municipio' => $configuration?->tb26_codigo_municipio ?? '',
            'tb26_municipio' => $configuration?->tb26_municipio ?? '',
            'tb26_uf' => $configuration?->tb26_uf ?? '',
            'tb26_cep' => $configuration?->tb26_cep ?? '',
            'tb26_telefone' => $configuration?->tb26_telefone ?? '',
            'tb26_email' => $configuration?->tb26_email ?? '',
            'has_certificate_password' => $configuration ? $fiscalCertificateService->hasStoredPassword($configuration) : false,
        ];
    }

    private function defaultConfigurationDiagnostics(int $selectedUnitId): array
    {
        return [
            'selected_unit_id' => $selectedUnitId > 0 ? $selectedUnitId : null,
            'configuration_found' => false,
            'configuration_id' => null,
            'storage_path' => null,
            'storage_exists' => false,
            'legacy_storage_exists' => false,
            'raw_password_present' => false,
            'shared_password_present' => false,
            'password_decryptable' => null,
            'password_source' => null,
            'password_status' => 'Configuracao fiscal ainda nao encontrada no banco.',
            'raw_configuration_found' => false,
            'raw_configuration_id' => null,
            'loading_error' => null,
        ];
    }

    private function buildSafeExceptionMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message !== '') {
            return $message;
        }

        return class_basename($exception);
    }

    private function buildConfigurationDiagnostics(
        ?ConfiguracaoFiscal $configuration,
        int $selectedUnitId,
        FiscalCertificateService $fiscalCertificateService,
    ): array
    {
        $diagnostics = $this->defaultConfigurationDiagnostics($selectedUnitId);

        if (! $configuration) {
            return $diagnostics;
        }

        $storagePath = $this->stringOrNull($configuration->tb26_certificado_arquivo);
        $rawPassword = trim((string) $configuration->getRawOriginal('tb26_certificado_senha'));
        $sharedPassword = trim((string) $configuration->getRawOriginal('tb26_certificado_senha_compartilhada'));
        $passwordDecryption = $fiscalCertificateService->resolveConfigurationPasswordDetails($configuration);

        $diagnostics['configuration_found'] = true;
        $diagnostics['configuration_id'] = (int) $configuration->tb26_id;
        $diagnostics['storage_path'] = $storagePath;
        $diagnostics['storage_exists'] = $storagePath ? Storage::exists($storagePath) : false;
        $diagnostics['legacy_storage_exists'] = $storagePath && ! str_starts_with($storagePath, 'private/')
            ? Storage::exists('private/' . $storagePath)
            : false;
        $diagnostics['raw_password_present'] = $rawPassword !== '' || $sharedPassword !== '';
        $diagnostics['shared_password_present'] = $sharedPassword !== '';
        $diagnostics['password_decryptable'] = $passwordDecryption['readable'];
        $diagnostics['password_status'] = $passwordDecryption['message'];
        $diagnostics['password_source'] = $passwordDecryption['source'];
        $diagnostics['raw_configuration_found'] = true;
        $diagnostics['raw_configuration_id'] = (int) $configuration->tb26_id;

        return $diagnostics;
    }

    private function buildRawConfigurationDiagnostics(int $selectedUnitId): array
    {
        $diagnostics = $this->defaultConfigurationDiagnostics($selectedUnitId);

        if ($selectedUnitId <= 0 || ! $this->fiscalTablesAreAvailable()) {
            return $diagnostics;
        }

        try {
            $row = DB::table('tb26_configuracoes_fiscais')
                ->where('tb2_id', $selectedUnitId)
                ->first([
                    'tb26_id',
                    'tb26_certificado_arquivo',
                    'tb26_certificado_senha',
                    'tb26_certificado_senha_compartilhada',
                ]);

            if (! $row) {
                return $diagnostics;
            }

            $storagePath = $this->stringOrNull($row->tb26_certificado_arquivo ?? null);

            $diagnostics['raw_configuration_found'] = true;
            $diagnostics['raw_configuration_id'] = (int) $row->tb26_id;
            $diagnostics['storage_path'] = $storagePath;
            $diagnostics['storage_exists'] = $storagePath ? Storage::exists($storagePath) : false;
            $diagnostics['legacy_storage_exists'] = $storagePath && ! str_starts_with($storagePath, 'private/')
                ? Storage::exists('private/' . $storagePath)
                : false;
            $diagnostics['raw_password_present'] = trim((string) ($row->tb26_certificado_senha ?? '')) !== '';
            $diagnostics['shared_password_present'] = trim((string) ($row->tb26_certificado_senha_compartilhada ?? '')) !== '';

            return $diagnostics;
        } catch (Throwable $exception) {
            $diagnostics['loading_error'] = sprintf(
                'Falha ao consultar a configuracao fiscal crua no banco: %s',
                $this->buildSafeExceptionMessage($exception)
            );

            return $diagnostics;
        }
    }
}
