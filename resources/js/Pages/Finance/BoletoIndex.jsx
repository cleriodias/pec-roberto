import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AlertMessage from '@/Components/Alert/AlertMessage';
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

export default function BoletoIndex({ activeUnit = null, filters = {}, boletos = null, canManageList }) {
    const { flash } = usePage().props;
    const today = new Date().toISOString().slice(0, 10);
    const { data, setData, post, processing, errors, reset } = useForm({
        description: '',
        amount: '',
        due_date: today,
        barcode: '',
        digitable_line: '',
    });

    const { data: filterData, setData: setFilterData, get, processing: filterProcessing } = useForm({
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
        paid: filters.paid ?? 'all',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('boletos.store'), {
            onSuccess: () => reset('description', 'amount', 'barcode', 'digitable_line'),
        });
    };

    const handleFilterSubmit = (event) => {
        event.preventDefault();
        get(route('boletos.index'), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            data: filterData,
        });
    };

    const handlePay = (boletoId) => {
        if (!boletoId || !window.confirm('Confirma marcar este boleto como pago?')) {
            return;
        }

        router.put(route('boletos.pay', boletoId));
    };

    const headerContent = (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Boletos
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Cadastre boletos com valor, vencimento e codigo de barras. Master pode visualizar a lista e dar baixa.
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-300">
                Unidade ativa: <span className="font-semibold text-gray-700 dark:text-gray-200">{activeUnit?.name ?? '--'}</span>
            </p>
        </div>
    );

    return (
        <AuthenticatedLayout header={headerContent}>
            <Head title="Boletos" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="md:col-span-2">
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Descricao
                                    </label>
                                    <input
                                        type="text"
                                        value={data.description}
                                        onChange={(event) => setData('description', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!activeUnit}
                                    />
                                    {errors.description && (
                                        <p className="text-sm text-red-600">{errors.description}</p>
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
                                        disabled={!activeUnit}
                                    />
                                    {errors.amount && (
                                        <p className="text-sm text-red-600">{errors.amount}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Vencimento
                                    </label>
                                    <input
                                        type="date"
                                        value={data.due_date}
                                        onChange={(event) => setData('due_date', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!activeUnit}
                                    />
                                    {errors.due_date && (
                                        <p className="text-sm text-red-600">{errors.due_date}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Codigo de barras
                                    </label>
                                    <input
                                        type="text"
                                        value={data.barcode}
                                        onChange={(event) => setData('barcode', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!activeUnit}
                                    />
                                    {errors.barcode && (
                                        <p className="text-sm text-red-600">{errors.barcode}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Linha digitavel
                                    </label>
                                    <input
                                        type="text"
                                        value={data.digitable_line}
                                        onChange={(event) => setData('digitable_line', event.target.value)}
                                        className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        disabled={!activeUnit}
                                    />
                                    {errors.digitable_line && (
                                        <p className="text-sm text-red-600">{errors.digitable_line}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing || !activeUnit}
                                    className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Salvar
                                </button>
                            </div>
                        </form>
                    </div>

                    {canManageList ? (
                        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                            <form onSubmit={handleFilterSubmit} className="rounded-2xl border border-gray-100 p-4 dark:border-gray-700">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-300">
                                            Inicio do periodo
                                        </label>
                                        <input
                                            type="date"
                                            value={filterData.start_date}
                                            onChange={(event) => setFilterData('start_date', event.target.value)}
                                            className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-300">
                                            Fim do periodo
                                        </label>
                                        <input
                                            type="date"
                                            value={filterData.end_date}
                                            onChange={(event) => setFilterData('end_date', event.target.value)}
                                            className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-300">
                                            Status
                                        </label>
                                        <select
                                            value={filterData.paid}
                                            onChange={(event) => setFilterData('paid', event.target.value)}
                                            className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        >
                                            <option value="all">Todos</option>
                                            <option value="paid">Pagos</option>
                                            <option value="unpaid">Nao pagos</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={filterProcessing}
                                        className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        Aplicar filtro
                                    </button>
                                </div>
                            </form>

                            <div className="mt-6 overflow-x-auto">
                                {boletos?.data?.length ? (
                                    <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                        <thead className="bg-gray-50 dark:bg-gray-900/40">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                    Descricao
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                    Vencimento
                                                </th>
                                                <th className="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                                                    Valor
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                    Status
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                    Usuario
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                    Unidade
                                                </th>
                                                <th className="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-300">
                                                    Acoes
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                            {boletos.data.map((boleto) => (
                                                <tr key={boleto.id}>
                                                    <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                        {boleto.description}
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-700 dark:text-gray-200">
                                                        {formatDate(boleto.due_date)}
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                                        {formatCurrency(boleto.amount)}
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                        {boleto.is_paid ? 'Pago' : 'Pendente'}
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                        {boleto.user?.name ?? '--'}
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-600 dark:text-gray-300">
                                                        {boleto.unit?.tb2_nome ?? '--'}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        {!boleto.is_paid && (
                                                            <button
                                                                type="button"
                                                                onClick={() => handlePay(boleto.id)}
                                                                className="rounded-lg border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-emerald-500/60 dark:text-emerald-200 dark:hover:bg-emerald-900/30"
                                                            >
                                                                Dar baixa
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                        Nenhum boleto encontrado para o filtro aplicado.
                                    </p>
                                )}
                            </div>

                            {boletos?.links && (
                                <div className="mt-4 flex flex-wrap items-center justify-center gap-1 text-xs">
                                    {boletos.links.map((link, index) => (
                                        <button
                                            key={`${link.label}-${index}`}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() =>
                                                link.url &&
                                                router.visit(link.url, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                    replace: true,
                                                })
                                            }
                                            className={`rounded-full border px-3 py-1 font-semibold transition ${
                                                link.active
                                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500/70 dark:bg-indigo-900/30 dark:text-indigo-200'
                                                    : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'
                                            }`}
                                        >
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-300">
                                Apenas usuarios Master conseguem visualizar a lista de boletos e dar baixa.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
