import AlertMessage from "@/Components/Alert/AlertMessage";
import DangerButton from "@/Components/Button/DangerButton";
import InfoButton from "@/Components/Button/InfoButton";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import WarningButton from "@/Components/Button/WarningButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";

const emptyForm = {
    tb33_codigo: "",
    tb33_nome: "",
    tb33_descricao: "",
    tb33_ncm: "",
    tb33_cest: "",
    tb33_cclass_trib: "",
    tb33_ativo: true,
    tb33_observacao_fiscal: "",
};

const normalizeForm = (group) => ({
    ...emptyForm,
    ...Object.fromEntries(Object.keys(emptyForm).map((field) => [field, group[field] ?? emptyForm[field]])),
    tb33_ativo: Boolean(group.tb33_ativo),
});

export default function GrupoNcm({ auth, groups = [] }) {
    const { flash } = usePage().props;
    const [editingId, setEditingId] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyForm);

    const editingGroup = useMemo(
        () => groups.find((group) => Number(group.tb33_id) === Number(editingId)),
        [groups, editingId]
    );

    const startEdit = (group) => {
        setEditingId(group.tb33_id);
        setData(normalizeForm(group));
    };

    const submit = (event) => {
        event.preventDefault();

        if (editingGroup) {
            put(route("products.grupos-ncm.update", { grupoNcm: editingGroup.tb33_id }), {
                preserveScroll: true,
                onSuccess: () => setEditingId(null),
            });
            return;
        }

        post(route("products.grupos-ncm.store"), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const removeGroup = (group) => {
        if (!window.confirm(`Excluir o grupo NCM ${group.tb33_nome}?`)) {
            return;
        }

        router.delete(route("products.grupos-ncm.destroy", { grupoNcm: group.tb33_id }), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Grupos NCM
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Cadastre NCM/CEST uma vez e vincule a varios produtos.
                        </p>
                    </div>
                    <Link href={route("products.index")}>
                        <PrimaryButton aria-label="Voltar" title="Voltar">
                            <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Grupos NCM" />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <AlertMessage message={flash} />
                <div className="grid gap-6 xl:grid-cols-[0.9fr_1.2fr]">
                    <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-gray-900">
                                {editingGroup ? "Editar grupo NCM" : "Novo grupo NCM"}
                            </h3>
                            {editingGroup && (
                                <InfoButton type="button" onClick={() => { setEditingId(null); reset(); }} aria-label="Cancelar edicao" title="Cancelar edicao">
                                    <i className="bi bi-x-lg text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            )}
                        </div>

                        <div className="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
                            O NCM deve ser validado com contador ou fonte oficial. Um grupo NCM pode ser usado em varios produtos.
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-gray-700">Codigo</label>
                                <input value={data.tb33_codigo} maxLength="30" onChange={(e) => setData("tb33_codigo", e.target.value.toUpperCase().replace(/\s+/g, "_"))} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                <p className="mt-1 text-xs text-gray-500">Exemplo: CAFE_PREPARADO.</p>
                                {errors.tb33_codigo && <p className="text-sm text-red-600">{errors.tb33_codigo}</p>}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700">Nome</label>
                                <input value={data.tb33_nome} maxLength="120" onChange={(e) => setData("tb33_nome", e.target.value.toUpperCase())} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                <p className="mt-1 text-xs text-gray-500">Exemplo: CAFE PREPARADO.</p>
                                {errors.tb33_nome && <p className="text-sm text-red-600">{errors.tb33_nome}</p>}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700">NCM</label>
                                <input value={data.tb33_ncm} maxLength="8" onChange={(e) => setData("tb33_ncm", e.target.value)} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                {errors.tb33_ncm && <p className="text-sm text-red-600">{errors.tb33_ncm}</p>}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700">CEST</label>
                                <input value={data.tb33_cest ?? ""} maxLength="7" onChange={(e) => setData("tb33_cest", e.target.value)} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                <p className="mt-1 text-xs text-gray-500">Preencha somente quando aplicavel.</p>
                                {errors.tb33_cest && <p className="text-sm text-red-600">{errors.tb33_cest}</p>}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700">cClassTrib</label>
                                <input value={data.tb33_cclass_trib ?? ""} maxLength="6" onChange={(e) => setData("tb33_cclass_trib", e.target.value)} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                {errors.tb33_cclass_trib && <p className="text-sm text-red-600">{errors.tb33_cclass_trib}</p>}
                            </div>
                            <label className="flex items-end gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={Boolean(data.tb33_ativo)} onChange={(e) => setData("tb33_ativo", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                Ativo
                            </label>
                        </div>

                        <div className="mt-4">
                            <label className="text-sm font-medium text-gray-700">Descricao</label>
                            <textarea value={data.tb33_descricao ?? ""} onChange={(e) => setData("tb33_descricao", e.target.value)} className="mt-1 min-h-20 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>

                        <div className="mt-4">
                            <label className="text-sm font-medium text-gray-700">Observacao fiscal</label>
                            <textarea value={data.tb33_observacao_fiscal ?? ""} onChange={(e) => setData("tb33_observacao_fiscal", e.target.value)} className="mt-1 min-h-20 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>

                        <div className="mt-4 flex justify-end">
                            {editingGroup ? (
                                <WarningButton type="submit" disabled={processing} aria-label="Salvar" title="Salvar">
                                    <i className="bi bi-floppy text-lg" aria-hidden="true"></i>
                                </WarningButton>
                            ) : (
                                <SuccessButton type="submit" disabled={processing} aria-label="Cadastrar" title="Cadastrar">
                                    <i className="bi bi-plus-lg text-lg" aria-hidden="true"></i>
                                </SuccessButton>
                            )}
                        </div>
                    </form>

                    <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 className="text-base font-semibold text-gray-900">Grupos cadastrados</h3>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-3 py-2">Nome</th>
                                        <th className="px-3 py-2">NCM</th>
                                        <th className="px-3 py-2">CEST</th>
                                        <th className="px-3 py-2">Produtos</th>
                                        <th className="px-3 py-2">Status</th>
                                        <th className="px-3 py-2 text-right">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {groups.map((group) => (
                                        <tr key={group.tb33_id}>
                                            <td className="px-3 py-2 font-semibold">{group.tb33_nome}</td>
                                            <td className="px-3 py-2">{group.tb33_ncm}</td>
                                            <td className="px-3 py-2">{group.tb33_cest ?? "--"}</td>
                                            <td className="px-3 py-2">{group.produtos_count}</td>
                                            <td className="px-3 py-2">
                                                <span className={group.tb33_ativo ? "rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" : "rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700"}>
                                                    {group.tb33_ativo ? "Ativo" : "Inativo"}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <InfoButton type="button" onClick={() => startEdit(group)} className="ms-1" aria-label="Editar" title="Editar">
                                                    <i className="bi bi-pencil-square text-lg" aria-hidden="true"></i>
                                                </InfoButton>
                                                <DangerButton type="button" onClick={() => removeGroup(group)} className="ms-1" aria-label="Excluir" title="Excluir">
                                                    <i className="bi bi-trash text-lg" aria-hidden="true"></i>
                                                </DangerButton>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
