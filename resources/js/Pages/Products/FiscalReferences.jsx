import AlertMessage from "@/Components/Alert/AlertMessage";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm, usePage } from "@inertiajs/react";

const formatRate = (value) =>
    Number(value ?? 0).toLocaleString("pt-BR", {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });

export default function FiscalReferences({ auth, references = [] }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        tb29_descricao: "",
        tb29_ncm: "",
        tb29_cfop: "",
        tb29_csosn: "",
        tb29_cst: "",
        tb29_cst_ibscbs: "",
        tb29_cclasstrib: "",
        tb29_aliquota_ibs_uf: "",
        tb29_aliquota_ibs_mun: "",
        tb29_aliquota_cbs: "",
        tb29_aliquota_is: "",
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route("products.fiscal-references.store"), {
            onSuccess: () =>
                reset(
                    "tb29_descricao",
                    "tb29_ncm",
                    "tb29_cfop",
                    "tb29_csosn",
                    "tb29_cst",
                    "tb29_cst_ibscbs",
                    "tb29_cclasstrib",
                    "tb29_aliquota_ibs_uf",
                    "tb29_aliquota_ibs_mun",
                    "tb29_aliquota_cbs",
                    "tb29_aliquota_is"
                ),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Referencias Fiscais
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Cadastre referencias para aplicar em lote nos produtos.
                        </p>
                    </div>
                    <Link href={route("products.fiscal-queue")}>
                        <PrimaryButton aria-label="Voltar para vinculo fiscal" title="Voltar para vinculo fiscal">
                            <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Referencias Fiscais" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <AlertMessage message={flash} />

                    <div className="grid gap-6 xl:grid-cols-[0.95fr_1.25fr]">
                        <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 className="text-sm font-semibold uppercase text-gray-600 dark:text-gray-300">
                                Nova referencia
                            </h3>

                            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                                <div>
                                    <label htmlFor="tb29_descricao" className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Descricao
                                    </label>
                                    <input
                                        id="tb29_descricao"
                                        type="text"
                                        maxLength="120"
                                        value={data.tb29_descricao}
                                        onChange={(event) => setData("tb29_descricao", event.target.value.toUpperCase())}
                                        className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        placeholder="Ex.: PADARIA PADRAO NFC-E"
                                    />
                                    {errors.tb29_descricao && <span className="text-sm text-red-600">{errors.tb29_descricao}</span>}
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="tb29_ncm" className="text-sm font-semibold text-gray-700 dark:text-gray-200">NCM</label>
                                        <input id="tb29_ncm" type="text" maxLength="8" value={data.tb29_ncm} onChange={(event) => setData("tb29_ncm", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_ncm && <span className="text-sm text-red-600">{errors.tb29_ncm}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_cfop" className="text-sm font-semibold text-gray-700 dark:text-gray-200">CFOP</label>
                                        <input id="tb29_cfop" type="text" maxLength="4" value={data.tb29_cfop} onChange={(event) => setData("tb29_cfop", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_cfop && <span className="text-sm text-red-600">{errors.tb29_cfop}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_csosn" className="text-sm font-semibold text-gray-700 dark:text-gray-200">CSOSN</label>
                                        <input id="tb29_csosn" type="text" maxLength="4" value={data.tb29_csosn} onChange={(event) => setData("tb29_csosn", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_csosn && <span className="text-sm text-red-600">{errors.tb29_csosn}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_cst" className="text-sm font-semibold text-gray-700 dark:text-gray-200">CST</label>
                                        <input id="tb29_cst" type="text" maxLength="3" value={data.tb29_cst} onChange={(event) => setData("tb29_cst", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_cst && <span className="text-sm text-red-600">{errors.tb29_cst}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_cst_ibscbs" className="text-sm font-semibold text-gray-700 dark:text-gray-200">CST IBS/CBS</label>
                                        <input id="tb29_cst_ibscbs" type="text" maxLength="3" value={data.tb29_cst_ibscbs} onChange={(event) => setData("tb29_cst_ibscbs", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_cst_ibscbs && <span className="text-sm text-red-600">{errors.tb29_cst_ibscbs}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_cclasstrib" className="text-sm font-semibold text-gray-700 dark:text-gray-200">cClassTrib</label>
                                        <input id="tb29_cclasstrib" type="text" maxLength="6" value={data.tb29_cclasstrib} onChange={(event) => setData("tb29_cclasstrib", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_cclasstrib && <span className="text-sm text-red-600">{errors.tb29_cclasstrib}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_aliquota_ibs_uf" className="text-sm font-semibold text-gray-700 dark:text-gray-200">Aliquota IBS UF</label>
                                        <input id="tb29_aliquota_ibs_uf" type="number" step="0.0001" min="0" max="100" value={data.tb29_aliquota_ibs_uf} onChange={(event) => setData("tb29_aliquota_ibs_uf", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_aliquota_ibs_uf && <span className="text-sm text-red-600">{errors.tb29_aliquota_ibs_uf}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_aliquota_ibs_mun" className="text-sm font-semibold text-gray-700 dark:text-gray-200">Aliquota IBS Municipio</label>
                                        <input id="tb29_aliquota_ibs_mun" type="number" step="0.0001" min="0" max="100" value={data.tb29_aliquota_ibs_mun} onChange={(event) => setData("tb29_aliquota_ibs_mun", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_aliquota_ibs_mun && <span className="text-sm text-red-600">{errors.tb29_aliquota_ibs_mun}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_aliquota_cbs" className="text-sm font-semibold text-gray-700 dark:text-gray-200">Aliquota CBS</label>
                                        <input id="tb29_aliquota_cbs" type="number" step="0.0001" min="0" max="100" value={data.tb29_aliquota_cbs} onChange={(event) => setData("tb29_aliquota_cbs", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_aliquota_cbs && <span className="text-sm text-red-600">{errors.tb29_aliquota_cbs}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb29_aliquota_is" className="text-sm font-semibold text-gray-700 dark:text-gray-200">Aliquota IS</label>
                                        <input id="tb29_aliquota_is" type="number" step="0.0001" min="0" max="100" value={data.tb29_aliquota_is} onChange={(event) => setData("tb29_aliquota_is", event.target.value)} className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" />
                                        {errors.tb29_aliquota_is && <span className="text-sm text-red-600">{errors.tb29_aliquota_is}</span>}
                                    </div>
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
                                Referencias cadastradas
                            </h3>

                            <div className="mt-4 overflow-x-auto">
                                {references.length ? (
                                    <table className="min-w-full text-left text-sm text-gray-700 dark:text-gray-200">
                                        <thead className="text-xs uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th className="px-3 py-2">Descricao</th>
                                                <th className="px-3 py-2">NCM</th>
                                                <th className="px-3 py-2">CFOP</th>
                                                <th className="px-3 py-2">CSOSN</th>
                                                <th className="px-3 py-2">CST</th>
                                                <th className="px-3 py-2">IBS UF</th>
                                                <th className="px-3 py-2">IBS Mun</th>
                                                <th className="px-3 py-2">CBS</th>
                                                <th className="px-3 py-2">IS</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {references.map((reference) => (
                                                <tr key={reference.tb29_id}>
                                                    <td className="px-3 py-2 font-semibold">{reference.tb29_descricao}</td>
                                                    <td className="px-3 py-2">{reference.tb29_ncm}</td>
                                                    <td className="px-3 py-2">{reference.tb29_cfop}</td>
                                                    <td className="px-3 py-2">{reference.tb29_csosn}</td>
                                                    <td className="px-3 py-2">
                                                        <div className="flex flex-col gap-1">
                                                            <span>{reference.tb29_cst}</span>
                                                            <span className="text-xs text-gray-500">IBS/CBS {reference.tb29_cst_ibscbs}</span>
                                                            <span className="text-xs text-gray-500">cClassTrib {reference.tb29_cclasstrib}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2">{formatRate(reference.tb29_aliquota_ibs_uf)}</td>
                                                    <td className="px-3 py-2">{formatRate(reference.tb29_aliquota_ibs_mun)}</td>
                                                    <td className="px-3 py-2">{formatRate(reference.tb29_aliquota_cbs)}</td>
                                                    <td className="px-3 py-2">{formatRate(reference.tb29_aliquota_is)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Nenhuma referencia fiscal cadastrada.
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
