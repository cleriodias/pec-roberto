import InfoButton from "@/Components/Button/InfoButton";
import WarningButton from "@/Components/Button/WarningButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm, router, usePage } from "@inertiajs/react";
import { useState } from "react";

const roleOptions = [
    { value: 0, label: 'MASTER' },
    { value: 1, label: 'GERENTE' },
    { value: 2, label: 'SUB-GERENTE' },
    { value: 3, label: 'CAIXA' },
    { value: 4, label: 'LANCHONETE' },
    { value: 5, label: 'FUNCIONARIO' },
    { value: 6, label: 'CLIENTE' },
];

const formatTime = (value) => {
    if (!value) {
        return '';
    }

    return value.substring(0, 5);
};

export default function UserEdit({ auth, user, units = [] }) {
    const pageProps = usePage().props;
    const csrfToken = pageProps?.csrf_token ?? '';

    const initialUnits = user.units && user.units.length
        ? user.units.map((unit) => String(unit.tb2_id))
        : (user.tb2_id ? [String(user.tb2_id)] : []);
    const { data, setData, put, processing, errors } = useForm({
        id: user.id || '',
        name: user.name || '',
        email: user.email || '',
        funcao: user.funcao !== undefined && user.funcao !== null ? String(user.funcao) : '5',
        hr_ini: formatTime(user.hr_ini) || '08:00',
        hr_fim: formatTime(user.hr_fim) || '17:00',
        salario: user.salario !== undefined && user.salario !== null ? String(user.salario) : '1518',
        vr_cred: user.vr_cred !== undefined && user.vr_cred !== null ? String(user.vr_cred) : '350',
        tb2_id: initialUnits,
    });

    const [passwordResetMessage, setPasswordResetMessage] = useState(null);

    const handleSubmit = (e) => {

        e.preventDefault();

        put(route('users.update', {user: data.id}));
    }

    const handlePasswordReset = () => {
        if (!window.confirm('Gerar uma nova senha temporária para este usuário?')) {
            return;
        }

        router.post(route('users.reset-password', { user: data.id }), { _token: csrfToken }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props?.flash;
                if (flash?.success) {
                    setPasswordResetMessage(flash.success);
                }
            },
        });
    };

    const selectedUnits = data.tb2_id ?? [];

    const handleUnitToggle = (unitId) => {
        if (selectedUnits.includes(unitId)) {
            setData('tb2_id', selectedUnits.filter((value) => value !== unitId));
        } else {
            setData('tb2_id', [...selectedUnits, unitId]);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{'Usu\u00E1rios'}</h2>}
        >
            <Head title={'Usu\u00E1rio'} />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div className="flex flex-wrap items-center justify-between gap-3 m-4">
                        <h3 className="text-lg">Editar</h3>
                        <div className="flex flex-wrap gap-3">
                            {passwordResetMessage && (
                                <span className="rounded-lg bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-200">
                                    {passwordResetMessage}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={handlePasswordReset}
                                className="inline-flex items-center gap-2 rounded-lg border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-400 dark:border-amber-500 dark:text-amber-200 dark:hover:bg-amber-500/20"
                            >
                                <i className="bi bi-key" aria-hidden="true"></i>
                                Trocar senha
                            </button>
                            <Link href={route('users.index')}>
                                <InfoButton aria-label="Listar" title="Listar">
                                    <i className="bi bi-list text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-gray-50 text-sm dark:bg-gray-700 p-4 rounded-lg shadow-m">
                        <form onSubmit={handleSubmit}>

                            <div className="mb-4">
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700">Nome</label>
                                <input
                                    id="name"
                                    type="text"
                                    placeholder={'Nome completo do usu\u00E1rio'}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.name && <span className="text-red-600">{errors.name}</span>}
                            </div>

                            <div className="mb-4">
                                <label htmlFor="email" className="block text-sm font-medium text-gray-700">E-mail</label>
                                <input
                                    id="email"
                                    type="email"
                                    placeholder={'Melhor e-mail do usu\u00E1rio'}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.email && <span className="text-red-600">{errors.email}</span>}
                            </div>

                            <div className="mb-4">
                                <label htmlFor="funcao" className="block text-sm font-medium text-gray-700">{'Fun\u00E7\u00E3o'}</label>
                                <select
                                    id="funcao"
                                    value={data.funcao}
                                    onChange={(e) => setData('funcao', e.target.value)}
                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    {roleOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.funcao && <span className="text-red-600">{errors.funcao}</span>}
                            </div>

                            <div className="mb-4">
                                <p className="block text-sm font-medium text-gray-700">Unidades</p>
                                {units.length ? (
                                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                        {units.map((unit) => {
                                            const unitId = String(unit.tb2_id);
                                            return (
                                                <label key={unit.tb2_id} className="inline-flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <input
                                                        type="checkbox"
                                                        value={unitId}
                                                        checked={selectedUnits.includes(unitId)}
                                                        onChange={() => handleUnitToggle(unitId)}
                                                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                    />
                                                    <span>#{unit.tb2_id} - {unit.tb2_nome}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Nenhuma unidade cadastrada. Cadastre ao menos uma unidade para prosseguir.
                                    </p>
                                )}
                                {errors.tb2_id && <span className="text-red-600">{errors.tb2_id}</span>}
                            </div>

                            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="hr_ini" className="block text-sm font-medium text-gray-700">{'In\u00EDcio da jornada'}</label>
                                    <input
                                        id="hr_ini"
                                        type="time"
                                        value={data.hr_ini}
                                        onChange={(e) => setData('hr_ini', e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.hr_ini && <span className="text-red-600">{errors.hr_ini}</span>}
                                </div>
                                <div>
                                    <label htmlFor="hr_fim" className="block text-sm font-medium text-gray-700">Fim da jornada</label>
                                    <input
                                        id="hr_fim"
                                        type="time"
                                        value={data.hr_fim}
                                        onChange={(e) => setData('hr_fim', e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.hr_fim && <span className="text-red-600">{errors.hr_fim}</span>}
                                </div>
                            </div>

                            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="salario" className="block text-sm font-medium text-gray-700">{'Sal\u00E1rio (R$)'}</label>
                                    <input
                                        id="salario"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.salario}
                                        onChange={(e) => setData('salario', e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.salario && <span className="text-red-600">{errors.salario}</span>}
                                </div>
                                <div>
                                    <label htmlFor="vr_cred" className="block text-sm font-medium text-gray-700">{'Cr\u00E9dito refei\u00E7\u00E3o (R$)'}</label>
                                    <input
                                        id="vr_cred"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.vr_cred}
                                        onChange={(e) => setData('vr_cred', e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.vr_cred && <span className="text-red-600">{errors.vr_cred}</span>}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <WarningButton
                                    type="submit"
                                    disabled={processing}
                                    className="text-sm"
                                    aria-label="Salvar"
                                    title="Salvar"
                                >
                                    <i className="bi bi-floppy text-lg" aria-hidden="true"></i>
                                </WarningButton>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

        </AuthenticatedLayout>
    )
}
