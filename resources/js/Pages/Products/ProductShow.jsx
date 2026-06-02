import AlertMessage from "@/Components/Alert/AlertMessage";
import InfoButton from "@/Components/Button/InfoButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, usePage } from "@inertiajs/react";

const formatCurrency = (value) => {
    const parsed = Number(value ?? 0);

    return parsed.toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL",
    });
};

const resolveLabel = (labels, key) => {
    if (!labels) {
        return "---";
    }

    return labels[key] ?? "---";
};

export default function ProductShow({ auth, product, fiscalData = {}, typeLabels, statusLabels }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Produtos
                </h2>
            }
        >
            <Head title="Produtos" />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <div className="flex justify-between items-center m-4">
                        <h3 className="text-lg">Visualizar</h3>
                        <div className="flex space-x-4">
                            {Number(product.tb1_tipo) === 3 && (
                                <Link href={route("products.production-stock", { product_id: product.tb1_id })}>
                                    <InfoButton aria-label="Estoque" title="Estoque de Producao">
                                        <i className="bi bi-boxes text-lg" aria-hidden="true"></i>
                                    </InfoButton>
                                </Link>
                            )}
                            <Link href={route("products.index")}>
                                <InfoButton aria-label="Listar" title="Listar">
                                    <i className="bi bi-list text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            </Link>
                        </div>
                    </div>

                    <AlertMessage message={flash} />

                    <div className="bg-gray-50 text-sm dark:bg-gray-700 p-4 rounded-lg shadow-m">
                        <div className="mb-4">
                            <p className="text-md font-semibold text-gray-700 dark:text-gray-200">ID</p>
                            <p className="text-gray-600 dark:text-gray-400">{product.tb1_id}</p>
                        </div>
                        <div className="mb-4">
                            <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Nome</p>
                            <p className="text-gray-600 dark:text-gray-400">{product.tb1_nome}</p>
                        </div>
                        <div className="mb-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Valor de custo</p>
                                <p className="text-gray-600 dark:text-gray-400">{formatCurrency(product.tb1_vlr_custo)}</p>
                            </div>
                            <div>
                                <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Valor de venda</p>
                                <p className="text-gray-600 dark:text-gray-400">{formatCurrency(product.tb1_vlr_venda)}</p>
                            </div>
                        </div>
                        <div className="mb-4">
                            <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Código de barras</p>
                            <p className="text-gray-600 dark:text-gray-400 break-all">{product.tb1_codbar}</p>
                        </div>
                        <div className="mb-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Tipo</p>
                                <p className="text-gray-600 dark:text-gray-400">
                                    {resolveLabel(typeLabels, product.tb1_tipo)}
                                </p>
                            </div>
                            <div>
                                <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Status</p>
                                <p className="text-gray-600 dark:text-gray-400">
                                    {resolveLabel(statusLabels, product.tb1_status)}
                                </p>
                            </div>
                        </div>
                        <div className="mb-4">
                            <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Estoque atual</p>
                            <p className="text-gray-600 dark:text-gray-400">{Number(product.tb1_qtd ?? 0)}</p>
                        </div>
                        <div className="mt-6 rounded-md border border-gray-200 bg-white p-4 dark:bg-gray-800">
                            <p className="text-md font-semibold text-gray-700 dark:text-gray-200">Dados fiscais herdados da categoria</p>
                            <div className="mt-3 grid gap-3 text-gray-600 sm:grid-cols-2 lg:grid-cols-4 dark:text-gray-300">
                                <p><span className="font-semibold">Categoria:</span> {fiscalData.categoria_nome ?? "--"}</p>
                                <p><span className="font-semibold">Grupo NCM:</span> {fiscalData.grupo_ncm_nome ?? "--"}</p>
                                <p><span className="font-semibold">Fonte:</span> {fiscalData.source ?? "--"}</p>
                                <p><span className="font-semibold">NCM:</span> {fiscalData.ncm ?? "--"}</p>
                                <p><span className="font-semibold">CEST:</span> {fiscalData.cest ?? "--"}</p>
                                <p><span className="font-semibold">CFOP:</span> {fiscalData.cfop ?? "--"}</p>
                                <p><span className="font-semibold">CSOSN:</span> {fiscalData.csosn ?? "--"}</p>
                                <p><span className="font-semibold">CST ICMS:</span> {fiscalData.cst ?? "--"}</p>
                                <p><span className="font-semibold">cClassTrib:</span> {fiscalData.cclasstrib ?? "--"}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
