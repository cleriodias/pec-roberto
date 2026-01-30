<?php

namespace App\Http\Controllers;

use App\Models\CashierClosure;
use App\Models\Expense;
use App\Models\ProductDiscard;
use App\Models\SalaryAdvance;
use App\Models\Supplier;
use App\Models\Venda;
use App\Models\VendaPagamento;
use App\Models\User;
use App\Models\Unidade;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    private const TYPE_META = [
        'dinheiro' => ['label' => 'Dinheiro', 'color' => '#16a34a'],
        'maquina' => ['label' => 'Maquina', 'color' => '#2563eb'],
        'vale' => ['label' => 'Vale', 'color' => '#f59e0b'],
        'refeicao' => ['label' => 'Refeição', 'color' => '#facc15'],
        'faturar' => ['label' => 'Faturar', 'color' => '#0f172a'],
    ];

    public function index(Request $request): Response
    {
        $this->ensureManager($request);

        $reports = [
            [
                'key' => 'control',
                'label' => 'Controle',
                'description' => 'Resumo financeiro mensal por unidade.',
                'icon' => 'bi-graph-up-arrow',
                'route' => 'reports.control',
            ],
            [
                'key' => 'cash-closure',
                'label' => 'Fechamento de caixa',
                'description' => 'Detalhe de pagamentos e diferencas por caixa.',
                'icon' => 'bi-clipboard-data',
                'route' => 'reports.cash.closure',
            ],
            [
                'key' => 'cash-discrepancies',
                'label' => 'Discrepancias de caixa',
                'description' => 'Fechamentos com diferencas entre sistema e fechamento.',
                'icon' => 'bi-exclamation-triangle',
                'route' => 'reports.cash.discrepancies',
            ],
            [
                'key' => 'sales-today',
                'label' => 'Vendas hoje',
                'description' => 'Total do dia e formas de pagamento.',
                'icon' => 'bi-calendar-day',
                'route' => 'reports.sales.today',
            ],
            [
                'key' => 'sales-period',
                'label' => 'Vendas periodo',
                'description' => 'Totais diarios no periodo selecionado.',
                'icon' => 'bi-calendar-range',
                'route' => 'reports.sales.period',
            ],
            [
                'key' => 'sales-detailed',
                'label' => 'Relatorio detalhado',
                'description' => 'Itens vendidos com detalhes por venda.',
                'icon' => 'bi-card-checklist',
                'route' => 'reports.sales.detailed',
            ],
            [
                'key' => 'lanchonete',
                'label' => 'Relatorio lanchonete',
                'description' => 'Comandas por dia e status na lanchonete.',
                'icon' => 'bi-cup-hot',
                'route' => 'reports.lanchonete',
            ],
            [
                'key' => 'vales',
                'label' => 'Vales',
                'description' => 'Compras feitas no vale.',
                'icon' => 'bi-ticket-perforated',
                'route' => 'reports.vale',
            ],
            [
                'key' => 'refeicao',
                'label' => 'Refeicao',
                'description' => 'Compras feitas na refeicao.',
                'icon' => 'bi-cup-straw',
                'route' => 'reports.refeicao',
            ],
            [
                'key' => 'adiantamentos',
                'label' => 'Adiantamento',
                'description' => 'Adiantamentos realizados no periodo.',
                'icon' => 'bi-wallet2',
                'route' => 'reports.adiantamentos',
            ],
            [
                'key' => 'fornecedores',
                'label' => 'Fornecedores',
                'description' => 'Fornecedores cadastrados.',
                'icon' => 'bi-truck',
                'route' => 'reports.fornecedores',
            ],
            [
                'key' => 'gastos',
                'label' => 'Gastos',
                'description' => 'Gastos cadastrados no periodo.',
                'icon' => 'bi-receipt',
                'route' => 'reports.gastos',
            ],
            [
                'key' => 'descarte',
                'label' => 'Descarte',
                'description' => 'Descartes registrados no periodo.',
                'icon' => 'bi-recycle',
                'route' => 'reports.descarte',
            ],
        ];

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function vale(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);

        $rows = Venda::query()
            ->with(['unidade:tb2_id,tb2_nome', 'caixa:id,name', 'valeUser:id,name'])
            ->where('tipo_pago', 'vale')
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            })
            ->whereBetween('data_hora', [$start, $end])
            ->orderByDesc('data_hora')
            ->get([
                'tb3_id',
                'id_comanda',
                'produto_nome',
                'valor_unitario',
                'quantidade',
                'valor_total',
                'data_hora',
                'id_unidade',
                'id_user_caixa',
                'id_user_vale',
            ])
            ->map(function (Venda $row) {
                return [
                    'id' => $row->tb3_id,
                    'comanda' => $row->id_comanda,
                    'product' => $row->produto_nome,
                    'quantity' => (int) $row->quantidade,
                    'unit_price' => (float) $row->valor_unitario,
                    'total' => round((float) $row->valor_total, 2),
                    'sold_at' => $row->data_hora ? $row->data_hora->toIso8601String() : null,
                    'unit_name' => $row->unidade?->tb2_nome ?? '---',
                    'cashier' => $row->caixa?->name ?? null,
                    'vale_user' => $row->valeUser?->name ?? null,
                ];
            })
            ->values();

        return Inertia::render('Reports/Vale', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function refeicao(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);

        $rows = Venda::query()
            ->with(['unidade:tb2_id,tb2_nome', 'caixa:id,name', 'valeUser:id,name'])
            ->where('tipo_pago', 'refeicao')
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            })
            ->whereBetween('data_hora', [$start, $end])
            ->orderByDesc('data_hora')
            ->get([
                'tb3_id',
                'id_comanda',
                'produto_nome',
                'valor_unitario',
                'quantidade',
                'valor_total',
                'data_hora',
                'id_unidade',
                'id_user_caixa',
                'id_user_vale',
            ])
            ->map(function (Venda $row) {
                return [
                    'id' => $row->tb3_id,
                    'comanda' => $row->id_comanda,
                    'product' => $row->produto_nome,
                    'quantity' => (int) $row->quantidade,
                    'unit_price' => (float) $row->valor_unitario,
                    'total' => round((float) $row->valor_total, 2),
                    'sold_at' => $row->data_hora ? $row->data_hora->toIso8601String() : null,
                    'unit_name' => $row->unidade?->tb2_nome ?? '---',
                    'cashier' => $row->caixa?->name ?? null,
                    'vale_user' => $row->valeUser?->name ?? null,
                ];
            })
            ->values();

        return Inertia::render('Reports/Refeicao', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function adiantamentos(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);

        $rows = SalaryAdvance::query()
            ->with('user:id,name,tb2_id')
            ->whereBetween('advance_date', [$startDate, $endDate])
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where(function ($sub) use ($filterUnitId) {
                    $sub->where('unit_id', $filterUnitId)
                        ->orWhere(function ($legacy) use ($filterUnitId) {
                            $legacy->whereNull('unit_id')
                                ->where(function ($legacyUnit) use ($filterUnitId) {
                                    $legacyUnit->whereHas('user', function ($userQuery) use ($filterUnitId) {
                                        $userQuery->where('tb2_id', $filterUnitId);
                                    })->orWhereHas('user.units', function ($unitQuery) use ($filterUnitId) {
                                        $unitQuery->where('tb2_unidades.tb2_id', $filterUnitId);
                                    });
                                });
                        });
                });
            })
            ->orderByDesc('advance_date')
            ->get([
                'id',
                'user_id',
                'unit_id',
                'advance_date',
                'amount',
                'reason',
            ])
            ->map(function (SalaryAdvance $advance) {
                return [
                    'id' => $advance->id,
                    'user_name' => $advance->user?->name ?? '---',
                    'advance_date' => $advance->advance_date?->toDateString(),
                    'amount' => round((float) $advance->amount, 2),
                    'reason' => $advance->reason,
                ];
            })
            ->values();

        return Inertia::render('Reports/Advances', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function fornecedores(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);

        $suppliers = Supplier::query()
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->whereHas('expenses', function ($expenseQuery) use ($filterUnitId) {
                    $expenseQuery->where('unit_id', $filterUnitId);
                });
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'access_code',
                'dispute',
                'created_at',
            ])
            ->map(function (Supplier $supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'access_code' => $supplier->access_code,
                    'dispute' => (bool) $supplier->dispute,
                    'created_at' => $supplier->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return Inertia::render('Reports/Suppliers', [
            'suppliers' => $suppliers,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function gastos(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);

        $rows = Expense::query()
            ->with(['supplier:id,name', 'unit:tb2_id,tb2_nome', 'user:id,name'])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where('unit_id', $filterUnitId);
            })
            ->orderByDesc('expense_date')
            ->get([
                'id',
                'supplier_id',
                'unit_id',
                'user_id',
                'expense_date',
                'amount',
                'notes',
            ])
            ->map(function (Expense $expense) {
                return [
                    'id' => $expense->id,
                    'expense_date' => $expense->expense_date?->toDateString(),
                    'supplier' => $expense->supplier?->name ?? '---',
                    'unit' => $expense->unit?->tb2_nome ?? '---',
                    'user_name' => $expense->user?->name ?? '---',
                    'amount' => round((float) $expense->amount, 2),
                    'notes' => $expense->notes,
                ];
            })
            ->values();

        return Inertia::render('Reports/Expenses', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function descarte(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);

        $rows = ProductDiscard::query()
            ->with(['product:tb1_id,tb1_nome,tb1_vlr_venda', 'user:id,name,tb2_id', 'unit:tb2_id,tb2_nome'])
            ->whereBetween('created_at', [$start, $end])
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where(function ($subQuery) use ($filterUnitId) {
                    $subQuery->where('unit_id', $filterUnitId)
                        ->orWhere(function ($legacy) use ($filterUnitId) {
                            $legacy->whereNull('unit_id')
                                ->where(function ($legacyUnit) use ($filterUnitId) {
                                    $legacyUnit->whereHas('user', function ($userQuery) use ($filterUnitId) {
                                        $userQuery->where('tb2_id', $filterUnitId);
                                    })->orWhereHas('user.units', function ($unitQuery) use ($filterUnitId) {
                                        $unitQuery->where('tb2_unidades.tb2_id', $filterUnitId);
                                    });
                                });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->get([
                'id',
                'product_id',
                'user_id',
                'unit_id',
                'quantity',
                'created_at',
            ])
            ->map(function (ProductDiscard $discard) {
                $unitPrice = (float) ($discard->product?->tb1_vlr_venda ?? 0);
                $quantity = (float) $discard->quantity;

                return [
                    'id' => $discard->id,
                    'product' => $discard->product?->tb1_nome ?? '---',
                    'quantity' => round($quantity, 3),
                    'unit_price' => round($unitPrice, 2),
                    'total' => round($unitPrice * $quantity, 2),
                    'user_name' => $discard->user?->name ?? '---',
                    'created_at' => $discard->created_at?->toIso8601String(),
                    'unit_name' => $discard->unit?->tb2_nome ?? '---',
                ];
            })
            ->values();

        return Inertia::render('Reports/Discards', [
            'rows' => $rows,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'unit' => $selectedUnit,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function today(Request $request): Response
    {
        $this->ensureManager($request);
        $user = $request->user();
        $availableUnits = $this->availableUnits($user);
        $requestedUnitId = $request->query('unit_id');
        $filterUnitId = $requestedUnitId !== null && $requestedUnitId !== '' ? (int) $requestedUnitId : null;

        if ($filterUnitId && !$availableUnits->contains(fn ($unit) => $unit['id'] === $filterUnitId)) {
            $filterUnitId = null;
        }

        $dayFilter = $request->query('day') === 'previous' ? 'previous' : 'current';
        $baseDate = Carbon::today();

        if ($dayFilter === 'previous') {
            $baseDate = $baseDate->subDay();
        }

        $start = $baseDate->copy()->startOfDay();
        $end = $baseDate->copy()->endOfDay();

        $payments = $this->fetchPayments($start, $end, $filterUnitId);
        [$totals, $details, $chartData] = $this->summarizePayments($payments);
        [$totals, $details] = $this->applyGlobalValeTotals($start, $end, $totals, $details, $filterUnitId);
        $chartData = $this->buildChartData($totals);

        $selectedUnit = $filterUnitId
            ? $availableUnits->firstWhere('id', $filterUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];

        return Inertia::render('Reports/SalesToday', [
            'meta' => self::TYPE_META,
            'chartData' => $chartData,
            'details' => $details,
            'totals' => $totals,
            'dateLabel' => $start->translatedFormat('d/m/Y'),
            'filterUnits' => $availableUnits->values(),
            'selectedUnitId' => $filterUnitId,
            'selectedUnit' => $selectedUnit,
            'selectedDay' => $dayFilter,
        ]);
    }

    public function cashClosure(Request $request): Response
    {
        $this->ensureManager($request);
        $user = $request->user();
        $availableUnits = $this->availableUnits($user);

        $requestedUnitId = $request->query('unit_id');
        $filterUnitId = $requestedUnitId !== null && $requestedUnitId !== '' ? (int) $requestedUnitId : null;
        if ($filterUnitId && !$availableUnits->contains(fn ($unit) => $unit['id'] === $filterUnitId)) {
            $filterUnitId = null;
        }

        $dateInput = $request->query('date');
        $date = $this->parseDate($dateInput, 'Y-m-d', Carbon::today());
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $dateDisplay = $date->translatedFormat('d/m/Y');
        $dateValue = $date->format('Y-m-d');

        $paymentsQuery = VendaPagamento::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['vendas' => function ($query) use ($filterUnitId) {
                $query->select('tb3_id', 'tb4_id', 'id_user_caixa', 'id_unidade')
                    ->orderBy('tb3_id');

                if ($filterUnitId) {
                    $query->where('id_unidade', $filterUnitId);
                }
            }]);

        if ($filterUnitId) {
            $paymentsQuery->whereHas('vendas', function ($query) use ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            });
        }

        $payments = $paymentsQuery->get([
            'tb4_id',
            'valor_total',
            'tipo_pagamento',
            'valor_pago',
            'troco',
            'dois_pgto',
            'created_at',
        ]);

        $cashierIds = $payments
            ->map(fn (VendaPagamento $payment) => optional($payment->vendas->first())->id_user_caixa)
            ->filter()
            ->unique();

        $unitIds = $payments
            ->map(fn (VendaPagamento $payment) => optional($payment->vendas->first())->id_unidade)
            ->filter()
            ->unique();

        $cashiers = User::whereIn('id', $cashierIds)->get(['id', 'name'])->keyBy('id');
        $units = Unidade::whereIn('tb2_id', $unitIds)->get(['tb2_id', 'tb2_nome'])->keyBy('tb2_id');
        $closureDate = $date->toDateString();
        $closureQuery = CashierClosure::whereIn('user_id', $cashierIds)
            ->whereDate('closed_date', $closureDate);

        if ($unitIds->isNotEmpty()) {
            $closureQuery->where(function ($query) use ($unitIds) {
                $query->whereIn('unit_id', $unitIds)
                    ->orWhereNull('unit_id');
            });
        }

        if ($filterUnitId) {
            $closureQuery->where(function ($query) use ($filterUnitId) {
                $query->whereNull('unit_id')
                    ->orWhere('unit_id', $filterUnitId);
            });
        }

        $closures = $closureQuery
            ->get()
            ->mapWithKeys(function ($closure) {
                $unitKey = $closure->unit_id ?? 'none';
                return [$closure->user_id . '-' . $unitKey => $closure];
            });

        $grouped = [];

        foreach ($payments as $payment) {
            $firstSale = optional($payment->vendas->first());
            $cashierId = $firstSale?->id_user_caixa;
            $unitId = $firstSale?->id_unidade;

            if (!$cashierId) {
                continue;
            }

            $groupKey = $cashierId . '-' . ($unitId ?? 'none');

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'row_key' => $groupKey,
                    'cashier_id' => $cashierId,
                    'cashier_name' => $cashiers[$cashierId]->name ?? 'Caixa #' . $cashierId,
                    'unit_id' => $unitId,
                    'unit_name' => $unitId ? ($units[$unitId]->tb2_nome ?? ('Unidade #' . $unitId)) : '---',
                    'totals' => [
                        'dinheiro' => 0.0,
                        'maquina' => 0.0,
                        'vale' => 0.0,
                        'refeicao' => 0.0,
                        'faturar' => 0.0,
                    ],
                    'grand_total' => 0.0,
                ];
            }

            foreach ($this->breakdownPayment($payment) as $type => $amount) {
                $grouped[$groupKey]['totals'][$type] += $amount;
                $grouped[$groupKey]['grand_total'] += $amount;
            }
        }

        $dateKey = $date->toDateString();
        $discardEntries = ProductDiscard::query()
            ->with('product:tb1_id,tb1_nome,tb1_vlr_venda')
            ->whereDate('created_at', $dateKey)
            ->orderByDesc('created_at')
            ->get();

        $discardTotals = $discardEntries
            ->groupBy('user_id')
            ->map(function ($group) {
                $quantity = $group->sum('quantity');
                $value = $group->sum(function (ProductDiscard $discard) {
                    $price = (float) ($discard->product->tb1_vlr_venda ?? 0);

                    return (float) $discard->quantity * $price;
                });

                return [
                    'quantity' => (float) $quantity,
                    'value' => round((float) $value, 2),
                ];
            });

        $discardDetails = $discardEntries
            ->take(100)
            ->map(function (ProductDiscard $discard) {
                $price = (float) ($discard->product->tb1_vlr_venda ?? 0);
                $value = (float) $discard->quantity * $price;

                return [
                    'id' => $discard->id,
                    'user_id' => $discard->user_id,
                    'quantity' => round((float) $discard->quantity, 3),
                    'value' => round($value, 2),
                    'product' => $discard->product
                        ? [
                            'id' => $discard->product->tb1_id,
                            'name' => $discard->product->tb1_nome,
                        ]
                        : null,
                    'created_at' => optional($discard->created_at)->toIso8601String(),
                ];
            });

        $records = collect($grouped)
            ->map(function (array $record) use ($closures, $discardTotals) {
                $record['totals'] = array_map(fn ($value) => round($value, 2), $record['totals']);
                $record['grand_total'] = round($record['grand_total'], 2);
                $record['row_key'] = $record['row_key'] ?? ($record['cashier_id'] . '-' . ($record['unit_id'] ?? 'none'));

                $cashSystem = $record['totals']['dinheiro'] ?? 0.0;
                $cardSystem = $record['totals']['maquina'] ?? 0.0;
                $systemTotal = $cashSystem + $cardSystem;

                $closureKey = $record['cashier_id'] . '-' . ($record['unit_id'] ?? 'none');
                $closure = $closures->get($closureKey);

                if ($closure) {
                    $cashClosure = (float) $closure->cash_amount;
                    $cardClosure = (float) $closure->card_amount;
                    $closureTotal = $cashClosure + $cardClosure;

                    $record['closure'] = [
                        'closed' => true,
                        'cash_amount' => round($cashClosure, 2),
                        'card_amount' => round($cardClosure, 2),
                        'total_amount' => round($closureTotal, 2),
                        'unit_id' => $closure->unit_id,
                        'unit_name' => $closure->unit_name,
                        'closed_at' => optional($closure->closed_at)->toIso8601String(),
                        'differences' => [
                            'cash' => round($cashSystem - $cashClosure, 2),
                            'card' => round($cardSystem - $cardClosure, 2),
                            'total' => round($systemTotal - $closureTotal, 2),
                        ],
                    ];
                } else {
                    $record['closure'] = [
                        'closed' => false,
                        'cash_amount' => 0.0,
                        'card_amount' => 0.0,
                        'total_amount' => 0.0,
                        'unit_id' => null,
                        'unit_name' => null,
                        'closed_at' => null,
                        'differences' => [
                            'cash' => round($cashSystem, 2),
                            'card' => round($cardSystem, 2),
                            'total' => round($systemTotal, 2),
                        ],
                    ];
                }

                $discardMeta = $discardTotals[$record['cashier_id']] ?? ['value' => 0.0, 'quantity' => 0.0];
                $record['discard_total'] = round((float) ($discardMeta['value'] ?? 0), 2);
                $record['discard_quantity'] = round((float) ($discardMeta['quantity'] ?? 0), 3);

                return $record;
            })
            ->sortByDesc('grand_total')
            ->values();

        $selectedUnit = $filterUnitId
            ? $availableUnits->firstWhere('id', $filterUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];

        return Inertia::render('Reports/CashClosure', [
            'records' => $records,
            'dateValue' => $dateDisplay,
            'dateInputValue' => $dateValue,
            'filterUnits' => $availableUnits->values(),
            'selectedUnitId' => $filterUnitId,
            'selectedUnit' => $selectedUnit,
            'discardDetails' => $discardDetails,
        ]);
    }

    public function cashDiscrepancies(Request $request): Response
    {
        $this->ensureManager($request);
        $user = $request->user();
        $availableUnits = $this->availableUnits($user);

        $requestedUnitId = $request->query('unit_id');
        $filterUnitId = $requestedUnitId !== null && $requestedUnitId !== '' && $requestedUnitId !== 'all'
            ? (int) $requestedUnitId
            : null;

        if ($filterUnitId && ! $availableUnits->contains(fn ($unit) => $unit['id'] === $filterUnitId)) {
            $filterUnitId = null;
        }

        $dateInput = $request->query('date');
        $date = $this->parseDate($dateInput, 'Y-m-d', Carbon::today());
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $dateValue = $date->format('Y-m-d');
        $referenceDate = $date->toDateString();

        $requestedCashierId = $request->query('cashier_id');
        $filterCashierId = $requestedCashierId !== null && $requestedCashierId !== '' && $requestedCashierId !== 'all'
            ? (int) $requestedCashierId
            : null;

        $allowedUnitIds = $availableUnits->pluck('id')->filter()->values();

        $closureBaseQuery = CashierClosure::query()
            ->whereDate('closed_date', $referenceDate);

        if ($allowedUnitIds->isNotEmpty()) {
            $closureBaseQuery->where(function ($query) use ($allowedUnitIds) {
                $query->whereIn('unit_id', $allowedUnitIds)
                    ->orWhereNull('unit_id');
            });
        }

        if ($filterUnitId) {
            $closureBaseQuery->where(function ($query) use ($filterUnitId) {
                $query->whereNull('unit_id')
                    ->orWhere('unit_id', $filterUnitId);
            });
        }

        $cashierIds = (clone $closureBaseQuery)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $cashiers = $cashierIds->isNotEmpty()
            ? User::whereIn('id', $cashierIds)->orderBy('name')->get(['id', 'name'])->keyBy('id')
            : collect();

        $cashierOptions = $cashiers
            ->map(fn (User $cashier) => ['id' => $cashier->id, 'name' => $cashier->name])
            ->values();

        $closureQuery = clone $closureBaseQuery;
        if ($filterCashierId) {
            $closureQuery->where('user_id', $filterCashierId);
        }

        $closures = $closureQuery->get([
            'id',
            'user_id',
            'unit_id',
            'unit_name',
            'cash_amount',
            'card_amount',
            'closed_date',
            'closed_at',
        ]);

        $applySaleFilters = function ($query) use ($filterUnitId, $filterCashierId, $allowedUnitIds) {
            if ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            } elseif ($allowedUnitIds->isNotEmpty()) {
                $query->whereIn('id_unidade', $allowedUnitIds);
            }

            if ($filterCashierId) {
                $query->where('id_user_caixa', $filterCashierId);
            }
        };

        $paymentsQuery = VendaPagamento::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['vendas' => function ($query) use ($applySaleFilters) {
                $query->select('tb3_id', 'tb4_id', 'id_user_caixa', 'id_unidade')
                    ->orderBy('tb3_id');
                $applySaleFilters($query);
            }]);

        $paymentsQuery->whereHas('vendas', function ($query) use ($applySaleFilters) {
            $applySaleFilters($query);
        });

        $payments = $paymentsQuery->get([
            'tb4_id',
            'valor_total',
            'tipo_pagamento',
            'valor_pago',
            'troco',
            'dois_pgto',
            'created_at',
        ]);

        $baseTotals = [
            'dinheiro' => 0.0,
            'maquina' => 0.0,
            'vale' => 0.0,
            'refeicao' => 0.0,
            'faturar' => 0.0,
        ];

        $groupedTotals = [];

        foreach ($payments as $payment) {
            $firstSale = optional($payment->vendas->first());
            $cashierId = $firstSale?->id_user_caixa;
            $unitId = $firstSale?->id_unidade;

            if (! $cashierId) {
                continue;
            }

            $groupKey = $cashierId . '-' . ($unitId ?? 'none');

            if (! isset($groupedTotals[$groupKey])) {
                $groupedTotals[$groupKey] = [
                    'cashier_id' => $cashierId,
                    'unit_id' => $unitId,
                    'totals' => $baseTotals,
                ];
            }

            foreach ($this->breakdownPayment($payment) as $type => $amount) {
                $groupedTotals[$groupKey]['totals'][$type] += $amount;
            }
        }

        $unitNameMap = $availableUnits
            ->mapWithKeys(fn ($unit) => [$unit['id'] => $unit['name']])
            ->all();

        $records = $closures
            ->map(function (CashierClosure $closure) use ($groupedTotals, $cashiers, $unitNameMap, $baseTotals) {
                $unitId = $closure->unit_id;
                $groupKey = $closure->user_id . '-' . ($unitId ?? 'none');
                $systemTotals = $groupedTotals[$groupKey]['totals'] ?? $baseTotals;

                $cashSystem = $systemTotals['dinheiro'] ?? 0.0;
                $cardSystem = $systemTotals['maquina'] ?? 0.0;
                $systemTotal = $cashSystem + $cardSystem;

                $cashClosure = (float) $closure->cash_amount;
                $cardClosure = (float) $closure->card_amount;
                $closureTotal = $cashClosure + $cardClosure;

                $discrepancy = round($systemTotal - $closureTotal, 2);

                $totalsRounded = array_map(fn ($value) => round((float) $value, 2), $systemTotals);

                return [
                    'id' => $closure->id,
                    'cashier_id' => $closure->user_id,
                    'cashier_name' => optional($cashiers->get($closure->user_id))->name
                        ?? 'Caixa #' . $closure->user_id,
                    'unit_id' => $unitId,
                    'unit_name' => $closure->unit_name
                        ?? ($unitId ? ($unitNameMap[$unitId] ?? ('Unidade #' . $unitId)) : '---'),
                    'closed_date' => $closure->closed_date?->toDateString(),
                    'closed_at' => optional($closure->closed_at)->toIso8601String(),
                    'discrepancy' => $discrepancy,
                    'totals' => $totalsRounded,
                    'closure' => [
                        'cash_amount' => round($cashClosure, 2),
                        'card_amount' => round($cardClosure, 2),
                        'total_amount' => round($closureTotal, 2),
                    ],
                ];
            })
            ->filter(fn (array $record) => abs($record['discrepancy']) >= 0.01)
            ->sortByDesc(fn (array $record) => abs($record['discrepancy']))
            ->values();

        return Inertia::render('Reports/CashDiscrepancies', [
            'records' => $records,
            'dateValue' => $dateValue,
            'filterUnits' => $availableUnits->values(),
            'cashiers' => $cashierOptions,
            'selectedUnitId' => $filterUnitId,
            'selectedCashierId' => $filterCashierId,
        ]);
    }

    public function period(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);

        $mode = $request->query('mode', 'month') === 'day' ? 'day' : 'month';
        $dateValue = $request->query('date');

        if ($mode === 'day') {
            $date = $this->parseDate($dateValue, 'Y-m-d', Carbon::today());
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
            $dateValue = $date->format('Y-m-d');
        } else {
            $month = $this->parseDate($dateValue, 'Y-m', Carbon::today()->startOfMonth());
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $dateValue = $month->format('Y-m');
        }

        $payments = $this->fetchPayments($start, $end, $filterUnitId);
        [$totals, $details, $chartData] = $this->summarizePayments($payments);
        [$totals, $details] = $this->applyGlobalValeTotals($start, $end, $totals, $details, $filterUnitId);
        $chartData = $this->buildChartData($totals);

        $dailyTotals = $payments
            ->groupBy(fn (VendaPagamento $payment) => $payment->created_at->format('Y-m-d'))
            ->map(function (Collection $group, string $date) {
                $carbon = Carbon::createFromFormat('Y-m-d', $date);

                return [
                    'date' => $date,
                    'label' => $carbon->translatedFormat('d/m/Y'),
                    'total' => round($group->sum('valor_total'), 2),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Reports/SalesPeriod', [
            'meta' => self::TYPE_META,
            'chartData' => $chartData,
            'totals' => $totals,
            'details' => $details,
            'dailyTotals' => $dailyTotals,
            'mode' => $mode,
            'dateValue' => $dateValue,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
            'unit' => $selectedUnit,
        ]);
    }

    public function detailed(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);

        $dateValue = $request->query('date');
        $date = $this->parseDate($dateValue, 'Y-m-d', Carbon::today());
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $dateValue = $date->format('Y-m-d');

        $payments = VendaPagamento::with(['vendas' => function ($query) use ($filterUnitId) {
            $query->orderBy('tb3_id')->select([
                'tb3_id',
                'tb4_id',
                'produto_nome',
                'valor_unitario',
                'quantidade',
                'valor_total',
                'data_hora',
                'id_comanda',
                'id_lanc',
            ]);

            if ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            }
        }])
            ->whereBetween('created_at', [$start, $end])
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->whereHas('vendas', function ($subQuery) use ($filterUnitId) {
                    $subQuery->where('id_unidade', $filterUnitId);
                });
            })
            ->orderByDesc('tb4_id')
            ->get([
                'tb4_id',
                'valor_total',
                'tipo_pagamento',
                'valor_pago',
                'troco',
                'dois_pgto',
                'created_at',
            ])
            ->values();

        $lancIds = $payments
            ->flatMap(fn (VendaPagamento $payment) => $payment->vendas->pluck('id_lanc'))
            ->filter()
            ->unique()
            ->values();

        $lancUsers = $lancIds->isNotEmpty()
            ? User::whereIn('id', $lancIds)->pluck('name', 'id')
            : collect();

        $payments = $payments
            ->map(function (VendaPagamento $payment) use ($lancUsers) {
                return [
                    'tb4_id' => $payment->tb4_id,
                    'valor_total' => (float) $payment->valor_total,
                    'tipo_pagamento' => $payment->tipo_pagamento,
                    'valor_pago' => $payment->valor_pago,
                    'troco' => $payment->troco,
                    'dois_pgto' => $payment->dois_pgto,
                    'created_at' => $payment->created_at->toIso8601String(),
                    'items' => $payment->vendas
                        ->map(function ($item) use ($lancUsers) {
                            return [
                                'tb3_id' => $item->tb3_id,
                                'produto_nome' => $item->produto_nome,
                                'quantidade' => $item->quantidade,
                                'valor_unitario' => $item->valor_unitario,
                                'valor_total' => $item->valor_total,
                                'data_hora' => optional($item->data_hora)->toIso8601String(),
                                'id_comanda' => $item->id_comanda,
                                'lanc_user_name' => $item->id_lanc ? ($lancUsers[$item->id_lanc] ?? null) : null,
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Reports/SalesDetailed', [
            'payments' => $payments,
            'dateValue' => $dateValue,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
            'unit' => $selectedUnit,
        ]);
    }

    public function lanchonete(Request $request): Response
    {
        $this->ensureManager($request);
        [$filterUnitId, $filterUnits, $selectedUnit] = $this->resolveReportUnit($request);
        $dateValue = $request->query('date');
        $date = $this->parseDate($dateValue, 'Y-m-d', Carbon::today());
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $dateValue = $date->format('Y-m-d');

        $rows = Venda::query()
            ->whereNotNull('id_comanda')
            ->whereBetween('id_comanda', [3000, 3100])
            ->when($filterUnitId, function ($query) use ($filterUnitId) {
                $query->where('id_unidade', $filterUnitId);
            })
            ->whereBetween('data_hora', [$start, $end])
            ->orderByDesc('data_hora')
            ->get([
                'tb3_id',
                'id_comanda',
                'produto_nome',
                'valor_unitario',
                'quantidade',
                'valor_total',
                'data_hora',
                'id_user_caixa',
                'id_lanc',
                'tipo_pago',
                'status_pago',
                'status',
                'id_unidade',
            ]);

        $userIds = $rows
            ->flatMap(fn (Venda $row) => [$row->id_user_caixa, $row->id_lanc])
            ->filter()
            ->unique()
            ->values();

        $users = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->pluck('name', 'id')
            : collect();

        $unitIds = $rows
            ->pluck('id_unidade')
            ->filter()
            ->unique()
            ->values();

        $units = $unitIds->isNotEmpty()
            ? Unidade::whereIn('tb2_id', $unitIds)->pluck('tb2_nome', 'tb2_id')
            : collect();

        $openComandas = [];
        $closedComandas = [];

        $grouped = $rows->groupBy('id_comanda');

        foreach ($grouped as $comanda => $items) {
            $first = $items->first();
            $status = (int) ($first->status ?? 0);
            $lastUpdate = $items->max('data_hora');
            $lastUpdate = $lastUpdate ? Carbon::parse($lastUpdate)->toIso8601String() : null;
            $unitName = $first?->id_unidade ? ($units[$first->id_unidade] ?? null) : null;

            $payload = [
                'comanda' => (int) $comanda,
                'status' => $status,
                'payment_type' => $first->tipo_pago,
                'status_paid' => (bool) $first->status_pago,
                'unit_name' => $unitName,
                'total' => round((float) $items->sum('valor_total'), 2),
                'quantity' => (int) $items->sum('quantidade'),
                'updated_at' => $lastUpdate,
                'items' => $items->map(function (Venda $item) use ($users) {
                    return [
                        'id' => $item->tb3_id,
                        'name' => $item->produto_nome,
                        'quantity' => (int) $item->quantidade,
                        'unit_price' => (float) $item->valor_unitario,
                        'total' => round((float) $item->valor_total, 2),
                        'launched_by' => $item->id_lanc ? ($users[$item->id_lanc] ?? null) : null,
                        'cashier' => $item->id_user_caixa ? ($users[$item->id_user_caixa] ?? null) : null,
                    ];
                })->values()->all(),
            ];

            if ($status === 0) {
                $openComandas[] = $payload;
            } else {
                $closedComandas[] = $payload;
            }
        }

        usort($openComandas, fn (array $a, array $b) => $a['comanda'] <=> $b['comanda']);
        usort($closedComandas, fn (array $a, array $b) => strcmp((string) $b['updated_at'], (string) $a['updated_at']));

        return Inertia::render('Reports/Lanchonete', [
            'unit' => [
                'id' => $selectedUnit['id'] ?? null,
                'name' => $selectedUnit['name'] ?? '---',
            ],
            'dateValue' => $dateValue,
            'openComandas' => $openComandas,
            'closedComandas' => $closedComandas,
            'filterUnits' => $filterUnits,
            'selectedUnitId' => $filterUnitId,
        ]);
    }

    public function control(Request $request): Response
    {
        $this->ensureManager($request);
        $user = $request->user();
        $availableUnits = $this->availableUnits($user);

        $requestedUnitId = $request->query('unit_id');
        $filterUnitId = $requestedUnitId !== null && $requestedUnitId !== ''
            ? (int) $requestedUnitId
            : null;

        if ($filterUnitId && !$availableUnits->contains(fn ($unit) => $unit['id'] === $filterUnitId)) {
            $filterUnitId = null;
        }

        $monthParam = $request->query('month');
        $yearParam = $request->query('year');
        $referenceMonth = Carbon::now()->startOfMonth();

        if ($monthParam) {
            $referenceMonth = $this->parseDate($monthParam, 'Y-m', $referenceMonth);
        } elseif ($yearParam && is_numeric($yearParam)) {
            $referenceMonth = $referenceMonth->copy()->year((int) $yearParam);
        }

        $start = $referenceMonth->copy()->startOfMonth();
        $end = $referenceMonth->copy()->endOfMonth();

        $payments = $this->fetchPayments($start, $end, $filterUnitId);
        $totalSales = (float) $payments->sum('valor_total');
        $globalValeTotals = $this->valeBreakdown($start, $end, null);
        $globalStandardVale = $globalValeTotals['vale'];
        $globalMealVale = $globalValeTotals['refeicao'];

        $valeTotals = $this->valeBreakdown($start, $end, $filterUnitId);
        $standardVale = $valeTotals['vale'];
        $mealVale = $valeTotals['refeicao'];
        $supplierExpensesQuery = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()]);
        if ($filterUnitId !== null) {
            $supplierExpensesQuery->where('unit_id', $filterUnitId);
        } else {
            $supplierExpensesQuery->whereNotNull('unit_id');
        }
        $supplierExpenses = (float) $supplierExpensesQuery->sum('amount');
        $netSales = max(0, $totalSales - $standardVale - $mealVale - $supplierExpenses);

        $totalPayrollGlobal = (float) User::sum('salario');
        $totalAdvancesGlobal = (float) SalaryAdvance::whereBetween('advance_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $netPayrollGlobal = max(0, $totalPayrollGlobal - $globalStandardVale - $globalMealVale - $totalAdvancesGlobal);

        $selectedUnit = $filterUnitId
            ? $availableUnits->firstWhere('id', $filterUnitId)
            : [
                'id' => null,
                'name' => 'Todas as unidades',
            ];

        $selectedYear = $start->year;
        $currentYear = Carbon::now()->year;
        $yearOptions = collect([$currentYear - 1, $currentYear])
            ->map(fn (int $year) => [
                'value' => (string) $year,
                'label' => (string) $year,
            ])
            ->values();

        $monthOptions = collect(range(1, 12))
            ->map(function (int $month) use ($selectedYear) {
                $carbon = Carbon::createFromDate($selectedYear, $month, 1);

                return [
                    'value' => $carbon->format('Y-m'),
                    'label' => mb_strtoupper($carbon->translatedFormat('M')),
                ];
            })
            ->values();

        return Inertia::render('Reports/ControlPanel', [
            'unit' => $selectedUnit,
            'filterUnits' => $availableUnits->values(),
            'selectedUnitId' => $filterUnitId,
            'selectedYear' => (string) $selectedYear,
            'selectedMonth' => $start->format('Y-m'),
            'monthOptions' => $monthOptions,
            'yearOptions' => $yearOptions,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->translatedFormat('d/m/Y') . ' - ' . $end->translatedFormat('d/m/Y'),
            ],
            'metrics' => [
                'total_sales' => round($totalSales, 2),
                'total_vale' => round($standardVale, 2),
                'total_refeicao' => round($mealVale, 2),
                'supplier_expenses' => round($supplierExpenses, 2),
                'net_sales' => round($netSales, 2),
                'total_advances' => round($totalAdvancesGlobal, 2),
                'total_payroll' => round($totalPayrollGlobal, 2),
                'net_payroll' => round($netPayrollGlobal, 2),
            ],
        ]);
    }

    private function ensureManager(Request $request): void
    {
        $user = $request->user();

        if (!$user || !in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }

    private function fetchPayments(Carbon $start, Carbon $end, ?int $unitId = null): Collection
    {
        $query = VendaPagamento::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($unitId) {
            $query->whereHas('vendas', function ($subQuery) use ($unitId) {
                $subQuery->where('id_unidade', $unitId);
            });
        }

        return $query
            ->orderByDesc('tb4_id')
            ->get([
                'tb4_id',
                'valor_total',
                'tipo_pagamento',
                'valor_pago',
                'troco',
                'dois_pgto',
                'created_at',
            ]);
    }

    private function summarizePayments(Collection $payments): array
    {
        $totals = [
            'dinheiro' => 0.0,
            'maquina' => 0.0,
            'vale' => 0.0,
            'refeicao' => 0.0,
            'faturar' => 0.0,
        ];
        $details = [
            'dinheiro' => [],
            'maquina' => [],
            'vale' => [],
            'refeicao' => [],
            'faturar' => [],
        ];

        foreach ($payments as $payment) {
            $type = $payment->tipo_pagamento;
            $base = [
                'tb4_id' => $payment->tb4_id,
                'tipo_pagamento' => $type,
                'valor_total' => (float) $payment->valor_total,
                'valor_pago' => $payment->valor_pago,
                'troco' => $payment->troco,
                'dois_pgto' => $payment->dois_pgto,
                'created_at' => $payment->created_at->toIso8601String(),
                'origin' => $type,
            ];

            if ($type === 'dinheiro') {
                $cardPortion = max((float) $payment->dois_pgto, 0);
                $cashPortion = max((float) $payment->valor_total - $cardPortion, 0);

                if ($cashPortion > 0) {
                    $totals['dinheiro'] += $cashPortion;
                    $details['dinheiro'][] = array_merge($base, [
                        'applied_total' => $cashPortion,
                    ]);
                }

                if ($cardPortion > 0) {
                    $totals['maquina'] += $cardPortion;
                    $details['maquina'][] = array_merge($base, [
                        'applied_total' => $cardPortion,
                    ]);
                }

                continue;
            }

            if (!isset($totals[$type])) {
                continue;
            }

            $totals[$type] += (float) $payment->valor_total;
            $details[$type][] = array_merge($base, [
                'applied_total' => (float) $payment->valor_total,
            ]);
        }

        $chartData = $this->buildChartData($totals);

        return [$totals, $details, $chartData];
    }

    private function parseDate(?string $value, string $format, Carbon $fallback): Carbon
    {
        if (!$value) {
            return $fallback;
        }

        try {
            return Carbon::createFromFormat($format, $value);
        } catch (InvalidFormatException $exception) {
            return $fallback;
        }
    }

    private function resolveUnitId(Request $request): int
    {
        $unit = $request->session()->get('active_unit');

        if (isset($unit['id'])) {
            return (int) $unit['id'];
        }

        return (int) ($request->user()?->tb2_id ?? 0);
    }

    private function valeBreakdown(Carbon $start, Carbon $end, ?int $unitId = null): array
    {
        $query = Venda::query()
            ->whereIn('tipo_pago', ['vale', 'refeicao'])
            ->whereBetween('data_hora', [$start, $end])
            ->selectRaw('tipo_pago as tipo, SUM(valor_total) as total')
            ->groupBy('tipo');

        if ($unitId) {
            $query->where('id_unidade', $unitId);
        }

        $rows = $query->get();

        $totals = [
            'vale' => 0.0,
            'refeicao' => 0.0,
        ];

        foreach ($rows as $row) {
            $tipo = $row->tipo === 'refeicao' ? 'refeicao' : 'vale';
            $totals[$tipo] += (float) $row->total;
        }

        return $totals;
    }

    private function applyGlobalValeTotals(Carbon $start, Carbon $end, array $totals, array $details, ?int $unitId = null): array
    {
        $valeIds = $this->valeSaleIds($start, $end, 'vale', $unitId);
        $refeicaoIds = $this->valeSaleIds($start, $end, 'refeicao', $unitId);

        $globalPayments = $this->fetchPayments($start, $end, $unitId);

        $valePayments = $valeIds->isEmpty()
            ? collect()
            : $globalPayments->filter(fn (VendaPagamento $payment) => $valeIds->contains($payment->tb4_id));

        $refeicaoPayments = $refeicaoIds->isEmpty()
            ? collect()
            : $globalPayments->filter(fn (VendaPagamento $payment) => $refeicaoIds->contains($payment->tb4_id));

        if ($valePayments->isNotEmpty()) {
            $valePayments = $valePayments->map(function (VendaPagamento $payment) {
                $cloned = clone $payment;
                $cloned->tipo_pagamento = 'vale';

                return $cloned;
            });

            [$valeTotals, $valeDetails] = $this->summarizePayments($valePayments);
            $totals['vale'] = $valeTotals['vale'];
            $details['vale'] = $valeDetails['vale'];
        } else {
            $totals['vale'] = 0.0;
            $details['vale'] = [];
        }

        if ($refeicaoPayments->isNotEmpty()) {
            $refeicaoPayments = $refeicaoPayments->map(function (VendaPagamento $payment) {
                $cloned = clone $payment;
                $cloned->tipo_pagamento = 'refeicao';

                return $cloned;
            });

            [$refTotals, $refDetails] = $this->summarizePayments($refeicaoPayments);
            $totals['refeicao'] = $refTotals['refeicao'];
            $details['refeicao'] = $refDetails['refeicao'];
        } else {
            $totals['refeicao'] = 0.0;
            $details['refeicao'] = [];
        }

        return [$totals, $details];
    }

    private function valeSaleIds(Carbon $start, Carbon $end, string $type, ?int $unitId = null): Collection
    {
        $query = Venda::query()
            ->where('tipo_pago', $type)
            ->whereBetween('data_hora', [$start, $end])
            ->select('tb4_id');

        if ($unitId) {
            $query->where('id_unidade', $unitId);
        }

        return $query->pluck('tb4_id')->unique()->values();
    }

    private function buildChartData(array $totals, ?array $meta = null): Collection
    {
        $meta = $meta ?? self::TYPE_META;

        return collect($meta)
            ->map(function (array $metaData, string $type) use ($totals) {
                return [
                    'type' => $type,
                    'label' => $metaData['label'],
                    'color' => $metaData['color'],
                    'total' => round($totals[$type] ?? 0, 2),
                ];
            })
            ->values();
    }

    private function breakdownPayment(VendaPagamento $payment): array
    {
        $type = $payment->tipo_pagamento;

        if ($type === 'dinheiro') {
            $cardPortion = max((float) $payment->dois_pgto, 0);
            $cashPortion = max((float) $payment->valor_total - $cardPortion, 0);
            $entries = [];
            if ($cashPortion > 0) {
                $entries['dinheiro'] = $cashPortion;
            }
            if ($cardPortion > 0) {
                $entries['maquina'] = $cardPortion;
            }

            return $entries;
        }

        if (!array_key_exists($type, self::TYPE_META)) {
            return [];
        }

        $amount = max((float) $payment->valor_total, 0);

        return $amount > 0 ? [$type => $amount] : [];
    }

    private function resolveDateRange(Request $request): array
    {
        $defaultStart = Carbon::today()->startOfMonth();
        $defaultEnd = Carbon::today()->endOfMonth();

        $startInput = $request->query('start_date');
        $endInput = $request->query('end_date');

        $start = $this->parseDate($startInput, 'Y-m-d', $defaultStart)->startOfDay();
        $end = $this->parseDate($endInput, 'Y-m-d', $defaultEnd)->endOfDay();

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, $start->toDateString(), $end->toDateString()];
    }

    private function resolveUnitPayload(int $unitId): array
    {
        if ($unitId <= 0) {
            return [
                'id' => $unitId,
                'name' => '---',
            ];
        }

        $unit = Unidade::find($unitId, ['tb2_id', 'tb2_nome']);

        return [
            'id' => $unit?->tb2_id ?? $unitId,
            'name' => $unit?->tb2_nome ?? '---',
        ];
    }

    private function resolveReportUnit(Request $request): array
    {
        $availableUnits = $this->availableUnits($request->user());
        $requestedUnitId = $request->query('unit_id', null);

        if ($requestedUnitId === null) {
            $filterUnitId = $this->resolveUnitId($request);
        } else {
            $filterUnitId =
                ($requestedUnitId !== '' && $requestedUnitId !== 'all')
                    ? (int) $requestedUnitId
                    : null;
        }

        if (! $filterUnitId) {
            $filterUnitId = null;
        }

        if ($filterUnitId && ! $availableUnits->contains(fn ($unit) => $unit['id'] === $filterUnitId)) {
            $filterUnitId = null;
        }

        $selectedUnit = $filterUnitId
            ? $availableUnits->firstWhere('id', $filterUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];

        return [$filterUnitId, $availableUnits->values(), $selectedUnit];
    }

    private function availableUnits(User $user): Collection
    {
        $units = $user->units()
            ->select('tb2_unidades.tb2_id', 'tb2_unidades.tb2_nome')
            ->get();

        if ($user->tb2_id && !$units->contains('tb2_id', $user->tb2_id)) {
            $primary = Unidade::find($user->tb2_id, ['tb2_id', 'tb2_nome']);
            if ($primary) {
                $units->push($primary);
            }
        }

        return $units
            ->map(fn ($unit) => [
                'id' => (int) $unit->tb2_id,
                'name' => $unit->tb2_nome,
            ])
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

}
