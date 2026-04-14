import { formatBrazilDateTime, formatBrazilShortDate } from '@/Utils/date';

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

export const buildContraChequeHtml = (detail, options = {}) => {
    const { showDetails = true } = options;
    const printedAt = formatBrazilDateTime(new Date());
    const unitLabel = (detail?.unit_names ?? []).join(' / ') || '---';
    const periodLabel = `${formatBrazilShortDate(detail?.start_date)} a ${formatBrazilShortDate(detail?.end_date)}`;

    const advancesHtml = (detail?.advances ?? [])
        .map(
            (advance) => `
                <div class="record">
                    <div class="record-head">
                        <span>${escapeHtml(formatBrazilShortDate(advance.advance_date))}</span>
                        <span>${escapeHtml(formatCurrency(advance.amount))}</span>
                    </div>
                    <div>Loja: ${escapeHtml(advance.unit_name || '---')}</div>
                    <div>Obs.: ${escapeHtml(advance.reason || '--')}</div>
                </div>
            `,
        )
        .join('');

    const valesHtml = (detail?.vales ?? [])
        .map(
            (vale) => `
                <div class="record">
                    <div class="record-head">
                        <span>Cupom #${escapeHtml(vale.id)}</span>
                        <span>${escapeHtml(formatCurrency(vale.total))}</span>
                    </div>
                    <div>${escapeHtml(formatBrazilDateTime(vale.date_time))}</div>
                    <div>Loja: ${escapeHtml(vale.unit_name || '---')}</div>
                    <div>Itens: ${escapeHtml(vale.items_label || '--')}</div>
                </div>
            `,
        )
        .join('');

    const detailsHtml = showDetails
        ? `
            <div class="divider"></div>
            <div class="section-title">Adiantamentos</div>
            ${advancesHtml || '<p class="empty">Nenhum adiantamento no periodo.</p>'}
            <div class="divider"></div>
            <div class="section-title">Vale em compras</div>
            ${valesHtml || '<p class="empty">Nenhum vale no periodo.</p>'}
        `
        : '';

    return `
        <!DOCTYPE html>
        <html>
            <head>
                <meta charset="utf-8" />
                <title>Contra-Cheque - ${escapeHtml(detail?.user_name || '---')}</title>
                <style>
                    * { box-sizing: border-box; font-family: 'Courier New', monospace; }
                    body { width: 80mm; margin: 0 auto; padding: 10px; color: #111827; }
                    h1, h2 { margin: 0; text-align: center; }
                    h1 { font-size: 16px; }
                    h2 { font-size: 12px; margin-top: 2px; }
                    p, div { font-size: 11px; line-height: 1.35; }
                    .divider { border-top: 1px dashed #000; margin: 8px 0; }
                    .meta { text-align: center; margin: 2px 0; }
                    .summary-row, .record-head { display: flex; justify-content: space-between; gap: 8px; }
                    .summary-row { font-weight: bold; margin: 3px 0; }
                    .record { padding: 5px 0; border-bottom: 1px dashed #d1d5db; }
                    .record-head { font-weight: bold; }
                    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; margin: 6px 0; }
                    .empty { text-align: center; padding: 6px 0; }
                </style>
            </head>
            <body>
                <h1>Contra-Cheque</h1>
                <h2>${escapeHtml(detail?.user_name || '---')}</h2>
                <p class="meta">Funcao: ${escapeHtml(detail?.role_label || '---')}</p>
                <p class="meta">Periodo: ${escapeHtml(periodLabel)}</p>
                <p class="meta">Lojas: ${escapeHtml(unitLabel)}</p>
                <p class="meta">Impresso em: ${escapeHtml(printedAt)}</p>
                <div class="divider"></div>
                <div class="summary-row"><span>Salario</span><span>${escapeHtml(formatCurrency(detail?.salary))}</span></div>
                <div class="summary-row"><span>Adiantamento</span><span>${escapeHtml(formatCurrency(detail?.advances_total))}</span></div>
                <div class="summary-row"><span>Vale em compras</span><span>${escapeHtml(formatCurrency(detail?.vales_total))}</span></div>
                <div class="summary-row"><span>Saldo a receber</span><span>${escapeHtml(formatCurrency(detail?.balance))}</span></div>
                ${detailsHtml}
            </body>
        </html>
    `;
};

export const printContraCheque = (detail, blockedMessage, options = {}) => {
    const printWindow = window.open('', '_blank', 'width=420,height=720');

    if (!printWindow) {
        return blockedMessage;
    }

    printWindow.document.write(buildContraChequeHtml(detail, options));
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();

    return '';
};
