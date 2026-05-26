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
    tb30_codigo: "",
    tb30_nome: "",
    tb30_descricao: "",
    tb30_origem_mercadoria: "FABRICACAO_PROPRIA",
    tb30_ativo: true,
    tb30_data_inicio_vigencia: "",
    tb30_data_fim_vigencia: "",
    tb30_ncm_padrao: "",
    tb30_cest: "",
    tb30_cclass_trib: "",
    tb30_cst_ibs: "",
    tb30_cst_cbs: "",
    tb30_aliquota_ibs_uf: "",
    tb30_aliquota_ibs_municipio: "",
    tb30_aliquota_cbs: "",
    tb30_aliquota_is: "",
    tb30_cfop_venda_interna: "",
    tb30_cfop_venda_interestadual: "",
    tb30_cfop_consumo_local: "",
    tb30_cfop_entrega: "",
    tb30_csosn: "",
    tb30_cst_icms: "",
    tb30_cst_pis: "",
    tb30_cst_cofins: "",
    tb30_aliquota_icms: "",
    tb30_aliquota_pis: "",
    tb30_aliquota_cofins: "",
    tb30_regra_icms: "",
    tb30_natureza_receita: "",
    tb30_aplica_balcao: true,
    tb30_aplica_delivery: true,
    tb30_aplica_consumo_local: true,
    tb30_permite_excecao_produto: true,
    tb30_observacao_fiscal: "",
};

const operationFields = [
    ["tb30_cfop_venda_interna", "CFOP venda interna", "4"],
    ["tb30_cfop_venda_interestadual", "CFOP interestadual", "4"],
    ["tb30_cfop_consumo_local", "CFOP consumo local", "4"],
    ["tb30_cfop_entrega", "CFOP entrega/delivery", "4"],
];

const simplesFields = [
    ["tb30_csosn", "CSOSN", "4"],
    ["tb30_cst_icms", "CST ICMS", "3"],
    ["tb30_cst_pis", "CST PIS", "3"],
    ["tb30_cst_cofins", "CST COFINS", "3"],
];

const reformFields = [
    ["tb30_cst_ibs", "CST IBS", "3"],
    ["tb30_cst_cbs", "CST CBS", "3"],
    ["tb30_cclass_trib", "cClassTrib", "6"],
];

const rateFields = [
    ["tb30_aliquota_icms", "Aliquota ICMS", "0.01"],
    ["tb30_aliquota_pis", "Aliquota PIS", "0.0001"],
    ["tb30_aliquota_cofins", "Aliquota COFINS", "0.0001"],
    ["tb30_aliquota_ibs_uf", "Aliquota IBS UF", "0.0001"],
    ["tb30_aliquota_ibs_municipio", "Aliquota IBS municipio", "0.0001"],
    ["tb30_aliquota_cbs", "Aliquota CBS", "0.0001"],
    ["tb30_aliquota_is", "Aliquota IS", "0.0001"],
];

const requiredWhenActive = [
    ["tb30_codigo", "Codigo"],
    ["tb30_nome", "Nome"],
    ["tb30_origem_mercadoria", "Origem"],
    ["tb30_data_inicio_vigencia", "Inicio da vigencia"],
    ["tb30_cfop_venda_interna", "CFOP venda interna"],
    ["tb30_cst_pis", "CST PIS"],
    ["tb30_cst_cofins", "CST COFINS"],
];

const dateValue = (value) => value ? String(value).slice(0, 10) : "";

const normalizeFormFromCategory = (category) => ({
    ...emptyForm,
    ...Object.fromEntries(Object.keys(emptyForm).map((field) => [field, category[field] ?? emptyForm[field]])),
    tb30_ativo: Boolean(category.tb30_ativo),
    tb30_aplica_balcao: Boolean(category.tb30_aplica_balcao),
    tb30_aplica_delivery: Boolean(category.tb30_aplica_delivery),
    tb30_aplica_consumo_local: Boolean(category.tb30_aplica_consumo_local),
    tb30_permite_excecao_produto: Boolean(category.tb30_permite_excecao_produto),
    tb30_data_inicio_vigencia: dateValue(category.tb30_data_inicio_vigencia),
    tb30_data_fim_vigencia: dateValue(category.tb30_data_fim_vigencia),
});

function FieldGroup({ title, help, children }) {
    return (
        <section className="rounded-md border border-gray-200 bg-gray-50 p-3">
            <div className="mb-3">
                <h4 className="text-sm font-semibold text-gray-900">{title}</h4>
                {help && <p className="mt-1 text-xs text-gray-500">{help}</p>}
            </div>
            {children}
        </section>
    );
}

function TextField({ data, errors, field, label, maxLength, setData, help, type = "text", step }) {
    return (
        <div>
            <label className="text-sm font-medium text-gray-700">{label}</label>
            <input
                type={type}
                step={step}
                min={type === "number" ? "0" : undefined}
                max={type === "number" ? "100" : undefined}
                value={data[field] ?? ""}
                maxLength={maxLength}
                onChange={(e) => setData(field, e.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            {help && <p className="mt-1 text-xs text-gray-500">{help}</p>}
            {errors[field] && <p className="text-sm text-red-600">{errors[field]}</p>}
        </div>
    );
}

export default function FiscalCategories({ auth, categories = [], linkedProducts = [], selectedCategoryId = null, originOptions = [] }) {
    const { flash } = usePage().props;
    const [editingId, setEditingId] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyForm);

    const editingCategory = useMemo(
        () => categories.find((category) => Number(category.tb30_id) === Number(editingId)),
        [categories, editingId]
    );

    const startEdit = (category) => {
        setEditingId(category.tb30_id);
        setData(normalizeFormFromCategory(category));
    };

    const missingRequired = requiredWhenActive.filter(([field]) => !String(data[field] ?? "").trim());
    const missingConditional = [
        data.tb30_aplica_consumo_local && !String(data.tb30_cfop_consumo_local ?? "").trim()
            ? "CFOP consumo local"
            : null,
        data.tb30_aplica_delivery && !String(data.tb30_cfop_entrega ?? "").trim()
            ? "CFOP entrega/delivery"
            : null,
        !String(data.tb30_csosn ?? "").trim() && !String(data.tb30_cst_icms ?? "").trim()
            ? "CSOSN ou CST ICMS"
            : null,
    ].filter(Boolean);
    const activationReady = missingRequired.length === 0 && missingConditional.length === 0;

    const submit = (event) => {
        event.preventDefault();

        if (editingCategory) {
            put(route("products.fiscal-categories.update", { categoriaFiscal: editingCategory.tb30_id }), {
                preserveScroll: true,
                onSuccess: () => setEditingId(null),
            });
            return;
        }

        post(route("products.fiscal-categories.store"), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const removeCategory = (category) => {
        if (!window.confirm(`Excluir a categoria fiscal ${category.tb30_nome}?`)) {
            return;
        }

        router.delete(route("products.fiscal-categories.destroy", { categoriaFiscal: category.tb30_id }), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between gap-3">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Categorias Fiscais
                    </h2>
                    <Link href={route("products.index")}>
                        <PrimaryButton aria-label="Voltar" title="Voltar">
                            <i className="bi bi-arrow-left text-lg" aria-hidden="true"></i>
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Categorias Fiscais" />
            <div className="py-4 max-w-7xl mx-auto sm:px-6 lg:px-8">
                <AlertMessage message={flash} />
                <div className="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
                    <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-gray-900">
                                {editingCategory ? "Editar categoria" : "Nova categoria"}
                            </h3>
                            {editingCategory && (
                                <InfoButton type="button" onClick={() => { setEditingId(null); reset(); }} aria-label="Cancelar edicao" title="Cancelar edicao">
                                    <i className="bi bi-x-lg text-lg" aria-hidden="true"></i>
                                </InfoButton>
                            )}
                        </div>

                        <div className="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
                            Categoria ativa sera usada na emissao da NF. Confirme CFOP, CSOSN/CST e campos IBS/CBS com o contador antes de ativar. NCM e CEST ficam no cadastro de Grupos NCM.
                        </div>

                        <div className="space-y-4">
                            <FieldGroup title="Identificacao" help="Codigo e o identificador interno; nome e o texto que aparece no cadastro do produto.">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <TextField data={data} errors={errors} field="tb30_codigo" label="Codigo" maxLength="30" setData={(field, value) => setData(field, value.toUpperCase().replace(/\s+/g, "_"))} help="Exemplo: PANIFICACAO_PROPRIA ou REVENDA_BEBIDAS." />
                                    <TextField data={data} errors={errors} field="tb30_nome" label="Nome" maxLength="120" setData={(field, value) => setData(field, value.toUpperCase())} help="Exemplo: PANIFICACAO PROPRIA. E o nome exibido para o usuario." />
                                    <div>
                                        <label className="text-sm font-medium text-gray-700">Origem</label>
                                        <select value={data.tb30_origem_mercadoria} onChange={(e) => setData("tb30_origem_mercadoria", e.target.value)} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                            {originOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                        </select>
                                        {errors.tb30_origem_mercadoria && <p className="text-sm text-red-600">{errors.tb30_origem_mercadoria}</p>}
                                    </div>
                                    <TextField data={data} errors={errors} field="tb30_descricao" label="Descricao" setData={setData} help="Opcional. Use para orientar quando aplicar esta categoria." />
                                </div>
                            </FieldGroup>

                            <FieldGroup title="Status e vigencia" help="A categoria so deve ficar ativa depois de revisada; categoria inativa bloqueia a emissao.">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" checked={Boolean(data.tb30_ativo)} onChange={(e) => setData("tb30_ativo", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                            Ativa
                                        </label>
                                        <label className="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" checked={Boolean(data.tb30_permite_excecao_produto)} onChange={(e) => setData("tb30_permite_excecao_produto", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                            Permite excecao no produto
                                        </label>
                                    </div>
                                    <div className={activationReady ? "rounded-md border border-green-200 bg-green-50 p-2 text-xs text-green-800" : "rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-900"}>
                                        {activationReady ? "Checklist minimo completo para ativacao." : `Falta preencher: ${missingRequired.map(([, label]) => label).concat(missingConditional).join(", ")}.`}
                                    </div>
                                    <TextField data={data} errors={errors} field="tb30_data_inicio_vigencia" label="Inicio vigencia" type="date" setData={setData} />
                                    <TextField data={data} errors={errors} field="tb30_data_fim_vigencia" label="Fim vigencia" type="date" setData={setData} help="Opcional. Preencha apenas quando a regra tiver prazo para acabar." />
                                </div>
                            </FieldGroup>

                            <FieldGroup title="CFOP por operacao" help="A emissao procura o CFOP conforme o tipo da venda. Delivery e consumo local so sao obrigatorios quando marcados abaixo.">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {operationFields.map(([field, label, maxLength]) => (
                                        <TextField key={field} data={data} errors={errors} field={field} label={label} maxLength={maxLength} setData={setData} />
                                    ))}
                                </div>
                            </FieldGroup>

                            <FieldGroup title="Simples, ICMS, PIS e COFINS" help="Para Simples Nacional, informe CSOSN. PIS e COFINS ajudam a deixar a categoria completa para emissao.">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {simplesFields.map(([field, label, maxLength]) => (
                                        <TextField key={field} data={data} errors={errors} field={field} label={label} maxLength={maxLength} setData={setData} />
                                    ))}
                                    <TextField data={data} errors={errors} field="tb30_regra_icms" label="Regra ICMS" maxLength="120" setData={setData} />
                                    <TextField data={data} errors={errors} field="tb30_natureza_receita" label="Natureza receita" maxLength="60" setData={setData} />
                                </div>
                            </FieldGroup>

                            <FieldGroup title="IBS, CBS e IS" help="Use os codigos e aliquotas conforme tabela oficial vigente e revisao do contador.">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {reformFields.map(([field, label, maxLength]) => (
                                        <TextField key={field} data={data} errors={errors} field={field} label={label} maxLength={maxLength} setData={setData} />
                                    ))}
                                    {rateFields.map(([field, label, step]) => (
                                        <TextField key={field} data={data} errors={errors} field={field} label={label} type="number" step={step} setData={setData} />
                                    ))}
                                </div>
                            </FieldGroup>

                            <FieldGroup title="Aplicabilidade" help="Essas marcas definem quais CFOPs podem ser exigidos na emissao.">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={Boolean(data.tb30_aplica_balcao)} onChange={(e) => setData("tb30_aplica_balcao", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                        Aplica balcao
                                    </label>
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={Boolean(data.tb30_aplica_delivery)} onChange={(e) => setData("tb30_aplica_delivery", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                        Aplica delivery
                                    </label>
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={Boolean(data.tb30_aplica_consumo_local)} onChange={(e) => setData("tb30_aplica_consumo_local", e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                        Aplica consumo local
                                    </label>
                                </div>
                            </FieldGroup>
                        </div>

                        <div className="mt-4">
                            <label className="text-sm font-medium text-gray-700">Observacao fiscal</label>
                            <textarea value={data.tb30_observacao_fiscal ?? ""} onChange={(e) => setData("tb30_observacao_fiscal", e.target.value)} className="mt-1 min-h-20 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>

                        <div className="mt-4 flex justify-end">
                            {editingCategory ? (
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

                    <div className="space-y-4">
                        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 className="text-base font-semibold text-gray-900">Categorias cadastradas</h3>
                            <div className="mt-3 overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead className="text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-3 py-2">Nome</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2">Produtos</th>
                                            <th className="px-3 py-2 text-right">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {categories.map((category) => (
                                            <tr key={category.tb30_id}>
                                                <td className="px-3 py-2 font-semibold">{category.tb30_nome}</td>
                                                <td className="px-3 py-2">
                                                    <span className={category.tb30_ativo ? "rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" : "rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700"}>
                                                        {category.tb30_ativo ? "Ativa" : "Inativa"}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Link href={route("products.fiscal-categories.index", { categoria: category.tb30_id })} className="text-indigo-600 hover:underline">
                                                        {category.produtos_count}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <InfoButton type="button" onClick={() => startEdit(category)} className="ms-1" aria-label="Editar" title="Editar">
                                                        <i className="bi bi-pencil-square text-lg" aria-hidden="true"></i>
                                                    </InfoButton>
                                                    <DangerButton type="button" onClick={() => removeCategory(category)} className="ms-1" aria-label="Excluir" title="Excluir">
                                                        <i className="bi bi-trash text-lg" aria-hidden="true"></i>
                                                    </DangerButton>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 className="text-base font-semibold text-gray-900">Produtos vinculados</h3>
                            {selectedCategoryId ? (
                                <div className="mt-3 space-y-2">
                                    {linkedProducts.length === 0 ? (
                                        <p className="text-sm text-gray-500">Nenhum produto vinculado.</p>
                                    ) : linkedProducts.map((product) => (
                                        <div key={product.tb1_id} className="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm">
                                            <span>{product.tb1_id} - {product.tb1_nome}</span>
                                            <span className="text-gray-500">{product.tb1_status ? "Ativo" : "Inativo"}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-gray-500">Clique na quantidade de produtos de uma categoria para visualizar os vinculados.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
