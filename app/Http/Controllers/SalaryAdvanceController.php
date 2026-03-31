<?php

namespace App\Http\Controllers;

use App\Models\SalaryAdvance;
use App\Models\User;
use App\Models\Unidade;
use App\Support\ManagementScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureManager($user);

        $monthParam = $request->query('month');
        $unitParam = $request->query('unit_id');
        $filters = [
            'month' => $monthParam,
            'unit_id' => $unitParam ? (int) $unitParam : null,
        ];

        $advancesQuery = SalaryAdvance::with(['user:id,name,tb2_id', 'unit:tb2_id,tb2_nome'])
            ->when(ManagementScope::isManager($user), function ($query) use ($user) {
                $allowedUnitIds = ManagementScope::managedUnitIds($user)->all();

                if (empty($allowedUnitIds)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function ($sub) use ($allowedUnitIds) {
                    $sub->whereIn('unit_id', $allowedUnitIds)
                        ->orWhere(function ($legacy) use ($user) {
                            $legacy->whereNull('unit_id')
                                ->whereHas('user', function ($userQuery) use ($user) {
                                    ManagementScope::applyManagedUserScope($userQuery, $user);
                                });
                        });
                });
            })
            ->when($filters['month'], function ($query) use ($filters) {
                try {
                    [$year, $month] = explode('-', $filters['month']);
                    $query->whereYear('advance_date', (int) $year)
                        ->whereMonth('advance_date', (int) $month);
                } catch (\Throwable $e) {
                    // ignore invalid format
                }
            })
            ->when($filters['unit_id'], function ($query) use ($filters) {
                $unitId = (int) $filters['unit_id'];
                $query->where(function ($sub) use ($unitId) {
                    $sub->where('unit_id', $unitId)
                        ->orWhere(function ($legacy) use ($unitId) {
                            $legacy->whereNull('unit_id')
                                ->where(function ($legacyUnit) use ($unitId) {
                                    $legacyUnit->whereHas('user', function ($userQuery) use ($unitId) {
                                        $userQuery->where('tb2_id', $unitId);
                                    })->orWhereHas('user.units', function ($unitQuery) use ($unitId) {
                                        $unitQuery->where('tb2_unidades.tb2_id', $unitId);
                                    });
                                });
                        });
                });
            });

        // se nenhum filtro de mes foi enviado, usar mes corrente
        if (empty($filters['month'])) {
            $now = now();
            $currentMonth = $now->format('Y-m');
            $filters['month'] = $currentMonth;
            $advancesQuery->whereYear('advance_date', $now->year)
                ->whereMonth('advance_date', $now->month);
        }

        $advances = $advancesQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $units = ManagementScope::managedUnits($user, ['tb2_id', 'tb2_nome']);

        return Inertia::render('Finance/SalaryAdvanceIndex', [
            'advances' => $advances,
            'filters' => $filters,
            'units' => $units,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $this->ensureManager($user);

        $users = User::query()->orderBy('name');
        ManagementScope::applyManagedUserScope($users, $user);
        $users = $users->get(['id', 'name', 'salario']);
        $activeUnit = $this->resolveUnit($request);

        return Inertia::render('Finance/SalaryAdvanceCreate', [
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'salary_limit' => (float) ($user->salario ?? 0),
                'formatted_limit' => number_format((float) ($user->salario ?? 0), 2, ',', '.'),
            ]),
            'activeUnit' => $activeUnit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManager($request->user());

        $activeUnit = $this->resolveUnit($request);
        if (! $activeUnit) {
            return back()->with('error', 'Unidade ativa nao definida para registrar o adiantamento.');
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::select('id', 'salario', 'funcao')->findOrFail($data['user_id']);
        $this->ensureCanManageUser($request->user(), $user);

        $totalTaken = SalaryAdvance::where('user_id', $user->id)->sum('amount');
        $totalDueAsVale = DB::table('tb3_vendas')
            ->where('id_user_vale', $user->id)
            ->where('tipo_pago', 'vale')
            ->sum('valor_total');

        $newTotal = $totalTaken + $totalDueAsVale + (float) $data['amount'];

        if ($newTotal > (float) ($user->salario ?? 0)) {
            throw ValidationException::withMessages([
                'amount' => 'Adiantamento excede o limite permitido para este usuario.',
            ]);
        }

        SalaryAdvance::create(array_merge($data, [
            'unit_id' => $activeUnit['id'],
        ]));

        return redirect()
            ->route('salary-advances.index')
            ->with('success', 'Adiantamento registrado com sucesso!');
    }

    public function destroy(Request $request, SalaryAdvance $salaryAdvance): RedirectResponse
    {
        $this->ensureManager($request->user());

        if (! $this->canManageAdvance($request->user(), $salaryAdvance)) {
            abort(403);
        }

        $salaryAdvance->delete();

        return redirect()
            ->route('salary-advances.index')
            ->with('success', 'Adiantamento excluido com sucesso!');
    }

    private function ensureManager($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }

    private function resolveUnit(Request $request): ?array
    {
        $sessionUnit = $request->session()->get('active_unit');

        if (is_array($sessionUnit) && isset($sessionUnit['id'])) {
            return [
                'id' => (int) $sessionUnit['id'],
                'name' => (string) ($sessionUnit['name'] ?? ''),
            ];
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

    private function ensureCanManageUser($actingUser, User $targetUser): void
    {
        if (! $actingUser || ! ManagementScope::canManageUser($actingUser, $targetUser)) {
            abort(403);
        }
    }

    private function canManageAdvance($actingUser, SalaryAdvance $salaryAdvance): bool
    {
        if (! $actingUser || ! ManagementScope::isAdmin($actingUser)) {
            return false;
        }

        if (ManagementScope::isMaster($actingUser)) {
            return true;
        }

        if ($salaryAdvance->unit_id) {
            return ManagementScope::canManageUnit($actingUser, (int) $salaryAdvance->unit_id);
        }

        $salaryAdvance->loadMissing('user.units:tb2_id,tb2_nome');

        return $salaryAdvance->user instanceof User
            && ManagementScope::canManageUser($actingUser, $salaryAdvance->user);
    }
}
