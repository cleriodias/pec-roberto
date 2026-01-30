<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleSwitchController extends Controller
{
    private const ROLE_OPTIONS = [
        0 => 'MASTER',
        1 => 'GERENTE',
        2 => 'SUB-GERENTE',
        3 => 'CAIXA',
        4 => 'LANCHONETE',
        5 => 'FUNCIONARIO',
        6 => 'CLIENTE',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureMasterOriginal($user);

        $roles = collect(self::ROLE_OPTIONS)
            ->map(function (string $label, int $value) use ($user) {
                return [
                    'value' => $value,
                    'label' => $label,
                    'active' => (int) $user->funcao === $value,
                ];
            })
            ->values();

        return Inertia::render('Reports/SwitchRole', [
            'roles' => $roles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureMasterOriginal($user);

        $validated = $request->validate([
            'role' => ['required', 'integer', 'between:0,6'],
        ]);

        $value = (int) $validated['role'];

        if (! array_key_exists($value, self::ROLE_OPTIONS)) {
            abort(422);
        }

        $user->forceFill(['funcao' => $value])->save();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Funcao alterada com sucesso!');
    }

    private function ensureMasterOriginal(?User $user): void
    {
        if (! $user || (int) ($user->funcao_original ?? $user->funcao) !== 0) {
            abort(403);
        }
    }
}
