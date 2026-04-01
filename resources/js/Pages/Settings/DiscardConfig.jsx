import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, useForm } from '@inertiajs/react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const formatPercentage = (value) => {
    if (value === null || value === undefined) {
        return '--';
    }

    return `${Number(value).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
};

const statusTone = (active) =>
    active
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-emerald-200 bg-emerald-50 text-emerald-800';

export default function DiscardConfig({ setting, monitoring }) {
    const { data, setData, put, processing, errors } = useForm({
        percentual_aceitavel: setting?.percentual_aceitavel ?? 0,
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        put(route('settings.discard-config.update'), {
            preserveScroll: true,
        });
    };

    const cards = [
        {
            key: 'today',
            label: 'Hoje',
            data: monitoring?.today,
        },
        {
            key: 'month',
            label: 'Mes acumulado',
            data: monitoring?.month,
        },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Configuracao do Discarte
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Defina o percentual aceitavel para emitir alerta no fechamento de caixa.
                    </p>
                </div>
            }
        >
            <Head title="Configuracao do Discarte" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={handleSubmit}
                        className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Percentual aceitavel
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    value={data.percentual_aceitavel}
                                    onChange={(event) => setData('percentual_aceitavel', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Ex.: 4"
                                />
                                <InputError message={errors.percentual_aceitavel} className="mt-2" />
                                <p className="mt-3 text-xs text-gray-500 dark:text-gray-300">
                                    Se o valor estiver em 0, o sistema nao trata alerta de discarte.
                                </p>
                            </div>

                            <div className="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                                    Escopo atual
                                </p>
                                <p className="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {monitoring?.scope?.unit_name ?? '---'}
                                </p>
                                <p className="mt-3 text-xs text-gray-500 dark:text-gray-300">
                                    O alerta global considera o dia atual e o acumulado do mes atual.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Salvar configuracao
                            </button>
                        </div>
                    </form>

                    <div className="grid gap-4 md:grid-cols-2">
                        {cards.map((card) => {
                            const cardData = card.data ?? {};
                            const exceeded = Boolean(cardData.exceeded);

                            return (
                                <div
                                    key={card.key}
                                    className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {card.label}
                                            </h3>
                                            <p className="text-sm text-gray-500 dark:text-gray-300">
                                                Descarte x faturamento para o escopo atual.
                                            </p>
                                        </div>
                                        <span
                                            className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(exceeded)}`}
                                        >
                                            {exceeded && (
                                                <i className="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                            )}
                                            {exceeded ? 'Limite atingido' : 'Dentro do limite'}
                                        </span>
                                    </div>

                                    <div className="mt-5 grid gap-4 sm:grid-cols-3">
                                        <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                                                Descarte
                                            </p>
                                            <p className="mt-2 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(cardData.discard_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                                                Faturamento
                                            </p>
                                            <p className="mt-2 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                {formatCurrency(cardData.revenue_total)}
                                            </p>
                                        </div>
                                        <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                                                Percentual
                                            </p>
                                            <p className="mt-2 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                {formatPercentage(cardData.percentage)}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-300">
                                                Limite: {formatPercentage(monitoring?.threshold_percentage ?? 0)}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
