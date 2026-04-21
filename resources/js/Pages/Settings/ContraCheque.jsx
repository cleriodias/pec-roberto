import PrimaryButton from '@/Components/Button/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { printContraCheque } from '@/Utils/contraChequePrint';
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
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

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
    const [printError, setPrintError] = useState('');

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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">
                            Contra-Cheque
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Resumo do mes atual para colaboradores com salario informado. Unidade atual: {unit?.name ?? '---'}.
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
                                    Inicio
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
                                    Fim
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
                                    Periodo considerado
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                    {formatBrazilShortDate(startDate)} a {formatBrazilShortDate(endDate)}
                                </p>
                            </div>
                            <span className="text-sm font-semibold text-gray-500 dark:text-gray-300">
                                {rows.length} colaborador(es)
                            </span>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
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
                                                Saldo
                                            </p>
                                            <p className={`mt-2 text-base font-semibold ${row.balance < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300'}`}>
                                                {formatCurrency(row.balance)}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-5">
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => handlePrint(row.detail)}
                                            className="w-full justify-center rounded-2xl px-4 py-3 text-sm font-semibold normal-case tracking-normal"
                                        >
                                            Imprimir 80mm
                                        </PrimaryButton>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
