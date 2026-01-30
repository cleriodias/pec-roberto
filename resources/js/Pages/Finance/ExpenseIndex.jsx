import AlertMessage from '@/Components/Alert/AlertMessage';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const formatDate = (value) => {
    const date = value ? new Date(value) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return '--/--/----';
    }

    return date.toLocaleDateString('pt-BR');
};

export default function ExpenseIndex({ suppliers = [], expenses = [], activeUnit = null }) {
    const { flash } = usePage().props;
    const defaultSupplier = suppliers.length ? String(suppliers[0].id) : '';
    const today = new Date().toISOString().slice(0, 10);
    const hasActiveUnit = Boolean(activeUnit?.id);
    const { data, setData, post, processing, errors, reset } = useForm({
        supplier_id: defaultSupplier,
        expense_date: today,
        amount: '',
        notes: '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('expenses.store'), {
            onSuccess: () => reset('amount', 'notes'),
        });
    };

    const handleDelete = (expenseId) => {
        if (!expenseId) {
            return;
        }
        if (!window.confirm('Confirma excluir este gasto?')) {
            return;
        }
        router.delete(route('expenses.destroy', expenseId));
    };

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Gastos
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Cadastre gastos com fornecedor, data, valor e observacao.
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Unidade ativa: <span className="font-semibold text-gray-700 dark:text-gray-200">{activeUnit?.name ?? '--'}</span>
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Gastos" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        {!hasActiveUnit && (
                            <p className="mb-4 text-sm text-red-600">
                                Nenhuma unidade ativa definida. Selecione uma unidade para cadastrar gastos.
                            </p>
                        )}
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                <div className="md:col-span-2">
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Fornecedor
                                    </label>
                                    <select
                                        value={data.supplier_id}
                                        onChange={(event) => setData('supplier_id', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!suppliers.length || !hasActiveUnit}
                                    >
                                        {!suppliers.length ? (
                                            <option value="">Nenhum fornecedor cadastrado</option>
                                        ) : (
                                            suppliers.map((supplier) => (
                                                <option key={supplier.id} value={supplier.id}>
                                                    {supplier.name}
                                                </option>
                                            ))
                                        )}
                                    </select>
                                    {errors.supplier_id && (
                                        <p className="text-sm text-red-600">{errors.supplier_id}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Data
                                    </label>
                                    <input
                                        type="date"
                                        value={data.expense_date}
                                        onChange={(event) => setData('expense_date', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!hasActiveUnit}
                                    />
                                    {errors.expense_date && (
                                        <p className="text-sm text-red-600">{errors.expense_date}</p>
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
                                        value={data.amount}
                                        onChange={(event) => setData('amount', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!hasActiveUnit}
                                    />
                                    {errors.amount && (
                                        <p className="text-sm text-red-600">{errors.amount}</p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Observacao
                                </label>
                                <textarea
                                    rows={3}
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    placeholder="Descreva o gasto"
                                    disabled={!hasActiveUnit}
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600">{errors.notes}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing || !suppliers.length || !hasActiveUnit}
                                    className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Salvar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="overflow-x-auto">
                            {expenses.length ? (
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-900/40">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Fornecedor
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Data
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                                                Valor
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Observacao
                                            </th>
                                            <th className="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-300">
                                                Acoes
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {expenses.map((expense) => (
                                            <tr key={expense.id}>
                                                <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                    {expense.supplier?.name ?? '---'}
                                                </td>
                                                <td className="px-3 py-2 text-gray-700 dark:text-gray-200">
                                                    {formatDate(expense.expense_date)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                                    {formatCurrency(expense.amount)}
                                                </td>
                                                <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                    {expense.notes ?? '--'}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(expense.id)}
                                                        className="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 dark:border-red-500/60 dark:text-red-300 dark:hover:bg-red-500/10"
                                                    >
                                                        Excluir
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="text-sm text-gray-500 dark:text-gray-300">
                                    Nenhum gasto cadastrado.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
