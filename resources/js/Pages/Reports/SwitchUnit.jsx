import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function SwitchUnit({ units }) {
    const [submittingId, setSubmittingId] = useState(null);

    const handleSelect = (unitId) => {
        setSubmittingId(unitId);
        router.post(
            route('reports.switch-unit.update'),
            { unit_id: unitId },
            {
                preserveScroll: true,
                onFinish: () => setSubmittingId(null),
            },
        );
    };

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Trocar unidade
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Disponivel para usuarios Master e Gerente.
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Trocar unidade" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        {units.length === 0 ? (
                            <p className="text-sm text-gray-500 dark:text-gray-300">
                                Nenhuma unidade disponível para troca.
                            </p>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {units.map((unit) => (
                                    <button
                                        key={unit.id}
                                        type="button"
                                        disabled={unit.active || submittingId === unit.id}
                                        onClick={() => handleSelect(unit.id)}
                                        className={`rounded-2xl border px-4 py-3 text-left text-lg font-semibold shadow transition ${
                                            unit.active
                                                ? 'border-green-400 bg-green-50 text-green-700 dark:border-green-500/60 dark:bg-green-900/20 dark:text-green-200'
                                                : 'border-gray-200 bg-white text-gray-800 hover:border-indigo-500 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:border-indigo-400'
                                        } ${submittingId ? 'cursor-not-allowed opacity-70' : ''}`}
                                    >
                                        {unit.name}
                                        {unit.active && (
                                            <span className="ml-2 text-xs font-normal text-green-600 dark:text-green-300">
                                                (Atual)
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
