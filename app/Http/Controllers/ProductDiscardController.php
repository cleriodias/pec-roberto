<?php

namespace App\Http\Controllers;

use App\Models\ProductDiscard;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductDiscardController extends Controller
{
    public function index(Request $request): Response
    {
        $activeUnit = $request->session()->get('active_unit');
        $unitId = is_array($activeUnit)
            ? ($activeUnit['id'] ?? $activeUnit['tb2_id'] ?? null)
            : (is_object($activeUnit) ? ($activeUnit->id ?? $activeUnit->tb2_id ?? null) : null);
        $unitId = $unitId ?? $request->user()->tb2_id;

        $recent = ProductDiscard::query()
            ->with('product:tb1_id,tb1_nome,tb1_codbar')
            ->where('user_id', $request->user()->id)
            ->when($unitId, function ($query) use ($unitId) {
                $query->where(function ($subQuery) use ($unitId) {
                    $subQuery->where('unit_id', $unitId)
                        ->orWhereNull('unit_id');
                });
            })
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(function (ProductDiscard $discard) {
                return [
                    'id' => $discard->id,
                    'quantity' => $discard->quantity,
                    'created_at' => $discard->created_at->toIso8601String(),
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
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
        ]);

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
        ]);

        return redirect()
            ->back()
            ->with('success', 'Descarte registrado com sucesso.');
    }
}
