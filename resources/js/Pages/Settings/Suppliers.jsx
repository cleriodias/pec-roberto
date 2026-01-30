import AlertMessage from '@/Components/Alert/AlertMessage';
import SuccessButton from '@/Components/Button/SuccessButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Suppliers({ auth, suppliers = [] }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        dispute: '0',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('settings.suppliers.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col gap-1">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Fornecedores
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Cadastre fornecedores e acompanhe o codigo de acesso.
                    </p>
                </div>
            }
        >
            <Head title="Fornecedores" />
            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 className="text-sm font-semibold uppercase text-gray-600 dark:text-gray-300">
                                Novo fornecedor
                            </h3>
                            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                                <div>
                                    <label htmlFor="name" className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Nome
                                    </label>
                                    <input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(event) => setData('name', event.target.value)}
                                        className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        placeholder="Nome do fornecedor"
                                    />
                                    {errors.name && <span className="text-sm text-red-600">{errors.name}</span>}
                                </div>

                                <div>
                                    <label htmlFor="dispute" className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Disputa
                                    </label>
                                    <select
                                        id="dispute"
                                        value={data.dispute}
                                        onChange={(event) => setData('dispute', event.target.value)}
                                        className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="0">Nao</option>
                                        <option value="1">Sim</option>
                                    </select>
                                    {errors.dispute && <span className="text-sm text-red-600">{errors.dispute}</span>}
                                </div>

                                <div>
                                    <label htmlFor="access_code" className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Codigo Acesso
                                    </label>
                                    <input
                                        id="access_code"
                                        type="text"
                                        value="Gerado automaticamente"
                                        disabled
                                        className="mt-2 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400"
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <SuccessButton type="submit" disabled={processing}>
                                        Salvar
                                    </SuccessButton>
                                </div>
                            </form>
                        </div>

                        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 className="text-sm font-semibold uppercase text-gray-600 dark:text-gray-300">
                                Fornecedores cadastrados
                            </h3>
                            <div className="mt-4 overflow-x-auto">
                                {suppliers.length ? (
                                    <table className="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                                        <thead className="text-xs uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th className="px-3 py-2">Nome</th>
                                                <th className="px-3 py-2">Disputa</th>
                                                <th className="px-3 py-2">Codigo Acesso</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {suppliers.map((supplier) => (
                                                <tr key={supplier.id}>
                                                    <td className="px-3 py-2 font-semibold">{supplier.name}</td>
                                                    <td className="px-3 py-2">{supplier.dispute ? 'Sim' : 'Nao'}</td>
                                                    <td className="px-3 py-2 font-mono">{supplier.access_code}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Nenhum fornecedor cadastrado.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
