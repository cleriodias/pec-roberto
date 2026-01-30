<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\CashierClosure;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'units' => Unidade::orderBy('tb2_nome')->get(['tb2_id', 'tb2_nome']),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $unitId = (int) $request->input('unit_id');
        $user = $request->user();
        $funcaoOriginal = $user->funcao_original ?? $user->funcao;

        if ((int) $funcaoOriginal === 3) {
            $closedToday = CashierClosure::where('user_id', $user->id)
                ->whereDate('closed_date', Carbon::today())
                ->where(function ($query) use ($unitId) {
                    $query->whereNull('unit_id')
                        ->orWhere('unit_id', $unitId);
                })
                ->exists();

            if ($closedToday) {
                Auth::logout();

                throw ValidationException::withMessages([
                    'email' => 'Seu caixa ja foi fechado hoje para esta unidade. Novo acesso apenas amanha.',
                ]);
            }
        }

        $hasAccess = $user->units()->where('tb2_unidades.tb2_id', $unitId)->exists() || (int) $user->tb2_id === $unitId;

        if (! $hasAccess) {
            Auth::logout();

            throw ValidationException::withMessages([
                'unit_id' => 'Voce nao tem acesso a esta unidade.',
            ]);
        }

        $selectedUnit = Unidade::select('tb2_id', 'tb2_nome', 'tb2_endereco', 'tb2_cnpj')->findOrFail($unitId);

        $request->session()->put('active_unit', [
            'id' => $selectedUnit->tb2_id,
            'name' => $selectedUnit->tb2_nome,
            'address' => $selectedUnit->tb2_endereco,
            'cnpj' => $selectedUnit->tb2_cnpj,
        ]);

        if ($user->funcao_original === null) {
            $user->forceFill(['funcao_original' => $user->funcao])->save();
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
