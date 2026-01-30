<?php

namespace App\Http\Controllers;

use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitSwitchController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureCanSwitchUnit($user);

        $units = $this->allowedUnits($user)
            ->map(fn (Unidade $unit) => [
                'id' => $unit->tb2_id,
                'name' => $unit->tb2_nome,
                'active' => (int) ($request->session()->get('active_unit.id')) === $unit->tb2_id,
            ])
            ->values();

        return Inertia::render('Reports/SwitchUnit', ['units' => $units,]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureCanSwitchUnit($user);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
        ]);

        $units = $this->allowedUnits($user);
        $unit = $units->firstWhere('tb2_id', (int) $validated['unit_id']);

        if (! $unit) {
            abort(403);
        }

        $request->session()->put('active_unit', [
            'id' => $unit->tb2_id,
            'name' => $unit->tb2_nome,
            'address' => $unit->tb2_endereco,
            'cnpj' => $unit->tb2_cnpj,
        ]);

        return redirect()->route('dashboard')->with('success', 'Unidade alterada com sucesso!');

    }

    private function allowedUnits($user)
    {
        $primaryId = (int) ($user->tb2_id ?? 0);

        $unitIds = $user->units()
            ->pluck('tb2_unidades.tb2_id')
            ->map(fn ($value) => (int) $value);

        if ($primaryId > 0 && ! $unitIds->contains($primaryId)) {
            $unitIds->push($primaryId);
        }

        return Unidade::whereIn('tb2_id', $unitIds->unique())
            ->orderBy('tb2_nome')
            ->get(['tb2_id', 'tb2_nome', 'tb2_endereco', 'tb2_cnpj']);
    }

    private function ensureCanSwitchUnit($user): void
    {
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao ?? -1);

        if (! $user || ! in_array($roleOriginal, [0, 1], true)) {
            abort(403);
        }
    }
}
