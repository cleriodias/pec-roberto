import AlertMessage from "@/Components/Alert/AlertMessage";
import InfoButton from "@/Components/Button/InfoButton";
import PrimaryButton from "@/Components/Button/PrimaryButton";
import SuccessButton from "@/Components/Button/SuccessButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";

const TYPE_LABELS = {
    0: "Industria",
    1: "Balanca",
    2: "Servico",
    3: "Producao",
};

const buildRows = (items = []) =>
    items.map((item) => ({
        ...item,
        tb1_ncm: item.tb1_ncm ?? "",
        tb1_cfop: item.tb1_cfop ?? "",
        tb1_csosn: item.tb1_csosn ?? "",
        tb1_cst: item.tb1_cst ?? "",
    }));

export default function ProductFiscalQueue({
    auth,
    items = [],
    pendingCount = 0,
    selectedType = null,
    search = "",
    typeOptions = [],
}) {
    const { flash } = usePage().props;
    const [rows, setRows] = useState(buildRows(items));
    const [remainingCount, setRemainingCount] = useState(Number(pendingCount ?? 0));
    const [loadingNextBatch, setLoadingNextBatch] = useState(false);
    const [savingId, setSavingId] = useState(null);
    const [feedback, setFeedback] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [rowErrors, setRowErrors] = useState({});
    const [searchTerm, setSearchTerm] = useState(search ?? "");
    const [activeType, setActiveType] = useState(
        selectedType === null || selectedType === undefined ? "" : String(selectedType)
    );
    const initialSearchHandled = useRef(false);

    useEffect(() => {
        setRows(buildRows(items));
        setRemainingCount(Number(pendingCount ?? 0));
        setActiveType(selectedType === null || selectedType === undefined ? "" : String(selectedType));
        setSearchTerm(search ?? "");
    }, [items, pendingCount, search, selectedType]);

    const visibleCount = rows.length;
    const completedBatch = useMemo(() => visibleCount === 0 && !loadingNextBatch, [visibleCount, loadingNextBatch]);

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

    const handleFieldChange = (productId, field, value) => {
        setRows((current) =>
            current.map((row) =>
                row.tb1_id === productId
                    ? { ...row, [field]: value }
                    : row
            )
        );

        setRowErrors((current) => {
            if (!current[productId]?.[field]) {
                return current;
            }

            return {
                ...current,
                [productId]: {
                    ...current[productId],
                    [field]: null,
                },
            };
        });
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

            if (trimmedTerm !== "" && trimmedTerm.length < 4) {
                return;
            }

            setFeedback("");
            setErrorMessage("");
            setRowErrors({});

            router.get(
                route("products.fiscal-queue"),
                buildQueueQuery(activeType, trimmedTerm),
                { preserveState: true, replace: true }
            );
        }, 400);

        return () => clearTimeout(handler);
    }, [activeType, search, searchTerm]);

    const loadNextBatch = async (typeValue = activeType, termValue = searchTerm.trim()) => {
        setLoadingNextBatch(true);
        setErrorMessage("");

        try {
            const response = await axios.get(route("products.fiscal-queue.items"), {
                params: buildQueueQuery(typeValue, termValue),
            });

            setRows(buildRows(response.data.items ?? []));
            setRemainingCount(Number(response.data.pendingCount ?? 0));
        } catch (error) {
            setErrorMessage("Nao foi possivel carregar a proxima lista de produtos pendentes.");
        } finally {
            setLoadingNextBatch(false);
        }
    };

    const handleTypeFilter = (typeValue) => {
        setFeedback("");
        setErrorMessage("");
        setRowErrors({});
        setActiveType(typeValue);
    };

    const handleSave = async (row) => {
        setSavingId(row.tb1_id);
        setFeedback("");
        setErrorMessage("");
        setRowErrors((current) => ({ ...current, [row.tb1_id]: {} }));

        try {
            await axios.patch(route("products.fiscal-queue.update", { product: row.tb1_id }), {
                tb1_ncm: row.tb1_ncm,
                tb1_cfop: row.tb1_cfop,
                tb1_csosn: row.tb1_csosn,
                tb1_cst: row.tb1_cst,
            });

            const nextRemaining = Math.max(remainingCount - 1, 0);
            const nextRows = rows.filter((item) => item.tb1_id !== row.tb1_id);

            setRows(nextRows);
            setRemainingCount(nextRemaining);
            setFeedback(`Produto ${row.tb1_id} atualizado com sucesso.`);

            if (nextRows.length === 0 && nextRemaining > 0) {
                await loadNextBatch(activeType, searchTerm.trim());
            }
        } catch (error) {
            if (error.response?.data?.errors) {
                const errors = error.response.data.errors;
                setRowErrors((current) => ({
                    ...current,
                    [row.tb1_id]: {
                        tb1_ncm: errors.tb1_ncm?.[0] ?? null,
                        tb1_cfop: errors.tb1_cfop?.[0] ?? null,
                        tb1_csosn: errors.tb1_csosn?.[0] ?? null,
                        tb1_cst: errors.tb1_cst?.[0] ?? null,
                    },
                }));
            } else {
                setErrorMessage("Nao foi possivel gravar os dados fiscais deste produto.");
            }
        } finally {
            setSavingId(null);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Fila Fiscal
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                            Atualize os dados fiscais em lote sem recarregar a tela.
                        </p>
                    </div>
                    <Link href={route("products.index")}>
                        <PrimaryButton aria-label="Voltar para produtos" title="Voltar para produtos">
                            <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Fila Fiscal" />

            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="overflow-hidden bg-white shadow-lg sm:rounded-lg dark:bg-gray-800">
                    <AlertMessage message={flash} />

                    <div className="px-4 py-4 space-y-4">
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
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
                                                aria-label={`Filtrar fila fiscal por ${option.label}`}
                                                title={`Filtrar fila fiscal por ${option.label}`}
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
                                    className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 xl:max-w-md dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Lista atual: {visibleCount} item(ns)
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Pendentes na fila: {remainingCount}
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Tipo selecionado: {activeType === "" ? "Todos" : TYPE_LABELS[activeType] ?? "Todos"}
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-300">
                                        Busca: {searchTerm.trim() === "" ? "Sem filtro" : searchTerm.trim()}
                                    </p>
                                </div>
                                {completedBatch && remainingCount > 0 && (
                                    <SuccessButton
                                        type="button"
                                        onClick={() => loadNextBatch(activeType, searchTerm.trim())}
                                        disabled={loadingNextBatch}
                                        aria-label="Carregar proxima lista"
                                        title="Carregar proxima lista"
                                    >
                                        <i className="bi bi-arrow-repeat text-lg" aria-hidden="true"></i>
                                    </SuccessButton>
                                )}
                            </div>
                        </div>

                        {feedback && (
                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                {feedback}
                            </div>
                        )}

                        {errorMessage && (
                            <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                {errorMessage}
                            </div>
                        )}

                        {rows.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                {remainingCount > 0
                                    ? "Os 20 itens atuais foram concluidos. Carregue a proxima lista."
                                    : "Nao existem mais produtos pendentes de cadastro fiscal nesta fila."}
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">Produto</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">NCM</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">CFOP</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">CSOSN</th>
                                            <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">CST</th>
                                            <th className="px-4 py-3 text-center text-sm font-medium text-gray-500">Gravar</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                        {rows.map((row) => {
                                            const errors = rowErrors[row.tb1_id] ?? {};

                                            return (
                                                <tr key={row.tb1_id}>
                                                    <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                                        <div className="flex flex-col">
                                                            <span className="font-medium">{row.tb1_nome}</span>
                                                            <span className="text-xs text-gray-400">
                                                                ID {row.tb1_id} | {TYPE_LABELS[row.tb1_tipo] ?? "---"} | {row.tb1_codbar || "--"}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="text"
                                                            maxLength="8"
                                                            placeholder="19059090"
                                                            value={row.tb1_ncm}
                                                            onChange={(event) => handleFieldChange(row.tb1_id, "tb1_ncm", event.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                        />
                                                        {errors.tb1_ncm && <p className="mt-1 text-xs text-rose-600">{errors.tb1_ncm}</p>}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="text"
                                                            maxLength="4"
                                                            placeholder="5102"
                                                            value={row.tb1_cfop}
                                                            onChange={(event) => handleFieldChange(row.tb1_id, "tb1_cfop", event.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                        />
                                                        {errors.tb1_cfop && <p className="mt-1 text-xs text-rose-600">{errors.tb1_cfop}</p>}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="text"
                                                            maxLength="4"
                                                            placeholder="102"
                                                            value={row.tb1_csosn}
                                                            onChange={(event) => handleFieldChange(row.tb1_id, "tb1_csosn", event.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                        />
                                                        {errors.tb1_csosn && <p className="mt-1 text-xs text-rose-600">{errors.tb1_csosn}</p>}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="text"
                                                            maxLength="3"
                                                            placeholder="040"
                                                            value={row.tb1_cst}
                                                            onChange={(event) => handleFieldChange(row.tb1_id, "tb1_cst", event.target.value)}
                                                            className="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                                        />
                                                        {errors.tb1_cst && <p className="mt-1 text-xs text-rose-600">{errors.tb1_cst}</p>}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <SuccessButton
                                                            type="button"
                                                            onClick={() => handleSave(row)}
                                                            disabled={savingId === row.tb1_id}
                                                            aria-label={`Gravar dados fiscais do produto ${row.tb1_nome}`}
                                                            title={`Gravar dados fiscais do produto ${row.tb1_nome}`}
                                                        >
                                                            <i className="bi bi-floppy text-lg" aria-hidden="true"></i>
                                                        </SuccessButton>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
