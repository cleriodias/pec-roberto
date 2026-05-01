import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function SwitchUnit({
    units = [],
    roles = [],
    currentUnitId,
    currentRole,
    currentRoleLabel,
    originalRoleLabel,
}) {
    const [selection, setSelection] = useState({
        unitId: currentUnitId ?? units[0]?.id ?? null,
        role: currentRole ?? roles[0]?.value ?? null,
    });
    const [processing, setProcessing] = useState(false);

    const selectedUnitName = useMemo(
        () => units.find((unit) => unit.id === Number(selection.unitId))?.name ?? '---',
        [selection.unitId, units],
    );
    const selectedRoleLabel = useMemo(
        () => roles.find((role) => role.value === Number(selection.role))?.label ?? currentRoleLabel ?? '---',
        [currentRoleLabel, roles, selection.role],
    );

    const submitSelection = (unitId, role) => {
        const normalizedUnitId = Number(unitId ?? 0);
        const normalizedRole = Number(role);

        if (
            processing ||
            !normalizedUnitId ||
            Number.isNaN(normalizedRole) ||
            (
                normalizedUnitId === Number(currentUnitId ?? 0) &&
                normalizedRole === Number(currentRole ?? -1)
            )
        ) {
            return;
        }

        setProcessing(true);
        router.post(
            route('reports.switch-unit.update'),
            {
                unit_id: normalizedUnitId,
                role: normalizedRole,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleUnitSelect = (unitId) => {
        setSelection((current) => ({
            ...current,
            unitId,
        }));
        submitSelection(unitId, selection.role);
    };

    const handleRoleSelect = (role) => {
        setSelection((current) => ({
            ...current,
            role,
        }));
        submitSelection(selection.unitId, role);
    };

    const renderToggleOption = (item, isSelected, isCurrent, onClick, labelKey = 'name') => (
        <button
            key={`${labelKey}-${item.id ?? item.value}`}
            type="button"
            onClick={onClick}
            disabled={processing}
            className="flex items-center gap-3 rounded-xl border border-transparent px-2 py-1.5 text-left transition hover:bg-white/70 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-gray-800/70"
        >
            <span
                className={`relative inline-flex h-7 w-11 shrink-0 rounded-full transition ${
                    isSelected ? 'bg-violet-600' : 'bg-slate-300 dark:bg-gray-600'
                }`}
            >
                <span
                    className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow-sm transition ${
                        isSelected ? 'left-5' : 'left-1'
                    }`}
                ></span>
            </span>
            <span className="min-w-0">
                <span className="block text-base font-semibold uppercase tracking-wide text-slate-900 dark:text-gray-100">
                    {item[labelKey]}
                </span>
                {isCurrent && (
                    <span className="mt-1 inline-flex rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        ATUAL
                    </span>
                )}
            </span>
        </button>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Troca rapida
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Unidade selecionada: {selectedUnitName} | Funcao selecionada: {selectedRoleLabel} | Funcao de origem: {originalRoleLabel ?? '---'}
                    </p>
                </div>
            }
            headerClassName="py-3"
        >
            <Head title="Trocar" />

            <div className="pt-3 pb-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-800">
                        <div className="space-y-5 p-4 text-gray-900 dark:text-gray-100 sm:p-5">
                            <div className="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/40 sm:px-5">
                                <div className="space-y-1.5">
                                    <h3 className="text-xl font-semibold text-slate-900 dark:text-gray-100">
                                        Troca rapida do perfil {currentRoleLabel ?? '---'}
                                    </h3>
                                    <p className="text-[13px] text-slate-500 dark:text-gray-300">
                                        Selecione uma unidade e uma funcao. Ao marcar uma de cada, a sessao e atualizada automaticamente.
                                    </p>
                                </div>

                                <div className="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold sm:text-sm">
                                    <span className="text-slate-500 dark:text-gray-300">SESSAO ATUAL</span>
                                    <span className="rounded-full bg-violet-600 px-2.5 py-1 text-white shadow-sm">
                                        {selectedUnitName}
                                    </span>
                                    <span className="rounded-full bg-amber-500 px-2.5 py-1 text-white shadow-sm">
                                        {selectedRoleLabel}
                                    </span>
                                    {processing && (
                                        <span className="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] text-slate-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                            Atualizando sessao...
                                        </span>
                                    )}
                                </div>

                                <div className="mt-6 grid gap-8 xl:grid-cols-2">
                                    <div>
                                        <h4 className="text-2xl font-semibold tracking-tight text-slate-900 dark:text-gray-100">
                                            Unidades
                                        </h4>
                                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                            {units.map((unit) =>
                                                renderToggleOption(
                                                    unit,
                                                    Number(selection.unitId) === unit.id,
                                                    Boolean(unit.active),
                                                    () => handleUnitSelect(unit.id),
                                                ),
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="text-2xl font-semibold tracking-tight text-slate-900 dark:text-gray-100">
                                            Funcao
                                        </h4>
                                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                            {roles.map((role) =>
                                                renderToggleOption(
                                                    role,
                                                    Number(selection.role) === role.value,
                                                    Boolean(role.active),
                                                    () => handleRoleSelect(role.value),
                                                    'label',
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
