import AlertMessage from "@/Components/Alert/AlertMessage";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import WarningButton from "@/Components/Button/WarningButton";
import ConfirmDeleteButton from "@/Components/Delete/ConfirmDeleteButton";
import Pagination from "@/Components/Pagination";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage, router } from "@inertiajs/react";

const funcaoLabels = {
    0: 'MASTER',
    1: 'GERENTE',
    2: 'SUB-GERENTE',
    3: 'CAIXA',
    4: 'LANCHONETE',
    5: 'FUNCIONARIO',
    6: 'CLIENTE',
};

const formatTime = (value) => {
    if (!value) {
        return '--:--';
    }

    return value.substring(0, 5);
};

const formatCurrency = (value) => {
    const parsed = Number(value ?? 0);

    return parsed.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const formatUnitIds = (user) => {
    if (user.units && user.units.length > 0) {
        return user.units.map((unit) => unit.tb2_id).join(', ');
    }

    return user.tb2_id ?? '---';
};

export default function UserIndex({ auth, users, units = [], filters = {} }) {

    const { flash } = usePage().props;
    const currentUnit = filters.unit ?? '';
    const currentRole = filters.funcao ?? '';

    const handleFilterChange = (key, value) => {
        const payload = {
            ...filters,
            [key]: value || undefined,
        };

        router.get(route('users.index'), payload, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{'Usu\u00E1rios'}</h2>}
        >
            <Head title={'Usu\u00E1rio'} />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div className="flex justify-between items-center m-4">
                        <h3 className="text-lg">Listar</h3>
                        <div className="flex space-x-4">
                            <Link href={route('users.create')}>
                                <SuccessButton aria-label="Cadastrar" title="Cadastrar">
                                    <i className="bi bi-plus-lg text-lg" aria-hidden="true"></i>
                                </SuccessButton>
                            </Link>
                        </div>
                    </div>

                    {/* Exibir mensagens de alerta */}
                    <AlertMessage message={flash} />

                    <div className="px-4 pb-3 sm:flex sm:items-end sm:gap-4">
                        <div className="flex flex-1 flex-col gap-1">
                            <label className="text-sm font-medium text-gray-700">Unidade</label>
                            <select
                                value={currentUnit}
                                onChange={(e) => handleFilterChange('unit', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="">Todas</option>
                                {units.map((unit) => (
                                    <option key={unit.tb2_id} value={unit.tb2_id}>
                                        {unit.tb2_nome} ({unit.tb2_id})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="mt-3 flex flex-1 flex-col gap-1 sm:mt-0">
                            <label className="text-sm font-medium text-gray-700">Perfil</label>
                            <select
                                value={currentRole}
                                onChange={(e) => handleFilterChange('funcao', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="">Todos</option>
                                {Object.entries(funcaoLabels).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>


                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">ID</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Nome</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">E-mail</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">{'Fun\u00E7\u00E3o'}</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Jornada</td>
                                <td className="px-4 py-3 text-right text-sm font-medium text-gray-500 tracking-wider">{'Sal\u00E1rio'}</td>
                                <td className="px-4 py-3 text-right text-sm font-medium text-gray-500 tracking-wider">{'Cr\u00E9dito VR'}</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Unidades (IDs)</td>
                                <td className="px-4 py-3 text-center text-sm font-medium text-gray-500 tracking-wider">{'A\u00E7\u00F5es'}</td>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {user.id}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {user.name}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {funcaoLabels[user.funcao] ?? '---'}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {formatTime(user.hr_ini)} - {formatTime(user.hr_fim)}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider text-right">
                                        {formatCurrency(user.salario)}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider text-right">
                                        {formatCurrency(user.vr_cred)}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        {formatUnitIds(user)}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-500 tracking-wider">
                                        <Link href={route('users.show', { user: user.id })}>
                                            <PrimaryButton
                                                className="ms-1"
                                                aria-label="Visualizar"
                                                title="Visualizar"
                                            >
                                                <i className="bi bi-eye text-lg" aria-hidden="true"></i>
                                            </PrimaryButton>
                                        </Link>
                                        <Link href={route('users.edit', { user: user.id })}>
                                            <WarningButton
                                                className="ms-1"
                                                aria-label="Editar"
                                                title="Editar"
                                            >
                                                <i className="bi bi-pencil-square text-lg" aria-hidden="true"></i>
                                            </WarningButton>
                                        </Link>
                                        <ConfirmDeleteButton id={user.id} routeName="users.destroy" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>


                    {/* Paginacao */}
                    <Pagination links={users.links} currentPage={users.current_page} />
                </div>
            </div>

        </AuthenticatedLayout>
    )
}
