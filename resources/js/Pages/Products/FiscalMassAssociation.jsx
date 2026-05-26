import AlertMessage from "@/Components/Alert/AlertMessage";
import InfoButton from "@/Components/Button/InfoButton";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import Modal from "@/Components/Modal";
import Pagination from "@/Components/Pagination";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";

export default function FiscalMassAssociation({ auth, products, categories = [], filters = {}, typeOptions = [], originOptions = [], fiscalSummary = {} }) {
    const { flash } = usePage().props;
    const [selectedProducts, setSelectedProducts] = useState([]);
    const [filterState, setFilterState] = useState(filters);
    const [editingProduct, setEditingProduct] = useState(null);
    const { data, setData, post, processing, errors } = useForm({
        product_ids: [],
        category_id: "",
    });
    const renameForm = useForm({
        tb1_nome: "",
        filters: {},
    });

    useEffect(() => {
        setData("product_ids", selectedProducts);
    }, [selectedProducts]);

    const visibleIds = useMemo(() => products.data.map((product) => Number(product.tb1_id)), [products.data]);
    const allSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedProducts.includes(id));
    const selectedCategory = categories.find((category) => Number(category.tb30_id) === Number(data.category_id));
    const selectedCategoryInactive = selectedCategory && !Boolean(selectedCategory.tb30_ativo);
    const summary = useMemo(() => ({
        total: Number(fiscalSummary.total ?? 0),
        linked: Number(fiscalSummary.linked ?? 0),
        pending: Number(fiscalSummary.pending ?? 0),
        linkedPercent: Number(fiscalSummary.linked_percent ?? 0),
        pendingPercent: Number(fiscalSummary.pending_percent ?? 0),
    }), [fiscalSummary]);
    const linkedChartPercent = Math.max(0, Math.min(summary.linkedPercent, 100));
    const chartStyle = {
        background: summary.total > 0
            ? `conic-gradient(#22c55e 0 ${linkedChartPercent}%, #ef4444 ${linkedChartPercent}% 100%)`
            : "#e5e7eb",
    };

    const applyFilters = () => {
        router.get(route("products.fiscal-mass-association.index"), filterState, {
            preserveState: true,
            replace: true,
        });
    };

    const toggleProduct = (productId) => {
        const id = Number(productId);
        setSelectedProducts((current) => current.includes(id)
            ? current.filter((item) => item !== id)
            : current.concat(id));
    };

    const toggleAll = () => {
        if (allSelected) {
            setSelectedProducts((current) => current.filter((id) => !visibleIds.includes(id)));
            return;
        }

        setSelectedProducts((current) => Array.from(new Set(current.concat(visibleIds))));
    };

    const openRenameModal = (product) => {
        setEditingProduct(product);
        renameForm.clearErrors();
        renameForm.setData({
            tb1_nome: product.tb1_nome ?? "",
            filters,
        });
    };

    const closeRenameModal = () => {
        if (renameForm.processing) {
            return;
        }

        setEditingProduct(null);
        renameForm.reset();
        renameForm.clearErrors();
    };

    const submit = (event) => {
        event.preventDefault();

        if (selectedCategoryInactive) {
            window.alert("A categoria fiscal selecionada esta inativa. Ative e revise a categoria antes de aplicar em massa.");
            return;
        }

        if (!window.confirm(`Aplicar categoria em ${selectedProducts.length} produto(s)?`)) {
            return;
        }

        post(route("products.fiscal-mass-association.apply"), {
            preserveScroll: true,
            onSuccess: () => setSelectedProducts([]),
        });
    };

    const submitRename = (event) => {
        event.preventDefault();

        if (!editingProduct) {
            return;
        }

        renameForm.patch(route("products.fiscal-mass-association.rename-product", editingProduct.tb1_id), {
            preserveScroll: true,
            onSuccess: closeRenameModal,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Associacao Fiscal em Massa
                    </h2>
                    <Link href={route("products.index")}>
                        <PrimaryButton aria-label="Voltar" title="Voltar">
                            <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Associacao Fiscal em Massa" />
            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <AlertMessage message={flash} />
                <div className="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(280px,420px)]">
                        <div className="space-y-3">
                            <div className="grid gap-3 md:grid-cols-2">
                                <input value={filterState.search ?? ""} onChange={(e) => setFilterState({ ...filterState, search: e.target.value })} placeholder="Descricao, ID ou codigo" className="rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                <select value={filterState.type ?? ""} onChange={(e) => setFilterState({ ...filterState, type: e.target.value })} className="rounded-md border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Tipo</option>
                                    {typeOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                                <select value={filterState.origin ?? ""} onChange={(e) => setFilterState({ ...filterState, origin: e.target.value })} className="rounded-md border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Origem</option>
                                    {originOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                                <select value={filterState.category_id ?? ""} onChange={(e) => setFilterState({ ...filterState, category_id: e.target.value })} className="rounded-md border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Categoria atual</option>
                                    {categories.map((category) => <option key={category.tb30_id} value={category.tb30_id}>{category.tb30_nome}</option>)}
                                </select>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-sm text-gray-700">
                                <InfoButton type="button" onClick={applyFilters} aria-label="Aplicar filtros" title="Aplicar filtros" className="px-3 py-2">
                                    <i className="bi bi-search text-lg" aria-hidden="true"></i>
                                </InfoButton>
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" checked={Boolean(filterState.without_category)} onChange={(e) => setFilterState({ ...filterState, without_category: e.target.checked ? 1 : 0 })} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                    Produtos sem categoria
                                </label>
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" checked={Boolean(filterState.only_exception)} onChange={(e) => setFilterState({ ...filterState, only_exception: e.target.checked ? 1 : 0 })} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                    Somente com excecao fiscal
                                </label>
                            </div>
                        </div>

                        <div className="grid gap-4 bg-gray-50 p-4 md:grid-cols-[150px_1fr]">
                            <div className="mx-auto flex h-[150px] w-[150px] items-center justify-center rounded-full" style={chartStyle} aria-label={`Grafico fiscal: ${summary.linkedPercent}% vinculados e ${summary.pendingPercent}% pendentes`}>
                                <div className="flex h-[76px] w-[76px] items-center justify-center rounded-full bg-white text-center text-xs font-semibold text-gray-700">
                                    {summary.total}
                                    <br />
                                    produtos
                                </div>
                            </div>
                            <div className="flex flex-col justify-center gap-3 text-sm">
                                <div>
                                    <p className="text-xs font-semibold uppercase text-gray-500">Resumo fiscal</p>
                                    <p className="mt-1 text-2xl font-semibold text-gray-900">{summary.total}</p>
                                    <p className="text-xs text-gray-500">produto(s) no filtro atual</p>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="flex items-center gap-2 text-gray-700"><span className="h-3 w-3 rounded-sm bg-green-500"></span>Com vinculo fiscal</span>
                                        <span className="font-semibold text-green-700">{summary.linkedPercent}% | {summary.linked}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="flex items-center gap-2 text-gray-700"><span className="h-3 w-3 rounded-sm bg-red-500"></span>Pendentes</span>
                                        <span className="font-semibold text-red-700">{summary.pendingPercent}% | {summary.pending}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <select value={data.category_id} onChange={(e) => setData("category_id", e.target.value)} className="rounded-md border border-gray-300 px-3 py-2 text-sm">
                                <option value="">Nova categoria fiscal</option>
                                {categories.map((category) => (
                                    <option key={category.tb30_id} value={category.tb30_id} disabled={!category.tb30_ativo}>
                                        {category.tb30_nome}{category.tb30_ativo ? "" : " (inativa)"}
                                    </option>
                                ))}
                            </select>
                            <SuccessButton type="submit" disabled={processing || selectedProducts.length === 0 || !data.category_id || selectedCategoryInactive} aria-label="Aplicar" title="Aplicar">
                                <i className="bi bi-check2 text-lg" aria-hidden="true"></i>
                            </SuccessButton>
                        </div>
                        {errors.category_id && <p className="text-sm text-red-600">{errors.category_id}</p>}
                        {errors.product_ids && <p className="text-sm text-red-600">{errors.product_ids}</p>}

                        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            Previa: {selectedProducts.length} produto(s) selecionado(s)
                            {selectedCategory ? ` para ${selectedCategory.tb30_nome}.` : "."}
                            {selectedCategoryInactive && (
                                <span className="mt-1 block font-semibold">
                                    Categoria inativa nao pode ser aplicada em massa.
                                </span>
                            )}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-xs uppercase text-gray-500">
                                    <tr>
                                        <th className="px-3 py-2">
                                            <input type="checkbox" checked={allSelected} onChange={toggleAll} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                        </th>
                                        <th className="px-3 py-2">Produto</th>
                                        <th className="px-3 py-2">Categoria atual</th>
                                        <th className="px-3 py-2">Excecao</th>
                                        <th className="px-3 py-2 text-right">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {products.data.map((product) => (
                                        <tr key={product.tb1_id} className={product.tb30_categoria_fiscal_id ? "" : "bg-amber-50"}>
                                            <td className="px-3 py-2">
                                                <input type="checkbox" checked={selectedProducts.includes(Number(product.tb1_id))} onChange={() => toggleProduct(product.tb1_id)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                            </td>
                                            <td className="px-3 py-2">
                                                <p className="font-semibold">{product.tb1_nome}</p>
                                                <p className="text-xs text-gray-500">ID {product.tb1_id} | {product.tb1_codbar || "--"}</p>
                                            </td>
                                            <td className="px-3 py-2">
                                                {product.categoria_fiscal?.tb30_nome ?? "Sem categoria"}
                                                {product.categoria_fiscal && !product.categoria_fiscal.tb30_ativo && (
                                                    <span className="ml-2 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                                        inativa
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">{product.tb1_usa_excecao_fiscal ? "Sim" : "Nao"}</td>
                                            <td className="px-3 py-2 text-right">
                                                <InfoButton type="button" onClick={() => openRenameModal(product)} aria-label={`Editar nome de ${product.tb1_nome}`} title="Editar nome">
                                                    <i className="bi bi-pencil-square text-lg" aria-hidden="true"></i>
                                                </InfoButton>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <Pagination links={products.links} currentPage={products.current_page} />
                </div>
            </div>

            <Modal show={Boolean(editingProduct)} onClose={closeRenameModal} maxWidth="lg" tone="light">
                <form onSubmit={submitRename} className="space-y-5 p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Editar nome do produto</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                ID {editingProduct?.tb1_id ?? "--"} | {editingProduct?.tb1_codbar || "--"}
                            </p>
                        </div>
                        <button type="button" onClick={closeRenameModal} disabled={renameForm.processing} className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 disabled:opacity-40" aria-label="Fechar" title="Fechar">
                            <i className="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div>
                        <label htmlFor="rename_product_name" className="mb-1 block text-sm font-medium text-gray-700">
                            Nome
                        </label>
                        <input
                            id="rename_product_name"
                            type="text"
                            value={renameForm.data.tb1_nome}
                            onChange={(event) => renameForm.setData("tb1_nome", event.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            maxLength={45}
                            autoFocus
                        />
                        {renameForm.errors.tb1_nome && (
                            <p className="mt-1 text-sm text-red-600">{renameForm.errors.tb1_nome}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeRenameModal} disabled={renameForm.processing} className="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-40">
                            Cancelar
                        </button>
                        <PrimaryButton type="submit" disabled={renameForm.processing || !renameForm.data.tb1_nome.trim()}>
                            <i className="bi bi-check2 mr-2 text-lg" aria-hidden="true"></i>
                            Salvar
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
