import AlertMessage from '@/Components/Alert/AlertMessage';
import PrimaryButton from '@/Components/Button/PrimaryButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { printContraCheque, printContraChequePdf } from '@/Utils/contraChequePrint';
import {
    formatBrazilShortDate,
    isoToBrazilShortDateInput,
    normalizeBrazilShortDateInput,
    shortBrazilDateInputToIso,
} from '@/Utils/date';
import {
    formatRoleBadgeLabel,
    formatUnitBadgeLabel,
    getRoleBadgeClassName,
    getRoleBadgeStyle,
    getUnitBadgeClassName,
    getUnitBadgeStyle,
} from '@/Utils/brandBadges';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const EXTRA_CREDIT_TYPE_OPTIONS = [
    { value: 'primeiro_domingo', label: 'Primeiro Domingo' },
    { value: 'feriado', label: 'Feriado' },
    { value: 'bonificacao', label: 'Bonificacao' },
    { value: 'inss', label: 'INSS' },
    { value: 'outros', label: 'Outros' },
];

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const normalizeWhatsappPhone = (value) => {
    const digits = String(value ?? '').replace(/\D/g, '');

    if (digits.length === 10 || digits.length === 11) {
        return `55${digits}`;
    }

    if (digits.length === 12 || digits.length === 13) {
        return digits;
    }

    return '';
};

const buildWhatsappSummaryMessage = (detail) => {
    const extraCredits = detail?.extra_credits_total ?? 0;
    const extraDiscounts = detail?.extra_discounts_total ?? 0;
    const extraCreditLines = (detail?.extra_credits ?? [])
        .map((credit) => `- ${credit.description || credit.type_label}: ${formatCurrency(credit.amount)}`)
        .join('\n');
    const extraDiscountLines = (detail?.extra_discounts ?? [])
        .map((discount) => `- ${discount.description || discount.type_label}: ${formatCurrency(discount.amount)}`)
        .join('\n');

    return [
        'Resumo do contra-cheque',
        `Funcionario: ${detail?.user_name ?? '---'}`,
        `Periodo: ${formatBrazilShortDate(detail?.start_date)} a ${formatBrazilShortDate(detail?.end_date)}`,
        `Salario base: ${formatCurrency(detail?.salary)}`,
        `Creditos extras: ${formatCurrency(extraCredits)}`,
        extraCreditLines ? `Lancamentos creditados:\n${extraCreditLines}` : null,
        `Adiantamentos: ${formatCurrency(detail?.advances_total)}`,
        `Vales: ${formatCurrency(detail?.vales_total)}`,
        `Descontos extras: ${formatCurrency(extraDiscounts)}`,
        extraDiscountLines ? `Descontos adicionais:\n${extraDiscountLines}` : null,
        `Liquido a receber: ${formatCurrency(detail?.balance)}`,
    ]
        .filter(Boolean)
        .join('\n');
};

export default function ContraCheque({
    rows = [],
    summary = {},
    startDate,
    endDate,
    filterUnits = [],
    filterUsers = [],
    roleOptions = [],
    selectedUnitId = null,
    selectedRole = null,
    selectedUserId = null,
    unit = null,
}) {
    const { flash } = usePage().props;
    const { data, setData, get, processing } = useForm({
        start_date: isoToBrazilShortDateInput(startDate ?? ''),
        end_date: isoToBrazilShortDateInput(endDate ?? ''),
        unit_id:
            selectedUnitId !== null && selectedUnitId !== undefined
                ? String(selectedUnitId)
                : 'all',
        role:
            selectedRole !== null && selectedRole !== undefined
                ? String(selectedRole)
                : 'all',
        user_id:
            selectedUserId !== null && selectedUserId !== undefined
                ? String(selectedUserId)
                : 'all',
    });
    const [selectedCreditRow, setSelectedCreditRow] = useState(null);
    const [printError, setPrintError] = useState('');
    const creditForm = useForm({
        start_date: startDate ?? '',
        end_date: endDate ?? '',
        filter_start_date: startDate ?? '',
        filter_end_date: endDate ?? '',
        unit_id:
            selectedUnitId !== null && selectedUnitId !== undefined
                ? String(selectedUnitId)
                : 'all',
        role:
            selectedRole !== null && selectedRole !== undefined
                ? String(selectedRole)
                : 'all',
        user_id:
            selectedUserId !== null && selectedUserId !== undefined
                ? String(selectedUserId)
                : 'all',
        credit_type: 'primeiro_domingo',
        other_description: '',
        amount: '',
    });

    const summaryCards = useMemo(
        () => [
            {
                key: 'employees',
                label: 'Colaboradores',
                value: Number(summary.employees_count ?? 0).toLocaleString('pt-BR'),
            },
            {
                key: 'salary',
                label: 'Salarios',
                value: formatCurrency(summary.salary_total),
            },
            {
                key: 'advances',
                label: 'Adiantamento',
                value: formatCurrency(summary.advances_total),
            },
            {
                key: 'vales',
                label: 'Vale',
                value: formatCurrency(summary.vales_total),
            },
            {
                key: 'credits',
                label: 'Creditos extras',
                value: formatCurrency(summary.extra_credits_total),
            },
            {
                key: 'discounts',
                label: 'Descontos extras',
                value: formatCurrency(summary.extra_discounts_total),
            },
            {
                key: 'balance',
                label: 'Saldo a receber',
                value: formatCurrency(summary.balance_total),
            },
        ],
        [summary],
    );

    const handlePrint = (detail) => {
        setPrintError(
            printContraCheque(detail, 'Permita pop-ups para imprimir o contra-cheque.', {
                showDetails: true,
            }),
        );
    };

    const handlePrintPdf = (detail) => {
        setPrintError(
            printContraChequePdf(detail, 'Permita pop-ups para gerar o PDF do contra-cheque.'),
        );
    };

    const handleOpenWhatsapp = (detail) => {
        const phone = normalizeWhatsappPhone(detail?.phone);

        if (!phone) {
            setPrintError('Cadastre um telefone valido para enviar o resumo do contra-cheque por WhatsApp.');
            return;
        }

        const message = buildWhatsappSummaryMessage(detail);
        window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        get(route('settings.contra-cheque'), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            data: {
                start_date: shortBrazilDateInputToIso(data.start_date) || undefined,
                end_date: shortBrazilDateInputToIso(data.end_date) || undefined,
                unit_id: data.unit_id,
                role: data.role,
                user_id: data.user_id,
            },
        });
    };

    const openCreditModal = (row) => {
        setSelectedCreditRow(row);
        creditForm.clearErrors();
        creditForm.setData({
            start_date: row?.detail?.start_date ?? startDate ?? '',
            end_date: row?.detail?.end_date ?? endDate ?? '',
            filter_start_date: startDate ?? '',
            filter_end_date: endDate ?? '',
            unit_id:
                selectedUnitId !== null && selectedUnitId !== undefined
                    ? String(selectedUnitId)
                    : 'all',
            role:
                selectedRole !== null && selectedRole !== undefined
                    ? String(selectedRole)
                    : 'all',
            user_id:
                selectedUserId !== null && selectedUserId !== undefined
                    ? String(selectedUserId)
                    : 'all',
            credit_type: 'primeiro_domingo',
            other_description: '',
            amount: '',
        });
    };

    const closeCreditModal = () => {
        setSelectedCreditRow(null);
        creditForm.clearErrors();
    };

    const handleCreditSubmit = (event) => {
        event.preventDefault();

        if (!selectedCreditRow?.id) {
            return;
        }

        creditForm.post(route('settings.contra-cheque.creditos.store', selectedCreditRow.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeCreditModal();
                creditForm.reset('credit_type', 'other_description', 'amount');
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">
                            Contra-Cheque
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Resumo por ciclo de pagamento. A janela abaixo define onde cai a data de pagamento de cada colaborador. Unidade atual: {unit?.name ?? '---'}.
                        </p>
                    </div>
                    <Link
                        href={route('settings.payroll')}
                        className="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
                    >
                        Ir para Folha de Pagamento
                    </Link>
                </div>
            }
        >
            <Head title="Contra-Cheque" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    {printError && (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                            {printError}
                        </div>
                    )}

                    <form
                        onSubmit={handleSubmit}
                        className="rounded-2xl bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Pagamento de
                                </label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={data.start_date}
                                    onChange={(event) => setData('start_date', normalizeBrazilShortDateInput(event.target.value))}
                                    placeholder="DD/MM/AA"
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Pagamento ate
                                </label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={data.end_date}
                                    onChange={(event) => setData('end_date', normalizeBrazilShortDateInput(event.target.value))}
                                    placeholder="DD/MM/AA"
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Unidade
                                </label>
                                <select
                                    value={data.unit_id}
                                    onChange={(event) => setData('unit_id', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <option value="all">Todas</option>
                                    {filterUnits.map((filterUnit) => (
                                        <option key={filterUnit.id} value={filterUnit.id}>
                                            {filterUnit.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Funcao
                                </label>
                                <select
                                    value={data.role}
                                    onChange={(event) => setData('role', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <option value="all">Todas</option>
                                    {roleOptions.map((roleOption) => (
                                        <option key={roleOption.id} value={roleOption.id}>
                                            {roleOption.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Usuario
                                </label>
                                <select
                                    value={data.user_id}
                                    onChange={(event) => setData('user_id', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <option value="all">Todos</option>
                                    {filterUsers.map((filterUser) => (
                                        <option key={filterUser.id} value={filterUser.id}>
                                            {filterUser.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <PrimaryButton
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 normal-case tracking-normal"
                            >
                                Filtrar
                            </PrimaryButton>
                        </div>
                    </form>

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-col gap-3 border-b border-gray-100 pb-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Janela de pagamento
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                    {formatBrazilShortDate(startDate)} a {formatBrazilShortDate(endDate)}
                                </p>
                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                    Os vales, adiantamentos e lancamentos sao calculados pelo periodo individual de cada colaborador.
                                </p>
                            </div>
                            <span className="text-sm font-semibold text-gray-500 dark:text-gray-300">
                                {rows.length} colaborador(es)
                            </span>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                            {summaryCards.map((card) => (
                                <div
                                    key={card.key}
                                    className="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-[0.25em] text-gray-400">
                                        {card.label}
                                    </p>
                                    <p className="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                        {card.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>

                    {rows.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-500 shadow dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            Nenhum colaborador com salario informado foi encontrado.
                        </div>
                    ) : (
                        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            {rows.map((row) => (
                                <article
                                    key={row.id}
                                    className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <div className="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-700">
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {row.name}
                                            </h3>
                                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-300">
                                                Periodo calculado: {row.period_label}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {row.payment_day_label
                                                    ? `Pagamento previsto no dia ${row.payment_day_label} de cada mes.`
                                                    : 'Sem data de pagamento cadastrada. Periodo definido pelos filtros.'}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <span
                                                className={getRoleBadgeClassName()}
                                                style={getRoleBadgeStyle(row.role_label)}
                                            >
                                                {formatRoleBadgeLabel(row.role_label)}
                                            </span>
                                            {(row.unit_names ?? []).map((unitName) => (
                                                <span
                                                    key={`${row.id}-${unitName}`}
                                                    className={getUnitBadgeClassName()}
                                                    style={getUnitBadgeStyle(unitName)}
                                                >
                                                    {formatUnitBadgeLabel(unitName)}
                                                </span>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Salario
                                            </p>
                                            <p className="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(row.salary)}
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Vale em compras
                                            </p>
                                            <p className="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(row.vales_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Adiantamento
                                            </p>
                                            <p className="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(row.advances_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Creditos extras
                                            </p>
                                            <p className="mt-2 text-base font-semibold text-blue-600 dark:text-blue-300">
                                                {formatCurrency(row.extra_credits_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Descontos extras
                                            </p>
                                            <p className="mt-2 text-base font-semibold text-red-600 dark:text-red-300">
                                                {formatCurrency(row.extra_discounts_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:col-span-2 dark:border-gray-700 dark:bg-gray-900">
                                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                                Saldo
                                            </p>
                                            <p className={`mt-2 text-base font-semibold ${row.balance < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300'}`}>
                                                {formatCurrency(row.balance)}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-5 flex gap-3">
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => handlePrint(row.detail)}
                                            className="flex-1 justify-center rounded-2xl px-4 py-3 text-sm font-semibold normal-case tracking-normal"
                                        >
                                            Imprimir 80mm
                                        </PrimaryButton>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => handlePrintPdf(row.detail)}
                                            className="flex-1 justify-center rounded-2xl px-4 py-3 text-sm font-semibold normal-case tracking-normal"
                                        >
                                            PDF
                                        </PrimaryButton>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => handleOpenWhatsapp(row.detail)}
                                            disabled={!normalizeWhatsappPhone(row.phone)}
                                            className="justify-center rounded-2xl px-4 py-3 text-sm font-semibold normal-case tracking-normal"
                                            aria-label={`Enviar resumo do contra-cheque de ${row.name} por WhatsApp`}
                                            title={normalizeWhatsappPhone(row.phone) ? 'Enviar resumo por WhatsApp' : 'Telefone nao cadastrado'}
                                        >
                                            WhatsApp
                                        </PrimaryButton>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => openCreditModal(row)}
                                            className="justify-center rounded-2xl px-4 py-3 text-lg font-semibold normal-case tracking-normal"
                                            aria-label={`Adicionar lancamento ao contra-cheque de ${row.name}`}
                                            title={`Adicionar lancamento ao contra-cheque de ${row.name}`}
                                        >
                                            +
                                        </PrimaryButton>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Modal show={Boolean(selectedCreditRow)} onClose={closeCreditModal} maxWidth="lg" tone="light">
                <form onSubmit={handleCreditSubmit}>
                    <div className="border-b border-gray-200 px-6 py-5">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Adicionar lancamento ao contra-cheque
                        </h3>
                        <p className="mt-1 text-sm text-gray-600">
                            {selectedCreditRow?.name ?? '---'} • Periodo {formatBrazilShortDate(creditForm.data.start_date)} a {formatBrazilShortDate(creditForm.data.end_date)}
                        </p>
                    </div>

                    <div className="space-y-5 px-6 py-5">
                        <div>
                            <label className="text-sm font-medium text-gray-700">
                                Tipo
                            </label>
                            <select
                                value={creditForm.data.credit_type}
                                onChange={(event) => creditForm.setData('credit_type', event.target.value)}
                                className="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            >
                                {EXTRA_CREDIT_TYPE_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={creditForm.errors.credit_type} className="mt-2" />
                        </div>

                        {creditForm.data.credit_type === 'outros' && (
                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Informe o tipo
                                </label>
                                <input
                                    type="text"
                                    value={creditForm.data.other_description}
                                    onChange={(event) => creditForm.setData('other_description', event.target.value)}
                                    className="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Descreva o lancamento"
                                />
                                <InputError message={creditForm.errors.other_description} className="mt-2" />
                            </div>
                        )}

                        <div>
                            <label className="text-sm font-medium text-gray-700">
                                Valor do lancamento
                            </label>
                            <input
                                type="text"
                                inputMode="decimal"
                                value={creditForm.data.amount}
                                onChange={(event) => {
                                    const normalizedValue = event.target.value
                                        .replace(/[^0-9,.-]/g, '')
                                        .replace(',', '.');

                                    creditForm.setData('amount', normalizedValue);
                                }}
                                className="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="0,00"
                            />
                            <InputError message={creditForm.errors.amount} className="mt-2" />
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                        <SecondaryButton type="button" onClick={closeCreditModal} className="rounded-xl normal-case tracking-normal">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={creditForm.processing} className="rounded-xl px-4 py-2 normal-case tracking-normal">
                            Salvar lancamento
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
