import AlertMessage from "@/Components/Alert/AlertMessage";
import SuccessButton from "@/Components/Button/SuccessButton";
import InfoButton from "@/Components/Button/InfoButton";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";

const TYPE_LABELS = {
    0: "Industria",
    1: "Balanca",
    2: "Servico",
    3: "Producao",
};

const FISCAL_FIELDS = [
    { key: "tb1_ncm", label: "NCM" },
    { key: "tb1_cfop", label: "CFOP" },
    { key: "tb1_csosn", label: "CSOSN" },
    { key: "tb1_cst", label: "CST" },
    { key: "tb1_cst_ibscbs", label: "CST IBS/CBS" },
    { key: "tb1_cclasstrib", label: "cClassTrib" },
    { key: "tb1_aliquota_ibs_uf", label: "Aliquota IBS UF" },
    { key: "tb1_aliquota_ibs_mun", label: "Aliquota IBS Municipio" },
    { key: "tb1_aliquota_cbs", label: "Aliquota CBS" },
    { key: "tb1_aliquota_is", label: "Aliquota IS" },
];

const isBlankFiscalValue = (value) =>
    value === null ||
    value === undefined ||
    (typeof value === "string" && value.trim() === "");

const formatRate = (value) => {
    if (isBlankFiscalValue(value)) {
        return "--";
    }

    return Number(value).toLocaleString("pt-BR", {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
};

const firstErrorMessage = (errors = {}) => {
    const entries = Object.values(errors);
    const firstEntry = entries.find((entry) => Array.isArray(entry) ? entry.length > 0 : Boolean(entry));

    if (Array.isArray(firstEntry)) {
        return firstEntry[0] ?? "";
    }

    return firstEntry ?? "";
};

export default function ProductFiscalQueue({
    auth,
    products = [],
    references = [],
    pendingCount = 0,
    selectedType = null,
    search = "",
    typeOptions = [],
}) {
    const { flash } = usePage().props;
    const [selectedProducts, setSelectedProducts] = useState([]);
    const [selectedReferenceId, setSelectedReferenceId] = useState(null);
    const [applyingReferenceId, setApplyingReferenceId] = useState(null);
    const [localError, setLocalError] = useState("");
    const [searchTerm, setSearchTerm] = useState(search ?? "");
    const [activeType, setActiveType] = useState(
        selectedType === null || selectedType === undefined ? "" : String(selectedType)
    );
    const initialSearchHandled = useRef(false);

    useEffect(() => {
        setSelectedProducts([]);
        setSelectedReferenceId(null);
        setLocalError("");
        setSearchTerm(search ?? "");
        setActiveType(selectedType === null || selectedType === undefined ? "" : String(selectedType));
    }, [products, references, search, selectedType]);

    const buildQueueQuery = (typeValue, termValue) => {
        const query = {};

        if (typeValue !== "" && typeValue !== null && typeValue !== undefined) {
            query.type = typeValue;
        }

        if (termValue !== "") {
            query.search = termValue;
        }

        return query;
    };

    useEffect(() => {
        const handler = setTimeout(() => {
            const trimmedTerm = searchTerm.trim();

            if (initialSearchHandled.current === false) {
                initialSearchHandled.current = true;
                if ((search ?? "") === trimmedTerm) {
                    return;
                }
            }

            router.get(
                route("products.fiscal-queue"),
                buildQueueQuery(activeType, trimmedTerm),
                { preserveState: true, replace: true }
            );
        }, 400);

        return () => clearTimeout(handler);
    }, [activeType, search, searchTerm]);

    const productsWithMissingFields = useMemo(
        () =>
            products.map((product) => ({
                ...product,
                missingFields: FISCAL_FIELDS.filter(({ key }) => isBlankFiscalValue(product[key])),
            })),
        [products]
    );

    const visibleProductIds = useMemo(
        () => productsWithMissingFields.map((product) => Number(product.tb1_id)),
        [productsWithMissingFields]
    );

    const referenceMap = useMemo(() => {
        const mapped = {};

        references.forEach((reference) => {
            mapped[Number(reference.tb29_id)] = reference;
        });

        return mapped;
    }, [references]);

    const selectedCount = selectedProducts.length;
    const allVisibleSelected =
        visibleProductIds.length > 0 &&
        visibleProductIds.every((productId) => selectedProducts.includes(productId));

    const toggleProduct = (productId) => {
        const normalizedId = Number(productId);
        setLocalError("");
        setSelectedProducts((current) =>
            current.includes(normalizedId)
                ? current.filter((item) => item !== normalizedId)
                : current.concat(normalizedId)
        );
    };

    const toggleSelectAll = () => {
        setLocalError("");

        if (allVisibleSelected) {
            setSelectedProducts((current) => current.filter((productId) => !visibleProductIds.includes(productId)));
            return;
        }

        setSelectedProducts((current) => {
            const merged = new Set(current);
            visibleProductIds.forEach((productId) => merged.add(productId));
            return Array.from(merged);
        });
    };

    const handleTypeFilter = (typeValue) => {
        setLocalError("");
        setActiveType(typeValue);
    };

    const submitReferenceApplication = (referenceId) => {
        if (!referenceId) {
            setLocalError("Selecione uma referencia fiscal.");
            return;
        }

        if (selectedProducts.length === 0) {
            setLocalError("Marque pelo menos um produto antes de aplicar a referencia fiscal.");
            return;
        }

        setApplyingReferenceId(referenceId);
        setLocalError("");

        router.post(
            route("products.fiscal-queue.apply-reference"),
            {
                reference_id: referenceId,
                product_ids: selectedProducts,
                ...buildQueueQuery(activeType, searchTerm.trim()),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedProducts([]);
                    setSelectedReferenceId(null);
                    setLocalError("");
                },
                onError: (errors) => {
                    setLocalError(firstErrorMessage(errors) || "Nao foi possivel aplicar a referencia fiscal.");
                },
                onFinish: () => {
                    setApplyingReferenceId(null);
                },
            }
        );
    };

    const handleReferenceSelection = (referenceId) => {
        const normalizedId = Number(referenceId);
        setSelectedReferenceId(normalizedId);
        submitReferenceApplication(normalizedId);
    };

    const selectedReference = selectedReferenceId ? referenceMap[selectedReferenceId] ?? null : null;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Vinculo Fiscal
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Marque os produtos pendentes e escolha uma referencia fiscal para aplicar em lote.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route("products.fiscal-references.index")}>
                            <SuccessButton aria-label="Cadastrar referencias fiscais" title="Cadastrar referencias fiscais">
                                <i className="bi bi-journal-plus text-lg" aria-hidden="true"></i>
                            </SuccessButton>
                        </Link>
                        <Link href={route("products.index")}>
                            <PrimaryButton aria-label="Voltar para produtos" title="Voltar para produtos">
                                <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                            </PrimaryButton>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Vinculo Fiscal" />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <AlertMessage message={flash} />

                    <div className="px-4 py-4 space-y-4">
                        <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div className="flex flex-col gap-3">
                                    <div className="flex flex-wrap gap-2">
                                        <InfoButton
                                            type="button"
                                            onClick={() => handleTypeFilter("")}
                                            aria-label="Mostrar todos os tipos"
                                            title="Mostrar todos os tipos"
                                            className={activeType === "" ? "ring-2 ring-offset-2 ring-indigo-300" : ""}
                                        >
                                            Todos
                                        </InfoButton>
                                        {typeOptions.map((option) => {
                                            const value = String(option.value);

                                            return (
                                                <InfoButton
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() => handleTypeFilter(value)}
                                                    aria-label={`Filtrar por ${option.label}`}
                                                    title={`Filtrar por ${option.label}`}
                                                    className={activeType === value ? "ring-2 ring-offset-2 ring-indigo-300" : ""}
                                                >
                                                    {option.label}
                                                </InfoButton>
                                            );
                                        })}
                                    </div>

                                    <input
                                        type="text"
                                        value={searchTerm}
                                        onChange={(event) => setSearchTerm(event.target.value)}
                                        placeholder="Buscar por nome, ID ou codigo de barras"
                                        className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 xl:min-w-[340px] dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                    />
                                </div>

                                <div className="grid gap-2 text-sm text-gray-600 dark:text-gray-300 xl:text-right">
                                    <p>Produtos pendentes: <span className="font-semibold">{pendingCount}</span></p>
                                    <p>Produtos visiveis: <span className="font-semibold">{productsWithMissingFields.length}</span></p>
                                    <p>Produtos marcados: <span className="font-semibold">{selectedCount}</span></p>
                                    <p>Referencias disponiveis: <span className="font-semibold">{references.length}</span></p>
                                </div>
                            </div>
                        </div>

                        {localError && (
                            <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {localError}
                            </div>
                        )}

                        <div className="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
                            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/20">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                            Top 30 produtos pendentes
                                        </h3>
                                        <p className="text-sm text-gray-500 dark:text-gray-300">
                                            Cada item abaixo ainda nao possui todas as referencias fiscais preenchidas.
                                        </p>
                                    </div>
                                    <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        <input
                                            type="checkbox"
                                            checked={allVisibleSelected}
                                            onChange={toggleSelectAll}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Marcar todos
                                    </label>
                                </div>

                                {productsWithMissingFields.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                        Nao existem produtos pendentes para os filtros atuais.
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {productsWithMissingFields.map((product) => {
                                            const isChecked = selectedProducts.includes(Number(product.tb1_id));

                                            return (
                                                <label
                                                    key={product.tb1_id}
                                                    className={`block cursor-pointer rounded-2xl border p-4 transition ${
                                                        isChecked
                                                            ? "border-indigo-500 bg-indigo-50 shadow-sm"
                                                            : "border-gray-200 bg-white hover:border-indigo-300"
                                                    }`}
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => toggleProduct(product.tb1_id)}
                                                            className="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-semibold text-gray-900">
                                                                        {product.tb1_nome}
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        ID {product.tb1_id} | {TYPE_LABELS[product.tb1_tipo] ?? "---"} | {product.tb1_codbar || "--"}
                                                                    </p>
                                                                </div>
                                                                <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                                                    {product.missingFields.length} campo(s) pendente(s)
                                                                </span>
                                                            </div>

                                                            <div className="mt-3 flex flex-wrap gap-2">
                                                                {product.missingFields.map((field) => (
                                                                    <span
                                                                        key={`${product.tb1_id}-${field.key}`}
                                                                        className="rounded-full bg-rose-100 px-3 py-1 text-xs font-medium text-rose-700"
                                                                    >
                                                                        {field.label}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                            <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/20">
                                <div className="mb-4">
                                    <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                        Referencias fiscais disponiveis
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Clique no radio da referencia desejada para preencher os produtos marcados.
                                    </p>
                                    {selectedReference && selectedProducts.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => submitReferenceApplication(selectedReference.tb29_id)}
                                            disabled={applyingReferenceId !== null}
                                            className="mt-3 rounded-xl border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            Aplicar referencia selecionada novamente
                                        </button>
                                    )}
                                </div>

                                {references.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                        Nenhuma referencia fiscal cadastrada em tb29_referencias_fiscais.
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {references.map((reference) => {
                                            const referenceId = Number(reference.tb29_id);
                                            const isSelected = selectedReferenceId === referenceId;
                                            const isApplying = applyingReferenceId === referenceId;

                                            return (
                                                <label
                                                    key={reference.tb29_id}
                                                    className={`block cursor-pointer rounded-2xl border p-4 transition ${
                                                        isSelected
                                                            ? "border-emerald-500 bg-emerald-50 shadow-sm"
                                                            : "border-gray-200 bg-white hover:border-emerald-300"
                                                    }`}
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <input
                                                            type="radio"
                                                            name="fiscal-reference"
                                                            checked={isSelected}
                                                            onChange={() => handleReferenceSelection(referenceId)}
                                                            disabled={applyingReferenceId !== null}
                                                            className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                                <div>
                                                                    <p className="text-sm font-semibold text-gray-900">
                                                                        {reference.tb29_descricao}
                                                                    </p>
                                                                    <p className="text-xs text-gray-500">
                                                                        Ref. #{reference.tb29_id}
                                                                    </p>
                                                                </div>
                                                                {isApplying && (
                                                                    <span className="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                                                        Aplicando...
                                                                    </span>
                                                                )}
                                                            </div>

                                                            <div className="mt-3 grid gap-2 text-xs text-gray-700 sm:grid-cols-2">
                                                                <p><span className="font-semibold">NCM:</span> {reference.tb29_ncm}</p>
                                                                <p><span className="font-semibold">CFOP:</span> {reference.tb29_cfop}</p>
                                                                <p><span className="font-semibold">CSOSN:</span> {reference.tb29_csosn}</p>
                                                                <p><span className="font-semibold">CST:</span> {reference.tb29_cst}</p>
                                                                <p><span className="font-semibold">CST IBS/CBS:</span> {reference.tb29_cst_ibscbs}</p>
                                                                <p><span className="font-semibold">cClassTrib:</span> {reference.tb29_cclasstrib}</p>
                                                                <p><span className="font-semibold">IBS UF:</span> {formatRate(reference.tb29_aliquota_ibs_uf)}</p>
                                                                <p><span className="font-semibold">IBS Municipio:</span> {formatRate(reference.tb29_aliquota_ibs_mun)}</p>
                                                                <p><span className="font-semibold">CBS:</span> {formatRate(reference.tb29_aliquota_cbs)}</p>
                                                                <p><span className="font-semibold">IS:</span> {formatRate(reference.tb29_aliquota_is)}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
