import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import { useMemo, useState } from "react";

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL",
    });

export default function SalaryAdvanceCreate({ users, activeUnit = null }) {
    const [form, setForm] = useState({
        user_id: 0,
        user_name: "",
        amount: "",
        advance_date: new Date().toISOString().substring(0, 10),
        reason: "",
    });

    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const hasActiveUnit = Boolean(activeUnit?.id);

    const filteredUsers = useMemo(() => {
        if (!form.user_name || form.user_name.length < 2) {
            return [];
        }

        return users.filter((user) =>
            user.name.toLowerCase().includes(form.user_name.toLowerCase()),
        );
    }, [form.user_name, users]);

    const handleSubmit = (event) => {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        router.post(
            route("salary-advances.store"),
            form,
            {
                onError: (err) => setErrors(err),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Adiantamento de salario
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Selecione o colaborador e informe o valor e o motivo para registrar um adiantamento.
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Adiantamento" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={handleSubmit}
                        className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <div className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Unidade da sessao
                                </label>
                                <input
                                    type="text"
                                    value={activeUnit?.name ?? "--"}
                                    readOnly
                                    disabled
                                    className="mt-2 w-full rounded-xl border border-gray-300 bg-gray-100 px-3 py-2 text-gray-800 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                />
                                {!hasActiveUnit && (
                                    <p className="text-sm text-red-600">
                                        Nenhuma unidade ativa definida para registrar o adiantamento.
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Usuario
                                </label>
                                <input
                                    type="text"
                                    value={form.user_name}
                                    onChange={(event) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            user_name: event.target.value,
                                        }))
                                    }
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Digite o nome"
                                />
                                {filteredUsers.length > 0 && (
                                    <div className="mt-2 rounded-2xl border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-gray-900">
                                        {filteredUsers.map((user) => (
                                            <button
                                                type="button"
                                                key={user.id}
                                                onClick={() =>
                                                    setForm((prev) => ({
                                                        ...prev,
                                                        user_id: user.id,
                                                        user_name: user.name,
                                                    }))
                                                }
                                                className="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50 dark:text-gray-100 dark:hover:bg-indigo-900/30"
                                            >
                                                {user.name} - limite {formatCurrency(user.salary_limit)}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {errors.user_id && (
                                    <p className="text-sm text-red-600">{errors.user_id}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Data
                                    </label>
                                    <input
                                        type="date"
                                        value={form.advance_date}
                                        onChange={(event) =>
                                            setForm((prev) => ({
                                                ...prev,
                                                advance_date: event.target.value,
                                            }))
                                        }
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                    {errors.advance_date && (
                                        <p className="text-sm text-red-600">{errors.advance_date}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Valor (R$)
                                    </label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={form.amount}
                                        onChange={(event) =>
                                            setForm((prev) => ({
                                                ...prev,
                                                amount: event.target.value,
                                            }))
                                        }
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                    {errors.amount && (
                                        <p className="text-sm text-red-600">{errors.amount}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Motivo
                                </label>
                                <textarea
                                    rows={3}
                                    value={form.reason}
                                    onChange={(event) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            reason: event.target.value,
                                        }))
                                    }
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Descreva brevemente o motivo"
                                />
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={submitting || !hasActiveUnit}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Registrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
