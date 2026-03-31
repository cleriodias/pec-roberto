<?php

namespace App\Support;

use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManagementScope
{
    public static function isMaster(?User $user): bool
    {
        return $user instanceof User && (int) $user->funcao === 0;
    }

    public static function isManager(?User $user): bool
    {
        return $user instanceof User && (int) $user->funcao === 1;
    }

    public static function isAdmin(?User $user): bool
    {
        return $user instanceof User && in_array((int) $user->funcao, [0, 1], true);
    }

    public static function managedUnitIds(User $user): Collection
    {
        $primaryId = (int) ($user->tb2_id ?? 0);

        $unitIds = $user->units()
            ->pluck('tb2_unidades.tb2_id')
            ->map(fn ($value) => (int) $value);

        if ($primaryId > 0 && ! $unitIds->contains($primaryId)) {
            $unitIds->push($primaryId);
        }

        return $unitIds
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
    }

    public static function managedUnits(User $user, array $columns = ['tb2_id', 'tb2_nome']): Collection
    {
        if (self::isMaster($user)) {
            return Unidade::orderBy('tb2_nome')->get($columns);
        }

        $unitIds = self::managedUnitIds($user);

        if ($unitIds->isEmpty()) {
            return collect();
        }

        return Unidade::whereIn('tb2_id', $unitIds)
            ->orderBy('tb2_nome')
            ->get($columns);
    }

    public static function canManageUnit(User $user, ?int $unitId): bool
    {
        if ($unitId === null || $unitId <= 0 || ! self::isAdmin($user)) {
            return false;
        }

        if (self::isMaster($user)) {
            return true;
        }

        return self::managedUnitIds($user)->contains($unitId);
    }

    public static function targetUserUnitIds(User $user): Collection
    {
        $primaryId = (int) ($user->tb2_id ?? 0);

        $unitIds = $user->relationLoaded('units')
            ? collect($user->units)->pluck('tb2_id')->map(fn ($value) => (int) $value)
            : $user->units()->pluck('tb2_unidades.tb2_id')->map(fn ($value) => (int) $value);

        if ($primaryId > 0 && ! $unitIds->contains($primaryId)) {
            $unitIds->push($primaryId);
        }

        return $unitIds
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
    }

    public static function canManageUser(User $actingUser, User $targetUser): bool
    {
        if (! self::isAdmin($actingUser)) {
            return false;
        }

        if (self::isMaster($actingUser)) {
            return true;
        }

        $allowedUnitIds = self::managedUnitIds($actingUser);
        $targetUnitIds = self::targetUserUnitIds($targetUser);

        return $targetUnitIds->isNotEmpty()
            && $targetUnitIds->every(fn (int $unitId) => $allowedUnitIds->contains($unitId));
    }

    public static function applyManagedUserScope(Builder $query, User $user): Builder
    {
        if (! self::isManager($user)) {
            return $query;
        }

        $allowedUnitIds = self::managedUnitIds($user)->all();

        if (empty($allowedUnitIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function (Builder $subQuery) use ($allowedUnitIds) {
                $subQuery
                    ->whereIn('users.tb2_id', $allowedUnitIds)
                    ->orWhereHas('units', function (Builder $unitQuery) use ($allowedUnitIds) {
                        $unitQuery->whereIn('tb2_unidades.tb2_id', $allowedUnitIds);
                    });
            })
            ->where(function (Builder $subQuery) use ($allowedUnitIds) {
                $subQuery
                    ->whereNull('users.tb2_id')
                    ->orWhereIn('users.tb2_id', $allowedUnitIds);
            })
            ->whereDoesntHave('units', function (Builder $unitQuery) use ($allowedUnitIds) {
                $unitQuery->whereNotIn('tb2_unidades.tb2_id', $allowedUnitIds);
            });
    }
}
