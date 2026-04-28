import InfoButton from "@/Components/Button/InfoButton";
import WarningButton from "@/Components/Button/WarningButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { useEffect } from "react";

const normalizeProductName = (value) => value.toLocaleUpperCase("pt-BR");

export default function ProductEdit({ auth, product, typeOptions = [], statusOptions = [], originOptions = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        tb1_id: product.tb1_id ?? "",
        tb1_nome: product.tb1_nome ?? "",
        tb1_vlr_custo: product.tb1_vlr_custo ?? "",
        tb1_vlr_venda: product.tb1_vlr_venda ?? "",
        tb1_codbar: product.tb1_codbar ?? "",
        sem_codigo_barras:
            Number(product.tb1_tipo) === 1 ||
            String(product.tb1_codbar ?? "").trim() === String(product.tb1_id ?? "").trim(),
        tb1_tipo: product.tb1_tipo ?? typeOptions[0]?.value ?? 0,
        tb1_ncm: product.tb1_ncm ?? "",
        tb1_cest: product.tb1_cest ?? "",
        tb1_cfop: product.tb1_cfop ?? "",
        tb1_unidade_comercial: product.tb1_unidade_comercial ?? "UN",
        tb1_unidade_tributavel: product.tb1_unidade_tributavel ?? "UN",
        tb1_origem: product.tb1_origem ?? originOptions[0]?.value ?? 0,
        tb1_csosn: product.tb1_csosn ?? "",
        tb1_cst: product.tb1_cst ?? "",
        tb1_aliquota_icms: product.tb1_aliquota_icms ?? "0.00",
        tb1_qtd: product.tb1_qtd ?? 0,
        tb1_status: product.tb1_status ?? statusOptions[0]?.value ?? 1,
        tb1_vr_credit: Boolean(product.tb1_vr_credit),
    });

    const isBalanceProduct = Number(data.tb1_tipo) === 1;
    const isProductionProduct = Number(data.tb1_tipo) === 3;
    const canEditPrices = [0, 1, 2].includes(Number(auth?.user?.funcao ?? -1));
    const withoutBarcode = isBalanceProduct || Boolean(data.sem_codigo_barras);

    useEffect(() => {
        if (isBalanceProduct && !data.sem_codigo_barras) {
            setData("sem_codigo_barras", true);
        }
    }, [isBalanceProduct, data.sem_codigo_barras, setData]);

    const handleSubmit = (event) => {
        event.preventDefault();
        put(route("products.update", { product: data.tb1_id }));
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
                        <h3 className="text-lg">Editar</h3>
                        <div className="flex space-x-4">
                            <Link href={route("products.production-stock", { product_id: data.tb1_id })}>
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
                                        disabled={!canEditPrices}
                                        className={`mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm ${
                                            canEditPrices
                                                ? "focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                : "bg-gray-100 text-gray-500 cursor-not-allowed"
                                        }`}
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
                                        disabled={!canEditPrices}
                                        className={`mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm ${
                                            canEditPrices
                                                ? "focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                : "bg-gray-100 text-gray-500 cursor-not-allowed"
                                        }`}
                                    />
                                    {errors.tb1_vlr_venda && <span className="text-red-600">{errors.tb1_vlr_venda}</span>}
                                </div>
                            </div>

                            {!canEditPrices && (
                                <p className="mb-4 text-xs text-amber-800">
                                    Apenas Master, Gerente e Sub-Gerente podem alterar os valores de custo e venda.
                                </p>
                            )}

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
                                            Quantidade atual
                                        </label>
                                        <input
                                            id="tb1_qtd"
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={data.tb1_qtd}
                                            onChange={(e) => setData("tb1_qtd", e.target.value)}
                                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">
                                            Para novas entradas e saidas, utilize a tela de estoque de Producao.
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
                                                ? "Produtos de balanca sempre usam o proprio ID no campo Codbarras."
                                                : "Quando marcado, o sistema grava o proprio ID do produto no campo Codbarras."}
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
                                        value={data.tb1_id}
                                        readOnly
                                        className="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 shadow-sm sm:text-sm"
                                    />
                                    <p className="mt-2 text-xs text-amber-800">
                                        Produto de balanca nao usa codigo de barras e o ID nao pode ser alterado.
                                        O campo COdbarras permanece igual ao proprio ID.
                                    </p>
                                    {errors.tb1_id && <span className="text-red-600">{errors.tb1_id}</span>}
                                </div>
                            ) : withoutBarcode ? (
                                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 p-4">
                                    <p className="text-sm font-medium text-gray-700">Codigo de barras</p>
                                    <p className="mt-2 text-xs text-amber-800">
                                        Este produto sera mantido sem codigo de barras proprio. Ao salvar, o sistema
                                        gravara o valor do tb1_id no campo tb1_codbar.
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

                            <div className="mb-6 rounded-md border border-gray-200 bg-white p-4">
                                <div className="mb-4">
                                    <h4 className="text-sm font-semibold text-gray-800">Cadastro fiscal</h4>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Esses campos alimentam a preparacao da NF-e/NFC-e.
                                    </p>
                                    <p className="mt-1 text-xs font-medium text-amber-700">
                                        Todos os campos fiscais sao opcionais na edicao. Preencha quando quiser deixar o produto pronto para emissao fiscal.
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label htmlFor="tb1_ncm" className="block text-sm font-medium text-gray-700">NCM</label>
                                        <input id="tb1_ncm" type="text" maxLength="8" value={data.tb1_ncm} onChange={(e) => setData("tb1_ncm", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_ncm && <span className="text-red-600">{errors.tb1_ncm}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_cest" className="block text-sm font-medium text-gray-700">CEST</label>
                                        <input id="tb1_cest" type="text" maxLength="7" value={data.tb1_cest} onChange={(e) => setData("tb1_cest", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_cest && <span className="text-red-600">{errors.tb1_cest}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_cfop" className="block text-sm font-medium text-gray-700">CFOP</label>
                                        <input id="tb1_cfop" type="text" maxLength="4" value={data.tb1_cfop} onChange={(e) => setData("tb1_cfop", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_cfop && <span className="text-red-600">{errors.tb1_cfop}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_origem" className="block text-sm font-medium text-gray-700">Origem</label>
                                        <select id="tb1_origem" value={data.tb1_origem} onChange={(e) => setData("tb1_origem", Number(e.target.value))} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            {originOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                        {errors.tb1_origem && <span className="text-red-600">{errors.tb1_origem}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_unidade_comercial" className="block text-sm font-medium text-gray-700">Unidade comercial</label>
                                        <input id="tb1_unidade_comercial" type="text" maxLength="6" value={data.tb1_unidade_comercial} onChange={(e) => setData("tb1_unidade_comercial", e.target.value.toUpperCase())} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_unidade_comercial && <span className="text-red-600">{errors.tb1_unidade_comercial}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_unidade_tributavel" className="block text-sm font-medium text-gray-700">Unidade tributavel</label>
                                        <input id="tb1_unidade_tributavel" type="text" maxLength="6" value={data.tb1_unidade_tributavel} onChange={(e) => setData("tb1_unidade_tributavel", e.target.value.toUpperCase())} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_unidade_tributavel && <span className="text-red-600">{errors.tb1_unidade_tributavel}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_csosn" className="block text-sm font-medium text-gray-700">CSOSN</label>
                                        <input id="tb1_csosn" type="text" maxLength="4" value={data.tb1_csosn} onChange={(e) => setData("tb1_csosn", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_csosn && <span className="text-red-600">{errors.tb1_csosn}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_cst" className="block text-sm font-medium text-gray-700">CST</label>
                                        <input id="tb1_cst" type="text" maxLength="3" value={data.tb1_cst} onChange={(e) => setData("tb1_cst", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_cst && <span className="text-red-600">{errors.tb1_cst}</span>}
                                    </div>
                                    <div>
                                        <label htmlFor="tb1_aliquota_icms" className="block text-sm font-medium text-gray-700">Aliquota ICMS</label>
                                        <input id="tb1_aliquota_icms" type="number" step="0.01" min="0" max="100" value={data.tb1_aliquota_icms} onChange={(e) => setData("tb1_aliquota_icms", e.target.value)} className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm" />
                                        {errors.tb1_aliquota_icms && <span className="text-red-600">{errors.tb1_aliquota_icms}</span>}
                                    </div>
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
    );
}
