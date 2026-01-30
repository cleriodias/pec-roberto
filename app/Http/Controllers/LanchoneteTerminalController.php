<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;

class LanchoneteTerminalController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Apenas perfil lanchonete (4) ou master (0) podem acessar.
        if (! in_array((int) $user->funcao, [0, 4], true)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Lanchonete/Terminal');
    }

    public function validateAccess(Request $request)
    {
        $validated = $request->validate([
            'cod_acesso' => ['required', 'alpha_num', 'min:4', 'max:10'],
        ]);

        $user = User::query()
            ->where('cod_acesso', strtoupper($validated['cod_acesso']))
            ->whereIn('funcao', [0, 4])
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Codigo de acesso invalido.'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'funcao' => $user->funcao,
            'cod_acesso' => $user->cod_acesso,
        ]);
    }
}
