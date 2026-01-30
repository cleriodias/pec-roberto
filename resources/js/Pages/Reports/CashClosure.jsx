import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const paymentColumns = [
    { key: 'dinheiro', label: 'Dinheiro' },
    { key: 'maquina', label: 'Maquina' },
    { key: 'vale', label: 'Vale' },
    { key: 'refeicao', label: 'Refei\u00e7\u00e3o' },
    { key: 'faturar', label: 'Faturar' },
];

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const formatQuantity = (value, decimals = 2) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

const formatDateTime = (value) => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const differenceTone = (value) => {
    if (Math.abs(value ?? 0) < 0.005) {
        return 'text-gray-900 dark:text-gray-100';
    }
    return (value ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400';
};

export default function CashClosure({
    records = [],
    dateValue = '',
    dateInputValue = '',
    filterUnits = [],
    selectedUnitId = null,
    selectedUnit = { name: 'Todas as unidades' },
    discardDetails = [],
}) {
    const normalizedSelectedUnit = selectedUnitId ?? null;
    const [dateInput, setDateInput] = useState(dateInputValue ?? '');
    const [unitFilter, setUnitFilter] = useState(normalizedSelectedUnit);
    const [discardModal, setDiscardModal] = useState({
        open: false,
        cashierName: '',
        items: [],
    });

    useEffect(() => {
        setDateInput(dateInputValue ?? '');
    }, [dateInputValue]);

    useEffect(() => {
        setUnitFilter(normalizedSelectedUnit);
    }, [normalizedSelectedUnit]);

    const discardMap = useMemo(() => {
        const grouped = {};
        discardDetails.forEach((entry) => {
            const key = entry.user_id;
            if (!grouped[key]) {
                grouped[key] = [];
            }

            grouped[key].push(entry);
        });

        return grouped;
    }, [discardDetails]);

    const totals = useMemo(() => {
        return paymentColumns.reduce(
            (acc, column) => ({
                ...acc,
                [column.key]: records.reduce(
                    (sum, record) => sum + (record.totals?.[column.key] ?? 0),
                    0,
                ),
            }),
            {},
        );
    }, [records]);

    const grandTotal = useMemo(
        () => records.reduce((sum, record) => sum + (record.grand_total ?? 0), 0),
        [records],
    );

    const unitOptions = useMemo(() => {
        const base = [{ id: null, name: 'Todas as unidades' }];
        if (!Array.isArray(filterUnits) || filterUnits.length === 0) {
            return base;
        }

        const mapped = filterUnits.map((unit) => ({
            id: unit.id ?? unit.tb2_id ?? null,
            name: unit.name ?? unit.tb2_nome ?? '---',
        }));

        return base.concat(mapped);
    }, [filterUnits]);

    const applyFilters = (dateValueParam, unitValueParam) => {
        const params = {};

        if (dateValueParam) {
            params.date = dateValueParam;
        }

        if (unitValueParam !== null && unitValueParam !== undefined) {
            params.unit_id = unitValueParam;
        }

        router.get(route('reports.cash.closure'), params, { preserveScroll: true });
    };

    const handleDateChange = (value) => {
        setDateInput(value);
        applyFilters(value, unitFilter);
    };

    const handleUnitFilter = (optionId) => {
        const normalized = optionId ?? null;
        setUnitFilter(normalized);
        applyFilters(dateInput, normalized);
    };

    const openDiscardModal = (record) => {
        const items = discardMap[record.cashier_id] ?? [];
        setDiscardModal({
            open: true,
            cashierName: record.cashier_name,
            items,
        });
    };

    const closeDiscardModal = () => {
        setDiscardModal({
            open: false,
            cashierName: '',
            items: [],
        });
    };

    const discardModalTotals = useMemo(() => {
        const quantity = discardModal.items.reduce((sum, item) => sum + (item.quantity ?? 0), 0);
        const value = discardModal.items.reduce((sum, item) => sum + (item.value ?? 0), 0);

        return {
            quantity,
            value,
        };
    }, [discardModal.items]);

    const renderPaymentCell = (record, column) => {
        const systemValue = record.totals?.[column.key] ?? 0;

        if (!['dinheiro', 'maquina'].includes(column.key)) {
            return (
                <p className="text-right font-semibold text-gray-900 dark:text-white">
                    {formatCurrency(systemValue)}
                </p>
            );
        }

        const closure = record.closure ?? null;
        const closureValue =
            column.key === 'dinheiro'
                ? closure?.cash_amount ?? 0
                : closure?.card_amount ?? 0;
        const diffValue =
            column.key === 'dinheiro'
                ? closure?.differences?.cash ?? systemValue
                : closure?.differences?.card ?? systemValue;
        const diffClass = differenceTone(diffValue);

        return (
            <div className="space-y-1 text-right">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Sistema</p>
                    <p className="font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(systemValue)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Fechamento</p>
                    <p className="text-sm text-gray-700 dark:text-gray-200">
                        {formatCurrency(closureValue)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Diferença</p>
                    <p className={`text-sm font-semibold ${diffClass}`}>
                        {formatCurrency(diffValue)}
                    </p>
                </div>
            </div>
        );
    };

    const renderConferenceCell = (record) => {
        const closure = record.closure ?? null;
        const cashSystem = record.totals?.dinheiro ?? 0;
        const cardSystem = record.totals?.maquina ?? 0;
        const systemTotal = cashSystem + cardSystem;
        const closureTotal = closure?.total_amount ?? 0;
        const diffTotal = closure?.differences?.total ?? systemTotal;
        const diffClass = differenceTone(diffTotal);

        return (
            <div className="space-y-1 text-right">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Sistema</p>
                    <p className="font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(systemTotal)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Fechamento</p>
                    <p className="text-sm text-gray-700 dark:text-gray-200">
                        {formatCurrency(closureTotal)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Diferença</p>
                    <p className={`text-sm font-semibold ${diffClass}`}>
                        {formatCurrency(diffTotal)}
                    </p>
                </div>
            </div>
        );
    };

    const filterCard = (
        <div className="rounded-2xl bg-white p-6 shadow">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="">
                    <div className="mt-0">
                        <input
                            id="closure-date"
                            type="date"
                            value={dateInput}
                            onChange={(event) => handleDateChange(event.target.value)}
                            className="mt-2 w-50 rounded-xl border border-gray-300 px-3 py-3 text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        />
                    </div>
                </div>
                <div className="flex-1 lg:pl-8">
                    <div className="mt-3 flex flex-wrap gap-2">
                        {unitOptions.map((unit) => {
                            const optionId = unit.id ?? null;
                            const isActive = optionId === (unitFilter ?? null);

                            return (
                                <button
                                    type="button"
                                    key={`unit-filter-${optionId ?? 'all'}`}
                                    onClick={() => handleUnitFilter(optionId)}
                                    className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                                        isActive
                                            ? 'bg-indigo-600 text-white shadow'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    }`}
                                >
                                    {unit.name}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );

    const summaryCard = (
        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Resumo geral
            </h3>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                {paymentColumns.map((column) => (
                    <div
                        key={column.key}
                        className="rounded-xl border border-gray-100 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <p className="text-sm text-gray-600 dark:text-gray-300">{column.label}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white">
                            {formatCurrency(totals[column.key] ?? 0)}
                        </p>
                    </div>
                ))}
                <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-center dark:border-indigo-500/40 dark:bg-indigo-900/30">
                    <p className="text-sm text-indigo-800 dark:text-indigo-200">Total geral</p>
                    <p className="text-2xl font-bold text-indigo-900 dark:text-white">
                        {formatCurrency(grandTotal)}
                    </p>
                </div>
            </div>
        </div>
    );

    const tableSection = (
        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Totais por caixa ({selectedUnit?.name ?? 'Todas as unidades'}) - Data do fechamento: {dateValue || '---'}
            </h3>
            <div className="mt-4 overflow-x-auto">
                {records.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                        Nenhum lan\u00e7amento encontrado para a data selecionada.
                    </p>
                ) : (
                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead className="bg-gray-50 text-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">Caixa</th>
                                <th className="px-3 py-2 text-left font-medium">Unidade</th>
                                <th className="px-3 py-2 text-left font-medium">Fechamento</th>
                                {paymentColumns.map((column) => (
                                    <th
                                        key={`header-${column.key}`}
                                        className="px-3 py-2 text-right font-medium"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                <th className="px-3 py-2 text-right font-medium">Total</th>
                                <th className="px-3 py-2 text-right font-medium">Conferência</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {records.map((record) => {
                                const closure = record.closure ?? null;
                                const closed = closure?.closed;
                                const statusClass = closed
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
                                const rowKey =
                                    record.row_key ??
                                    `${record.cashier_id}-${record.unit_id ?? 'all'}`;

                                return (
                                    <tr key={rowKey}>
                                        <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                            {record.cashier_name}
                                        </td>
                                        <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                            {record.unit_name ?? '---'}
                                        </td>
                                        <td className="px-3 py-2 text-gray-700 dark:text-gray-200">
                                            <button
                                                type="button"
                                                onClick={() => openDiscardModal(record)}
                                                className={`mb-2 w-full rounded-full px-3 py-1 text-xs font-semibold transition ${
                                                    record.discard_total > 0
                                                        ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-200'
                                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300'
                                                }`}
                                            >
                                                <span className="flex items-center justify-between">
                                                    <span>Descarte</span>
                                                    <span>{formatCurrency(record.discard_total ?? 0)}</span>
                                                </span>
                                                <span className="mt-0.5 block text-[10px] font-normal text-gray-500 dark:text-gray-400">
                                                    Qtd: {formatQuantity(record.discard_quantity ?? 0, 3)}
                                                </span>
                                            </button>
                                            <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusClass}`}>
                                                {closed ? 'Fechado' : 'Pendente'}
                                            </span>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {closed ? formatDateTime(closure?.closed_at) : 'Sem registro'}
                                            </p>
                                        </td>
                                        {paymentColumns.map((column) => (
                                            <td
                                                key={`${record.cashier_id}-${column.key}`}
                                                className={`px-3 py-2 text-right text-gray-700 dark:text-gray-200 ${
                                                    ['dinheiro', 'maquina'].includes(column.key)
                                                        ? 'align-top'
                                                        : ''
                                                }`}
                                            >
                                                {renderPaymentCell(record, column)}
                                            </td>
                                        ))}
                                        <td className="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                            {formatCurrency(record.grand_total ?? 0)}
                                        </td>
                                        <td className="px-3 py-2 text-right text-gray-700 dark:text-gray-200 align-top">
                                            {renderConferenceCell(record)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );

    const discrepancyParams = {};
    if (dateInput) {
        discrepancyParams.date = dateInput;
    }
    if (unitFilter !== null && unitFilter !== undefined) {
        discrepancyParams.unit_id = unitFilter;
    }

    const headerContent = (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Fechamento de CAIXA
                </h2>
                <p className="text-sm text-gray-500 dark:text-gray-300">
                    Totais de venda por atendente organizados por forma de pagamento.
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Link
                    href={route('reports.cash.discrepancies', discrepancyParams)}
                    className="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
                    aria-label="Atalho para fechamentos com discrepancia"
                >
                    <i className="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <span>Fechamentos com discrepancia</span>
                </Link>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Fechamento de CAIXA" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {filterCard}
                    {summaryCard}
                    {tableSection}
                </div>
            </div>
            <Modal show={discardModal.open} onClose={closeDiscardModal} maxWidth="xl" tone="light">
                <div className="bg-white p-6 text-gray-800">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">
                                Descartes do dia {discardModal.cashierName ? `- ${discardModal.cashierName}` : ''}
                            </h3>
                            <p className="text-sm text-gray-500">
                                Totais aproximados com base no preco atual do produto.
                            </p>
                        </div>
                        <div className="text-right text-sm">
                            <p className="font-semibold text-gray-700">
                                Valor: {formatCurrency(discardModalTotals.value)}
                            </p>
                            <p className="text-gray-500">
                                Quantidade: {formatQuantity(discardModalTotals.quantity, 3)}
                            </p>
                        </div>
                    </div>

                    {!discardModal.items.length ? (
                        <p className="mt-6 text-sm text-gray-500">Nenhum descarte registrado para este caixa.</p>
                    ) : (
                        <div className="mt-6 max-h-96 overflow-y-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">Produto</th>
                                        <th className="px-3 py-2 text-left font-medium">Quantidade</th>
                                        <th className="px-3 py-2 text-left font-medium">Valor estimado</th>
                                        <th className="px-3 py-2 text-left font-medium">Data/Hora</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {discardModal.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 text-gray-800">
                                                {item.product?.name ?? 'Produto removido'}
                                            </td>
                                            <td className="px-3 py-2 text-gray-700">
                                                {formatQuantity(item.quantity, 3)}
                                            </td>
                                            <td className="px-3 py-2 text-gray-700">
                                                {formatCurrency(item.value ?? 0)}
                                            </td>
                                            <td className="px-3 py-2 text-gray-600">
                                                {formatDateTime(item.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </Modal>

        </AuthenticatedLayout>
    );
}
