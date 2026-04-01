<?php

namespace App\Support;

use App\Models\ConfiguracaoDiscarte;
use App\Models\ProductDiscard;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VendaPagamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DiscardAlertService
{
    public function configuration(): ConfiguracaoDiscarte
    {
        return ConfiguracaoDiscarte::query()->firstOrCreate([], [
            'percentual_aceitavel' => 0,
        ]);
    }

    public function thresholdPercentage(): float
    {
        return round((float) $this->configuration()->percentual_aceitavel, 2);
    }

    public function forRequest(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! ManagementScope::isAdmin($user)) {
            return null;
        }

        $activeUnit = $request->session()->get('active_unit');
        $unitId = is_array($activeUnit)
            ? ($activeUnit['id'] ?? $activeUnit['tb2_id'] ?? null)
            : (is_object($activeUnit) ? ($activeUnit->id ?? $activeUnit->tb2_id ?? null) : null);

        $unitName = is_array($activeUnit)
            ? ($activeUnit['name'] ?? $activeUnit['tb2_nome'] ?? null)
            : (is_object($activeUnit) ? ($activeUnit->name ?? $activeUnit->tb2_nome ?? null) : null);

        return $this->monitoringForUser(
            $user,
            $unitId ? (int) $unitId : null,
            Carbon::today(),
            $unitName,
        );
    }

    public function monitoringForUser(
        User $user,
        ?int $requestedUnitId = null,
        ?Carbon $referenceDate = null,
        ?string $requestedUnitName = null,
    ): array {
        $referenceDate = $referenceDate?->copy() ?? Carbon::today();
        $scope = $this->resolveScope($user, $requestedUnitId, $requestedUnitName);
        $threshold = $this->thresholdPercentage();
        $today = $this->buildRangeStatus(
            $user,
            $scope,
            $referenceDate->copy()->startOfDay(),
            $referenceDate->copy()->endOfDay(),
            $threshold,
        );
        $month = $this->buildRangeStatus(
            $user,
            $scope,
            $referenceDate->copy()->startOfMonth(),
            $referenceDate->copy()->endOfDay(),
            $threshold,
        );

        return [
            'enabled' => $threshold > 0,
            'has_alert' => $threshold > 0 && ($today['exceeded'] || $month['exceeded']),
            'threshold_percentage' => $threshold,
            'scope' => [
                'unit_id' => $scope['unit_id'],
                'unit_name' => $scope['unit_name'],
                'mode' => $scope['mode'],
            ],
            'today' => $today,
            'month' => $month,
            'reference_date' => $referenceDate->toDateString(),
        ];
    }

    public function evaluateAmounts(float $discardTotal, float $revenueTotal, ?float $threshold = null): array
    {
        $threshold = $threshold ?? $this->thresholdPercentage();
        $discardTotal = round($discardTotal, 2);
        $revenueTotal = round($revenueTotal, 2);
        $percentage = $revenueTotal > 0
            ? round(($discardTotal / $revenueTotal) * 100, 2)
            : null;

        $exceeded = false;
        if ($threshold > 0) {
            $exceeded = $revenueTotal > 0
                ? ($percentage !== null && $percentage >= $threshold)
                : $discardTotal > 0;
        }

        return [
            'discard_total' => $discardTotal,
            'revenue_total' => $revenueTotal,
            'percentage' => $percentage,
            'exceeded' => $exceeded,
            'threshold_percentage' => round($threshold, 2),
        ];
    }

    private function buildRangeStatus(
        User $user,
        array $scope,
        Carbon $start,
        Carbon $end,
        float $threshold,
    ): array {
        $revenueTotal = $this->resolveRevenueTotal($user, $scope, $start, $end);
        $discardTotal = $this->resolveDiscardTotal($user, $scope, $start, $end);

        return array_merge(
            $this->evaluateAmounts($discardTotal, $revenueTotal, $threshold),
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        );
    }

    private function resolveRevenueTotal(User $user, array $scope, Carbon $start, Carbon $end): float
    {
        $query = VendaPagamento::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($scope['mode'] === 'unit' && $scope['unit_id']) {
            $query->whereHas('vendas', function (Builder $subQuery) use ($scope) {
                $subQuery->where('id_unidade', $scope['unit_id']);
            });
        } elseif ($scope['mode'] === 'managed') {
            if (empty($scope['unit_ids'])) {
                return 0.0;
            }

            $query->whereHas('vendas', function (Builder $subQuery) use ($scope) {
                $subQuery->whereIn('id_unidade', $scope['unit_ids']);
            });
        }

        return round((float) $query->sum('valor_total'), 2);
    }

    private function resolveDiscardTotal(User $user, array $scope, Carbon $start, Carbon $end): float
    {
        $query = ProductDiscard::query()
            ->with('product:tb1_id,tb1_vlr_venda')
            ->whereBetween('created_at', [$start, $end]);

        if ($scope['mode'] === 'unit' && $scope['unit_id']) {
            $unitId = $scope['unit_id'];
            $query->where(function (Builder $builder) use ($unitId) {
                $builder->where('unit_id', $unitId)
                    ->orWhere(function (Builder $legacy) use ($unitId) {
                        $legacy->whereNull('unit_id')
                            ->where(function (Builder $legacyUnit) use ($unitId) {
                                $legacyUnit->whereHas('user', function (Builder $userQuery) use ($unitId) {
                                    $userQuery->where('tb2_id', $unitId);
                                })->orWhereHas('user.units', function (Builder $unitQuery) use ($unitId) {
                                    $unitQuery->where('tb2_unidades.tb2_id', $unitId);
                                });
                            });
                    });
            });
        } elseif ($scope['mode'] === 'managed') {
            if (empty($scope['unit_ids'])) {
                return 0.0;
            }

            $unitIds = $scope['unit_ids'];
            $query->where(function (Builder $builder) use ($unitIds) {
                $builder->whereIn('unit_id', $unitIds)
                    ->orWhere(function (Builder $legacy) use ($unitIds) {
                        $legacy->whereNull('unit_id')
                            ->whereHas('user', function (Builder $userQuery) use ($unitIds) {
                                $userQuery->where(function (Builder $scopeQuery) use ($unitIds) {
                                    $scopeQuery->whereIn('tb2_id', $unitIds)
                                        ->orWhereHas('units', function (Builder $unitQuery) use ($unitIds) {
                                            $unitQuery->whereIn('tb2_unidades.tb2_id', $unitIds);
                                        });
                                });
                            });
                    });
            });
        }

        return round($query->get()->sum(function (ProductDiscard $discard) {
            $unitPrice = $discard->unit_price !== null
                ? (float) $discard->unit_price
                : (float) ($discard->product?->tb1_vlr_venda ?? 0);

            return (float) $discard->quantity * $unitPrice;
        }), 2);
    }

    private function resolveScope(User $user, ?int $requestedUnitId, ?string $requestedUnitName = null): array
    {
        $requestedUnitId = $requestedUnitId && $requestedUnitId > 0 ? $requestedUnitId : null;

        if ($requestedUnitId && ManagementScope::canManageUnit($user, $requestedUnitId)) {
            return [
                'mode' => 'unit',
                'unit_id' => $requestedUnitId,
                'unit_ids' => [$requestedUnitId],
                'unit_name' => $requestedUnitName ?? $this->resolveUnitName($requestedUnitId),
            ];
        }

        if (ManagementScope::isMaster($user)) {
            return [
                'mode' => 'all',
                'unit_id' => null,
                'unit_ids' => [],
                'unit_name' => 'Todas as unidades',
            ];
        }

        $unitIds = ManagementScope::managedUnitIds($user)
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'mode' => 'managed',
            'unit_id' => null,
            'unit_ids' => $unitIds,
            'unit_name' => 'Unidades gerenciadas',
        ];
    }

    private function resolveUnitName(int $unitId): string
    {
        $unit = Unidade::find($unitId, ['tb2_id', 'tb2_nome']);

        return $unit?->tb2_nome ?? ('Unidade #' . $unitId);
    }
}
