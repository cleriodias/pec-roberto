import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

const COLOR_CYCLE = [
    'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-100',
    'border-slate-400 bg-slate-100 text-slate-900 dark:border-slate-500/40 dark:bg-slate-500/10 dark:text-slate-100',
    'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100',
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100',
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100',
    'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-500/40 dark:bg-violet-500/10 dark:text-violet-100',
    'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-500/40 dark:bg-cyan-500/10 dark:text-cyan-100',
];

export default function SwitchUnit({
    units = [],
    roles = [],
    currentUnitId,
    currentRole,
    currentRoleLabel,
    originalRoleLabel,
}) {
    const { data, setData, post, processing } = useForm({
        unit_id: currentUnitId ?? units[0]?.id ?? null,
        role: currentRole ?? roles[0]?.value ?? null,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('reports.switch-unit.update'));
    };

    const selectedUnitName = units.find((unit) => unit.id === Number(data.unit_id))?.name ?? '---';
    const selectedRoleLabel = roles.find((role) => role.value === Number(data.role))?.label ?? currentRoleLabel ?? '---';

    const renderOption = (item, index, selected, onSelect, valueKey = 'id') => {
        const color = COLOR_CYCLE[index % COLOR_CYCLE.length];
        const isCurrent = item.active;

        return (
            <button
                key={item[valueKey]}
                type="button"
                onClick={() => onSelect(item[valueKey])}
                className={`relative rounded-2xl border px-4 py-3 text-left text-sm font-semibold shadow-sm transition ${
                    selected
                        ? `${color} ring-2 ring-offset-2 ring-indigo-500 dark:ring-offset-gray-900`
                        : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100'
                }`}
            >
                <span className="block pr-16">{item.name ?? item.label}</span>
                {isCurrent && (
                    <span className="absolute right-3 top-3 rounded-full bg-white/80 px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-gray-700 dark:bg-gray-900/70 dark:text-gray-100">
                        Atual
                    </span>
                )}
            </button>
        );
    };

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Trocar
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Unidade selecionada: {selectedUnitName} | Funcao selecionada: {selectedRoleLabel} | Funcao de origem: {originalRoleLabel ?? '---'}
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Trocar" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Trocar Unidade
                            </h3>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {units.map((unit, index) =>
                                    renderOption(unit, index, Number(data.unit_id) === unit.id, (value) =>
                                        setData('unit_id', value),
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Trocar Funcao
                            </h3>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {roles.map((role, index) =>
                                    renderOption(
                                        role,
                                        index,
                                        Number(data.role) === role.value,
                                        (value) => setData('role', value),
                                        'value',
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                Atualizar sessao
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
