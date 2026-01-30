<?php

namespace App\Http\Controllers;

use App\Models\CashierClosure;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function openComandas(Request $request): JsonResponse
    {
        $unit = $request->session()->get('active_unit');
        $unitId = is_array($unit)
            ? ($unit['id'] ?? $unit['tb2_id'] ?? null)
            : (is_object($unit) ? ($unit->id ?? $unit->tb2_id ?? null) : null);
        $unitId = $unitId ?? ($request->user()?->tb2_id);

        $baseQuery = Venda::query()
            ->whereNotNull('id_comanda')
            ->where('status', 0);

        if ($unitId) {
            $baseQuery->where('id_unidade', $unitId);
        }

        $open = (clone $baseQuery)
            ->selectRaw('COUNT(DISTINCT id_comanda) as total_comandas, COALESCE(SUM(valor_total), 0) as total_amount')
            ->first();

        $comandas = (clone $baseQuery)
            ->selectRaw('id_comanda, COALESCE(SUM(valor_total), 0) as total_amount')
            ->groupBy('id_comanda')
            ->orderBy('id_comanda')
            ->get()
            ->map(function ($row) {
                return [
                    'codigo' => (int) $row->id_comanda,
                    'total' => (float) $row->total_amount,
                ];
            });

        return response()->json([
            'total_comandas' => (int) ($open->total_comandas ?? 0),
            'total_amount' => (float) ($open->total_amount ?? 0),
            'comandas' => $comandas,
        ]);
    }

    public function restrictions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || (int) $user->funcao !== 3) {
            return response()->json([
                'requires_closure' => false,
                'pending_closure_date' => null,
                'pending_comandas' => [],
            ]);
        }

        $unit = $request->session()->get('active_unit');
        $restrictions = $this->resolveCashierRestrictions($user, $unit);

        return response()->json([
            'requires_closure' => $restrictions['requires_closure'],
            'pending_closure_date' => $restrictions['pending_closure_date'],
            'pending_comandas' => $restrictions['pending_comandas'],
        ]);
    }

    public function comandaItems(int $codigo): JsonResponse
    {
        $items = $this->getComandaItems($codigo);

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $unit = $request->session()->get('active_unit');

        if (!$unit) {
            throw ValidationException::withMessages([
                'unit' => 'Selecione uma unidade para registrar as vendas.',
            ]);
        }

        $unitId = is_array($unit)
            ? ($unit['id'] ?? $unit['tb2_id'] ?? null)
            : (is_object($unit) ? ($unit->id ?? $unit->tb2_id ?? null) : null);
        $unitId = $unitId ?? ($user?->tb2_id);
        $unitName = is_array($unit)
            ? ($unit['name'] ?? $unit['tb2_nome'] ?? null)
            : (is_object($unit) ? ($unit->name ?? $unit->tb2_nome ?? null) : null);
        $unitAddress = is_array($unit)
            ? ($unit['address'] ?? $unit['tb2_endereco'] ?? null)
            : (is_object($unit) ? ($unit->address ?? $unit->tb2_endereco ?? null) : null);
        $unitCnpj = is_array($unit)
            ? ($unit['cnpj'] ?? $unit['tb2_cnpj'] ?? null)
            : (is_object($unit) ? ($unit->cnpj ?? $unit->tb2_cnpj ?? null) : null);

        if ($unitId && (! $unitName || ! $unitAddress || ! $unitCnpj)) {
            $unitRecord = Unidade::select('tb2_id', 'tb2_nome', 'tb2_endereco', 'tb2_cnpj')->find($unitId);
            if ($unitRecord) {
                $unitName = $unitName ?? $unitRecord->tb2_nome;
                $unitAddress = $unitAddress ?? $unitRecord->tb2_endereco;
                $unitCnpj = $unitCnpj ?? $unitRecord->tb2_cnpj;
            }
        }

        $validated = $request->validate([
            'items' => ['array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:tb1_produto,tb1_id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:1000'],
            'tipo_pago' => [
                'required',
                'string',
                Rule::in(['maquina', 'dinheiro', 'vale', 'refeicao', 'faturar']),
            ],
            'vale_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'vale_type' => ['nullable', 'string', Rule::in(['vale', 'refeicao'])],
            'comanda_codigo' => ['nullable', 'integer', 'between:3000,3100'],
        ]);

        $comandaCodigo = $validated['comanda_codigo'] ?? null;
        $isComandaSale = $comandaCodigo !== null;
        $pendingComandas = [];
        $requiresClosure = false;
        $isPendingComanda = false;
        if ((int) $user->funcao === 3) {
            $restrictions = $this->resolveCashierRestrictions($user, $unit);
            $pendingComandas = $restrictions['pending_comandas'];
            $requiresClosure = $restrictions['requires_closure'];

            if (! empty($pendingComandas)) {
                if (! $comandaCodigo || ! in_array($comandaCodigo, $pendingComandas, true)) {
                    throw ValidationException::withMessages([
                        'comanda_codigo' => 'Ha comanda pendente de dia anterior. Receba apenas a comanda pendente antes de novas vendas.',
                    ]);
                }
                $isPendingComanda = true;
            }

            if ($requiresClosure && ! $isComandaSale) {
                throw ValidationException::withMessages([
                    'items' => 'Existe fechamento pendente de caixa. Receba uma comanda em aberto antes de registrar novas vendas.',
                ]);
            }
        }

        $selectedValeType = $validated['vale_type'] ?? 'vale';
        $finalPaymentType = $validated['tipo_pago'];
        $isValePayment = in_array($finalPaymentType, ['vale', 'refeicao'], true);

        if ($finalPaymentType === 'vale' && $selectedValeType === 'refeicao') {
            $finalPaymentType = 'refeicao';
        }

        if ($finalPaymentType === 'refeicao') {
            $selectedValeType = 'refeicao';
        }

        if ($isValePayment && empty($validated['vale_user_id'])) {
            throw ValidationException::withMessages([
                'vale_user_id' => 'Selecione o colaborador responsavel pelo vale.',
            ]);
        }

        if ($validated['tipo_pago'] === 'vale' && empty($validated['vale_type'])) {
            throw ValidationException::withMessages([
                'vale_type' => 'Selecione se o vale e Refeicao ou Vale tradicional.',
            ]);
        }

        $groupedItems = [];
        foreach (($validated['items'] ?? []) as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $groupedItems[$productId] = ($groupedItems[$productId] ?? 0) + $quantity;
        }

        $dateTime = Carbon::now();
        $monthStart = $dateTime->copy()->startOfMonth();
        $monthEnd = $dateTime->copy()->endOfMonth();
        $isPaid = in_array($finalPaymentType, ['maquina', 'dinheiro'], true);
        $valeUserId = $isValePayment ? $validated['vale_user_id'] : null;
        $valeUserName = null;
        $valeUser = null;

        if ($valeUserId) {
            $valeUser = User::select('id', 'name', 'vr_cred')->find($valeUserId);
            $valeUserName = $valeUser?->name;
        }

        $itemsPayload = [];
        $requiresVrEligible = $finalPaymentType === 'refeicao';
        $extraItems = [];

        if ($comandaCodigo) {
            $comandaItems = Venda::query()
                ->where('id_comanda', $comandaCodigo)
                ->where('status', 0)
                ->get([
                    'tb1_id',
                    'produto_nome',
                    'valor_unitario',
                    'quantidade',
                ]);

            if ($comandaItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'comanda_codigo' => 'Comanda informada nao possui itens em aberto.',
                ]);
            }

            $comandaPayload = $comandaItems
                ->groupBy('tb1_id')
                ->map(function ($group) {
                    $first = $group->first();
                    $quantity = $group->sum('quantidade');
                    $unitPrice = (float) $first->valor_unitario;

                    return [
                        'product_id' => $first->tb1_id,
                        'product_name' => $first->produto_nome,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'subtotal' => $quantity * $unitPrice,
                    ];
                })
                ->keyBy('product_id');

            $finalItemsMap = $comandaPayload->map(fn ($item) => $item)->all();

            if (! empty($groupedItems)) {
                $products = Produto::query()
                    ->whereIn('tb1_id', array_keys($groupedItems))
                    ->get(['tb1_id', 'tb1_nome', 'tb1_vlr_venda', 'tb1_vr_credit'])
                    ->keyBy('tb1_id');

                foreach ($groupedItems as $productId => $requestedQuantity) {
                    $requestedQuantity = (int) $requestedQuantity;
                    $existingItem = $comandaPayload->get($productId);
                    $existingQuantity = $existingItem ? (int) $existingItem['quantity'] : 0;

                    if ($requestedQuantity < $existingQuantity) {
                        throw ValidationException::withMessages([
                            'items' => 'Nao e possivel reduzir itens da comanda no fechamento.',
                        ]);
                    }

                    if ($existingItem) {
                        if ($requestedQuantity > $existingQuantity) {
                            $extraQuantity = $requestedQuantity - $existingQuantity;
                            $unitPrice = (float) $existingItem['unit_price'];
                            $extraItems[] = [
                                'product_id' => $existingItem['product_id'],
                                'product_name' => $existingItem['product_name'],
                                'unit_price' => $unitPrice,
                                'quantity' => $extraQuantity,
                                'subtotal' => round($unitPrice * $extraQuantity, 2),
                            ];

                            $finalItemsMap[$productId]['quantity'] = $requestedQuantity;
                            $finalItemsMap[$productId]['subtotal'] = round($unitPrice * $requestedQuantity, 2);
                        }
                    } else {
                        $product = $products->get($productId);

                        if (! $product) {
                            continue;
                        }

                        $unitPrice = (float) $product->tb1_vlr_venda;
                        $total = round($unitPrice * $requestedQuantity, 2);
                        $finalItemsMap[$productId] = [
                            'product_id' => $product->tb1_id,
                            'product_name' => $product->tb1_nome,
                            'unit_price' => $unitPrice,
                            'quantity' => $requestedQuantity,
                            'subtotal' => $total,
                        ];
                        $extraItems[] = [
                            'product_id' => $product->tb1_id,
                            'product_name' => $product->tb1_nome,
                            'unit_price' => $unitPrice,
                            'quantity' => $requestedQuantity,
                            'subtotal' => $total,
                        ];
                    }
                }
            }

            $itemsPayload = array_values($finalItemsMap);

            if ($requiresVrEligible) {
                $eligibility = Produto::query()
                    ->whereIn('tb1_id', array_column($itemsPayload, 'product_id'))
                    ->pluck('tb1_vr_credit', 'tb1_id');

                foreach ($itemsPayload as $payload) {
                    $isEligible = (bool) ($eligibility[$payload['product_id']] ?? false);
                    if (! $isEligible) {
                        throw ValidationException::withMessages([
                            'comanda_codigo' => sprintf('O produto %s nao esta liberado para VR Credito.', $payload['product_name']),
                        ]);
                    }
                }
            }
        } else {
            if (empty($groupedItems)) {
                throw ValidationException::withMessages([
                    'items' => 'Informe ao menos um item ou selecione uma comanda.',
                ]);
            }

            foreach ($groupedItems as $productId => $quantity) {
                $product = Produto::select('tb1_id', 'tb1_nome', 'tb1_vlr_venda', 'tb1_vr_credit')->findOrFail($productId);

                if ($requiresVrEligible && ! $product->tb1_vr_credit) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('O produto %s nAo estA? liberado para VR CrA(c)dito.', $product->tb1_nome),
                    ]);
                }
                $unitPrice = (float) $product->tb1_vlr_venda;
                $total = round($unitPrice * $quantity, 2);

                $itemsPayload[] = [
                    'product_id' => $product->tb1_id,
                    'product_name' => $product->tb1_nome,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $total,
                ];
            }
        }

        $totalValue = collect($itemsPayload)->sum('subtotal');
        $valorPago = isset($validated['valor_pago']) ? (float) $validated['valor_pago'] : null;

        if ($finalPaymentType === 'refeicao' && $valeUserId) {
            if (! $valeUser) {
                throw ValidationException::withMessages([
                    'vale_user_id' => 'Colaborador selecionado nao foi encontrado.',
                ]);
            }

            $monthlyUsage = Venda::query()
                ->where('tipo_pago', 'refeicao')
                ->where('id_user_vale', $valeUserId)
                ->whereBetween('data_hora', [$monthStart, $monthEnd])
                ->sum('valor_total');

            $availableBalance = max(0, (float) $valeUser->vr_cred - (float) $monthlyUsage);

            if ($totalValue > $availableBalance) {
                throw ValidationException::withMessages([
                    'vale_user_id' => sprintf(
                        'Saldo de refeicao insuficiente. Disponivel: R$ %s',
                        number_format($availableBalance, 2, ',', '.')
                    ),
                ]);
            }
        }

        if ($finalPaymentType === 'dinheiro' && ($valorPago === null || $valorPago <= 0)) {
            throw ValidationException::withMessages([
                'valor_pago' => 'Informe o valor recebido em dinheiro.',
            ]);
        }

        if ($finalPaymentType !== 'dinheiro') {
            $valorPago = null;
        }

        $troco = 0;
        $cardComplement = 0;
        if ($finalPaymentType === 'dinheiro' && $valorPago !== null) {
            if ($valorPago >= $totalValue) {
                $troco = round($valorPago - $totalValue, 2);
            } else {
                $cardComplement = round($totalValue - $valorPago, 2);
            }
        }

        $payment = VendaPagamento::create([
            'valor_total' => $totalValue,
            'tipo_pagamento' => $finalPaymentType,
            'valor_pago' => $valorPago,
            'troco' => $troco,
            'dois_pgto' => $cardComplement,
        ]);

        if ($comandaCodigo) {
            Venda::query()
                ->where('id_comanda', $comandaCodigo)
                ->where('status', 0)
                ->update([
                    'tb4_id' => $payment->tb4_id,
                    'id_user_caixa' => $user->id,
                    'id_user_vale' => $valeUserId,
                    'tipo_pago' => $finalPaymentType,
                    'status_pago' => $isPaid,
                    'status' => 1,
                    'data_hora' => $dateTime,
                ]);

            foreach ($extraItems as $item) {
                Venda::create([
                    'tb4_id' => $payment->tb4_id,
                    'tb1_id' => $item['product_id'],
                    'id_comanda' => $comandaCodigo,
                    'produto_nome' => $item['product_name'],
                    'valor_unitario' => $item['unit_price'],
                    'quantidade' => $item['quantity'],
                    'valor_total' => $item['subtotal'],
                    'data_hora' => $dateTime,
                    'id_user_caixa' => $user->id,
                    'id_user_vale' => $valeUserId,
                    'id_lanc' => $user->id,
                    'id_unidade' => $unitId,
                    'tipo_pago' => $finalPaymentType,
                    'status_pago' => $isPaid,
                    'status' => 1,
                ]);
            }
        } else {
            foreach ($itemsPayload as $item) {
                Venda::create([
                    'tb4_id' => $payment->tb4_id,
                    'tb1_id' => $item['product_id'],
                    'produto_nome' => $item['product_name'],
                    'valor_unitario' => $item['unit_price'],
                    'quantidade' => $item['quantity'],
                    'valor_total' => $item['subtotal'],
                    'data_hora' => $dateTime,
                    'id_user_caixa' => $user->id,
                    'id_user_vale' => $valeUserId,
                    'id_unidade' => $unitId,
                    'tipo_pago' => $finalPaymentType,
                    'status_pago' => $isPaid,
                    'status' => 1,
                ]);
            }
        }

        return response()->json([
            'sale' => [
                'items' => $itemsPayload,
                'total' => $totalValue,
                'date_time' => $dateTime->toIso8601String(),
                'tipo_pago' => $finalPaymentType,
                'status_pago' => $isPaid,
                'cashier_name' => $user->name,
                'unit_name' => $unitName,
                'unit_address' => $unitAddress,
                'unit_cnpj' => $unitCnpj,
                'vale_user_name' => $valeUserName,
                'vale_type' => $isValePayment ? $selectedValeType : null,
                'payment' => [
                    'id' => $payment->tb4_id,
                    'valor_total' => $payment->valor_total,
                    'valor_pago' => $payment->valor_pago,
                    'troco' => $payment->troco,
                    'dois_pgto' => $payment->dois_pgto,
                    'tipo_pagamento' => $payment->tipo_pagamento,
                ],
            ],
        ]);
    }

    public function addComandaItem(Request $request, int $codigo): JsonResponse
    {
        $user = $request->user();
        $unit = $request->session()->get('active_unit');
        $unitId = is_array($unit)
            ? ($unit['id'] ?? $unit['tb2_id'] ?? null)
            : (is_object($unit) ? ($unit->id ?? $unit->tb2_id ?? null) : null);
        $unitId = $unitId ?? ($user?->tb2_id);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:tb1_produto,tb1_id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'access_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($codigo < 3000 || $codigo > 3100) {
            return response()->json(['message' => 'Codigo de comanda invalido (3000-3100).'], 422);
        }

        $product = Produto::select('tb1_id', 'tb1_nome', 'tb1_vlr_venda')->findOrFail($validated['product_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);
        $price = (float) $product->tb1_vlr_venda;
        $total = round($price * $quantity, 2);

        // Upsert para manter um registro por produto/comanda em aberto.
        $existing = Venda::query()
            ->where('id_comanda', $codigo)
            ->where('tb1_id', $product->tb1_id)
            ->where('status', 0)
            ->first();

        if ($existing) {
            $newQuantity = $existing->quantidade + $quantity;
            $existing->update([
                'quantidade' => $newQuantity,
                'valor_total' => round($price * $newQuantity, 2),
                'data_hora' => Carbon::now(),
                'id_lanc' => $validated['access_user_id'],
                'id_user_caixa' => null,
            ]);
        } else {
            Venda::create([
                'tb1_id' => $product->tb1_id,
                'id_comanda' => $codigo,
                'produto_nome' => $product->tb1_nome,
                'valor_unitario' => $price,
                'quantidade' => $quantity,
                'valor_total' => $total,
                'data_hora' => Carbon::now(),
                'id_user_caixa' => null,
                'id_user_vale' => null,
                'id_lanc' => $validated['access_user_id'],
                'id_unidade' => $unitId,
                'tipo_pago' => 'faturar',
                'status_pago' => false,
                'status' => 0,
            ]);
        }

        $items = $this->getComandaItems($codigo);

        return response()->json([
            'items' => $items,
        ]);
    }

    public function updateComandaItem(Request $request, int $codigo, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000'],
            'access_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $record = Venda::query()
            ->where('id_comanda', $codigo)
            ->where('tb1_id', $productId)
            ->where('status', 0)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Item nao encontrado na comanda.'], 404);
        }

        if ($validated['quantity'] === 0) {
            $record->delete();
        } else {
            $record->update([
                'quantidade' => $validated['quantity'],
                'valor_total' => round($record->valor_unitario * $validated['quantity'], 2),
                'data_hora' => Carbon::now(),
                'id_lanc' => $validated['access_user_id'] ?? $record->id_lanc,
                'id_user_caixa' => null,
            ]);
        }

        $items = $this->getComandaItems($codigo);

        return response()->json(['items' => $items]);
    }

    private function resolveCashierRestrictions(User $user, $unit): array
    {
        $unitId = is_array($unit)
            ? ($unit['id'] ?? $unit['tb2_id'] ?? null)
            : (is_object($unit) ? ($unit->id ?? $unit->tb2_id ?? null) : null);
        $unitId = $unitId ?? ($user?->tb2_id);

        if (! $unitId) {
            return [
                'unit_id' => null,
                'pending_comandas' => [],
                'pending_closure_date' => null,
                'requires_closure' => false,
            ];
        }

        $today = Carbon::today();
        $pendingComandas = Venda::query()
            ->whereNotNull('id_comanda')
            ->whereBetween('id_comanda', [3000, 3100])
            ->where('status', 0)
            ->where('id_unidade', $unitId)
            ->whereDate('data_hora', '<', $today)
            ->pluck('id_comanda')
            ->unique()
            ->values()
            ->map(fn ($value) => (int) $value)
            ->all();

        $pendingClosureDate = $this->resolvePendingClosureDate($user, $unitId, $today);

        return [
            'unit_id' => $unitId,
            'pending_comandas' => $pendingComandas,
            'pending_closure_date' => $pendingClosureDate ? $pendingClosureDate->toDateString() : null,
            'requires_closure' => $pendingClosureDate !== null,
        ];
    }

    private function resolvePendingClosureDate(User $user, int $unitId, Carbon $today): ?Carbon
    {
        $lastSaleDate = VendaPagamento::query()
            ->whereHas('vendas', function ($query) use ($user, $unitId) {
                $query->where('id_user_caixa', $user->id)
                    ->where('id_unidade', $unitId)
                    ->where('status', 1);
            })
            ->whereDate('created_at', '<', $today)
            ->latest('created_at')
            ->value('created_at');

        if (! $lastSaleDate) {
            return null;
        }

        $lastSaleDay = Carbon::parse($lastSaleDate)->startOfDay();
        $hasClosure = CashierClosure::where('user_id', $user->id)
            ->whereDate('closed_date', $lastSaleDay)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
            })
            ->exists();

        return $hasClosure ? null : $lastSaleDay;
    }

    private function getComandaItems(int $codigo): array
    {
        $items = Venda::query()
            ->where('id_comanda', $codigo)
            ->where('status', 0)
            ->get(['tb1_id', 'produto_nome', 'valor_unitario', 'quantidade', 'id_lanc']);

        if ($items->isEmpty()) {
            return [];
        }

        $lancIds = $items->pluck('id_lanc')->filter()->unique()->values();
        $lancUsers = $lancIds->isNotEmpty()
            ? User::whereIn('id', $lancIds)->pluck('name', 'id')
            : collect();

        return $items
            ->groupBy('tb1_id')
            ->map(function ($group) use ($lancUsers) {
                $first = $group->first();
                $quantity = $group->sum('quantidade');
                $price = (float) $first->valor_unitario;

                return [
                    'id' => $first->tb1_id,
                    'name' => $first->produto_nome,
                    'price' => $price,
                    'quantity' => $quantity,
                    'lanc_user_id' => $first->id_lanc,
                    'lanc_user_name' => $first->id_lanc ? ($lancUsers[$first->id_lanc] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }
}
