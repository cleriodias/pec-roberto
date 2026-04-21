import AlertMessage from '@/Components/Alert/AlertMessage';
import PrimaryButton from '@/Components/Button/PrimaryButton';
import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { buildFiscalReceiptHtml, formatReceiptCurrency } from '@/Utils/receipt';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUS_CLASS = {
    pendente_configuracao: 'border-amber-200 bg-amber-50 text-amber-800',
    erro_validacao: 'border-rose-200 bg-rose-50 text-rose-800',
    erro_transmissao: 'border-rose-200 bg-rose-50 text-rose-800',
    pendente_emissao: 'border-blue-200 bg-blue-50 text-blue-800',
    xml_assinado: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    emitida: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    cancelada: 'border-slate-200 bg-slate-100 text-slate-700',
};

const STATUS_LABEL = {
    pendente_configuracao: 'Pendente configuracao',
    erro_validacao: 'Erro de validacao',
    erro_transmissao: 'Erro de transmissao',
    pendente_emissao: 'Pendente emissao',
    xml_assinado: 'XML assinado',
    emitida: 'Emitida',
    cancelada: 'Cancelada',
};

const EMITTER_FIELDS = [
    ['tb26_razao_social', 'Razao social'],
    ['tb26_nome_fantasia', 'Nome fantasia'],
    ['tb26_ie', 'Inscricao estadual'],
    ['tb26_im', 'Inscricao municipal'],
    ['tb26_cnae', 'CNAE'],
    ['tb26_email', 'Email fiscal'],
    ['tb26_telefone', 'Telefone'],
    ['tb26_cep', 'CEP'],
    ['tb26_logradouro', 'Logradouro', 'xl:col-span-2'],
    ['tb26_numero', 'Numero'],
    ['tb26_complemento', 'Complemento'],
    ['tb26_bairro', 'Bairro'],
    ['tb26_codigo_municipio', 'Codigo municipio IBGE'],
    ['tb26_municipio', 'Municipio'],
    ['tb26_uf', 'UF'],
];

const badgeClassName = (status) =>
    `inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${
        STATUS_CLASS[status] ?? STATUS_CLASS.pendente_configuracao
    }`;

const inputClassName =
    'mt-2 block w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';

const UnitCard = ({ unit }) => (
    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
        <div className="flex flex-col gap-2">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {unit?.name ?? 'Unidade'}
            </h3>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                CNPJ base da unidade: {unit?.cnpj ?? '--'}
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Endereco cadastrado na unidade: {unit?.endereco ?? '--'}
            </p>
        </div>
    </div>
);

const CertificateSummaryCard = ({ unit, configuration, resolvedEndpoints }) => (
    <div className="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm dark:border-blue-500/20 dark:bg-blue-500/10">
        <div className="flex flex-col gap-2 text-sm text-blue-900 dark:text-blue-100">
            <p className="text-xs font-semibold uppercase tracking-wide">Associacao loja e certificado</p>
            <p>Loja: {unit?.name ?? '--'}</p>
            <p>CNPJ da loja: {unit?.cnpj ?? '--'}</p>
            <p>Nome do certificado: {configuration?.tb26_certificado_nome || '--'}</p>
            <p>CNPJ do certificado: {configuration?.tb26_certificado_cnpj || '--'}</p>
            <p>Validade do certificado: {configuration?.tb26_certificado_valido_ate || '--'}</p>
            <p>Arquivo vinculado: {configuration?.tb26_certificado_arquivo || '--'}</p>
            <p>Endpoint envio NFC-e: {resolvedEndpoints?.authorization || resolvedEndpoints?.error || '--'}</p>
            <p>WSDL autorizacao NFC-e: {resolvedEndpoints?.authorization_wsdl || '--'}</p>
        </div>
    </div>
);

const DiagnosticsCard = ({ diagnostics }) => {
    if (!diagnostics) {
        return null;
    }

    const statusClassName =
        diagnostics.password_decryptable === false
            ? 'text-rose-700 dark:text-rose-200'
            : diagnostics.password_decryptable === true
              ? 'text-emerald-700 dark:text-emerald-200'
              : 'text-amber-700 dark:text-amber-200';

    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <div className="flex flex-col gap-2 text-sm text-slate-800 dark:text-slate-100">
                <p className="text-xs font-semibold uppercase tracking-wide">Diagnostico do ambiente</p>
                <p>Unidade selecionada: {diagnostics.selected_unit_id ?? '--'}</p>
                <p>Configuracao encontrada no banco: {diagnostics.configuration_found ? 'Sim' : 'Nao'}</p>
                <p>ID da configuracao fiscal: {diagnostics.configuration_id ?? '--'}</p>
                <p>Configuracao crua encontrada no banco: {diagnostics.raw_configuration_found ? 'Sim' : 'Nao'}</p>
                <p>ID cru da configuracao fiscal: {diagnostics.raw_configuration_id ?? '--'}</p>
                <p>Caminho salvo do certificado: {diagnostics.storage_path || '--'}</p>
                <p>Arquivo existe no storage deste ambiente: {diagnostics.storage_exists ? 'Sim' : 'Nao'}</p>
                <p>Arquivo existe no caminho legado deste ambiente: {diagnostics.legacy_storage_exists ? 'Sim' : 'Nao'}</p>
                <p>Senha criptografada presente no banco: {diagnostics.raw_password_present ? 'Sim' : 'Nao'}</p>
                <p>Senha compartilhada presente no banco: {diagnostics.shared_password_present ? 'Sim' : 'Nao'}</p>
                <p>Fonte da senha lida neste ambiente: {diagnostics.password_source || '--'}</p>
                <p className={statusClassName}>Leitura da senha neste ambiente: {diagnostics.password_status || '--'}</p>
                {diagnostics.loading_error ? (
                    <p className="text-rose-700 dark:text-rose-200">Falha de carregamento: {diagnostics.loading_error}</p>
                ) : null}
            </div>
        </div>
    );
};

const actionButtonClassName =
    'inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold';

const TrashIcon = () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M10 11v6" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M14 11v6" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
    </svg>
);

const InvoiceTable = ({ invoices = [], onDeleteInvoice, onPrintFiscalReceipt }) => (
    <section className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
        <div className="flex flex-col gap-1">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Ultimas notas preparadas
            </h3>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                As vendas finalizadas passam a gerar um registro fiscal para preparacao e validacao.
            </p>
        </div>

        {invoices.length === 0 ? (
            <p className="mt-4 rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                Ainda nao existem notas preparadas para esta unidade.
            </p>
        ) : (
            <div className="mt-4 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead className="bg-gray-100 dark:bg-gray-900/60">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Venda</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Modelo</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Numero</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Mensagem</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Criada em</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cupom</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Regenerar</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">XML</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Transmissao</th>
                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Excluir</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                        {invoices.map((invoice) => (
                            <tr key={invoice.id}>
                                <td className="px-3 py-3 text-gray-800 dark:text-gray-100">#{invoice.payment_id}</td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">{String(invoice.modelo ?? '').toUpperCase()}</td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.serie ? `${invoice.serie}/${invoice.numero ?? '--'}` : '--'}
                                </td>
                                <td className="px-3 py-3">
                                    <span className={badgeClassName(invoice.status)}>
                                        {STATUS_LABEL[invoice.status] ?? invoice.status}
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">{invoice.mensagem ?? '--'}</td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">{invoice.criada_em ?? '--'}</td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.fiscal_receipt ? (
                                        <button
                                            type="button"
                                            onClick={() => onPrintFiscalReceipt(invoice.fiscal_receipt)}
                                            className={`${actionButtonClassName} border-slate-200 bg-slate-50 text-slate-700`}
                                        >
                                            {`Cupom ${formatReceiptCurrency(invoice.total)}`}
                                        </button>
                                    ) : (
                                        '--'
                                    )}
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.pode_regenerar ? (
                                        <Link
                                            href={route('settings.fiscal.invoices.regenerate', { notaFiscal: invoice.id })}
                                            method="post"
                                            as="button"
                                            className={`${actionButtonClassName} border-amber-200 bg-amber-50 text-amber-700`}
                                        >
                                            Regenerar nota
                                        </Link>
                                    ) : (
                                        '--'
                                    )}
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.xml_disponivel ? (
                                        <a
                                            href={route('settings.fiscal.invoices.xml', { notaFiscal: invoice.id })}
                                            className={`${actionButtonClassName} border-blue-200 bg-blue-50 text-blue-700`}
                                        >
                                            Baixar XML
                                        </a>
                                    ) : (
                                        '--'
                                    )}
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.status === 'xml_assinado' ? (
                                        <Link
                                            href={route('settings.fiscal.invoices.transmit', { notaFiscal: invoice.id })}
                                            method="post"
                                            as="button"
                                            className={`${actionButtonClassName} border-emerald-200 bg-emerald-50 text-emerald-700`}
                                        >
                                            Transmitir
                                        </Link>
                                    ) : (
                                        '--'
                                    )}
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    {invoice.pode_excluir ? (
                                        <button
                                            type="button"
                                            onClick={() => onDeleteInvoice(invoice)}
                                            className={`${actionButtonClassName} border-rose-200 bg-rose-50 text-rose-700`}
                                            title={`Excluir nota preparada da venda ${invoice.payment_id}`}
                                            aria-label={`Excluir nota preparada da venda ${invoice.payment_id}`}
                                        >
                                            <TrashIcon />
                                        </button>
                                    ) : (
                                        '--'
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        )}
    </section>
);

export default function FiscalConfig({
    auth,
    units = [],
    selectedUnitId = null,
    unit = null,
    configuration = {},
    resolvedEndpoints = null,
    configurationDiagnostics = null,
    invoices = [],
    fiscalUnavailableMessage = null,
    invoiceLoadWarning = null,
}) {
    const [printError, setPrintError] = useState('');
    const { flash = {} } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        tb2_id: configuration?.tb2_id ?? selectedUnitId ?? '',
        tb26_emitir_nfe: Boolean(configuration?.tb26_emitir_nfe),
        tb26_emitir_nfce: Boolean(configuration?.tb26_emitir_nfce),
        tb26_geracao_automatica_ativa: configuration?.tb26_geracao_automatica_ativa ?? true,
        tb26_ambiente: configuration?.tb26_ambiente ?? 'homologacao',
        tb26_serie: configuration?.tb26_serie ?? '1',
        tb26_proximo_numero: configuration?.tb26_proximo_numero ?? 1,
        tb26_crt: configuration?.tb26_crt ?? '',
        tb26_csc_id: configuration?.tb26_csc_id ?? '',
        tb26_csc: configuration?.tb26_csc ?? '',
        tb26_certificado_tipo: configuration?.tb26_certificado_tipo ?? 'A1',
        tb26_certificado_nome: configuration?.tb26_certificado_nome ?? '',
        tb26_certificado_cnpj: configuration?.tb26_certificado_cnpj ?? '',
        tb26_certificado_arquivo_upload: null,
        tb26_certificado_senha: '',
        remover_certificado: false,
        tb26_razao_social: configuration?.tb26_razao_social ?? '',
        tb26_nome_fantasia: configuration?.tb26_nome_fantasia ?? '',
        tb26_ie: configuration?.tb26_ie ?? '',
        tb26_im: configuration?.tb26_im ?? '',
        tb26_cnae: configuration?.tb26_cnae ?? '',
        tb26_logradouro: configuration?.tb26_logradouro ?? '',
        tb26_numero: configuration?.tb26_numero ?? '',
        tb26_complemento: configuration?.tb26_complemento ?? '',
        tb26_bairro: configuration?.tb26_bairro ?? '',
        tb26_codigo_municipio: configuration?.tb26_codigo_municipio ?? '',
        tb26_municipio: configuration?.tb26_municipio ?? '',
        tb26_uf: configuration?.tb26_uf ?? '',
        tb26_cep: configuration?.tb26_cep ?? '',
        tb26_telefone: configuration?.tb26_telefone ?? '',
        tb26_email: configuration?.tb26_email ?? '',
    });
    const fiscalGenerationEnabled = Boolean(data.tb26_geracao_automatica_ativa);

    const filter = useForm({
        unit_id: selectedUnitId ?? '',
    });
    const reprocess = useForm({
        tb2_id: selectedUnitId ?? '',
    });

    useEffect(() => {
        setData('tb2_id', configuration?.tb2_id ?? selectedUnitId ?? '');
    }, [configuration?.tb2_id, selectedUnitId, setData]);

    useEffect(() => {
        reprocess.setData('tb2_id', selectedUnitId ?? '');
    }, [reprocess.setData, selectedUnitId]);

    const handleFilterSubmit = (event) => {
        event.preventDefault();
        filter.get(route('settings.fiscal'), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('settings.fiscal.update'), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    const handleReprocess = () => {
        reprocess.setData('tb2_id', selectedUnitId ?? '');
        reprocess.post(route('settings.fiscal.reprocess'), {
            preserveScroll: true,
        });
    };

    const handlePrintFiscalReceipt = (receipt) => {
        setPrintError('');

        if (!receipt) {
            setPrintError('Nao foi possivel montar os dados do cupom fiscal desta nota.');
            return;
        }

        const printWindow = window.open('', '_blank', 'width=400,height=600');

        if (!printWindow) {
            setPrintError('Permita pop-ups para imprimir o cupom.');
            return;
        }

        printWindow.document.write(buildFiscalReceiptHtml(receipt));
        printWindow.document.close();
    };

    const handleDeleteInvoice = (invoice) => {
        if (!invoice?.id) {
            return;
        }

        const confirmed = window.confirm(
            `Excluir a nota preparada da venda #${invoice.payment_id}? Esta acao remove apenas notas que ainda nao foram transmitidas.`
        );

        if (!confirmed) {
            return;
        }

        router.delete(route('settings.fiscal.invoices.destroy', { notaFiscal: invoice.id }), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Configuracao Fiscal</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Prepare a emissao de NF-e e NFC-e por unidade.
                    </p>
                </div>
            }
        >
            <Head title="Configuracao Fiscal" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    <form onSubmit={handleFilterSubmit} className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Unidade</label>
                                <select
                                    value={filter.data.unit_id}
                                    onChange={(event) => filter.setData('unit_id', event.target.value)}
                                    className={inputClassName}
                                >
                                    <option value="">Selecione</option>
                                    {units.map((store) => (
                                        <option key={store.id} value={store.id}>{store.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <PrimaryButton
                                    type="submit"
                                    disabled={filter.processing || !filter.data.unit_id}
                                    className="px-5 py-3 text-sm font-semibold normal-case tracking-normal"
                                >
                                    Carregar unidade
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>

                    {!selectedUnitId ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-500 shadow dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            Selecione uma unidade para configurar a emissao fiscal.
                        </div>
                    ) : (
                        <>
                            {fiscalUnavailableMessage && (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 shadow dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                                    {fiscalUnavailableMessage}
                                </div>
                            )}
                            {invoiceLoadWarning && (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 shadow dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                                    {invoiceLoadWarning}
                                </div>
                            )}
                            <UnitCard unit={unit} />
                            <CertificateSummaryCard unit={unit} configuration={configuration} resolvedEndpoints={resolvedEndpoints} />
                            <DiagnosticsCard diagnostics={configurationDiagnostics} />

                            {printError && (
                                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                                    {printError}
                                </div>
                            )}

                            <form
                                onSubmit={handleSubmit}
                                className="space-y-6 rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                            >
                                <InputError message={errors.tb2_id} className="mt-2" />

                                <section className="space-y-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Emissao e numeracao
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            Defina tipo de nota, ambiente e sequencia fiscal da unidade.
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                                        <label className="flex items-center justify-between gap-4">
                                            <div className="space-y-1">
                                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    Geracao automatica de notas fiscais
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                                    Desligue para concluir vendas sem preparar nota fiscal automaticamente nesta loja.
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => setData('tb26_geracao_automatica_ativa', !fiscalGenerationEnabled)}
                                                className={`relative inline-flex h-8 w-14 items-center rounded-full transition ${
                                                    fiscalGenerationEnabled ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'
                                                }`}
                                                aria-pressed={fiscalGenerationEnabled}
                                            >
                                                <span className="sr-only">Alternar geracao automatica de notas fiscais</span>
                                                <span
                                                    className={`inline-block h-6 w-6 transform rounded-full bg-white transition ${
                                                        fiscalGenerationEnabled ? 'translate-x-7' : 'translate-x-1'
                                                    }`}
                                                />
                                            </button>
                                        </label>
                                        <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                                            Status atual: {fiscalGenerationEnabled ? 'Ativa' : 'Desligada'}
                                        </p>
                                        <InputError message={errors.tb26_geracao_automatica_ativa} className="mt-2" />
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <label className="rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                            <span className="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(data.tb26_emitir_nfe)}
                                                    onChange={(event) => setData('tb26_emitir_nfe', event.target.checked)}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                Emitir NF-e
                                            </span>
                                        </label>

                                        <label className="rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                            <span className="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(data.tb26_emitir_nfce)}
                                                    onChange={(event) => setData('tb26_emitir_nfce', event.target.checked)}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                Emitir NFC-e
                                            </span>
                                        </label>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Ambiente</label>
                                            <select
                                                value={data.tb26_ambiente}
                                                onChange={(event) => setData('tb26_ambiente', event.target.value)}
                                                className={inputClassName}
                                            >
                                                <option value="homologacao">Homologacao</option>
                                                <option value="producao">Producao</option>
                                            </select>
                                            <InputError message={errors.tb26_ambiente} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Serie</label>
                                            <input
                                                type="text"
                                                value={data.tb26_serie}
                                                onChange={(event) => setData('tb26_serie', event.target.value)}
                                                className={inputClassName}
                                            />
                                            <InputError message={errors.tb26_serie} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Proximo numero</label>
                                            <input
                                                type="number"
                                                min="1"
                                                value={data.tb26_proximo_numero}
                                                onChange={(event) => setData('tb26_proximo_numero', event.target.value)}
                                                className={inputClassName}
                                            />
                                            <InputError message={errors.tb26_proximo_numero} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">CRT</label>
                                            <select
                                                value={data.tb26_crt}
                                                onChange={(event) => setData('tb26_crt', event.target.value)}
                                                className={inputClassName}
                                            >
                                                <option value="">Selecione</option>
                                                <option value="1">1 - Simples Nacional</option>
                                                <option value="2">2 - Simples excesso sublimite</option>
                                                <option value="3">3 - Regime normal</option>
                                            </select>
                                            <InputError message={errors.tb26_crt} className="mt-2" />
                                        </div>
                                    </div>
                                </section>

                                <section className="space-y-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Credenciais fiscais
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            Configure CSC para NFC-e e o certificado digital da unidade.
                                        </p>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">CSC ID</label>
                                            <input
                                                type="text"
                                                value={data.tb26_csc_id}
                                                onChange={(event) => setData('tb26_csc_id', event.target.value)}
                                                className={inputClassName}
                                            />
                                            <InputError message={errors.tb26_csc_id} className="mt-2" />
                                        </div>

                                        <div className="md:col-span-2">
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">CSC</label>
                                            <input
                                                type="text"
                                                value={data.tb26_csc}
                                                onChange={(event) => setData('tb26_csc', event.target.value)}
                                                className={inputClassName}
                                            />
                                            <InputError message={errors.tb26_csc} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Tipo de certificado</label>
                                            <select
                                                value={data.tb26_certificado_tipo}
                                                onChange={(event) => setData('tb26_certificado_tipo', event.target.value)}
                                                className={inputClassName}
                                            >
                                                <option value="A1">A1</option>
                                                <option value="A3">A3</option>
                                            </select>
                                            <InputError message={errors.tb26_certificado_tipo} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Nome do certificado</label>
                                            <input
                                                type="text"
                                                value={data.tb26_certificado_nome}
                                                onChange={(event) => setData('tb26_certificado_nome', event.target.value)}
                                                className={inputClassName}
                                            />
                                            <InputError message={errors.tb26_certificado_nome} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">CNPJ do certificado</label>
                                            <input
                                                type="text"
                                                value={data.tb26_certificado_cnpj}
                                                onChange={(event) => setData('tb26_certificado_cnpj', event.target.value)}
                                                className={inputClassName}
                                                placeholder="Somente o certificado desta loja"
                                            />
                                            <InputError message={errors.tb26_certificado_cnpj} className="mt-2" />
                                        </div>

                                        <div className="md:col-span-2">
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Arquivo do certificado</label>
                                            <input
                                                type="file"
                                                accept=".pfx,.p12"
                                                onChange={(event) => setData('tb26_certificado_arquivo_upload', event.target.files?.[0] ?? null)}
                                                className={`${inputClassName} file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 dark:file:bg-blue-500/10 dark:file:text-blue-200`}
                                            />
                                            <InputError message={errors.tb26_certificado_arquivo_upload} className="mt-2" />
                                        </div>

                                        <div>
                                            <label className="text-sm font-medium text-gray-700 dark:text-gray-200">Senha do certificado</label>
                                            <input
                                                type="password"
                                                value={data.tb26_certificado_senha}
                                                onChange={(event) => setData('tb26_certificado_senha', event.target.value)}
                                                className={inputClassName}
                                                placeholder={configuration?.has_certificate_password ? 'Ja cadastrada' : ''}
                                            />
                                            <InputError message={errors.tb26_certificado_senha} className="mt-2" />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                        <div className="flex flex-col gap-2 text-sm text-gray-700 dark:text-gray-200">
                                            <span>Arquivo atual: {configuration?.tb26_certificado_arquivo || 'Nenhum certificado enviado'}</span>
                                            <label className="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(data.remover_certificado)}
                                                    onChange={(event) => setData('remover_certificado', event.target.checked)}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                Remover certificado atual
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <section className="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-100">
                                    <p className="font-semibold">Reprocessamento fiscal</p>
                                    <p className="mt-1">
                                        Use este botao depois de corrigir certificado, CSC ou cadastro fiscal dos produtos para refazer as notas pendentes desta loja.
                                    </p>
                                    <div className="mt-4">
                                        <PrimaryButton
                                            type="button"
                                            onClick={handleReprocess}
                                            disabled={reprocess.processing}
                                            className="px-5 py-3 text-sm font-semibold normal-case tracking-normal"
                                        >
                                            Reprocessar notas pendentes
                                        </PrimaryButton>
                                    </div>
                                </section>

                                <section className="space-y-4">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Dados do emitente
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            Esses dados alimentam o XML fiscal da unidade emitente.
                                        </p>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        {EMITTER_FIELDS.map(([field, label, extraClass]) => (
                                            <div key={field} className={extraClass ?? ''}>
                                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">{label}</label>
                                                <input
                                                    type="text"
                                                    value={data[field]}
                                                    onChange={(event) => setData(field, event.target.value)}
                                                    className={inputClassName}
                                                />
                                                <InputError message={errors[field]} className="mt-2" />
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                <div className="flex justify-end">
                                    <PrimaryButton
                                        type="submit"
                                        disabled={processing}
                                        className="px-6 py-3 text-sm font-semibold normal-case tracking-normal"
                                    >
                                        Salvar configuracao fiscal
                                    </PrimaryButton>
                                </div>
                            </form>

                            <InvoiceTable
                                invoices={invoices}
                                onDeleteInvoice={handleDeleteInvoice}
                                onPrintFiscalReceipt={handlePrintFiscalReceipt}
                            />
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
