<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFiscal;
use App\Models\CategoriaFiscalHistorico;
use App\Models\GrupoNcm;
use App\Models\Produto;
use App\Support\ProductQuickLookupCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductFiscalMassAssociationController extends Controller
{
    private const PRODUCT_NAME_ENCODING = 'UTF-8';

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => $request->query('type', ''),
            'origin' => $request->query('origin', ''),
            'without_category' => $request->boolean('without_category'),
            'category_id' => $request->query('category_id', ''),
            'group_id' => $request->query('group_id', ''),
            'only_exception' => $request->boolean('only_exception'),
        ];

        $products = $this->filteredProductsSearchQuery($filters)
            ->with(
                'categoriaFiscal:tb30_id,tb30_nome,tb30_ativo',
                'grupoNcm:tb33_id,tb33_nome,tb33_ativo',
            )
            ->orderBy('tb1_nome')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Products/FiscalMassAssociation', [
            'products' => $products,
            'categories' => CategoriaFiscal::query()
                ->orderByDesc('tb30_ativo')
                ->orderBy('tb30_nome')
                ->get(['tb30_id', 'tb30_nome', 'tb30_ativo', 'tb30_origem_mercadoria']),
            'groups' => GrupoNcm::query()
                ->orderByDesc('tb33_ativo')
                ->orderBy('tb33_nome')
                ->get(['tb33_id', 'tb33_nome', 'tb33_ativo']),
            'filters' => $filters,
            'typeOptions' => $this->typeOptions(),
            'fiscalSummary' => $this->fiscalSummary(),
            'originOptions' => [
                ['value' => 'FABRICACAO_PROPRIA', 'label' => 'Fabricacao propria'],
                ['value' => 'REVENDA', 'label' => 'Revenda'],
                ['value' => 'PREPARO_MONTAGEM', 'label' => 'Preparo/montagem'],
            ],
        ]);
    }

    private function filteredProductsQuery(array $filters)
    {
        return Produto::query()
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = str_replace(['%', '_'], ['\%', '\_'], $filters['search']);
                $query->where(function ($builder) use ($term) {
                    $builder->where('tb1_nome', 'like', '%' . $term . '%')
                        ->orWhere('tb1_codbar', 'like', '%' . $term . '%');

                    if (ctype_digit($term)) {
                        $builder->orWhere('tb1_id', (int) $term);
                    }
                });
            })
            ->when($filters['type'] !== '', function ($query) use ($filters) {
                if ($filters['type'] === 'balanca_industria') {
                    $query->whereIn('tb1_tipo', [0, 1]);
                    return;
                }

                $query->where('tb1_tipo', (int) $filters['type']);
            })
            ->when($filters['origin'] !== '', function ($query) use ($filters) {
                $query->whereHas('categoriaFiscal', fn ($categoryQuery) => $categoryQuery
                    ->where('tb30_origem_mercadoria', $filters['origin']));
            })
            ->when($filters['only_exception'], fn ($query) => $query->where('tb1_usa_excecao_fiscal', true));
    }

    private function filteredProductsSearchQuery(array $filters)
    {
        return $this->filteredProductsQuery($filters)
            ->when($filters['without_category'], fn ($query) => $query->where(function ($builder) {
                $builder->whereNull('tb30_categoria_fiscal_id')
                    ->orWhereNull('tb33_grupo_ncm_id');
            }))
            ->when($filters['category_id'] !== '', fn ($query) => $query->where('tb30_categoria_fiscal_id', (int) $filters['category_id']))
            ->when($filters['group_id'] !== '', fn ($query) => $query->where('tb33_grupo_ncm_id', (int) $filters['group_id']));
    }

    private function fiscalSummary(): array
    {
        $baseQuery = Produto::query()->whereIn('tb1_tipo', [0, 1]);
        $total = (clone $baseQuery)->count();
        $linked = (clone $baseQuery)
            ->whereNotNull('tb30_categoria_fiscal_id')
            ->whereNotNull('tb33_grupo_ncm_id')
            ->count();
        $pending = max($total - $linked, 0);

        return [
            'total' => $total,
            'linked' => $linked,
            'pending' => $pending,
            'linked_percent' => $total > 0 ? round(($linked / $total) * 100, 1) : 0,
            'pending_percent' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
        ];
    }

    public function apply(Request $request, ProductQuickLookupCache $quickLookupCache): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', Rule::exists('tb1_produto', 'tb1_id')],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('tb30_categorias_fiscais', 'tb30_id')->where('tb30_ativo', true),
            ],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('tb33_grupos_ncm', 'tb33_id')->where('tb33_ativo', true),
            ],
        ], [
            'product_ids.required' => 'Selecione pelo menos um produto.',
            'product_ids.min' => 'Selecione pelo menos um produto.',
            'category_id.required' => 'Selecione a nova categoria fiscal.',
            'category_id.exists' => 'Selecione uma categoria fiscal ativa.',
            'group_id.required' => 'Selecione o novo grupo NCM.',
            'group_id.exists' => 'Selecione um grupo NCM ativo.',
        ]);

        $productIds = collect($data['product_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $categoryId = (int) $data['category_id'];
        $groupId = (int) $data['group_id'];
        $userId = $request->user()?->id;

        $affected = DB::transaction(function () use ($productIds, $categoryId, $groupId, $userId) {
            $products = Produto::query()
                ->whereIn('tb1_id', $productIds)
                ->lockForUpdate()
                ->get(['tb1_id', 'tb30_categoria_fiscal_id', 'tb33_grupo_ncm_id']);

            foreach ($products as $product) {
                CategoriaFiscalHistorico::create([
                    'tb30_categoria_fiscal_id' => $categoryId,
                    'tb1_id' => $product->tb1_id,
                    'user_id' => $userId,
                    'tb32_acao' => 'associacao_massa',
                    'tb32_campo' => 'tb30_categoria_fiscal_id',
                    'tb32_valor_anterior' => $product->tb30_categoria_fiscal_id === null ? null : (string) $product->tb30_categoria_fiscal_id,
                    'tb32_valor_novo' => (string) $categoryId,
                    'tb32_registros_afetados' => $products->count(),
                ]);

                CategoriaFiscalHistorico::create([
                    'tb30_categoria_fiscal_id' => $categoryId,
                    'tb1_id' => $product->tb1_id,
                    'user_id' => $userId,
                    'tb32_acao' => 'associacao_massa',
                    'tb32_campo' => 'tb33_grupo_ncm_id',
                    'tb32_valor_anterior' => $product->tb33_grupo_ncm_id === null ? null : (string) $product->tb33_grupo_ncm_id,
                    'tb32_valor_novo' => (string) $groupId,
                    'tb32_registros_afetados' => $products->count(),
                ]);
            }

            Produto::query()
                ->whereIn('tb1_id', $products->pluck('tb1_id'))
                ->update([
                    'tb30_categoria_fiscal_id' => $categoryId,
                    'tb33_grupo_ncm_id' => $groupId,
                    'tb1_responsavel_ultima_alteracao' => $userId,
                    'updated_at' => now(),
                ]);

            return $products->count();
        });

        $quickLookupCache->invalidateCatalog();

        return redirect()
            ->route('products.fiscal-mass-association.index', $request->only([
                'search',
                'type',
                'origin',
                'without_category',
                'category_id',
                'group_id',
                'only_exception',
            ]))
            ->with('success', sprintf('Categoria fiscal e grupo NCM aplicados em %d produto(s).', $affected));
    }

    public function renameProduct(Request $request, Produto $product, ProductQuickLookupCache $quickLookupCache): RedirectResponse
    {
        $data = $request->validate([
            'tb1_nome' => ['required', 'string', 'max:45'],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('tb33_grupos_ncm', 'tb33_id')->where('tb33_ativo', true),
            ],
            'filters' => ['nullable', 'array'],
            'apply_group_to_filters' => ['nullable', 'boolean'],
        ], [
            'tb1_nome.required' => 'Informe o nome do produto.',
            'tb1_nome.max' => 'O nome nao pode exceder :max caracteres.',
            'group_id.required' => 'Selecione o grupo NCM.',
            'group_id.exists' => 'Selecione um grupo NCM ativo.',
        ]);

        $name = $this->normalizeProductName($data['tb1_nome']);
        $groupId = (int) $data['group_id'];
        $applyGroupToFilters = (bool) ($data['apply_group_to_filters'] ?? false);
        $userId = $request->user()?->id;

        if ($name === '') {
            throw ValidationException::withMessages([
                'tb1_nome' => 'Informe o nome do produto.',
            ]);
        }

        $affectedGroupProducts = DB::transaction(function () use ($product, $name, $groupId, $applyGroupToFilters, $data, $userId) {
            $filters = array_merge([
                'search' => '',
                'type' => '',
                'origin' => '',
                'without_category' => false,
                'category_id' => '',
                'group_id' => '',
                'only_exception' => false,
            ], $this->filterQueryFromPayload($data['filters'] ?? []));

            $targetProducts = $applyGroupToFilters
                ? $this->filteredProductsSearchQuery($filters)
                    ->lockForUpdate()
                    ->get(['tb1_id', 'tb30_categoria_fiscal_id', 'tb33_grupo_ncm_id'])
                : Produto::query()
                    ->where('tb1_id', $product->tb1_id)
                    ->lockForUpdate()
                    ->get(['tb1_id', 'tb30_categoria_fiscal_id', 'tb33_grupo_ncm_id']);

            $product->update([
                'tb1_nome' => $name,
                'tb1_responsavel_ultima_alteracao' => $userId,
            ]);

            foreach ($targetProducts as $targetProduct) {
                CategoriaFiscalHistorico::create([
                    'tb30_categoria_fiscal_id' => $targetProduct->tb30_categoria_fiscal_id,
                    'tb1_id' => $targetProduct->tb1_id,
                    'user_id' => $userId,
                    'tb32_acao' => $applyGroupToFilters ? 'grupo_ncm_massa_busca' : 'edicao_produto_modal',
                    'tb32_campo' => 'tb33_grupo_ncm_id',
                    'tb32_valor_anterior' => $targetProduct->tb33_grupo_ncm_id === null ? null : (string) $targetProduct->tb33_grupo_ncm_id,
                    'tb32_valor_novo' => (string) $groupId,
                    'tb32_registros_afetados' => $targetProducts->count(),
                ]);
            }

            Produto::query()
                ->whereIn('tb1_id', $targetProducts->pluck('tb1_id'))
                ->update([
                    'tb33_grupo_ncm_id' => $groupId,
                    'tb1_responsavel_ultima_alteracao' => $userId,
                    'updated_at' => now(),
                ]);

            return $targetProducts->count();
        });

        $quickLookupCache->invalidateCatalog();

        return redirect()
            ->route('products.fiscal-mass-association.index', $this->filterQueryFromPayload($data['filters'] ?? []))
            ->with('success', $applyGroupToFilters
                ? sprintf('Nome do produto atualizado. Grupo NCM aplicado em %d produto(s) da busca atual.', $affectedGroupProducts)
                : 'Nome e Grupo NCM do produto atualizados com sucesso.');
    }

    private function typeOptions(): array
    {
        return [
            ['value' => 'balanca_industria', 'label' => 'Balanca ou Industria'],
            ['value' => 0, 'label' => 'Industria'],
            ['value' => 1, 'label' => 'Balanca'],
            ['value' => 2, 'label' => 'Servico'],
            ['value' => 3, 'label' => 'Producao'],
        ];
    }

    private function normalizeProductName(mixed $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return '';
        }

        return mb_strtoupper($normalized, self::PRODUCT_NAME_ENCODING);
    }

    private function filterQueryFromPayload(array $filters): array
    {
        $query = [];

        foreach (['search', 'type', 'origin', 'category_id', 'group_id'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value !== '') {
                $query[$field] = $value;
            }
        }

        foreach (['without_category', 'only_exception'] as $field) {
            if (filter_var($filters[$field] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $query[$field] = 1;
            }
        }

        return $query;
    }
}
