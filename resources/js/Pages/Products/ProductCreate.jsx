import InfoButton from "@/Components/Button/InfoButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { useEffect } from "react";

const normalizeProductName = (value) => value.toLocaleUpperCase("pt-BR");

export default function ProductCreate({ auth, typeOptions = [], statusOptions = [] }) {
    const defaultType = typeOptions[0]?.value ?? 0;
    const defaultStatus = statusOptions[0]?.value ?? 1;

    const { data, setData, post, processing, errors } = useForm({
        tb1_id: "",
        tb1_nome: "",
        tb1_vlr_custo: "",
        tb1_vlr_venda: "",
        tb1_codbar: "",
        sem_codigo_barras: false,
        tb1_tipo: defaultType,
        tb1_qtd: "0",
        tb1_status: defaultStatus,
        tb1_vr_credit: false,
    });

    const isBalanceProduct = Number(data.tb1_tipo) === 1;
    const isProductionProduct = Number(data.tb1_tipo) === 3;
    const withoutBarcode = isBalanceProduct || Boolean(data.sem_codigo_barras);

    useEffect(() => {
        if (isBalanceProduct && !data.sem_codigo_barras) {
            setData("sem_codigo_barras", true);
        }
    }, [isBalanceProduct, data.sem_codigo_barras, setData]);

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route("products.store"));
    };

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
                        <h3 className="text-lg">Cadastrar</h3>
                        <div className="flex space-x-4">
                            <Link href={route("products.production-stock")}>
                                <InfoButton aria-label="Estoque" title="Estoque de Producao">
                                    <i className="bi bi-boxes text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            </Link>
                            <Link href={route("products.index")}>
                                <InfoButton aria-label="Listar" title="Listar">
                                    <i className="bi bi-list text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            </Link>
                        </div>
                    </div>

                    <div className="bg-gray-50 text-sm dark:bg-gray-700 p-4 rounded-lg shadow-m">
                        <form onSubmit={handleSubmit}>
                            <div className="mb-4">
                                <label htmlFor="tb1_nome" className="block text-sm font-medium text-gray-700">
                                    Nome
                                </label>
                                <input
                                    id="tb1_nome"
                                    type="text"
                                    placeholder="Nome do produto"
                                    value={data.tb1_nome}
                                    onChange={(e) => setData("tb1_nome", normalizeProductName(e.target.value))}
                                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.tb1_nome && <span className="text-red-600">{errors.tb1_nome}</span>}
                            </div>

                            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="tb1_vlr_custo" className="block text-sm font-medium text-gray-700">
                                        Valor de custo
                                    </label>
                                    <input
                                        id="tb1_vlr_custo"
                                        type="number"
                                        step="0.01"
                                        placeholder="0,00"
                                        value={data.tb1_vlr_custo}
                                        onChange={(e) => setData("tb1_vlr_custo", e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.tb1_vlr_custo && <span className="text-red-600">{errors.tb1_vlr_custo}</span>}
                                </div>
                                <div>
                                    <label htmlFor="tb1_vlr_venda" className="block text-sm font-medium text-gray-700">
                                        Valor de venda
                                    </label>
                                    <input
                                        id="tb1_vlr_venda"
                                        type="number"
                                        step="0.01"
                                        placeholder="0,00"
                                        value={data.tb1_vlr_venda}
                                        onChange={(e) => setData("tb1_vlr_venda", e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.tb1_vlr_venda && <span className="text-red-600">{errors.tb1_vlr_venda}</span>}
                                </div>
                            </div>

                            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="tb1_tipo" className="block text-sm font-medium text-gray-700">
                                        Tipo
                                    </label>
                                    <select
                                        id="tb1_tipo"
                                        value={data.tb1_tipo}
                                        onChange={(e) => setData("tb1_tipo", Number(e.target.value))}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    >
                                        {typeOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.tb1_tipo && <span className="text-red-600">{errors.tb1_tipo}</span>}
                                </div>
                                {isProductionProduct && (
                                    <div>
                                        <label htmlFor="tb1_qtd" className="block text-sm font-medium text-gray-700">
                                            Quantidade inicial
                                        </label>
                                        <input
                                            id="tb1_qtd"
                                            type="number"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                            value={data.tb1_qtd}
                                            onChange={(e) => setData("tb1_qtd", e.target.value)}
                                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">
                                            Produtos do tipo Producao utilizam esta quantidade como estoque inicial.
                                        </p>
                                        {errors.tb1_qtd && <span className="text-red-600">{errors.tb1_qtd}</span>}
                                    </div>
                                )}
                                <div>
                                    <label htmlFor="tb1_status" className="block text-sm font-medium text-gray-700">
                                        Status
                                    </label>
                                    <select
                                        id="tb1_status"
                                        value={data.tb1_status}
                                        onChange={(e) => setData("tb1_status", Number(e.target.value))}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    >
                                        {statusOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.tb1_status && <span className="text-red-600">{errors.tb1_status}</span>}
                                </div>
                            </div>

                            <div className="mb-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                                <label className="flex items-start gap-3 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={withoutBarcode}
                                        disabled={isBalanceProduct}
                                        onChange={(event) => setData("sem_codigo_barras", event.target.checked)}
                                        className="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    />
                                    <span>
                                        <span className="block font-medium text-gray-800">
                                            Produto sem codigo de barras
                                        </span>
                                        <span className="mt-1 block text-xs text-gray-500">
                                            {isBalanceProduct
                                                ? "Produtos de balanca sempre usam o proprio tb1_id no campo tb1_codbar."
                                                : "Quando marcado, o sistema gravara o proprio tb1_id no campo tb1_codbar."}
                                        </span>
                                    </span>
                                </label>
                                {errors.sem_codigo_barras && (
                                    <span className="mt-2 block text-red-600">{errors.sem_codigo_barras}</span>
                                )}
                            </div>

                            {isBalanceProduct ? (
                                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4">
                                    <label htmlFor="tb1_id" className="block text-sm font-medium text-gray-700">
                                        ID do produto
                                    </label>
                                    <input
                                        id="tb1_id"
                                        type="number"
                                        min="1"
                                        placeholder="Informe o tb1_id"
                                        value={data.tb1_id}
                                        onChange={(e) => setData("tb1_id", e.target.value)}
                                        className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm"
                                    />
                                    <p className="mt-2 text-xs text-amber-800">
                                        Produto de balanca nao usa codigo de barras. Informe o tb1_id manualmente.
                                        O sistema gravara esse mesmo tb1_id no campo tb1_codbar. Se o ID ja existir,
                                        o cadastro sera bloqueado e os dados atuais serao exibidos. IDs de 3000 a
                                        3100 sao reservados para comandas e tambem serao bloqueados.
                                    </p>
                                    {errors.tb1_id && <span className="text-red-600">{errors.tb1_id}</span>}
                                </div>
                            ) : withoutBarcode ? (
                                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4">
                                    <p className="text-sm font-medium text-gray-700">Codigo de barras</p>
                                    <p className="mt-2 text-xs text-amber-800">
                                        Este produto sera salvo sem codigo de barras proprio. O sistema vai copiar o
                                        tb1_id gerado automaticamente para o campo tb1_codbar.
                                    </p>
                                    {errors.tb1_codbar && <span className="text-red-600">{errors.tb1_codbar}</span>}
                                </div>
                            ) : (
                                <div className="mb-4">
                                    <label htmlFor="tb1_codbar" className="block text-sm font-medium text-gray-700">
                                        Codigo de barras
                                    </label>
                                    <input
                                        id="tb1_codbar"
                                        type="text"
                                        placeholder="0000000000000"
                                        value={data.tb1_codbar}
                                        onChange={(e) => setData("tb1_codbar", e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.tb1_codbar && <span className="text-red-600">{errors.tb1_codbar}</span>}
                                </div>
                            )}

                            <div className="mb-6">
                                <span className="text-sm font-medium text-gray-700">Disponivel para VR Credito</span>
                                <label className="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-200">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.tb1_vr_credit)}
                                        onChange={(event) => setData("tb1_vr_credit", event.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    Permitir que este produto seja pago com VR Credito.
                                </label>
                                {errors.tb1_vr_credit && (
                                    <span className="text-red-600">{errors.tb1_vr_credit}</span>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <SuccessButton
                                    type="submit"
                                    disabled={processing}
                                    className="text-sm"
                                    aria-label="Cadastrar"
                                    title="Cadastrar"
                                >
                                    <i className="bi bi-plus-lg text-lg" aria-hidden="true"></i>
                                </SuccessButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
