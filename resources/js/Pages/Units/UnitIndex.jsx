import AlertMessage from "@/Components/Alert/AlertMessage";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import WarningButton from "@/Components/Button/WarningButton";
import ConfirmDeleteButton from "@/Components/Delete/ConfirmDeleteButton";
import Modal from "@/Components/Modal";
import Pagination from "@/Components/Pagination";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";

export default function UnitIndex({ auth, units, canCreate = false }) {
    const { flash } = usePage().props;
    const [togglingUnitId, setTogglingUnitId] = useState(null);
    const [activationUnit, setActivationUnit] = useState(null);
    const activationForm = useForm({
        password: '',
    });

    const formatCurrency = (value) =>
        Number(value ?? 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });

    const handleToggleFiscalGeneration = (unit) => {
        if (!unit?.tb2_id || togglingUnitId !== null) {
            return;
        }

        if (!unit.tb26_geracao_automatica_ativa) {
            activationForm.clearErrors();
            activationForm.setData('password', '');
            setActivationUnit(unit);
            return;
        }

        setTogglingUnitId(unit.tb2_id);

        router.patch(
            route('units.fiscal-generation.toggle', { unit: unit.tb2_id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingUnitId(null),
            }
        );
    };

    const closeActivationModal = () => {
        if (activationForm.processing) {
            return;
        }

        setActivationUnit(null);
        activationForm.reset();
        activationForm.clearErrors();
    };

    const submitFiscalActivation = (event) => {
        event.preventDefault();

        if (!activationUnit?.tb2_id) {
            return;
        }

        setTogglingUnitId(activationUnit.tb2_id);

        activationForm.patch(
            route('units.fiscal-generation.toggle', { unit: activationUnit.tb2_id }),
            {
                preserveScroll: true,
                onSuccess: closeActivationModal,
                onFinish: () => setTogglingUnitId(null),
            }
        );
    };

    const renderStatus = (status) => {
        const isActive = Number(status) === 1;

        return (
            <span
                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                    isActive
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-red-100 text-red-700'
                }`}
            >
                {isActive ? 'Ativa' : 'Inativa'}
            </span>
        );
    };

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
                            {canCreate && (
                                <Link href={route('units.create')}>
                                    <SuccessButton aria-label="Cadastrar" title="Cadastrar">
                                        <i className="bi bi-plus-lg text-lg" aria-hidden="true"></i>
                                    </SuccessButton>
                                </Link>
                            )}
                        </div>
                    </div>

                    <AlertMessage message={flash} />

                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">ID</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Nome</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">NF</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">CEP</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Telefone</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">CNPJ</td>
                                <td className="px-4 py-3 text-left text-sm font-medium text-gray-500 tracking-wider">Status</td>
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
                                        <div className="flex min-w-[180px] items-center justify-between gap-3">
                                            <Link
                                                href={route('settings.fiscal', { unit_id: unit.tb2_id })}
                                                className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition ${
                                                    unit.tb26_geracao_automatica_ativa
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                        : 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                }`}
                                                title={
                                                    unit.tb26_geracao_automatica_ativa
                                                        ? 'Geracao automatica de notas ativa'
                                                        : 'Geracao automatica de notas desligada'
                                                }
                                            >
                                                {formatCurrency(unit.tb2_nf_total)}
                                            </Link>

                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => handleToggleFiscalGeneration(unit)}
                                                    disabled={togglingUnitId !== null}
                                                    className={`relative inline-flex h-7 w-12 items-center rounded-full transition ${
                                                        unit.tb26_geracao_automatica_ativa
                                                            ? 'bg-blue-600'
                                                            : 'bg-slate-300'
                                                    } ${togglingUnitId !== null ? 'cursor-not-allowed opacity-70' : ''}`}
                                                    aria-label={`Alternar geracao automatica de notas da loja ${unit.tb2_nome}`}
                                                    aria-pressed={Boolean(unit.tb26_geracao_automatica_ativa)}
                                                    title={
                                                        unit.tb26_geracao_automatica_ativa
                                                            ? 'Clique para desligar a geracao automatica'
                                                            : 'Clique para ligar a geracao automatica'
                                                    }
                                                >
                                                    <span className="sr-only">Alternar geracao automatica de notas fiscais</span>
                                                    <span
                                                        className={`inline-block h-5 w-5 transform rounded-full bg-white transition ${
                                                            unit.tb26_geracao_automatica_ativa ? 'translate-x-6' : 'translate-x-1'
                                                        }`}
                                                    />
                                                </button>
                                                <span
                                                    className={`text-xs font-semibold ${
                                                        unit.tb26_geracao_automatica_ativa ? 'text-emerald-700' : 'text-rose-700'
                                                    }`}
                                                >
                                                    {unit.tb26_geracao_automatica_ativa ? 'Ativa' : 'Off'}
                                                </span>
                                            </div>
                                        </div>
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
                                        {renderStatus(unit.tb2_status)}
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

            <Modal show={Boolean(activationUnit)} onClose={closeActivationModal} maxWidth="md" tone="light">
                <form onSubmit={submitFiscalActivation} className="space-y-5 p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Ativar emissao de NF</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                {activationUnit?.tb2_nome ?? 'Unidade'} exigira senha para ligar a geracao automatica.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={closeActivationModal}
                            disabled={activationForm.processing}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 disabled:opacity-40"
                            aria-label="Fechar"
                            title="Fechar"
                        >
                            <i className="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div>
                        <label htmlFor="fiscal_activation_password" className="mb-1 block text-sm font-medium text-gray-700">
                            Senha
                        </label>
                        <input
                            id="fiscal_activation_password"
                            type="password"
                            value={activationForm.data.password}
                            onChange={(event) => activationForm.setData('password', event.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            autoComplete="current-password"
                            autoFocus
                        />
                        {activationForm.errors.password && (
                            <p className="mt-1 text-sm text-red-600">{activationForm.errors.password}</p>
                        )}
                    </div>

                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Ao confirmar, a emissao automatica de notas fiscais sera ativada para esta unidade.
                    </div>

                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={closeActivationModal}
                            disabled={activationForm.processing}
                            className="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-40"
                        >
                            Cancelar
                        </button>
                        <PrimaryButton type="submit" disabled={activationForm.processing || !activationForm.data.password}>
                            <i className="bi bi-check2 mr-2 text-lg" aria-hidden="true"></i>
                            Ativar
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
