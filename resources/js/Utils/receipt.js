import { formatBrazilDateTime } from '@/Utils/date';

export const RECEIPT_PAYMENT_LABELS = {
    dinheiro: 'Dinheiro',
    maquina: 'Maquina',
    vale: 'Vale',
    refeicao: 'Refeicao',
    faturar: 'Faturar',
};

const hasReceiptValue = (value) =>
    value !== null && value !== undefined && String(value).trim() !== '';

export const formatReceiptCurrency = (value) =>
    Number(value ?? 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

export const formatReceiptDateTime = (value) => {
    if (!value) {
        return '--';
    }

    return formatBrazilDateTime(value);
};

export const resolveReceiptId = (receipt) => {
    const candidate = receipt?.id ?? receipt?.payment?.id ?? null;

    return hasReceiptValue(candidate) ? String(candidate).trim() : null;
};

export const resolveReceiptComanda = (receipt) => {
    if (hasReceiptValue(receipt?.comanda)) {
        return String(receipt.comanda).trim();
    }

    const items = Array.isArray(receipt?.items) ? receipt.items : [];
    const comandas = [...new Set(
        items
            .map((item) => item?.comanda)
            .filter(hasReceiptValue)
            .map((value) => String(value).trim()),
    )];

    return comandas.length > 0 ? comandas.join(', ') : null;
};

export const buildReceiptHtml = (receipt) => {
    const receiptId = resolveReceiptId(receipt);
    const receiptComanda = resolveReceiptComanda(receipt);
    const paymentLabel = RECEIPT_PAYMENT_LABELS[receipt?.tipo_pago] ?? receipt?.tipo_pago;
    const unitInfoHtml = `
        ${receipt?.unit_address ? `<p>Endereco: ${receipt.unit_address}</p>` : ''}
        ${receipt?.unit_cnpj ? `<p>CNPJ: ${receipt.unit_cnpj}</p>` : ''}
    `;

    const itemsHtml = (receipt?.items || [])
        .map(
            (item) => `
                <div class="items-row">
                    <span>${item.quantity}x ${item.product_name}</span>
                    <span>${formatReceiptCurrency(item.unit_price)}</span>
                </div>
                <div class="items-row items-row-subtotal">
                    <span>Subtotal</span>
                    <span>${formatReceiptCurrency(item.subtotal)}</span>
                </div>
            `,
        )
        .join('');

    const paymentHtml = receipt?.payment
        ? `
                ${paymentLabel ? `<p>Pagamento: ${paymentLabel}</p>` : ''}
                ${
                    receipt.payment.valor_pago !== null
                        ? `<p>Pago em dinheiro: ${formatReceiptCurrency(receipt.payment.valor_pago)}</p>`
                        : ''
                }
                <p>Troco: ${formatReceiptCurrency(receipt.payment.troco ?? 0)}</p>
                ${
                    Number(receipt.payment.dois_pgto ?? 0) > 0
                        ? `<p>Cartao (compl.): ${formatReceiptCurrency(receipt.payment.dois_pgto)}</p>`
                        : ''
                }
            `
        : paymentLabel
            ? `<p>Pagamento: ${paymentLabel}</p>`
            : '';

    return `
        <!DOCTYPE html>
        <html>
            <head>
                <meta charset="utf-8" />
                <title>${receiptId ? `Cupom #${receiptId}` : 'Cupom'}</title>
                <style>
                    * { font-family: 'Courier New', monospace; box-sizing: border-box; }
                    body { width: 80mm; margin: 0 auto; padding: 12px; }
                    h1 { text-align: center; font-size: 16px; margin: 0 0 10px 0; }
                    p { font-size: 12px; margin: 4px 0; }
                    .divider { border-top: 1px dashed #000; margin: 10px 0; }
                    .items-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px; }
                    .items-row-subtotal { font-style: italic; }
                    .total { font-size: 14px; font-weight: bold; text-align: right; margin-top: 10px; }
                </style>
            </head>
            <body>
                <h1>${receipt?.unit_name || 'Cupom'}</h1>
                ${unitInfoHtml}
                ${receiptId ? `<p>Cupom: #${receiptId}</p>` : ''}
                ${receiptComanda ? `<p>Comanda: #${receiptComanda}</p>` : ''}
                <p>Caixa: ${receipt?.cashier_name || '---'}</p>
                ${
                    receipt?.vale_user_name
                        ? `<p>Vale: ${receipt.vale_user_name}${
                              receipt.vale_type === 'refeicao' ? ' (Refeicao)' : ''
                          }</p>`
                        : ''
                }
                <p>Data: ${formatReceiptDateTime(receipt?.date_time)}</p>
                <div class="divider"></div>
                ${itemsHtml}
                <div class="divider"></div>
                ${paymentHtml}
                <div class="total">Total: ${formatReceiptCurrency(receipt?.total)}</div>
                <p style="text-align:center;margin-top:12px;">Obrigado pela preferencia</p>
            </body>
        </html>
    `;
};
