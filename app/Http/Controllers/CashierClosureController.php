<?php

namespace App\Http\Controllers;

use App\Models\CashierClosure;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashierClosureController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureCashier($user);

        $today = Carbon::today();
        $activeUnit = $request->session()->get('active_unit');
        $unitId = $activeUnit['id'] ?? $user->tb2_id;
        $pendingClosureDate = $this->resolvePendingClosureDate($user, $unitId, $today);

        $todayClosure = CashierClosure::where('user_id', $user->id)
            ->whereDate('closed_date', $today)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
            })
            ->latest('closed_at')
            ->first();

        $lastClosure = CashierClosure::where('user_id', $user->id)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
            })
            ->latest('closed_at')
            ->first();

        return Inertia::render('Cashier/Close', [
            'activeUnit' => $activeUnit,
            'todayClosure' => $todayClosure ? [
                'cash_amount' => $todayClosure->cash_amount,
                'card_amount' => $todayClosure->card_amount,
                'closed_at' => optional($todayClosure->closed_at)->toIso8601String(),
                'unit_name' => $todayClosure->unit_name,
            ] : null,
            'lastClosure' => $lastClosure ? [
                'cash_amount' => $lastClosure->cash_amount,
                'card_amount' => $lastClosure->card_amount,
                'closed_at' => optional($lastClosure->closed_at)->toIso8601String(),
                'unit_name' => $lastClosure->unit_name,
            ] : null,
            'pendingClosureDate' => $pendingClosureDate?->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureCashier($user);

        $validated = $request->validate([
            'cash_amount' => ['required', 'numeric', 'min:0'],
            'card_amount' => ['required', 'numeric', 'min:0'],
            'closure_date' => ['nullable', 'date'],
            'confirm_pending' => ['nullable', 'boolean'],
        ]);

        $today = Carbon::today();
        $activeUnit = $request->session()->get('active_unit');
        $unitId = $activeUnit['id'] ?? $user->tb2_id;
        $unitName = $activeUnit['name'] ?? optional($user->primaryUnit)->tb2_nome;

        $openComandas = Venda::query()
            ->whereNotNull('id_comanda')
            ->whereBetween('id_comanda', [3000, 3100])
            ->where('status', 0)
            ->where('id_unidade', $unitId)
            ->exists();

        if ($openComandas) {
            return redirect()
                ->route('cashier.close')
                ->withErrors([
                    'cash_amount' => 'Existem comandas da lanchonete em aberto. Finalize antes de fechar o caixa.',
                ]);
        }

        $pendingClosureDate = $this->resolvePendingClosureDate($user, $unitId, $today);
        $pendingDateString = $pendingClosureDate?->toDateString();
        $confirmedPending = (bool) ($validated['confirm_pending'] ?? false);

        if ($pendingClosureDate) {
            $requestedDate = $validated['closure_date'] ?? null;

            if ($requestedDate !== $pendingDateString || ! $confirmedPending) {
                return redirect()
                    ->route('cashier.close')
                    ->withErrors([
                        'confirm_pending' => sprintf(
                            'Existe fechamento pendente em %s. Confirme o fechamento pendente para continuar.',
                            $pendingClosureDate->format('d/m/Y')
                        ),
                    ]);
            }
        } elseif (! empty($validated['closure_date']) || $confirmedPending) {
            return redirect()
                ->route('cashier.close')
                ->withErrors([
                    'confirm_pending' => 'Nao ha fechamento pendente para confirmar.',
                ]);
        }

        $closureDate = $pendingClosureDate ?? $today;

        $alreadyClosed = CashierClosure::where('user_id', $user->id)
            ->whereDate('closed_date', $closureDate)
            ->where(function ($query) use ($unitId) {
                $query->whereNull('unit_id')->orWhere('unit_id', $unitId);
            })
            ->exists();

        if ($alreadyClosed) {
            $dateMessage = $closureDate->isSameDay($today)
                ? 'O caixa ja foi fechado hoje para esta unidade.'
                : 'O caixa ja foi fechado em ' . $closureDate->format('d/m/Y') . ' para esta unidade.';

            return redirect()
                ->route('cashier.close')
                ->withErrors([
                    'cash_amount' => $dateMessage,
                ]);
        }

        CashierClosure::create([
            'user_id' => $user->id,
            'unit_id' => $unitId,
            'unit_name' => $unitName,
            'cash_amount' => $validated['cash_amount'],
            'card_amount' => $validated['card_amount'],
            'closed_date' => $closureDate->toDateString(),
            'closed_at' => now(),
        ]);

        if ($closureDate->isSameDay($today)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Fechamento concluido. Voce podera acessar novamente amanha.');
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Fechamento pendente concluido. Voce pode continuar.');
    }

    private function ensureCashier(?User $user): void
    {
        if (!$user || (int) $user->funcao !== 3) {
            abort(403);
        }
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
}
