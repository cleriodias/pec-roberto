<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Supplier;
use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureManager($request->user());

        $activeUnit = $this->resolveUnit($request);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $expensesQuery = Expense::with('supplier:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($activeUnit) {
            $expensesQuery->where('unit_id', $activeUnit['id']);
        } else {
            $expensesQuery->whereRaw('1 = 0');
        }

        $expenses = $expensesQuery->get([
            'id',
            'supplier_id',
            'unit_id',
            'expense_date',
            'amount',
            'notes',
        ]);

        return Inertia::render('Finance/ExpenseIndex', [
            'suppliers' => $suppliers,
            'expenses' => $expenses,
            'activeUnit' => $activeUnit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManager($request->user());

        $activeUnit = $this->resolveUnit($request);
        if (! $activeUnit) {
            return back()->with('error', 'Unidade ativa nao definida para registrar o gasto.');
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Expense::create(array_merge($data, [
            'unit_id' => $activeUnit['id'],
            'user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Gasto cadastrado com sucesso!');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->ensureManager($request->user());
        $activeUnit = $this->resolveUnit($request);

        if (! $activeUnit || (int) $expense->unit_id !== (int) $activeUnit['id']) {
            abort(403);
        }

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Gasto removido com sucesso!');
    }

    private function ensureManager($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1, 3], true)) {
            abort(403);
        }
    }

    private function resolveUnit(Request $request): ?array
    {
        $sessionUnit = $request->session()->get('active_unit');

        if (is_array($sessionUnit)) {
            $unitId = $sessionUnit['id'] ?? $sessionUnit['tb2_id'] ?? null;
            if ($unitId) {
                return [
                    'id' => (int) $unitId,
                    'name' => (string) ($sessionUnit['name'] ?? $sessionUnit['tb2_nome'] ?? ''),
                ];
            }
        }

        if (is_object($sessionUnit)) {
            $unitId = $sessionUnit->id ?? $sessionUnit->tb2_id ?? null;
            if ($unitId) {
                return [
                    'id' => (int) $unitId,
                    'name' => (string) ($sessionUnit->name ?? $sessionUnit->tb2_nome ?? ''),
                ];
            }
        }

        $user = $request->user();
        $unitId = (int) ($user?->tb2_id ?? 0);

        if ($unitId <= 0) {
            return null;
        }

        $unit = Unidade::find($unitId, ['tb2_id', 'tb2_nome']);

        return [
            'id' => (int) $unitId,
            'name' => $unit?->tb2_nome ?? ('Unidade #' . $unitId),
        ];
    }
}
