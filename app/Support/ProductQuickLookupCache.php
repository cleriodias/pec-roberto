<?php

namespace App\Support;

use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductQuickLookupCache
{
    private const CACHE_VERSION = 'v1';

    private const CACHE_TTL_MINUTES = 480;

    private const POPULAR_PRODUCTS_LIMIT = 300;

    private const POPULAR_PRODUCTS_DAYS = 30;

    public function forRequest(Request $request): array
    {
        $unitId = $this->resolveActiveUnitId($request);

        if ($unitId <= 0) {
            return [];
        }

        return $this->forUnit($unitId);
    }

    public function forUnit(int $unitId): array
    {
        return Cache::remember(
            $this->cacheKey($unitId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->buildForUnit($unitId)
        );
    }

    public function rememberProductForRequest(Produto $product, Request $request): void
    {
        $unitId = $this->resolveActiveUnitId($request);

        if ($unitId <= 0 || (int) $product->tb1_status !== 1) {
            return;
        }

        $key = $this->cacheKey($unitId);
        $productPayload = $this->productPayload($product);
        $currentProducts = Cache::get($key, []);

        if (! is_array($currentProducts)) {
            $currentProducts = [];
        }

        $nextProducts = array_values(array_filter(
            $currentProducts,
            fn ($cachedProduct) => (int) ($cachedProduct['tb1_id'] ?? 0) !== (int) $product->tb1_id
        ));

        array_unshift($nextProducts, $productPayload);
        $nextProducts = array_slice($nextProducts, 0, self::POPULAR_PRODUCTS_LIMIT);

        Cache::put($key, $nextProducts, now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function productPayload(Produto $product): array
    {
        return [
            'tb1_id' => (int) $product->tb1_id,
            'tb1_nome' => (string) $product->tb1_nome,
            'tb1_codbar' => (string) $product->tb1_codbar,
            'tb1_vlr_custo' => (float) $product->tb1_vlr_custo,
            'tb1_vlr_venda' => (float) $product->tb1_vlr_venda,
            'tb1_tipo' => (int) $product->tb1_tipo,
            'tb1_qtd' => (int) ($product->tb1_qtd ?? 0),
            'tb1_status' => (int) $product->tb1_status,
            'tb1_vr_credit' => (bool) $product->tb1_vr_credit,
        ];
    }

    public function limit(): int
    {
        return self::POPULAR_PRODUCTS_LIMIT;
    }

    public function ttlHours(): int
    {
        return (int) (self::CACHE_TTL_MINUTES / 60);
    }

    private function buildForUnit(int $unitId): array
    {
        $topProductIds = Venda::query()
            ->select('tb1_id')
            ->selectRaw('SUM(quantidade) as sold_quantity')
            ->where('id_unidade', $unitId)
            ->where('status', 1)
            ->where('data_hora', '>=', now()->subDays(self::POPULAR_PRODUCTS_DAYS))
            ->groupBy('tb1_id')
            ->orderByDesc('sold_quantity')
            ->limit(self::POPULAR_PRODUCTS_LIMIT)
            ->pluck('tb1_id')
            ->map(fn ($productId) => (int) $productId)
            ->values();

        if ($topProductIds->isEmpty()) {
            return [];
        }

        $order = array_flip($topProductIds->all());

        return Produto::query()
            ->whereIn('tb1_id', $topProductIds->all())
            ->where('tb1_status', 1)
            ->get([
                'tb1_id',
                'tb1_nome',
                'tb1_codbar',
                'tb1_vlr_custo',
                'tb1_vlr_venda',
                'tb1_tipo',
                'tb1_qtd',
                'tb1_status',
                'tb1_vr_credit',
            ])
            ->sortBy(fn (Produto $product) => $order[(int) $product->tb1_id] ?? PHP_INT_MAX)
            ->values()
            ->map(fn (Produto $product) => $this->productPayload($product))
            ->all();
    }

    private function resolveActiveUnitId(Request $request): int
    {
        $activeUnit = $request->session()->get('active_unit');
        $unitId = 0;

        if (is_array($activeUnit)) {
            $unitId = (int) ($activeUnit['id'] ?? $activeUnit['tb2_id'] ?? 0);
        } elseif (is_object($activeUnit)) {
            $unitId = (int) ($activeUnit->id ?? $activeUnit->tb2_id ?? 0);
        }

        if ($unitId <= 0) {
            $unitId = (int) ($request->user()?->tb2_id ?? 0);
        }

        return $unitId;
    }

    private function cacheKey(int $unitId): string
    {
        return sprintf('dashboard:quick-products:%s:unit:%d', self::CACHE_VERSION, $unitId);
    }
}
