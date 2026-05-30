<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\CashierClosure;
use App\Models\OnlineUser;
use App\Models\Unidade;
use App\Models\User;
use App\Support\PaymentControlNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    private const LOGIN_DOMAIN = '@paoecafepremium.com.br';

    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $requestedUnitId = (int) $request->query('l', 0);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'selectedUnitId' => $requestedUnitId > 0 ? $requestedUnitId : null,
            'units' => [],
        ]);
    }

    public function units(Request $request): JsonResponse
    {
        $username = trim((string) $request->query('username', ''));

        if ($username === '') {
            return response()->json([]);
        }

        $email = Str::lower(Str::before($username, '@') . self::LOGIN_DOMAIN);
        $user = User::query()
            ->with(['units' => fn ($query) => $query
                ->select('tb2_unidades.tb2_id', 'tb2_unidades.tb2_nome')
                ->where('tb2_unidades.tb2_status', 1)
                ->orderBy('tb2_unidades.tb2_nome')])
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json([]);
        }

        $units = $user->units;

        if ((int) $user->tb2_id > 0 && ! $units->contains('tb2_id', (int) $user->tb2_id)) {
            $primaryUnit = Unidade::active()
                ->where('tb2_id', (int) $user->tb2_id)
                ->first(['tb2_id', 'tb2_nome']);

            if ($primaryUnit) {
                $units->push($primaryUnit);
            }
        }

        return response()->json(
            $units
                ->unique('tb2_id')
                ->sortBy('tb2_nome')
                ->values()
                ->map(fn (Unidade $unit) => [
                    'tb2_id' => $unit->tb2_id,
                    'tb2_nome' => $unit->tb2_nome,
                ])
        );
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

        if (in_array((int) $funcaoOriginal, [5, 6], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Este perfil nao possui acesso ao sistema.',
            ]);
        }

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
                    'username' => 'Seu caixa ja foi fechado hoje para esta unidade. Novo acesso apenas amanha.',
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

        $selectedUnit = Unidade::active()
            ->select('tb2_id', 'tb2_nome', 'tb2_endereco', 'tb2_cnpj')
            ->find($unitId);

        if (! $selectedUnit) {
            Auth::logout();

            throw ValidationException::withMessages([
                'unit_id' => 'A unidade selecionada esta inativa.',
            ]);
        }

        $request->session()->put('active_unit', [
            'id' => $selectedUnit->tb2_id,
            'name' => $selectedUnit->tb2_nome,
            'address' => $selectedUnit->tb2_endereco,
            'cnpj' => $selectedUnit->tb2_cnpj,
        ]);

        if ($user->funcao_original === null) {
            $user->forceFill(['funcao_original' => $user->funcao])->save();
        }

        $request->session()->put('active_role', (int) ($user->funcao_original ?? $user->funcao));
        app(PaymentControlNotificationService::class)->notifyUserOnLogin($user, (int) $selectedUnit->tb2_id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        OnlineUser::query()
            ->where('session_id', $request->session()->getId())
            ->delete();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
