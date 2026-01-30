import AlertMessage from "@/Components/Alert/AlertMessage";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import WarningButton from "@/Components/Button/WarningButton";
import ConfirmDeleteButton from "@/Components/Delete/ConfirmDeleteButton";
import Pagination from "@/Components/Pagination";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";

export default function UnitIndex({ auth, units }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{'Unidades'}</h2>}
        >
            <Head title="Unidades" />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div className="flex justify-between items-center m-4">
                        <h3 className="text-lg">Listar</h3>
                        <div className="flex space-x-4">
                            <Link href={route('units.create')}>
                                <SuccessButton aria-label="Cadastrar" title="Cadastrar">
                                    <i className="bi bi-plus-lg text-lg" aria-hidden="true"></i>
                                </SuccessButton>
                            </Link>
                        </div>
                    </div>

                    <AlertMessage message={flash} />

                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">ID</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Nome</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">{'Endere\u00E7o'}</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">CEP</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Telefone</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">CNPJ</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">{'Localiza\u00E7\u00E3o'}</td>
                                <td className="px-4 py-3 text-center text-sm font-medium text-gray-500 tracking-wider">{'A\u00E7\u00F5es'}</td>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            {units.data.map((unit) => (
                                <tr key={unit.tb2_id}>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_id}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_nome}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_endereco}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_cep}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_fone}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {unit.tb2_cnpj}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        <a
                                            href={unit.tb2_localizacao}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-blue-600 hover:underline"
                                        >
                                            Ver mapa
                                        </a>
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        <Link href={route('units.show', { unit: unit.tb2_id })}>
                                            <PrimaryButton
                                                className="ms-1"
                                                aria-label="Visualizar"
                                                title="Visualizar"
                                            >
                                                <i className="bi bi-eye text-lg" aria-hidden="true"></i>
                                            </PrimaryButton>
                                        </Link>
                                        <Link href={route('units.edit', { unit: unit.tb2_id })}>
                                            <WarningButton
                                                className="ms-1"
                                                aria-label="Editar"
                                                title="Editar"
                                            >
                                                <i className="bi bi-pencil-square text-lg" aria-hidden="true"></i>
                                            </WarningButton>
                                        </Link>
                                        <ConfirmDeleteButton id={unit.tb2_id} routeName="units.destroy" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <Pagination links={units.links} currentPage={units.current_page} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
