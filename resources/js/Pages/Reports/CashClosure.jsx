import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatBrazilDateTime } from '@/Utils/date';
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

    return formatBrazilDateTime(value);
};

const differenceTone = (value) => {
    if (Math.abs(value ?? 0) < 0.005) {
        return 'text-gray-900 dark:text-gray-100';
    }
    return (value ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400';
};

const hexToRgb = (value) => {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.replace('#', '').trim();

    if (normalized.length !== 6) {
        return null;
    }

    const intValue = Number.parseInt(normalized, 16);

    if (Number.isNaN(intValue)) {
        return null;
    }

    return {
        r: (intValue >> 16) & 255,
        g: (intValue >> 8) & 255,
        b: intValue & 255,
    };
};

const getContrastingTextColor = (backgroundColor) => {
    const rgb = hexToRgb(backgroundColor);

    if (!rgb) {
        return '#ffffff';
    }

    const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;

    return brightness > 160 ? '#111827' : '#ffffff';
};

export default function CashClosure({
    records = [],
    dateValue = '',
    dateInputValue = '',
    filterUnits = [],
    selectedUnitId = null,
    selectedUnit = { name: 'Todas as unidades' },
    discardDetails = [],
    meta = {},
}) {
    const normalizedSelectedUnit = selectedUnitId ?? null;
    const [dateInput, setDateInput] = useState(dateInputValue ?? '');
    const [unitFilter, setUnitFilter] = useState(normalizedSelectedUnit);
    const [discardModal, setDiscardModal] = useState({
        open: false,
        cashierName: '',
        items: [],
        rowKey: null,
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
            const key = `${entry.user_id}-${entry.unit_id ?? 'none'}`;
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

    const salesGrandTotal = useMemo(
        () => records.reduce((sum, record) => sum + (record.grand_total ?? 0), 0),
        [records],
    );

    const conferenceGrandTotal = useMemo(
        () =>
            records.reduce(
                (sum, record) =>
                    sum + (record.totals?.dinheiro ?? 0) + (record.totals?.maquina ?? 0),
                0,
            ),
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
        const rowKey = record.row_key ?? `${record.cashier_id}-${record.unit_id ?? 'none'}`;
        const items = discardMap[rowKey] ?? [];
        setDiscardModal({
            open: true,
            cashierName: record.cashier_name,
            items,
            rowKey,
        });
    };

    const closeDiscardModal = () => {
        setDiscardModal({
            open: false,
            cashierName: '',
            items: [],
            rowKey: null,
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

    const renderSystemAlignedCell = (label, value) => (
        <div className="space-y-1 text-right">
            <div>
                <p className="text-xs text-gray-500 dark:text-gray-400">{label}</p>
                <p className="font-semibold text-gray-900 dark:text-white">
                    {formatCurrency(value)}
                </p>
            </div>
            <div>
                <p className="text-xs text-transparent select-none">Fechamento</p>
                <p className="text-sm text-transparent select-none">{formatCurrency(0)}</p>
            </div>
            <div>
                <p className="text-xs text-transparent select-none">Diferenca</p>
                <p className="text-sm font-semibold text-transparent select-none">
                    {formatCurrency(0)}
                </p>
            </div>
        </div>
    );

    const renderPaymentCell = (record, column) => {
        const systemValue = record.totals?.[column.key] ?? 0;

        if (!['dinheiro', 'maquina'].includes(column.key)) {
            return renderSystemAlignedCell('Sistema', systemValue);
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
                    <p className="text-xs text-gray-500 dark:text-gray-400">Sistema caixa</p>
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
                {paymentColumns.map((column) => {
                    const columnMeta = meta?.[column.key] ?? {};
                    const backgroundColor = columnMeta.color ?? '#e5e7eb';
                    const textColor = getContrastingTextColor(backgroundColor);

                    return (
                        <div
                            key={column.key}
                            className="rounded-xl border p-4 text-center shadow-sm"
                            style={{
                                backgroundColor,
                                borderColor: backgroundColor,
                                color: textColor,
                            }}
                        >
                            <p className="text-sm font-semibold">{column.label}</p>
                            <p className="text-xl font-bold">
                                {formatCurrency(totals[column.key] ?? 0)}
                            </p>
                        </div>
                    );
                })}
                <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-center dark:border-indigo-500/40 dark:bg-indigo-900/30">
                    <p className="text-sm text-indigo-800 dark:text-indigo-200">Total de vendas</p>
                    <p className="text-2xl font-bold text-indigo-900 dark:text-white">
                        {formatCurrency(salesGrandTotal)}
                    </p>
                </div>
                <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-center dark:border-emerald-500/40 dark:bg-emerald-900/30">
                    <p className="text-sm text-emerald-800 dark:text-emerald-200">
                        Base da conferencia
                    </p>
                    <p className="text-2xl font-bold text-emerald-900 dark:text-white">
                        {formatCurrency(conferenceGrandTotal)}
                    </p>
                    <p className="mt-1 text-xs text-emerald-700 dark:text-emerald-200">
                        Somente dinheiro + cartao
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
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-300">
                A diferenca do caixa considera somente dinheiro e cartao. Vale, refeicao e faturar aparecem apenas como informativos de venda.
            </p>
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
                                <th className="px-3 py-2 text-right font-medium">Total vendas</th>
                                <th className="px-3 py-2 text-right font-medium">Conferencia caixa</th>
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
                                    `${record.cashier_id}-${record.unit_id ?? 'none'}`;

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
                                                    record.discard_alert?.exceeded
                                                        ? 'border border-amber-300 bg-amber-100 text-amber-900 hover:bg-amber-200 dark:border-amber-500/60 dark:bg-amber-900/40 dark:text-amber-100'
                                                        : record.discard_total > 0
                                                            ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-200'
                                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300'
                                                }`}
                                            >
                                                <span className="flex items-center justify-between">
                                                    <span className="inline-flex items-center gap-2">
                                                        <span>Descarte</span>
                                                        {record.discard_alert?.exceeded && (
                                                            <i className="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                                        )}
                                                    </span>
                                                    <span>{formatCurrency(record.discard_total ?? 0)}</span>
                                                </span>
                                                <span className="mt-0.5 block text-[10px] font-normal text-gray-500 dark:text-gray-400">
                                                    Qtd: {formatQuantity(record.discard_quantity ?? 0, 3)}
                                                    {record.discard_alert?.exceeded && record.discard_alert?.percentage !== null
                                                        ? ` • ${Number(record.discard_alert.percentage).toLocaleString('pt-BR', {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        })}%`
                                                        : ''}
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
                                                className="px-3 py-2 text-right text-gray-700 dark:text-gray-200 align-top"
                                            >
                                                {renderPaymentCell(record, column)}
                                            </td>
                                        ))}
                                        <td className="px-3 py-2 text-right text-gray-700 dark:text-gray-200 align-top">
                                            {renderSystemAlignedCell('Sistema', record.grand_total ?? 0)}
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
                    Totais de venda por atendente organizados por forma de pagamento. A conferencia do caixa usa somente dinheiro e cartao.
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
                                Totais calculados com base no valor registrado em cada discarte.
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
