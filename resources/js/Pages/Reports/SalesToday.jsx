import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const formatDate = (value) => {
    if (!value) {
        return '--';
    }

    const date = new Date(value);
    return date.toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const dayOptions = [
    { id: 'current', label: 'Hoje' },
    { id: 'previous', label: 'Ontem' },
];

export default function SalesToday({
    meta,
    chartData,
    details,
    totals,
    dateLabel,
    filterUnits = [],
    selectedUnitId = null,
    selectedDay = 'current',
}) {
    const initialType = useMemo(() => {
        const withValue = chartData.find((item) => item.total > 0);
        return withValue?.type ?? 'dinheiro';
    }, [chartData]);

    const [selectedType, setSelectedType] = useState(initialType);

    const unitOptions = useMemo(() => {
        const base = [{ id: null, name: 'Todas as unidades' }];
        if (!Array.isArray(filterUnits) || filterUnits.length === 0) {
            return base;
        }

        return base.concat(
            filterUnits.map((unit) => ({
                id: unit.id ?? unit.tb2_id ?? null,
                name: unit.name ?? unit.tb2_nome ?? '---',
            })),
        );
    }, [filterUnits]);

    const handleFilterChange = (unitId) => {
        const normalized = unitId ?? null;
        if (normalized === (selectedUnitId ?? null)) {
            return;
        }

        const params = {};
        if (normalized !== null) {
            params.unit_id = normalized;
        }
        if (selectedDay && selectedDay !== 'current') {
            params.day = selectedDay;
        }

        router.get(route('reports.sales.today'), params, { preserveScroll: true });
    };

    const handleDayChange = (day) => {
        if (day === selectedDay) {
            return;
        }

        const params = {};
        if (selectedUnitId !== null && selectedUnitId !== undefined) {
            params.unit_id = selectedUnitId;
        }
        if (day !== 'current') {
            params.day = day;
        }

        router.get(route('reports.sales.today'), params, { preserveScroll: true });
    };

    const totalSum = chartData.reduce((sum, item) => sum + item.total, 0);

    const pieStyle = useMemo(() => {
        if (totalSum <= 0) {
            return { background: '#e5e7eb' };
        }

        let accumulated = 0;
        const segments = chartData.flatMap((item) => {
            if (item.total <= 0) {
                return [];
            }

            const angle = (item.total / totalSum) * 360;
            const start = accumulated;
            const end = accumulated + angle;
            accumulated = end;

            return [`${item.color} ${start}deg ${end}deg`];
        });

        return {
            background: `conic-gradient(${segments.join(',')})`,
        };
    }, [chartData, totalSum]);

    const selectedDetails = details[selectedType] ?? [];
    const selectedMeta = meta[selectedType] ?? { label: selectedType, color: '#111827' };
    const selectedTotal = totals[selectedType] ?? 0;

    const observations = (record) => {
        if (record.origin === 'dinheiro' && selectedType === 'maquina') {
            return 'Complemento no cartao';
        }

        if (record.origin === 'dinheiro') {
            return 'Venda em dinheiro (pode conter complemento)';
        }

        if (record.origin === 'refeicao') {
            return 'Vale refei\u00e7\u00e3o';
        }

        if (record.origin === 'vale') {
            return 'Vale tradicional';
        }

        return 'Pagamento direto';
    };

    const headerContent = (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Vendas de hoje ({dateLabel})
                </h2>
                <p className="text-sm text-gray-500 dark:text-gray-300">
                    Visao geral dos pagamentos registrados no dia, com destaque para cada forma de
                    pagamento.
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Link
                    href={route('reports.control')}
                    className="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
                    aria-label="Voltar: Controle financeiro"
                    title="Voltar"
                >
                    <i className="bi bi-arrow-left" aria-hidden="true"></i>
                </Link>
                <Link
                    href={route('reports.sales.detailed')}
                    className="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
                    aria-label="Avancar: Relatorio detalhado"
                    title="Avancar"
                >
                    <i className="bi bi-arrow-right" aria-hidden="true"></i>
                </Link>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Vendas de hoje" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-col gap-6">
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.3em] text-gray-400">Período</p>
                                <div className="flex flex-wrap gap-2">
                                    {dayOptions.map((option) => {
                                        const isActive = option.id === selectedDay;

                                        return (
                                            <button
                                                type="button"
                                                key={option.id}
                                                onClick={() => handleDayChange(option.id)}
                                                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                                                    isActive
                                                        ? 'bg-indigo-600 text-white shadow'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200'
                                                }`}
                                            >
                                                {option.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.3em] text-gray-400">Unidade</p>
                                <div className="flex flex-wrap gap-2">
                                    {unitOptions.map((unit) => {
                                        const isActive = (unit.id ?? null) === (selectedUnitId ?? null);

                                        return (
                                            <button
                                                type="button"
                                                key={`unit-filter-${unit.id ?? 'all'}`}
                                                onClick={() => handleFilterChange(unit.id)}
                                                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                                                    isActive
                                                        ? 'bg-indigo-600 text-white shadow'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200'
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

                    <div className="grid gap-6 rounded-2xl bg-white p-6 shadow dark:bg-gray-800 lg:grid-cols-2">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Totais por tipo
                            </h3>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                {chartData.map((item) => (
                                    <div
                                        key={item.type}
                                        className="rounded-2xl border border-gray-100 bg-gray-50 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/40"
                                    >
                                        <p className="text-sm text-gray-600 dark:text-gray-300">
                                            {item.label}
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {formatCurrency(item.total)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex flex-col items-center justify-center gap-4">
                            <div className="text-center">
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-300">
                                    Total geral
                                </p>
                                <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                    {formatCurrency(totalSum)}
                                </p>
                            </div>
                            <div className="relative">
                                <div className="absolute inset-0 translate-y-3 rounded-full bg-black/20 blur-2xl" />
                                <div
                                    className="relative flex h-48 w-48 items-center justify-center rounded-full border-[10px] border-white shadow-2xl shadow-indigo-900/30 dark:border-gray-900"
                                    style={{
                                        ...pieStyle,
                                        boxShadow:
                                            'inset 0 12px 24px rgba(255,255,255,0.35), inset 0 -12px 24px rgba(15,23,42,0.4), 0 15px 35px rgba(15,23,42,0.25)',
                                    }}
                                >
                                    <div className="absolute inset-[14%] rounded-full bg-white/70 backdrop-blur dark:bg-gray-900/70" />
                                    <div className="absolute inset-[18%] rounded-full border border-white/40" />
                                </div>
                            </div>
                            <div className="flex flex-wrap justify-center gap-3 text-sm">
                                {chartData.map((item) => (
                                    <div key={item.type} className="flex items-center gap-2">
                                        <span
                                            className="inline-block h-3 w-3 rounded-full"
                                            style={{ backgroundColor: item.color }}
                                        />
                                        <span className="text-gray-600 dark:text-gray-200">
                                            {item.label}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Detalhes por pagamento
                                </h3>
                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                    Selecione uma forma de pagamento para listar as vendas.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-indigo-700 shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200">
                                    <p className="text-[10px] font-semibold uppercase tracking-wide">
                                        Total em {selectedMeta.label}
                                    </p>
                                    <p className="text-sm font-bold">
                                        {formatCurrency(selectedTotal)}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {chartData.map((item) => (
                                        <button
                                            type="button"
                                            key={item.type}
                                            onClick={() => setSelectedType(item.type)}
                                            className={`rounded-full px-4 py-2 text-sm font-semibold text-white shadow ${
                                                selectedType === item.type
                                                    ? 'ring-2 ring-offset-2 ring-offset-gray-50 dark:ring-offset-gray-800'
                                                    : 'opacity-70'
                                            }`}
                                            style={{ backgroundColor: item.color }}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 overflow-x-auto">
                            {selectedDetails.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                    Nenhuma venda registrada nesta forma de pagamento hoje.
                                </p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                    <thead className="bg-gray-100 dark:bg-gray-900/60">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                ID
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Data/Hora
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                                                Valor considerado
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Observacao
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {selectedDetails.map((record) => (
                                            <tr key={`${record.tb4_id}-${record.origin}-${record.applied_total}`}>
                                                <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                    #{record.tb4_id}
                                                </td>
                                                <td className="px-3 py-2 text-gray-700 dark:text-gray-200">
                                                    {formatDate(record.created_at)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                                    {formatCurrency(record.applied_total)}
                                                </td>
                                                <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {observations(record)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
