import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    formatBrazilDateTime,
    formatBrazilShortDate,
    isoToBrazilShortDateInput,
    normalizeBrazilShortDateInput,
    shortBrazilDateInputToIso,
} from '@/Utils/date';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const formatCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const buildAdvanceDetailHtml = (detail) => {
    const printedAt = formatBrazilDateTime(new Date());
    const periodLabel = `${formatBrazilShortDate(detail?.start_date)} a ${formatBrazilShortDate(detail?.end_date)}`;
    const rowsHtml = (detail?.records || [])
        .map(
            (record) => `
                <div class="record">
                    <div class="record-head">
                        <span>${escapeHtml(formatBrazilShortDate(record.advance_date))}</span>
                        <span>${escapeHtml(formatCurrency(record.amount))}</span>
                    </div>
                    <p>Loja: ${escapeHtml(record.unit_name || '---')}</p>
                    <p>Obs.: ${escapeHtml(record.reason || '--')}</p>
                </div>
            `,
        )
        .join('');

    return `
        <!DOCTYPE html>
        <html>
            <head>
                <meta charset="utf-8" />
                <title>Adiantamentos - ${escapeHtml(detail?.user_name || '---')}</title>
                <style>
                    * { font-family: 'Courier New', monospace; box-sizing: border-box; }
                    body { width: 80mm; margin: 0 auto; padding: 12px; color: #111827; }
                    h1 { margin: 0; text-align: center; font-size: 16px; }
                    p { margin: 4px 0; font-size: 12px; }
                    .divider { border-top: 1px dashed #000; margin: 10px 0; }
                    .meta { text-align: center; }
                    .record { padding: 6px 0; border-bottom: 1px dashed #d1d5db; }
                    .record-head { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; font-weight: bold; }
                    .total { display: flex; justify-content: space-between; gap: 8px; font-size: 14px; font-weight: bold; }
                </style>
            </head>
            <body>
                <h1>Adiantamentos</h1>
                <p class="meta">Funcionario: ${escapeHtml(detail?.user_name || '---')}</p>
                <p class="meta">Periodo: ${escapeHtml(periodLabel)}</p>
                <p class="meta">Registros: ${escapeHtml(detail?.records_count ?? 0)}</p>
                <p class="meta">Impresso em: ${escapeHtml(printedAt)}</p>
                <div class="divider"></div>
                ${rowsHtml || '<p>Nenhum registro para imprimir.</p>'}
                <div class="divider"></div>
                <div class="total">
                    <span>Total</span>
                    <span>${escapeHtml(formatCurrency(detail?.total_amount))}</span>
                </div>
            </body>
        </html>
    `;
};

export default function AdvancesReport({
    rows = [],
    startDate,
    endDate,
    unit,
    filterUnits = [],
    selectedUnitId = null,
}) {
    const { data, setData, get, processing } = useForm({
        start_date: isoToBrazilShortDateInput(startDate ?? ''),
        end_date: isoToBrazilShortDateInput(endDate ?? ''),
        unit_id:
            selectedUnitId !== null && selectedUnitId !== undefined
                ? String(selectedUnitId)
                : 'all',
    });
    const [selectedDetail, setSelectedDetail] = useState(null);
    const [printError, setPrintError] = useState('');

    const totalAmount = useMemo(
        () => rows.reduce((sum, row) => sum + (Number(row.total_amount) || 0), 0),
        [rows],
    );

    const handleSubmit = (event) => {
        event.preventDefault();

        get(route('reports.adiantamentos'), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            data: {
                start_date: shortBrazilDateInputToIso(data.start_date) || undefined,
                end_date: shortBrazilDateInputToIso(data.end_date) || undefined,
                unit_id: data.unit_id,
            },
        });
    };

    const handlePrint = (detail) => {
        setPrintError('');

        const printWindow = window.open('', '_blank', 'width=400,height=600');

        if (!printWindow) {
            setPrintError('Permita pop-ups para imprimir o detalhamento dos adiantamentos.');
            return;
        }

        printWindow.document.write(buildAdvanceDetailHtml(detail));
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Relatorio Adiantamento
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-300">
                        Adiantamentos agrupados por usuario. Unidade atual: {unit?.name ?? '---'}.
                    </p>
                </div>
            }
        >
            <Head title="Relatorio Adiantamento" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {printError && (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200">
                            {printError}
                        </div>
                    )}

                    <form
                        onSubmit={handleSubmit}
                        className="rounded-2xl bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Inicio
                                </label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={data.start_date}
                                    onChange={(event) => setData('start_date', normalizeBrazilShortDateInput(event.target.value))}
                                    placeholder="DD/MM/AA"
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Fim
                                </label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={data.end_date}
                                    onChange={(event) => setData('end_date', normalizeBrazilShortDateInput(event.target.value))}
                                    placeholder="DD/MM/AA"
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Unidade
                                </label>
                                <select
                                    value={data.unit_id}
                                    onChange={(event) => setData('unit_id', event.target.value)}
                                    className="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <option value="all">Todas</option>
                                    {filterUnits.map((filterUnit) => (
                                        <option key={filterUnit.id} value={filterUnit.id}>
                                            {filterUnit.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Filtrar
                            </button>
                        </div>
                    </form>

                    <div className="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Adiantamentos por usuario
                            </h3>
                            <div className="flex flex-wrap items-center gap-3">
                                <span className="text-xs font-semibold text-gray-500 dark:text-gray-300">
                                    {rows.length} usuario(s)
                                </span>
                                <div className="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-indigo-700 shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200">
                                    <p className="text-[10px] font-semibold uppercase tracking-wide">
                                        Total filtrado
                                    </p>
                                    <p className="text-sm font-bold">
                                        {formatCurrency(totalAmount)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {rows.length === 0 ? (
                            <p className="mt-4 rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-300">
                                Nenhum adiantamento para o periodo selecionado.
                            </p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                    <thead className="bg-gray-100 dark:bg-gray-900/60">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">
                                                Colaborador
                                            </th>
                                            <th className="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-300">
                                                Lancamentos
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                                                Total
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">
                                                Detalhe
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {rows.map((row) => (
                                            <tr key={row.id}>
                                                <td className="px-3 py-2 text-gray-800 dark:text-gray-100">
                                                    {row.user_name}
                                                </td>
                                                <td className="px-3 py-2 text-center text-gray-700 dark:text-gray-200">
                                                    {row.records_count}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                                                    {formatCurrency(row.total_amount)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedDetail(row.detail)}
                                                        className="rounded-xl bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                                                    >
                                                        Ver detalhe
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Modal show={Boolean(selectedDetail)} onClose={() => setSelectedDetail(null)} maxWidth="2xl" tone="light">
                <div className="bg-white p-6 text-gray-900">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-semibold">
                                Detalhamento de adiantamentos
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Este detalhe ignora o filtro de loja e considera todos os lancamentos do periodo informado.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSelectedDetail(null)}
                            className="text-sm font-semibold text-gray-500 transition hover:text-gray-800"
                        >
                            Fechar
                        </button>
                    </div>

                    {selectedDetail && (
                        <>
                            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <p className="text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                                        Usuario
                                    </p>
                                    <p className="mt-2 text-base font-semibold text-gray-900">
                                        {selectedDetail.user_name}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <p className="text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                                        Periodo
                                    </p>
                                    <p className="mt-2 text-base font-semibold text-gray-900">
                                        {formatBrazilShortDate(selectedDetail.start_date)} a {formatBrazilShortDate(selectedDetail.end_date)}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <p className="text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                                        Lancamentos
                                    </p>
                                    <p className="mt-2 text-base font-semibold text-gray-900">
                                        {selectedDetail.records_count}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                    <p className="text-xs font-bold uppercase tracking-[0.25em] text-gray-400">
                                        Valor total
                                    </p>
                                    <p className="mt-2 text-base font-semibold text-gray-900">
                                        {formatCurrency(selectedDetail.total_amount)}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-6 max-h-[28rem] overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600">
                                                Data
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600">
                                                Loja
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium text-gray-600">
                                                Valor
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600">
                                                Observacao
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {selectedDetail.records.map((record) => (
                                            <tr key={record.id}>
                                                <td className="px-3 py-2 text-gray-700">
                                                    {formatBrazilShortDate(record.advance_date)}
                                                </td>
                                                <td className="px-3 py-2 text-gray-700">
                                                    {record.unit_name ?? '---'}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold text-gray-900">
                                                    {formatCurrency(record.amount)}
                                                </td>
                                                <td className="px-3 py-2 text-gray-700">
                                                    {record.reason || '--'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setSelectedDetail(null)}
                                    className="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
                                >
                                    Fechar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handlePrint(selectedDetail)}
                                    className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700"
                                >
                                    Imprimir
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
