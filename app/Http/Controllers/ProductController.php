<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFiscal;
use App\Models\CategoriaFiscalHistorico;
use App\Models\GrupoNcm;
use App\Models\Produto;
use App\Models\ProdutoExcecaoFiscal;
use App\Models\ReferenciaFiscal;
use App\Models\User;
use App\Support\FiscalProductTaxService;
use App\Support\ProductQuickLookupCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const RESERVED_PRODUCT_ID_START = 3000;

    private const RESERVED_PRODUCT_ID_END = 3100;

    private const MAX_SAFE_PRODUCT_ID = 9999;

    private const TYPE_LABELS = [
        0 => 'Industria',
        1 => 'Balanca',
        2 => 'Servico',
        3 => 'Producao',
    ];

    private const STATUS_LABELS = [
        0 => 'Inativo',
        1 => 'Ativo',
    ];

    private const ORIGIN_LABELS = [
        0 => '0 - Nacional',
        1 => '1 - Estrangeira importacao direta',
        2 => '2 - Estrangeira adquirida no mercado interno',
        3 => '3 - Nacional com conteudo de importacao superior a 40%',
        4 => '4 - Nacional com processo produtivo basico',
        5 => '5 - Nacional com conteudo de importacao ate 40%',
        6 => '6 - Estrangeira importacao direta sem similar nacional',
        7 => '7 - Estrangeira mercado interno sem similar nacional',
        8 => '8 - Nacional com conteudo de importacao superior a 70%',
    ];

    private const PRODUCT_NAME_ENCODING = 'UTF-8';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $vrCreditOnly = in_array(strtolower((string) $request->input('vr_credit', '0')), ['1', 'true'], true);
        $fiscalStatus = strtolower(trim((string) $request->input('fiscal_status', '')));
        $sort = (string) $request->input('sort', '');
        $direction = strtolower((string) $request->input('direction', 'asc'));
        $query = Produto::query();

        if ($search !== '') {
            $isNumeric = ctype_digit($search);
            $safeTerm = str_replace(['%', '_'], ['\%', '\_'], $search);
            $likeTerm = '%' . $safeTerm . '%';
            $numericTerm = $isNumeric ? (int) $search : null;
            $isLongNumeric = $isNumeric && mb_strlen($search) > 4;

            $query->where(function ($builder) use ($isNumeric, $isLongNumeric, $likeTerm, $numericTerm) {
                if ($isNumeric) {
                    if ($isLongNumeric) {
                        $builder->where('tb1_codbar', 'like', $likeTerm);
                    } else {
                        $builder->where('tb1_id', $numericTerm);
                    }

                    return;
                }

                $builder->where('tb1_nome', 'like', $likeTerm);
            });
        }

        if ($vrCreditOnly) {
            $query->where('tb1_vr_credit', true);
        }

        if (in_array($fiscalStatus, ['complete', 'incomplete'], true)) {
            $query->where(function ($builder) use ($fiscalStatus) {
                if ($fiscalStatus === 'complete') {
                    $builder->whereHas('categoriaFiscal', function ($categoryQuery) {
                        $categoryQuery
                            ->where('tb30_ativo', true)
                            ->whereNotNull('tb30_cfop_venda_interna')
                            ->where('tb30_cfop_venda_interna', '!=', '')
                            ->where(function ($taxQuery) {
                                $taxQuery->whereNotNull('tb30_csosn')
                                    ->where('tb30_csosn', '!=', '')
                                    ->orWhere(function ($cstQuery) {
                                        $cstQuery->whereNotNull('tb30_cst_icms')
                                            ->where('tb30_cst_icms', '!=', '');
                                    });
                            });
                    })->whereHas('grupoNcm', function ($groupQuery) {
                        $groupQuery
                            ->where('tb33_ativo', true)
                            ->whereNotNull('tb33_ncm')
                            ->where('tb33_ncm', '!=', '');
                    });
                } else {
                    $builder->whereNull('tb30_categoria_fiscal_id')
                        ->orWhereNull('tb33_grupo_ncm_id')
                        ->orWhereDoesntHave('categoriaFiscal')
                        ->orWhereDoesntHave('grupoNcm')
                        ->orWhereHas('grupoNcm', function ($groupQuery) {
                            $groupQuery
                                ->where('tb33_ativo', false)
                                ->orWhereNull('tb33_ncm')
                                ->orWhere('tb33_ncm', '=', '');
                        })
                        ->orWhereHas('categoriaFiscal', function ($categoryQuery) {
                            $categoryQuery
                                ->where('tb30_ativo', false)
                                ->orWhereNull('tb30_cfop_venda_interna')
                                ->orWhere('tb30_cfop_venda_interna', '=', '')
                                ->orWhere(function ($taxQuery) {
                                    $taxQuery->where(function ($csosnQuery) {
                                        $csosnQuery->whereNull('tb30_csosn')
                                            ->orWhere('tb30_csosn', '=', '');
                                    })->where(function ($cstQuery) {
                                        $cstQuery->whereNull('tb30_cst_icms')
                                            ->orWhere('tb30_cst_icms', '=', '');
                                    });
                                });
                        });
                }
            });
        }

        $allowedSorts = [
            'tb1_favorito',
            'tb1_id',
            'tb1_nome',
            'tb1_vlr_custo',
            'tb1_vlr_venda',
            'tb1_codbar',
            'tb1_tipo',
            'tb1_qtd',
            'tb1_status',
        ];
        $allowedDirections = ['asc', 'desc'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }

        if (! in_array($direction, $allowedDirections, true)) {
            $direction = 'asc';
        }

        if ($sort !== '') {
            $query->orderBy($sort, $direction)
                ->orderBy('tb1_id', $direction);
        } else {
            $query->orderByDesc('tb1_favorito')
                ->orderByDesc('tb1_id');
            $direction = '';
        }

        $products = $query->paginate(10)->withQueryString();

        return Inertia::render('Products/ProductIndex', [
            'products' => $products,
            'typeLabels' => self::TYPE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'search' => $search,
            'vrCreditOnly' => $vrCreditOnly,
            'fiscalStatus' => $fiscalStatus,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Produto $product): Response
    {
        $product->load(['categoriaFiscal', 'grupoNcm', 'excecoesFiscais']);

        return Inertia::render('Products/ProductShow', [
            'product' => $product,
            'fiscalData' => app(FiscalProductTaxService::class)->resolve($product),
            'typeLabels' => self::TYPE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/ProductCreate', $this->formOptions());
    }

    public function fiscalQueue(): Response
    {
        $typeFilter = $this->resolveFiscalQueueTypeFilter(request());
        $search = $this->resolveFiscalQueueSearch(request());
        $pendingQuery = $this->pendingFiscalProductsQuery($typeFilter, $search);

        return Inertia::render('Products/ProductFiscalQueue', [
            'products' => $pendingQuery->limit(30)->get(),
            'pendingCount' => $this->pendingFiscalProductsQuery($typeFilter, $search)->count(),
            'references' => ReferenciaFiscal::query()
                ->orderBy('tb29_descricao')
                ->orderBy('tb29_id')
                ->get([
                    'tb29_id',
                    'tb29_descricao',
                    'tb29_ncm',
                    'tb29_cfop',
                    'tb29_csosn',
                    'tb29_cst',
                    'tb29_cst_ibscbs',
                    'tb29_cclasstrib',
                    'tb29_aliquota_ibs_uf',
                    'tb29_aliquota_ibs_mun',
                    'tb29_aliquota_cbs',
                    'tb29_aliquota_is',
                ]),
            'selectedType' => $typeFilter,
            'search' => $search,
            'typeOptions' => $this->formOptions()['typeOptions'],
        ]);
    }

    public function applyFiscalReference(Request $request, ProductQuickLookupCache $quickLookupCache)
    {
        $data = $request->validate(
            [
                'reference_id' => ['required', 'integer', Rule::exists('tb29_referencias_fiscais', 'tb29_id')],
                'product_ids' => ['required', 'array', 'min:1'],
                'product_ids.*' => ['required', 'integer', Rule::exists('tb1_produto', 'tb1_id')],
            ],
            [
                'reference_id.required' => 'Selecione uma referencia fiscal.',
                'reference_id.exists' => 'A referencia fiscal selecionada nao foi encontrada.',
                'product_ids.required' => 'Selecione pelo menos um produto.',
                'product_ids.array' => 'A lista de produtos selecionados e invalida.',
                'product_ids.min' => 'Selecione pelo menos um produto.',
                'product_ids.*.exists' => 'Um dos produtos selecionados nao foi encontrado.',
            ]
        );

        $reference = ReferenciaFiscal::query()->findOrFail((int) $data['reference_id']);
        $productIds = collect($data['product_ids'])
            ->map(fn ($productId) => (int) $productId)
            ->unique()
            ->values()
            ->all();

        $affected = Produto::query()
            ->whereIn('tb1_id', $productIds)
            ->update([
                'tb1_ncm' => $this->normalizeDigitsField($reference->tb29_ncm, 8),
                'tb1_cfop' => $this->normalizeDigitsField($reference->tb29_cfop, 4),
                'tb1_csosn' => $this->normalizeDigitsField($reference->tb29_csosn, 4),
                'tb1_cst' => $this->normalizeDigitsField($reference->tb29_cst, 3),
                'tb1_cst_ibscbs' => $this->normalizeDigitsField($reference->tb29_cst_ibscbs, 3),
                'tb1_cclasstrib' => $this->normalizeDigitsField($reference->tb29_cclasstrib, 6),
                'tb1_aliquota_ibs_uf' => $this->normalizeNullableDecimal($reference->tb29_aliquota_ibs_uf, 4),
                'tb1_aliquota_ibs_mun' => $this->normalizeNullableDecimal($reference->tb29_aliquota_ibs_mun, 4),
                'tb1_aliquota_cbs' => $this->normalizeNullableDecimal($reference->tb29_aliquota_cbs, 4),
                'tb1_aliquota_is' => $this->normalizeNullableDecimal($reference->tb29_aliquota_is, 4),
            ]);
        $quickLookupCache->invalidateCatalog();

        return Redirect::route('products.fiscal-queue', $this->buildFiscalQueueQueryFromRequest($request))
            ->with('success', sprintf(
                'Referencia fiscal "%s" aplicada em %d produto(s).',
                $reference->tb29_descricao,
                $affected
            ));
    }

    public function store(Request $request, ProductQuickLookupCache $quickLookupCache)
    {
        $data = $this->validateProduct($request);

        if ((int) ($data['tb1_tipo'] ?? 0) !== 1 || blank($data['tb1_id'] ?? null)) {
            $data['tb1_id'] = $this->nextSafeProductId($this->shouldUseOwnIdAsBarcode($data));
        }

        $data['tb1_vr_credit'] = (bool) ($data['tb1_vr_credit'] ?? false);
        $data = $this->prepareProductData($data);

        $product = Produto::create($data);
        $this->syncFiscalException($request, $product);
        $this->logProductCategoryChange($request, $product, null, $product->tb30_categoria_fiscal_id, 'criacao_produto');
        $quickLookupCache->invalidateCatalog();

        return Redirect::route('products.show', ['product' => $product->tb1_id])
            ->with('success', 'Produto cadastrado com sucesso!');
    }

    public function edit(Produto $product): Response
    {
        $product->load(['categoriaFiscal', 'grupoNcm', 'excecoesFiscais']);

        return Inertia::render('Products/ProductEdit', array_merge(
            [
                'product' => $product,
                'fiscalData' => app(FiscalProductTaxService::class)->resolve($product),
            ],
            $this->formOptions()
        ));
    }

    public function update(Request $request, Produto $product, ProductQuickLookupCache $quickLookupCache)
    {
        $data = $this->validateProduct($request, $product);
        $data['tb1_vr_credit'] = (bool) ($data['tb1_vr_credit'] ?? false);
        $data = $this->prepareProductData($data, $product);
        $previousCategoryId = $product->tb30_categoria_fiscal_id;

        $product->update($data);
        $this->syncFiscalException($request, $product);
        $this->logProductCategoryChange($request, $product, $previousCategoryId, $product->tb30_categoria_fiscal_id, 'troca_categoria_produto');
        $quickLookupCache->invalidateCatalog();

        return Redirect::route('products.show', ['product' => $product->tb1_id])
            ->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $product, ProductQuickLookupCache $quickLookupCache)
    {
        $product->delete();
        $quickLookupCache->invalidateCatalog();

        return Redirect::route('products.index')
            ->with('success', 'Produto removido com sucesso!');
    }

    public function toggleFavorite(
        Request $request,
        Produto $product,
        ProductQuickLookupCache $quickLookupCache
    )
    {
        $data = $request->validate([
            'favorite' => 'required|boolean',
        ]);

        $product->update([
            'tb1_favorito' => $data['favorite'],
        ]);
        $quickLookupCache->invalidateCatalog();

        return Redirect::back()->with('success', $data['favorite'] ? 'Produto marcado como favorito.' : 'Produto removido dos favoritos.');
    }

    public function favorites(): JsonResponse
    {
        $favorites = Produto::query()
            ->where('tb1_favorito', true)
            ->where('tb1_status', 1)
            ->orderBy('tb1_nome')
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
            ]);

        return response()->json($favorites);
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $typeFilter = $request->input('type');

        $isNumeric = ctype_digit($term);

        if (mb_strlen($term) < 3 && ! $isNumeric) {
            return response()->json([]);
        }

        $numericTerm = $isNumeric ? (int) $term : null;
        $isLongNumeric = $isNumeric && mb_strlen($term) > 4;

        $selectedColumns = [
            'tb1_id',
            'tb1_nome',
            'tb1_codbar',
            'tb1_vlr_custo',
            'tb1_vlr_venda',
            'tb1_tipo',
            'tb1_qtd',
            'tb1_status',
            'tb1_vr_credit',
        ];

        $baseQuery = Produto::query();

        if ($typeFilter !== null && $typeFilter !== '') {
            $baseQuery->where('tb1_tipo', (int) $typeFilter);
        }

        if ($isNumeric) {
            $products = (clone $baseQuery)
                ->where(function ($query) use ($isLongNumeric, $numericTerm, $term) {
                    if ($isLongNumeric) {
                        $query->where('tb1_codbar', $term);
                    } else {
                        $query->where('tb1_id', $numericTerm);
                    }
                })
                ->orderByDesc('tb1_status')
                ->orderBy('tb1_nome')
                ->limit(10)
                ->get($selectedColumns);

            return response()->json($products);
        }

        $safeLikeTerm = str_replace(['%', '_'], ['\%', '\_'], $term);
        $products = (clone $baseQuery)
            ->where('tb1_nome', 'like', '%' . $safeLikeTerm . '%')
            ->orderByDesc('tb1_status')
            ->orderBy('tb1_nome')
            ->limit(10)
            ->get($selectedColumns);

        return response()->json($products);
    }

    public function quickLookup(Request $request, ProductQuickLookupCache $quickLookupCache): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if ($term === '' || ! ctype_digit($term)) {
            return response()->json([
                'message' => 'Informe um codigo de barras ou ID numerico.',
            ], 422);
        }

        $weightedBarcode = $this->parseWeightedBarcode($term);
        $isLongNumeric = mb_strlen($term) > 4;

        $product = Produto::query()
            ->when($weightedBarcode !== null, function ($query) use ($weightedBarcode) {
                $query->where('tb1_id', $weightedBarcode['product_id']);
            })
            ->when($weightedBarcode === null && $isLongNumeric, function ($query) use ($term) {
                $query->where('tb1_codbar', $term);
            })
            ->when($weightedBarcode === null && ! $isLongNumeric, function ($query) use ($term) {
                $query->where('tb1_id', (int) $term);
            })
            ->first([
                'tb1_id',
                'tb1_nome',
                'tb1_codbar',
                'tb1_vlr_custo',
                'tb1_vlr_venda',
                'tb1_tipo',
                'tb1_qtd',
                'tb1_status',
                'tb1_vr_credit',
            ]);

        if (! $product) {
            return response()->json([
                'message' => 'Produto nao encontrado.',
            ], 404);
        }

        $quickLookupCache->rememberProductForRequest($product, $request);

        return response()->json($quickLookupCache->productPayload($product));
    }

    public function quickLookupSnapshot(
        Request $request,
        ProductQuickLookupCache $quickLookupCache
    ): JsonResponse {
        $snapshot = $quickLookupCache->snapshotForRequest($request);
        $requestedVersion = (int) $request->query('version', 0);
        $currentVersion = (int) ($snapshot['version'] ?? 1);

        if ($requestedVersion > 0 && $requestedVersion === $currentVersion) {
            return response()->json([
                'changed' => false,
                'version' => $currentVersion,
            ]);
        }

        return response()->json([
            'changed' => true,
            'version' => $currentVersion,
            'products' => $snapshot['products'] ?? [],
        ]);
    }

    private function parseWeightedBarcode(?string $barcode): ?array
    {
        $barcode = trim((string) $barcode);

        if (
            $barcode === '' ||
            ! preg_match('/^\d{13}$/', $barcode) ||
            substr($barcode, 0, 1) !== '2'
        ) {
            return null;
        }

        $productId = (int) substr($barcode, 1, 4);

        if ($productId <= 0) {
            return null;
        }

        return [
            'barcode' => $barcode,
            'product_id' => $productId,
        ];
    }

    private function validateProduct(Request $request, ?Produto $product = null): array
    {
        $data = $request->validate(
            [
                'tb1_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:' . self::MAX_SAFE_PRODUCT_ID,
                ],
                'tb1_nome' => 'nullable|string|max:45',
                'tb1_vlr_custo' => 'nullable|numeric|min:0',
                'tb1_vlr_venda' => 'nullable|numeric|min:0',
                'tb1_codbar' => [
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('tb1_produto', 'tb1_codbar')->ignore($product?->tb1_id, 'tb1_id'),
                ],
                'tb1_tipo' => [
                    'nullable',
                    'integer',
                    Rule::in(array_keys(self::TYPE_LABELS)),
                ],
                'tb30_categoria_fiscal_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('tb30_categorias_fiscais', 'tb30_id'),
                ],
                'tb33_grupo_ncm_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('tb33_grupos_ncm', 'tb33_id'),
                ],
                'tb1_ncm_proprio' => ['nullable', 'string', 'size:8'],
                'tb1_usa_excecao_fiscal' => ['nullable', 'boolean'],
                'tb1_ncm' => ['nullable', 'string', 'size:8'],
                'tb1_cest' => ['nullable', 'string', 'size:7'],
                'tb1_cfop' => ['nullable', 'string', 'size:4'],
                'tb1_unidade_comercial' => ['nullable', 'string', 'max:6'],
                'tb1_unidade_tributavel' => ['nullable', 'string', 'max:6'],
                'tb1_origem' => ['nullable', 'integer', Rule::in(array_keys(self::ORIGIN_LABELS))],
                'tb1_csosn' => ['nullable', 'string', 'max:4'],
                'tb1_cst' => ['nullable', 'string', 'max:3'],
                'tb1_aliquota_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tb1_cst_ibscbs' => ['nullable', 'string', 'size:3'],
                'tb1_cclasstrib' => ['nullable', 'string', 'size:6'],
                'tb1_cff_nt' => ['nullable', 'string', 'max:20'],
                'tb1_ind_doacao' => ['nullable', 'boolean'],
                'tb1_aliquota_ibs_uf' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tb1_aliquota_ibs_mun' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tb1_aliquota_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tb1_aliquota_is' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tb1_qtd' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'tb1_status' => [
                    'nullable',
                    'integer',
                    Rule::in(array_keys(self::STATUS_LABELS)),
                ],
                'tb1_vr_credit' => [
                    'nullable',
                    'boolean',
                ],
                'excecao_fiscal' => ['nullable', 'array'],
                'excecao_fiscal.tb31_motivo_excecao' => ['nullable', 'string', 'max:255'],
                'excecao_fiscal.tb31_data_inicio_vigencia' => ['nullable', 'date'],
                'excecao_fiscal.tb31_data_fim_vigencia' => ['nullable', 'date', 'after_or_equal:excecao_fiscal.tb31_data_inicio_vigencia'],
                'excecao_fiscal.tb31_ncm' => ['nullable', 'string', 'size:8'],
                'excecao_fiscal.tb31_cest' => ['nullable', 'string', 'size:7'],
                'excecao_fiscal.tb31_cclass_trib' => ['nullable', 'string', 'size:6'],
                'excecao_fiscal.tb31_cst_ibs' => ['nullable', 'string', 'size:3'],
                'excecao_fiscal.tb31_cst_cbs' => ['nullable', 'string', 'size:3'],
                'excecao_fiscal.tb31_aliquota_ibs_uf' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_aliquota_ibs_municipio' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_aliquota_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_aliquota_is' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_cfop_venda_interna' => ['nullable', 'string', 'size:4'],
                'excecao_fiscal.tb31_cfop_venda_interestadual' => ['nullable', 'string', 'size:4'],
                'excecao_fiscal.tb31_cfop_consumo_local' => ['nullable', 'string', 'size:4'],
                'excecao_fiscal.tb31_cfop_entrega' => ['nullable', 'string', 'size:4'],
                'excecao_fiscal.tb31_csosn' => ['nullable', 'string', 'max:4'],
                'excecao_fiscal.tb31_cst_icms' => ['nullable', 'string', 'max:3'],
                'excecao_fiscal.tb31_cst_pis' => ['nullable', 'string', 'max:3'],
                'excecao_fiscal.tb31_cst_cofins' => ['nullable', 'string', 'max:3'],
                'excecao_fiscal.tb31_aliquota_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_aliquota_pis' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_aliquota_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'excecao_fiscal.tb31_observacao_fiscal' => ['nullable', 'string'],
                'sem_codigo_barras' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'tb1_id.integer' => 'O ID do produto deve ser numerico.',
                'tb1_id.min' => 'O ID do produto deve ser maior que zero.',
                'tb1_id.max' => 'O ID do produto deve ser no maximo :max.',
                'tb1_nome.max' => 'O nome nao pode exceder :max caracteres.',
                'tb1_vlr_custo.numeric' => 'O valor de custo deve ser numerico.',
                'tb1_vlr_custo.min' => 'O valor de custo deve ser maior ou igual a zero.',
                'tb1_vlr_venda.numeric' => 'O valor de venda deve ser numerico.',
                'tb1_vlr_venda.min' => 'O valor de venda deve ser maior ou igual a zero.',
                'tb1_codbar.max' => 'O codigo de barras deve ter no maximo :max caracteres.',
                'tb1_codbar.unique' => 'Este codigo de barras ja esta cadastrado.',
                'tb1_tipo.integer' => 'Tipo de produto invalido.',
                'tb1_tipo.in' => 'Tipo de produto nao reconhecido.',
                'tb30_categoria_fiscal_id.exists' => 'Categoria fiscal nao encontrada.',
                'tb33_grupo_ncm_id.exists' => 'Grupo NCM nao encontrado.',
                'tb1_ncm_proprio.size' => 'O NCM proprio deve ter exatamente 8 digitos.',
                'tb1_ncm.size' => 'O NCM deve ter exatamente 8 digitos.',
                'tb1_cest.size' => 'O CEST deve ter exatamente 7 digitos.',
                'tb1_cfop.size' => 'O CFOP deve ter exatamente 4 digitos.',
                'tb1_unidade_comercial.max' => 'A unidade comercial deve ter no maximo :max caracteres.',
                'tb1_unidade_tributavel.max' => 'A unidade tributavel deve ter no maximo :max caracteres.',
                'tb1_origem.in' => 'Origem fiscal invalida.',
                'tb1_csosn.max' => 'O CSOSN deve ter no maximo :max caracteres.',
                'tb1_cst.max' => 'O CST deve ter no maximo :max caracteres.',
                'tb1_aliquota_icms.numeric' => 'A aliquota de ICMS deve ser numerica.',
                'tb1_aliquota_icms.min' => 'A aliquota de ICMS nao pode ser negativa.',
                'tb1_aliquota_icms.max' => 'A aliquota de ICMS nao pode ultrapassar 100%.',
                'tb1_cst_ibscbs.size' => 'O CST IBS/CBS deve ter exatamente 3 digitos.',
                'tb1_cclasstrib.size' => 'O cClassTrib deve ter exatamente 6 digitos.',
                'tb1_cff_nt.max' => 'O campo CFF / NT deve ter no maximo :max caracteres.',
                'tb1_ind_doacao.boolean' => 'Valor invalido para indicador de doacao.',
                'tb1_aliquota_ibs_uf.numeric' => 'A aliquota IBS UF deve ser numerica.',
                'tb1_aliquota_ibs_uf.min' => 'A aliquota IBS UF nao pode ser negativa.',
                'tb1_aliquota_ibs_uf.max' => 'A aliquota IBS UF nao pode ultrapassar 100%.',
                'tb1_aliquota_ibs_mun.numeric' => 'A aliquota IBS Municipio deve ser numerica.',
                'tb1_aliquota_ibs_mun.min' => 'A aliquota IBS Municipio nao pode ser negativa.',
                'tb1_aliquota_ibs_mun.max' => 'A aliquota IBS Municipio nao pode ultrapassar 100%.',
                'tb1_aliquota_cbs.numeric' => 'A aliquota CBS deve ser numerica.',
                'tb1_aliquota_cbs.min' => 'A aliquota CBS nao pode ser negativa.',
                'tb1_aliquota_cbs.max' => 'A aliquota CBS nao pode ultrapassar 100%.',
                'tb1_aliquota_is.numeric' => 'A aliquota IS deve ser numerica.',
                'tb1_aliquota_is.min' => 'A aliquota IS nao pode ser negativa.',
                'tb1_aliquota_is.max' => 'A aliquota IS nao pode ultrapassar 100%.',
                'tb1_qtd.integer' => 'A quantidade em estoque deve ser numerica e inteira.',
                'tb1_qtd.min' => 'A quantidade em estoque nao pode ser negativa.',
                'tb1_status.integer' => 'Status invalido.',
                'tb1_status.in' => 'Status nao reconhecido.',
                'tb1_vr_credit.boolean' => 'Valor invalido para VR Credito.',
                'sem_codigo_barras.boolean' => 'Valor invalido para a opcao sem codigo de barras.',
            ]
        );

        $requestedId = isset($data['tb1_id']) ? (int) $data['tb1_id'] : null;

        if ($product && $requestedId !== null && $requestedId !== (int) $product->tb1_id) {
            throw ValidationException::withMessages([
                'tb1_id' => 'Nao e permitido alterar o ID de um produto ja cadastrado.',
            ]);
        }

        if ($product === null && (int) ($data['tb1_tipo'] ?? 0) === 1 && $requestedId !== null) {
            if ($this->isReservedProductId($requestedId)) {
                throw ValidationException::withMessages([
                    'tb1_id' => sprintf(
                        'Os IDs de %d a %d sao reservados para comandas. Informe outro ID de balanca.',
                        self::RESERVED_PRODUCT_ID_START,
                        self::RESERVED_PRODUCT_ID_END
                    ),
                ]);
            }

            $existingProduct = Produto::query()->find($requestedId);

            if ($existingProduct) {
                throw ValidationException::withMessages([
                    'tb1_id' => $this->existingProductMessage($existingProduct),
                ]);
            }
        }

        $this->ensurePriceEditingIsAuthorized($data, $product, $request->user());

        $resolvedBarcode = $this->resolveProductBarcode($data, $product);

        if ($resolvedBarcode !== '') {
            $barcodeInUse = Produto::query()
                ->where('tb1_codbar', $resolvedBarcode)
                ->when(
                    $product,
                    fn ($query) => $query->where('tb1_id', '!=', $product->tb1_id)
                )
                ->first();

            if ($barcodeInUse) {
                $field = $this->shouldUseOwnIdAsBarcode($data, $product) ? 'tb1_id' : 'tb1_codbar';

                throw ValidationException::withMessages([
                    $field => sprintf(
                        'O codigo de barras %s ja esta em uso no produto %d.',
                        $resolvedBarcode,
                        $barcodeInUse->tb1_id
                    ),
                ]);
            }
        }

        return $data;
    }

    private function formOptions(): array
    {
        $format = fn (array $labels) => collect($labels)
            ->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();

        return [
            'typeOptions' => $format(self::TYPE_LABELS),
            'statusOptions' => $format(self::STATUS_LABELS),
            'originOptions' => $format(self::ORIGIN_LABELS),
            'fiscalCategories' => CategoriaFiscal::query()
                ->orderByDesc('tb30_ativo')
                ->orderBy('tb30_nome')
                ->get([
                    'tb30_id',
                    'tb30_nome',
                    'tb30_ativo',
                    'tb30_cfop_venda_interna',
                    'tb30_cfop_venda_interestadual',
                    'tb30_cfop_consumo_local',
                    'tb30_cfop_entrega',
                    'tb30_csosn',
                    'tb30_cst_icms',
                    'tb30_cst_pis',
                    'tb30_cst_cofins',
                    'tb30_cst_ibs',
                    'tb30_cst_cbs',
                    'tb30_cclass_trib',
                    'tb30_aliquota_ibs_uf',
                    'tb30_aliquota_ibs_municipio',
                    'tb30_aliquota_cbs',
                    'tb30_aliquota_is',
                    'tb30_observacao_fiscal',
                ]),
            'gruposNcm' => GrupoNcm::query()
                ->orderByDesc('tb33_ativo')
                ->orderBy('tb33_nome')
                ->get([
                    'tb33_id',
                    'tb33_codigo',
                    'tb33_nome',
                    'tb33_ncm',
                    'tb33_cest',
                    'tb33_cclass_trib',
                    'tb33_ativo',
                    'tb33_observacao_fiscal',
                ]),
        ];
    }

    private function pendingFiscalProductsQuery(?int $typeFilter = null, string $search = '')
    {
        return Produto::query()
            ->select([
                'tb1_id',
                'tb1_nome',
                'tb1_codbar',
                'tb1_tipo',
                'tb30_categoria_fiscal_id',
                'tb33_grupo_ncm_id',
                'tb1_ncm',
                'tb1_cfop',
                'tb1_csosn',
                'tb1_cst',
                'tb1_cst_ibscbs',
                'tb1_cclasstrib',
                'tb1_aliquota_ibs_uf',
                'tb1_aliquota_ibs_mun',
                'tb1_aliquota_cbs',
                'tb1_aliquota_is',
            ])
            ->when($typeFilter !== null, function ($query) use ($typeFilter) {
                $query->where('tb1_tipo', $typeFilter);
            })
            ->when($search !== '', function ($query) use ($search) {
                $isNumeric = ctype_digit($search);
                $safeTerm = str_replace(['%', '_'], ['\%', '\_'], $search);
                $likeTerm = '%' . $safeTerm . '%';
                $numericTerm = $isNumeric ? (int) $search : null;
                $isLongNumeric = $isNumeric && mb_strlen($search) > 4;

                $query->where(function ($builder) use ($isNumeric, $isLongNumeric, $likeTerm, $numericTerm) {
                    if ($isNumeric) {
                        if ($isLongNumeric) {
                            $builder->where('tb1_codbar', 'like', $likeTerm);
                        } else {
                            $builder->where('tb1_id', $numericTerm);
                        }

                        return;
                    }

                    $builder->where('tb1_nome', 'like', $likeTerm);
                });
            })
            ->where(function ($query) {
                $query
                    ->whereNull('tb30_categoria_fiscal_id')
                    ->orWhereNull('tb33_grupo_ncm_id')
                    ->orWhereNull('tb1_cfop')
                    ->orWhere('tb1_cfop', '=', '')
                    ->orWhereNull('tb1_csosn')
                    ->orWhere('tb1_csosn', '=', '')
                    ->orWhereNull('tb1_cst')
                    ->orWhere('tb1_cst', '=', '')
                    ->orWhereNull('tb1_cst_ibscbs')
                    ->orWhere('tb1_cst_ibscbs', '=', '')
                    ->orWhereNull('tb1_cclasstrib')
                    ->orWhere('tb1_cclasstrib', '=', '')
                    ->orWhereNull('tb1_aliquota_ibs_uf')
                    ->orWhereNull('tb1_aliquota_ibs_mun')
                    ->orWhereNull('tb1_aliquota_cbs')
                    ->orWhereNull('tb1_aliquota_is');
            })
            ->orderBy('tb1_id');
    }

    private function resolveFiscalQueueTypeFilter(Request $request): ?int
    {
        $type = $request->input('type');

        if ($type === null || $type === '') {
            return null;
        }

        $type = (int) $type;

        return array_key_exists($type, self::TYPE_LABELS) ? $type : null;
    }

    private function resolveFiscalQueueSearch(Request $request): string
    {
        return trim((string) $request->input('search', ''));
    }

    private function buildFiscalQueueQueryFromRequest(Request $request): array
    {
        $query = [];
        $typeFilter = $this->resolveFiscalQueueTypeFilter($request);
        $search = $this->resolveFiscalQueueSearch($request);

        if ($typeFilter !== null) {
            $query['type'] = $typeFilter;
        }

        if ($search !== '') {
            $query['search'] = $search;
        }

        return $query;
    }

    private function prepareProductData(array $data, ?Produto $product = null): array
    {
        $type = (int) ($data['tb1_tipo'] ?? $product?->tb1_tipo ?? 0);
        $status = isset($data['tb1_status']) && $data['tb1_status'] !== ''
            ? (int) $data['tb1_status']
            : (int) ($product?->tb1_status ?? 1);

        $data['tb1_nome'] = $this->normalizeProductName($data['tb1_nome'] ?? $product?->tb1_nome ?? '');
        $data['tb1_vlr_custo'] = round((float) ($data['tb1_vlr_custo'] ?? $product?->tb1_vlr_custo ?? 0), 2);
        $data['tb1_vlr_venda'] = round((float) ($data['tb1_vlr_venda'] ?? $product?->tb1_vlr_venda ?? 0), 2);
        $data['tb1_codbar'] = $this->resolveProductBarcode($data, $product);
        $data['tb1_tipo'] = $type;
        $data['tb1_status'] = $status;
        $data['tb30_categoria_fiscal_id'] = isset($data['tb30_categoria_fiscal_id']) && $data['tb30_categoria_fiscal_id'] !== ''
            ? (int) $data['tb30_categoria_fiscal_id']
            : null;
        $data['tb33_grupo_ncm_id'] = isset($data['tb33_grupo_ncm_id']) && $data['tb33_grupo_ncm_id'] !== ''
            ? (int) $data['tb33_grupo_ncm_id']
            : null;
        $data['tb1_ncm_proprio'] = $this->normalizeDigitsField($data['tb1_ncm_proprio'] ?? $product?->tb1_ncm_proprio ?? null, 8);
        $data['tb1_usa_excecao_fiscal'] = (bool) ($data['tb1_usa_excecao_fiscal'] ?? false);
        $data['tb1_responsavel_ultima_alteracao'] = auth()->id();
        $data['tb1_ncm'] = $this->normalizeDigitsField($data['tb1_ncm'] ?? $product?->tb1_ncm ?? null, 8);
        $data['tb1_cest'] = $this->normalizeDigitsField($data['tb1_cest'] ?? $product?->tb1_cest ?? null, 7);
        $data['tb1_cfop'] = $this->normalizeDigitsField($data['tb1_cfop'] ?? $product?->tb1_cfop ?? null, 4);
        $data['tb1_unidade_comercial'] = $this->normalizeShortCode($data['tb1_unidade_comercial'] ?? $product?->tb1_unidade_comercial ?? 'UN', 'UN');
        $data['tb1_unidade_tributavel'] = $this->normalizeShortCode($data['tb1_unidade_tributavel'] ?? $product?->tb1_unidade_tributavel ?? 'UN', 'UN');
        $data['tb1_origem'] = isset($data['tb1_origem']) && $data['tb1_origem'] !== ''
            ? (int) $data['tb1_origem']
            : (int) ($product?->tb1_origem ?? 0);
        $data['tb1_csosn'] = $this->normalizeDigitsField($data['tb1_csosn'] ?? $product?->tb1_csosn ?? null, 4);
        $data['tb1_cst'] = $this->normalizeDigitsField($data['tb1_cst'] ?? $product?->tb1_cst ?? null, 3);
        $data['tb1_aliquota_icms'] = round((float) ($data['tb1_aliquota_icms'] ?? $product?->tb1_aliquota_icms ?? 0), 2);
        $data['tb1_cst_ibscbs'] = $this->normalizeDigitsField($data['tb1_cst_ibscbs'] ?? $product?->tb1_cst_ibscbs ?? null, 3);
        $data['tb1_cclasstrib'] = $this->normalizeDigitsField($data['tb1_cclasstrib'] ?? $product?->tb1_cclasstrib ?? null, 6);
        $data['tb1_cff_nt'] = $this->normalizeNullableText($data['tb1_cff_nt'] ?? $product?->tb1_cff_nt ?? null, 20);
        $data['tb1_ind_doacao'] = (bool) ($data['tb1_ind_doacao'] ?? $product?->tb1_ind_doacao ?? false);
        $data['tb1_aliquota_ibs_uf'] = $this->normalizeNullableDecimal($data['tb1_aliquota_ibs_uf'] ?? $product?->tb1_aliquota_ibs_uf ?? null, 4);
        $data['tb1_aliquota_ibs_mun'] = $this->normalizeNullableDecimal($data['tb1_aliquota_ibs_mun'] ?? $product?->tb1_aliquota_ibs_mun ?? null, 4);
        $data['tb1_aliquota_cbs'] = $this->normalizeNullableDecimal($data['tb1_aliquota_cbs'] ?? $product?->tb1_aliquota_cbs ?? null, 4);
        $data['tb1_aliquota_is'] = $this->normalizeNullableDecimal($data['tb1_aliquota_is'] ?? $product?->tb1_aliquota_is ?? null, 4);

        $data['tb1_qtd'] = (int) ($data['tb1_qtd'] ?? $product?->tb1_qtd ?? 0);

        unset($data['sem_codigo_barras']);
        unset($data['excecao_fiscal']);

        if ($product) {
            unset($data['tb1_id']);
        }

        return $data;
    }

    private function syncFiscalException(Request $request, Produto $product): void
    {
        if (! $request->boolean('tb1_usa_excecao_fiscal')) {
            $product->excecoesFiscais()
                ->where('tb31_ativo', true)
                ->update(['tb31_ativo' => false]);

            return;
        }

        $exceptionData = $request->input('excecao_fiscal', []);

        if (! is_array($exceptionData) || collect($exceptionData)->filter(fn ($value) => filled($value))->isEmpty()) {
            return;
        }

        $prepared = ['tb1_id' => $product->tb1_id, 'tb31_ativo' => true];

        foreach ([
            'tb31_ncm' => 8,
            'tb31_cest' => 7,
            'tb31_cclass_trib' => 6,
            'tb31_cst_ibs' => 3,
            'tb31_cst_cbs' => 3,
            'tb31_cfop_venda_interna' => 4,
            'tb31_cfop_venda_interestadual' => 4,
            'tb31_cfop_consumo_local' => 4,
            'tb31_cfop_entrega' => 4,
            'tb31_csosn' => 4,
            'tb31_cst_icms' => 3,
            'tb31_cst_pis' => 3,
            'tb31_cst_cofins' => 3,
        ] as $field => $size) {
            $prepared[$field] = $this->normalizeDigitsField($exceptionData[$field] ?? null, $size);
        }

        foreach ([
            'tb31_aliquota_ibs_uf',
            'tb31_aliquota_ibs_municipio',
            'tb31_aliquota_cbs',
            'tb31_aliquota_is',
            'tb31_aliquota_icms',
            'tb31_aliquota_pis',
            'tb31_aliquota_cofins',
        ] as $field) {
            $prepared[$field] = $this->normalizeNullableDecimal($exceptionData[$field] ?? null, str_contains($field, 'icms') ? 2 : 4);
        }

        foreach ([
            'tb31_motivo_excecao',
            'tb31_data_inicio_vigencia',
            'tb31_data_fim_vigencia',
            'tb31_observacao_fiscal',
        ] as $field) {
            $prepared[$field] = filled($exceptionData[$field] ?? null) ? $exceptionData[$field] : null;
        }

        ProdutoExcecaoFiscal::updateOrCreate(
            ['tb1_id' => $product->tb1_id, 'tb31_ativo' => true],
            $prepared
        );
    }

    private function logProductCategoryChange(Request $request, Produto $product, mixed $previousCategoryId, mixed $newCategoryId, string $action): void
    {
        if ((string) $previousCategoryId === (string) $newCategoryId) {
            return;
        }

        CategoriaFiscalHistorico::create([
            'tb30_categoria_fiscal_id' => $newCategoryId ?: null,
            'tb1_id' => $product->tb1_id,
            'user_id' => $request->user()?->id,
            'tb32_acao' => $action,
            'tb32_campo' => 'tb30_categoria_fiscal_id',
            'tb32_valor_anterior' => $previousCategoryId === null ? null : (string) $previousCategoryId,
            'tb32_valor_novo' => $newCategoryId === null ? null : (string) $newCategoryId,
            'tb32_registros_afetados' => 1,
        ]);
    }

    private function resolveProductBarcode(array $data, ?Produto $product = null): string
    {
        if ($this->shouldUseOwnIdAsBarcode($data, $product)) {
            $productId = isset($data['tb1_id'])
                ? (int) $data['tb1_id']
                : (int) ($product?->tb1_id ?? 0);

            return $this->formatGeneratedBarcode($productId);
        }

        $barcode = trim((string) ($data['tb1_codbar'] ?? $product?->tb1_codbar ?? ''));

        if ($barcode !== '') {
            return $barcode;
        }

        $productId = isset($data['tb1_id'])
            ? (int) $data['tb1_id']
            : (int) ($product?->tb1_id ?? 0);

        return $this->formatGeneratedBarcode($productId);
    }

    private function nextSafeProductId(bool $reserveOwnIdBarcode = false): int
    {
        $maxExistingId = (int) Produto::query()
            ->where('tb1_id', '<=', self::MAX_SAFE_PRODUCT_ID)
            ->where(function ($query) {
                $query->where('tb1_id', '<', self::RESERVED_PRODUCT_ID_START)
                    ->orWhere('tb1_id', '>', self::RESERVED_PRODUCT_ID_END);
            })
            ->max('tb1_id');

        $candidateId = $maxExistingId + 1;

        while ($candidateId <= self::MAX_SAFE_PRODUCT_ID) {
            if ($candidateId >= self::RESERVED_PRODUCT_ID_START && $candidateId <= self::RESERVED_PRODUCT_ID_END) {
                $candidateId = self::RESERVED_PRODUCT_ID_END + 1;
                continue;
            }

            if (! $reserveOwnIdBarcode) {
                return $candidateId;
            }

            $generatedBarcode = $this->formatGeneratedBarcode($candidateId);

            $barcodeInUse = Produto::query()
                ->where('tb1_codbar', $generatedBarcode)
                ->exists();

            if (! $barcodeInUse) {
                return $candidateId;
            }

            $candidateId++;
        }

        throw ValidationException::withMessages([
            'tb1_nome' => 'Nao ha mais IDs seguros disponiveis para novos produtos.',
        ]);
    }

    private function isReservedProductId(int $productId): bool
    {
        return $productId >= self::RESERVED_PRODUCT_ID_START
            && $productId <= self::RESERVED_PRODUCT_ID_END;
    }

    private function ensurePriceEditingIsAuthorized(array $data, ?Produto $product, ?User $user): void
    {
        if (! $product || $this->canEditProductPrices($user)) {
            return;
        }

        $errors = [];

        if ($this->priceValueChanged($data['tb1_vlr_custo'] ?? null, $product->tb1_vlr_custo)) {
            $errors['tb1_vlr_custo'] = 'Apenas Master, Gerente e Sub-Gerente podem alterar o valor de custo.';
        }

        if ($this->priceValueChanged($data['tb1_vlr_venda'] ?? null, $product->tb1_vlr_venda)) {
            $errors['tb1_vlr_venda'] = 'Apenas Master, Gerente e Sub-Gerente podem alterar o valor de venda.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function canEditProductPrices(?User $user): bool
    {
        return $user instanceof User
            && in_array((int) $user->funcao, [0, 1, 2], true);
    }

    private function priceValueChanged(mixed $requestedValue, mixed $currentValue): bool
    {
        if ($requestedValue === null) {
            return false;
        }

        return abs((float) $requestedValue - (float) $currentValue) > 0.00001;
    }

    private function existingProductMessage(Produto $product): string
    {
        $type = self::TYPE_LABELS[(int) $product->tb1_tipo] ?? '---';
        $status = self::STATUS_LABELS[(int) $product->tb1_status] ?? '---';

        return sprintf(
            'O ID %d ja esta cadastrado. Nome: %s | Tipo: %s | Status: %s.',
            $product->tb1_id,
            $product->tb1_nome,
            $type,
            $status
        );
    }

    private function shouldUseOwnIdAsBarcode(array $data, ?Produto $product = null): bool
    {
        $type = (int) ($data['tb1_tipo'] ?? $product?->tb1_tipo ?? 0);

        if ($type === 1) {
            return true;
        }

        return (bool) ($data['sem_codigo_barras'] ?? false);
    }

    private function formatGeneratedBarcode(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        return (string) $productId;
    }

    private function normalizeProductName(mixed $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return '';
        }

        return mb_strtoupper($normalized, self::PRODUCT_NAME_ENCODING);
    }

    private function normalizeDigitsField(mixed $value, int $size): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $value);

        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $size);
    }

    private function normalizeShortCode(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);
        $normalized = $normalized === '' ? $fallback : $normalized;

        return mb_strtoupper($normalized, self::PRODUCT_NAME_ENCODING);
    }

    private function normalizeNullableDecimal(mixed $value, int $scale = 2): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) $value, $scale);
    }

    private function normalizeNullableText(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return mb_substr(mb_strtoupper($normalized, self::PRODUCT_NAME_ENCODING), 0, $maxLength);
    }
}
