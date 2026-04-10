<?php

namespace App\Http\Controllers;

use App\Models\SalaryAdvance;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Support\ManagementScope;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    private const ROLE_LABELS = [
        0 => 'Master',
        1 => 'Gerente',
        2 => 'Sub-Gerente',
        3 => 'Caixa',
        4 => 'Lanchonete',
        5 => 'Funcionario',
        6 => 'Cliente',
    ];

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request->user());

        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);
        $filterUnits = ManagementScope::managedUnits($request->user(), ['tb2_id', 'tb2_nome'])
            ->map(fn (Unidade $unit) => [
                'id' => (int) $unit->tb2_id,
                'name' => $unit->tb2_nome,
            ])
            ->values();
        $allowedUnitIds = $this->reportUnitIds($filterUnits);
        $selectedUnitId = $this->resolveSelectedUnitId($request->query('unit_id'), $allowedUnitIds);
        $selectedRole = $this->resolveSelectedRole($request->query('role'));
        $selectedUnit = $selectedUnitId
            ? $filterUnits->firstWhere('id', $selectedUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];
        $roleOptions = collect(self::ROLE_LABELS)
            ->reject(fn (string $label, int $role) => $role === 6)
            ->map(fn (string $label, int $role) => [
                'id' => (int) $role,
                'label' => $label,
            ])
            ->values();

        $usersQuery = User::query()
            ->with([
                'units:tb2_id,tb2_nome',
                'primaryUnit:tb2_id,tb2_nome',
            ])
            ->where('funcao', '!=', 6)
            ->orderBy('name');

        ManagementScope::applyManagedUserScope($usersQuery, $request->user());

        if ($selectedUnitId) {
            $usersQuery->where(function ($query) use ($selectedUnitId) {
                $query
                    ->where('tb2_id', $selectedUnitId)
                    ->orWhereHas('units', function ($unitQuery) use ($selectedUnitId) {
                        $unitQuery->where('tb2_unidades.tb2_id', $selectedUnitId);
                    });
            });
        }

        if ($selectedRole !== null) {
            $usersQuery->where('funcao', $selectedRole);
        }

        $users = $usersQuery->get(['id', 'name', 'funcao', 'salario', 'tb2_id']);
        $userIds = $users->pluck('id')->map(fn ($value) => (int) $value)->values();

        $advances = $userIds->isEmpty()
            ? collect()
            : $this->advanceQuery($userIds, $startDate, $endDate, $selectedUnitId, $allowedUnitIds)
                ->with(['unit:tb2_id,tb2_nome'])
                ->orderBy('advance_date')
                ->orderBy('id')
                ->get(['id', 'user_id', 'unit_id', 'advance_date', 'amount', 'reason']);

        $valeSales = $userIds->isEmpty()
            ? collect()
            : $this->valeQuery($userIds, $start, $end, $selectedUnitId, $allowedUnitIds)
                ->with(['unidade:tb2_id,tb2_nome'])
                ->orderBy('data_hora')
                ->orderBy('tb3_id')
                ->get([
                    'tb3_id',
                    'tb4_id',
                    'id_user_vale',
                    'id_unidade',
                    'produto_nome',
                    'quantidade',
                    'valor_total',
                    'data_hora',
                ]);

        $advancesByUser = $advances->groupBy('user_id');
        $valeSalesByUser = $valeSales->groupBy('id_user_vale');

        $rows = $users
            ->map(function (User $user) use ($advancesByUser, $valeSalesByUser, $startDate, $endDate) {
                $advanceRecords = $advancesByUser
                    ->get($user->id, collect())
                    ->map(function (SalaryAdvance $advance) {
                        return [
                            'id' => (int) $advance->id,
                            'advance_date' => $advance->advance_date?->toDateString(),
                            'amount' => round((float) $advance->amount, 2),
                            'reason' => $advance->reason,
                            'unit_name' => $advance->unit?->tb2_nome ?? '---',
                        ];
                    })
                    ->values();

                $valeRecords = $valeSalesByUser
                    ->get($user->id, collect())
                    ->groupBy('tb4_id')
                    ->map(function (Collection $group, $receiptId) {
                        /** @var Venda|null $first */
                        $first = $group->first();

                        return [
                            'id' => (int) $receiptId,
                            'date_time' => $first?->data_hora?->toIso8601String(),
                            'unit_name' => $first?->unidade?->tb2_nome ?? '---',
                            'items_count' => (int) $group->sum('quantidade'),
                            'items_label' => $group
                                ->map(fn (Venda $sale) => trim(sprintf('%sx %s', (int) $sale->quantidade, $sale->produto_nome)))
                                ->implode(', '),
                            'total' => round((float) $group->sum('valor_total'), 2),
                        ];
                    })
                    ->sortBy('date_time')
                    ->values();

                $advanceTotal = round((float) $advanceRecords->sum('amount'), 2);
                $valeTotal = round((float) $valeRecords->sum('total'), 2);
                $salary = round((float) ($user->salario ?? 0), 2);
                $balance = round($salary - $advanceTotal - $valeTotal, 2);
                $unitNames = $this->resolveUserUnitNames($user);

                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                    'salary' => $salary,
                    'advances_total' => $advanceTotal,
                    'vales_total' => $valeTotal,
                    'balance' => $balance,
                    'unit_names' => $unitNames->values()->all(),
                    'detail' => [
                        'user_id' => (int) $user->id,
                        'user_name' => $user->name,
                        'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                        'unit_names' => $unitNames->values()->all(),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'salary' => $salary,
                        'advances_total' => $advanceTotal,
                        'vales_total' => $valeTotal,
                        'balance' => $balance,
                        'advances_count' => $advanceRecords->count(),
                        'vales_count' => $valeRecords->count(),
                        'advances' => $advanceRecords->all(),
                        'vales' => $valeRecords->all(),
                    ],
                ];
            })
            ->values();

        $summary = [
            'employees_count' => $rows->count(),
            'salary_total' => round((float) $rows->sum('salary'), 2),
            'advances_total' => round((float) $rows->sum('advances_total'), 2),
            'vales_total' => round((float) $rows->sum('vales_total'), 2),
            'balance_total' => round((float) $rows->sum('balance'), 2),
        ];

        return Inertia::render('Settings/FolhaPagamento', [
            'rows' => $rows,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterUnits' => $filterUnits,
            'roleOptions' => $roleOptions,
            'selectedUnitId' => $selectedUnitId,
            'selectedRole' => $selectedRole,
            'unit' => $selectedUnit,
        ]);
    }

    private function ensureAdmin($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }

    private function resolveDateRange(Request $request): array
    {
        $defaultStart = Carbon::today()->startOfMonth();
        $defaultEnd = Carbon::today()->endOfMonth();

        $start = $this->parseFlexibleDate($request->query('start_date'), $defaultStart)->startOfDay();
        $end = $this->parseFlexibleDate($request->query('end_date'), $defaultEnd)->endOfDay();

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, $start->toDateString(), $end->toDateString()];
    }

    private function parseFlexibleDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy();
        }

        foreach (['d/m/y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (InvalidFormatException $exception) {
                continue;
            }
        }

        return $fallback->copy();
    }

    private function resolveSelectedUnitId(mixed $requestedUnitId, Collection $allowedUnitIds): ?int
    {
        if ($requestedUnitId === null || $requestedUnitId === '' || $requestedUnitId === 'all') {
            return null;
        }

        $unitId = (int) $requestedUnitId;

        if ($unitId <= 0 || ! $allowedUnitIds->contains($unitId)) {
            return null;
        }

        return $unitId;
    }

    private function resolveSelectedRole(mixed $requestedRole): ?int
    {
        if ($requestedRole === null || $requestedRole === '' || $requestedRole === 'all') {
            return null;
        }

        $role = (int) $requestedRole;

        if (! array_key_exists($role, self::ROLE_LABELS) || $role === 6) {
            return null;
        }

        return $role;
    }

    private function reportUnitIds(iterable $units): Collection
    {
        return collect($units)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
    }

    private function advanceQuery(
        Collection $userIds,
        string $startDate,
        string $endDate,
        ?int $selectedUnitId,
        Collection $allowedUnitIds
    ) {
        $query = SalaryAdvance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('advance_date', [$startDate, $endDate]);

        if ($selectedUnitId) {
            $query->where(function ($sub) use ($selectedUnitId) {
                $sub->where('unit_id', $selectedUnitId)
                    ->orWhere(function ($legacy) use ($selectedUnitId) {
                        $legacy->whereNull('unit_id')
                            ->where(function ($legacyUnit) use ($selectedUnitId) {
                                $legacyUnit->whereHas('user', function ($userQuery) use ($selectedUnitId) {
                                    $userQuery->where('tb2_id', $selectedUnitId);
                                })->orWhereHas('user.units', function ($unitQuery) use ($selectedUnitId) {
                                    $unitQuery->where('tb2_unidades.tb2_id', $selectedUnitId);
                                });
                            });
                    });
            });

            return $query;
        }

        if ($allowedUnitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($sub) use ($allowedUnitIds) {
            $sub->whereIn('unit_id', $allowedUnitIds)
                ->orWhere(function ($legacy) use ($allowedUnitIds) {
                    $legacy->whereNull('unit_id')
                        ->where(function ($legacyUnit) use ($allowedUnitIds) {
                            $legacyUnit->whereHas('user', function ($userQuery) use ($allowedUnitIds) {
                                $userQuery->whereIn('tb2_id', $allowedUnitIds);
                            })->orWhereHas('user.units', function ($unitQuery) use ($allowedUnitIds) {
                                $unitQuery->whereIn('tb2_unidades.tb2_id', $allowedUnitIds);
                            });
                        });
                });
        });
    }

    private function valeQuery(
        Collection $userIds,
        Carbon $start,
        Carbon $end,
        ?int $selectedUnitId,
        Collection $allowedUnitIds
    ) {
        $query = Venda::query()
            ->whereIn('id_user_vale', $userIds)
            ->where('tipo_pago', 'vale')
            ->whereBetween('data_hora', [$start, $end]);

        if ($selectedUnitId) {
            return $query->where('id_unidade', $selectedUnitId);
        }

        if ($allowedUnitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id_unidade', $allowedUnitIds);
    }

    private function resolveUserUnitNames(User $user): Collection
    {
        $units = collect($user->units ?? [])
            ->pluck('tb2_nome')
            ->filter();

        if ($user->primaryUnit?->tb2_nome && ! $units->contains($user->primaryUnit->tb2_nome)) {
            $units->push($user->primaryUnit->tb2_nome);
        }

        return $units->unique()->sort()->values();
    }
}
