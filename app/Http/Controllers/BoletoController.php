<?php

namespace App\Http\Controllers;

use App\Models\Boleto;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoletoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureAccess($user);

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'paid' => $request->query('paid', 'all'),
        ];

        $activeUnit = $this->resolveUnit($request);
        $activeUnitId = is_array($activeUnit) ? ($activeUnit['id'] ?? null) : null;
        $boletos = null;

        if ($this->isMaster($user)) {
            $boletos = Boleto::with(['user:id,name', 'paidBy:id,name', 'unit:tb2_id,tb2_nome'])
                ->forUnit($activeUnitId)
                ->withPaidStatus($filters['paid'])
                ->when($filters['start_date'], function ($query) use ($filters) {
                    $query->whereDate('due_date', '>=', $filters['start_date']);
                })
                ->when($filters['end_date'], function ($query) use ($filters) {
                    $query->whereDate('due_date', '<=', $filters['end_date']);
                })
                ->orderBy('due_date')
                ->paginate(15)
                ->withQueryString();
        }

        return Inertia::render('Finance/BoletoIndex', [
            'activeUnit' => $activeUnit,
            'filters' => $filters,
            'boletos' => $boletos,
            'canManageList' => $this->isMaster($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureCreator($user);

        $activeUnit = $this->resolveUnit($request);
        if (! $activeUnit) {
            return back()->with('error', 'Unidade ativa nao definida para registrar o boleto.');
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'barcode' => ['required', 'string', 'max:128'],
            'digitable_line' => ['required', 'string', 'max:256'],
        ]);

        Boleto::create([
            'unit_id' => $activeUnit['id'],
            'user_id' => $user->id,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'due_date' => $data['due_date'],
            'barcode' => $data['barcode'],
            'digitable_line' => $data['digitable_line'],
        ]);

        return redirect()
            ->route('boletos.index')
            ->with('success', 'Boleto registrado com sucesso!');
    }

    public function pay(Request $request, Boleto $boleto): RedirectResponse
    {
        $user = $request->user();
        $this->ensureMaster($user);

        $activeUnit = $this->resolveUnit($request);
        if (! $activeUnit || (int) $boleto->unit_id !== (int) $activeUnit['id']) {
            abort(403);
        }

        if ($boleto->is_paid) {
            return redirect()
                ->route('boletos.index')
                ->with('error', 'Boleto ja esta pago.');
        }

        $boleto->update([
            'is_paid' => true,
            'paid_at' => Carbon::now(),
            'paid_by' => $user->id,
        ]);

        return redirect()
            ->route('boletos.index')
            ->with('success', 'Boleto baixado com sucesso!');
    }

    private function ensureAccess($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 3], true)) {
            abort(403);
        }
    }

    private function ensureCreator($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 3], true)) {
            abort(403);
        }
    }

    private function ensureMaster($user): void
    {
        if (! $user || (int) $user->funcao !== 0) {
            abort(403);
        }
    }

    private function isMaster($user): bool
    {
        return $user && (int) $user->funcao === 0;
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
