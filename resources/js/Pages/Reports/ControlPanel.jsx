import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const MetricCard = ({ title, value, description, accent }) => (
    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <p className="text-sm font-medium text-gray-500">{title}</p>
        <p className="mt-2 text-3xl font-bold" style={{ color: accent ?? '#111827' }}>
            {formatCurrency(value)}
        </p>
        {description && <p className="mt-2 text-sm text-gray-500">{description}</p>}
    </div>
);

const Pill = ({ active, children, onClick, tone = 'default', size = 'md' }) => {
    const toneClasses = {
        default: active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700',
        dark: active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
    };
    const sizeClasses = size === 'sm' ? 'px-3 py-1 text-xs' : 'px-4 py-2 text-sm';

    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-indigo-400 ${toneClasses[tone] ?? toneClasses.default} ${sizeClasses}`}
        >
            {children}
        </button>
    );
};

export default function ControlPanel({
    unit,
    period,
    metrics,
    filterUnits = [],
    selectedUnitId = null,
    monthOptions = [],
    selectedMonth = null,
    yearOptions = [],
    selectedYear = null,
}) {
    const safeMetrics = {
        total_sales: metrics?.total_sales ?? 0,
        total_vale: metrics?.total_vale ?? 0,
        total_refeicao: metrics?.total_refeicao ?? 0,
        supplier_expenses: metrics?.supplier_expenses ?? 0,
        net_sales: metrics?.net_sales ?? 0,
        total_advances: metrics?.total_advances ?? 0,
        total_payroll: metrics?.total_payroll ?? 0,
        net_payroll: metrics?.net_payroll ?? 0,
    };

    const headerContent = (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Controle financeiro</h2>
                <p className="text-sm text-gray-500">
                    Consolidado mensal da unidade{' '}
                    <span className="font-semibold text-gray-700">{unit?.name ?? '---'}</span>{' '}
                    ({period?.label ?? ''})
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Link
                    href={route('reports.sales.period')}
                    className="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
                    aria-label="Voltar: Vendas por periodo"
                    title="Voltar"
                >
                    <i className="bi bi-arrow-left" aria-hidden="true"></i>
                </Link>
                <Link
                    href={route('reports.sales.today')}
                    className="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
                    aria-label="Avancar: Vendas de hoje"
                    title="Avancar"
                >
                    <i className="bi bi-arrow-right" aria-hidden="true"></i>
                </Link>
            </div>
        </div>
    );

    const unitsOptions = [{ id: null, name: 'Todas as unidades' }, ...filterUnits].filter(
        (option, index, self) => index === self.findIndex((item) => item.id === option.id),
    );

    const currentMonthValue = selectedMonth ?? (period?.start?.slice(0, 7) ?? '');
    const currentYearValue = selectedYear ?? (currentMonthValue?.slice(0, 4) ?? '');

    const handleFilter = (unitId) => {
        const params = { unit_id: unitId ?? '' };
        if (currentMonthValue) {
            params.month = currentMonthValue;
        }
        router.get(route('reports.control'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleMonthChange = (value) => {
        if ((value ?? '') === currentMonthValue) return;
        const params = { unit_id: selectedUnitId ?? '' };
        if (value) {
            params.month = value;
        }
        router.get(route('reports.control'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleYearChange = (year) => {
        if (!year || year === currentYearValue) return;
        const targetMonth = currentMonthValue ? currentMonthValue.slice(5, 7) : '01';
        handleMonthChange(`${year}-${targetMonth}`);
    };

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Controle financeiro" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-3xl bg-white p-6 shadow ring-1 ring-gray-100">
                        <div className="space-y-3">
                            <div>
                                <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                    Filtro
                                </p>
                                <p className="text-sm text-gray-500">Escolha as opcoes de filtro.</p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {unitsOptions.map((option) => {
                                    const isActive =
                                        (option.id === null && selectedUnitId === null) ||
                                        option.id === selectedUnitId;
                                    return (
                                        <Pill
                                            key={option.id ?? 'all'}
                                            active={isActive}
                                            onClick={() => handleFilter(option.id)}
                                        >
                                            {option.name}
                                        </Pill>
                                    );
                                })}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {(yearOptions.length
                                    ? yearOptions
                                    : [{ value: currentYearValue, label: currentYearValue }])
                                    .filter((y) => y.value)
                                    .map((option) => (
                                        <Pill
                                            key={`year-${option.value}`}
                                            active={option.value === currentYearValue}
                                            tone="dark"
                                            size="sm"
                                            onClick={() => handleYearChange(option.value)}
                                        >
                                            {option.label}
                                        </Pill>
                                    ))}
                                {(monthOptions.length
                                    ? monthOptions
                                    : [{ value: currentMonthValue, label: period?.label ?? 'Atual' }]).map(
                                    (option) => (
                                        <Pill
                                            key={`month-${option.value}`}
                                            active={option.value === currentMonthValue}
                                            size="sm"
                                            onClick={() => handleMonthChange(option.value)}
                                        >
                                            {option.label}
                                        </Pill>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="space-y-6 rounded-3xl bg-gradient-to-br from-indigo-50 via-white to-white p-8 shadow-xl ring-1 ring-indigo-100">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                                Vendas
                            </p>
                            <h3 className="mt-1 text-2xl font-bold text-gray-900">Resultado operacional</h3>
                            <p className="text-sm text-gray-600">
                                Considera todas as vendas do mes corrente, destacando o impacto dos vales e refeicao.
                            </p>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                            <MetricCard
                                title="Faturamento bruto"
                                value={safeMetrics.total_sales}
                                description="Todas as vendas registradas no periodo"
                                accent="#4338ca"
                            />
                            <MetricCard
                                title="Total em vales"
                                value={safeMetrics.total_vale}
                                description="Vendas lancadas como vale tradicional"
                                accent="#f97316"
                            />
                            <MetricCard
                                title="Total em refeicao"
                                value={safeMetrics.total_refeicao}
                                description="Consumos abatidos do saldo de refeicao"
                                accent="#d97706"
                            />
                            <MetricCard
                                title="Gastos fornecedor"
                                value={safeMetrics.supplier_expenses}
                                description="Total gasto com fornecedores no periodo"
                                accent="#ef4444"
                            />
                            <MetricCard
                                title="Faturamento liquido"
                                value={safeMetrics.net_sales}
                                description="Bruto menos vales, refeicao e gastos fornecedor"
                                accent="#16a34a"
                            />
                        </div>
                    </section>

                    <section className="space-y-6 rounded-3xl bg-white p-8 shadow-xl ring-1 ring-gray-100">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                Pessoas
                            </p>
                            <h3 className="mt-1 text-2xl font-bold text-gray-900">Folha e beneficios</h3>
                            <p className="text-sm text-gray-600">
                                Valores sempre consideram todos os colaboradores (todas as unidades).
                            </p>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            <MetricCard
                                title="Adiantamentos concedidos"
                                value={safeMetrics.total_advances}
                                description="Somatorio de adiantamentos no mes"
                                accent="#0ea5e9"
                            />
                            <MetricCard
                                title="Folha bruta"
                                value={safeMetrics.total_payroll}
                                description="Soma dos salarios dos colaboradores da unidade"
                                accent="#334155"
                            />
                            <MetricCard
                                title="Folha liquida"
                                value={safeMetrics.net_payroll}
                                description="Folha bruta - vales - refeicao - adiantamentos"
                                accent="#22c55e"
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
