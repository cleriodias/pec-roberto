<?php

namespace App\Http\Controllers;

use App\Models\ProductDiscard;
use App\Models\Produto;
use App\Support\ManagementScope;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductDiscardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isMaster = ManagementScope::isMaster($user);
        $activeUnit = $request->session()->get('active_unit');
        $unitId = is_array($activeUnit)
            ? ($activeUnit['id'] ?? $activeUnit['tb2_id'] ?? null)
            : (is_object($activeUnit) ? ($activeUnit->id ?? $activeUnit->tb2_id ?? null) : null);
        $unitId = $unitId ?? $user->tb2_id;

        $filterUnits = collect();
        $selectedDate = null;
        $selectedUnitId = null;

        $recentQuery = ProductDiscard::query()
            ->with([
                'product:tb1_id,tb1_nome,tb1_codbar,tb1_vlr_venda',
                'unit:tb2_id,tb2_nome',
            ]);

        if ($isMaster) {
            $filterUnits = ManagementScope::managedUnits($user, ['tb2_id', 'tb2_nome'])
                ->map(fn ($unit) => [
                    'id' => (int) $unit->tb2_id,
                    'name' => $unit->tb2_nome,
                ])
                ->values();

            $requestedDate = (string) $request->query('date', Carbon::now('America/Sao_Paulo')->toDateString());
            $selectedDate = Carbon::now('America/Sao_Paulo')->toDateString();
            $selectedDateRangeStart = null;
            $selectedDateRangeEnd = null;

            if ($requestedDate !== '') {
                try {
                    $selectedDateCarbon = Carbon::createFromFormat('Y-m-d', $requestedDate, 'America/Sao_Paulo');
                    $selectedDate = $selectedDateCarbon->toDateString();
                    $selectedDateRangeStart = $selectedDateCarbon->copy()->startOfDay()->utc();
                    $selectedDateRangeEnd = $selectedDateCarbon->copy()->endOfDay()->utc();
                } catch (\Throwable) {
                    $selectedDate = Carbon::now('America/Sao_Paulo')->toDateString();
                    $selectedDateRangeStart = Carbon::now('America/Sao_Paulo')->startOfDay()->utc();
                    $selectedDateRangeEnd = Carbon::now('America/Sao_Paulo')->endOfDay()->utc();
                }
            }

            if ($selectedDateRangeStart === null || $selectedDateRangeEnd === null) {
                $selectedDateCarbon = Carbon::createFromFormat('Y-m-d', $selectedDate, 'America/Sao_Paulo');
                $selectedDateRangeStart = $selectedDateCarbon->copy()->startOfDay()->utc();
                $selectedDateRangeEnd = $selectedDateCarbon->copy()->endOfDay()->utc();
            }

            $requestedUnitId = $request->query('unit_id');
            if ($requestedUnitId !== null && $requestedUnitId !== '' && $requestedUnitId !== 'all') {
                $candidateUnitId = (int) $requestedUnitId;

                if ($filterUnits->contains(fn (array $unit) => $unit['id'] === $candidateUnitId)) {
                    $selectedUnitId = $candidateUnitId;
                }
            }

            $recentQuery
                ->whereBetween('created_at', [$selectedDateRangeStart, $selectedDateRangeEnd])
                ->when($selectedUnitId, fn ($query) => $query->where('unit_id', $selectedUnitId))
                ->orderByDesc('created_at');
        } else {
            $recentQuery
                ->where('user_id', $user->id)
                ->when($unitId, function ($query) use ($unitId) {
                    $query->where(function ($subQuery) use ($unitId) {
                        $subQuery->where('unit_id', $unitId)
                            ->orWhereNull('unit_id');
                    });
                })
                ->orderByDesc('created_at')
                ->limit(15);
        }

        $recent = $recentQuery
            ->get()
            ->map(function (ProductDiscard $discard) {
                return [
                    'id' => $discard->id,
                    'quantity' => $discard->quantity,
                    'unit_price' => $discard->unit_price,
                    'created_at' => $discard->created_at->toIso8601String(),
                    'unit' => $discard->unit
                        ? [
                            'id' => $discard->unit->tb2_id,
                            'name' => $discard->unit->tb2_nome,
                        ]
                        : null,
                    'product' => $discard->product
                        ? [
                            'id' => $discard->product->tb1_id,
                            'name' => $discard->product->tb1_nome,
                            'barcode' => $discard->product->tb1_codbar,
                        ]
                        : null,
                ];
            });

        return Inertia::render('Products/ProductDiscard', [
            'recentDiscards' => $recent,
            'filterUnits' => $filterUnits,
            'selectedDate' => $selectedDate,
            'selectedUnitId' => $selectedUnitId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                'product_id' => [
                    'required',
                    'integer',
                    'exists:tb1_produto,tb1_id',
                ],
                'quantity' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],
                'unit_price' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
            ],
            [
                'product_id.required' => 'Selecione um produto.',
                'product_id.integer' => 'O produto selecionado é inválido.',
                'product_id.exists' => 'O produto selecionado não foi encontrado.',
                'quantity.required' => 'Informe a quantidade descartada.',
                'quantity.numeric' => 'A quantidade deve ser um número válido.',
                'quantity.min' => 'A quantidade deve ser maior que zero.',
                'unit_price.required' => 'Informe o valor unitário.',
                'unit_price.numeric' => 'O valor unitário deve ser um número válido.',
                'unit_price.min' => 'O valor unitário não pode ser negativo.',
            ],
        );

        $activeUnit = $request->session()->get('active_unit');
        $unitId = is_array($activeUnit)
            ? ($activeUnit['id'] ?? $activeUnit['tb2_id'] ?? null)
            : (is_object($activeUnit) ? ($activeUnit->id ?? $activeUnit->tb2_id ?? null) : null);
        $unitId = $unitId ?? $request->user()->tb2_id;

        $product = Produto::findOrFail($data['product_id']);

        if ((int) $product->tb1_tipo !== 1) {
            return redirect()
                ->back()
                ->withErrors([
                    'product_id' => 'Apenas produtos do tipo balança podem ser descartados.',
                ]);
        }

        ProductDiscard::create([
            'product_id' => $product->tb1_id,
            'user_id' => $request->user()->id,
            'unit_id' => $unitId,
            'quantity' => $data['quantity'],
            'unit_price' => round((float) $data['unit_price'], 2),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Descarte registrado com sucesso.');
    }

    public function update(Request $request, ProductDiscard $discard): RedirectResponse
    {
        $actingUser = $request->user();

        if (! $actingUser || ! ManagementScope::isMaster($actingUser)) {
            abort(403);
        }

        $data = $request->validate(
            [
                'quantity' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],
                'unit_price' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
            ],
            [
                'quantity.required' => 'Informe a quantidade descartada.',
                'quantity.numeric' => 'A quantidade deve ser um numero valido.',
                'quantity.min' => 'A quantidade deve ser maior que zero.',
                'unit_price.required' => 'Informe o valor unitario.',
                'unit_price.numeric' => 'O valor unitario deve ser um numero valido.',
                'unit_price.min' => 'O valor unitario nao pode ser negativo.',
            ],
        );

        $discard->update([
            'quantity' => $data['quantity'],
            'unit_price' => round((float) $data['unit_price'], 2),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Descarte atualizado com sucesso.');
    }

    public function destroy(Request $request, ProductDiscard $discard): RedirectResponse
    {
        $actingUser = $request->user();

        if (! $actingUser || ! ManagementScope::canManageDiscard($actingUser, $discard)) {
            abort(403);
        }

        $discard->delete();

        return redirect()
            ->back()
            ->with('success', 'Descarte excluido com sucesso.');
    }
}
