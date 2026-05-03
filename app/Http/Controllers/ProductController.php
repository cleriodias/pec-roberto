<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\User;
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
                $requiredFiscalFields = [
                    'tb1_ncm',
                    'tb1_cfop',
                    'tb1_csosn',
                    'tb1_cst',
                    'tb1_cst_ibscbs',
                    'tb1_cclasstrib',
                    'tb1_aliquota_ibs_uf',
                    'tb1_aliquota_ibs_mun',
                    'tb1_aliquota_cbs',
                ];

                foreach ($requiredFiscalFields as $field) {
                    if ($fiscalStatus === 'complete') {
                        $builder->whereNotNull($field)
                            ->where($field, '!=', '');
                    } else {
                        $builder->orWhereNull($field)
                            ->orWhere($field, '=', '');
                    }
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
        return Inertia::render('Products/ProductShow', [
            'product' => $product,
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
            'items' => $pendingQuery->limit(20)->get(),
            'pendingCount' => $this->pendingFiscalProductsQuery($typeFilter, $search)->count(),
            'selectedType' => $typeFilter,
            'search' => $search,
            'typeOptions' => $this->formOptions()['typeOptions'],
        ]);
    }

    public function fiscalQueueItems(Request $request): JsonResponse
    {
        $typeFilter = $this->resolveFiscalQueueTypeFilter($request);
        $search = $this->resolveFiscalQueueSearch($request);

        return response()->json([
            'items' => $this->pendingFiscalProductsQuery($typeFilter, $search)->limit(20)->get(),
            'pendingCount' => $this->pendingFiscalProductsQuery($typeFilter, $search)->count(),
            'selectedType' => $typeFilter,
            'search' => $search,
        ]);
    }

    public function updateFiscalQueueItem(Request $request, Produto $product): JsonResponse
    {
        $data = $request->validate(
            [
                'tb1_ncm' => ['required', 'string', 'size:8'],
                'tb1_cfop' => ['required', 'string', 'size:4'],
                'tb1_csosn' => ['required', 'string', 'max:4'],
                'tb1_cst' => ['required', 'string', 'max:3'],
                'tb1_cst_ibscbs' => ['required', 'string', 'size:3'],
                'tb1_cclasstrib' => ['required', 'string', 'size:6'],
                'tb1_aliquota_ibs_uf' => ['required', 'numeric', 'min:0', 'max:100'],
                'tb1_aliquota_ibs_mun' => ['required', 'numeric', 'min:0', 'max:100'],
                'tb1_aliquota_cbs' => ['required', 'numeric', 'min:0', 'max:100'],
            ],
            [
                'tb1_ncm.required' => 'Informe o NCM.',
                'tb1_ncm.size' => 'O NCM deve ter exatamente 8 digitos.',
                'tb1_cfop.required' => 'Informe o CFOP.',
                'tb1_cfop.size' => 'O CFOP deve ter exatamente 4 digitos.',
                'tb1_csosn.required' => 'Informe o CSOSN.',
                'tb1_csosn.max' => 'O CSOSN deve ter no maximo :max caracteres.',
                'tb1_cst.required' => 'Informe o CST.',
                'tb1_cst.max' => 'O CST deve ter no maximo :max caracteres.',
                'tb1_cst_ibscbs.required' => 'Informe o CST do IBS/CBS.',
                'tb1_cst_ibscbs.size' => 'O CST do IBS/CBS deve ter exatamente 3 digitos.',
                'tb1_cclasstrib.required' => 'Informe o cClassTrib.',
                'tb1_cclasstrib.size' => 'O cClassTrib deve ter exatamente 6 digitos.',
                'tb1_aliquota_ibs_uf.required' => 'Informe a aliquota IBS UF.',
                'tb1_aliquota_ibs_uf.numeric' => 'A aliquota IBS UF deve ser numerica.',
                'tb1_aliquota_ibs_uf.min' => 'A aliquota IBS UF nao pode ser negativa.',
                'tb1_aliquota_ibs_uf.max' => 'A aliquota IBS UF nao pode ultrapassar 100%.',
                'tb1_aliquota_ibs_mun.required' => 'Informe a aliquota IBS Municipio.',
                'tb1_aliquota_ibs_mun.numeric' => 'A aliquota IBS Municipio deve ser numerica.',
                'tb1_aliquota_ibs_mun.min' => 'A aliquota IBS Municipio nao pode ser negativa.',
                'tb1_aliquota_ibs_mun.max' => 'A aliquota IBS Municipio nao pode ultrapassar 100%.',
                'tb1_aliquota_cbs.required' => 'Informe a aliquota CBS.',
                'tb1_aliquota_cbs.numeric' => 'A aliquota CBS deve ser numerica.',
                'tb1_aliquota_cbs.min' => 'A aliquota CBS nao pode ser negativa.',
                'tb1_aliquota_cbs.max' => 'A aliquota CBS nao pode ultrapassar 100%.',
            ]
        );

        $product->update([
            'tb1_ncm' => $this->normalizeDigitsField($data['tb1_ncm'], 8),
            'tb1_cfop' => $this->normalizeDigitsField($data['tb1_cfop'], 4),
            'tb1_csosn' => $this->normalizeDigitsField($data['tb1_csosn'], 4),
            'tb1_cst' => $this->normalizeDigitsField($data['tb1_cst'], 3),
            'tb1_cst_ibscbs' => $this->normalizeDigitsField($data['tb1_cst_ibscbs'], 3),
            'tb1_cclasstrib' => $this->normalizeDigitsField($data['tb1_cclasstrib'], 6),
            'tb1_aliquota_ibs_uf' => $this->normalizeNullableDecimal($data['tb1_aliquota_ibs_uf'], 4),
            'tb1_aliquota_ibs_mun' => $this->normalizeNullableDecimal($data['tb1_aliquota_ibs_mun'], 4),
            'tb1_aliquota_cbs' => $this->normalizeNullableDecimal($data['tb1_aliquota_cbs'], 4),
        ]);

        return response()->json([
            'message' => 'Dados fiscais gravados com sucesso.',
            'product_id' => (int) $product->tb1_id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        if ((int) ($data['tb1_tipo'] ?? 0) !== 1) {
            $data['tb1_id'] = $this->nextSafeProductId($this->shouldUseOwnIdAsBarcode($data));
        }

        $data['tb1_vr_credit'] = (bool) ($data['tb1_vr_credit'] ?? false);
        $data = $this->prepareProductData($data);

        $product = Produto::create($data);

        return Redirect::route('products.show', ['product' => $product->tb1_id])
            ->with('success', 'Produto cadastrado com sucesso!');
    }

    public function edit(Produto $product): Response
    {
        return Inertia::render('Products/ProductEdit', array_merge(
            ['product' => $product],
            $this->formOptions()
        ));
    }

    public function update(Request $request, Produto $product)
    {
        $data = $this->validateProduct($request, $product);
        $data['tb1_vr_credit'] = (bool) ($data['tb1_vr_credit'] ?? false);
        $data = $this->prepareProductData($data, $product);

        $product->update($data);

        return Redirect::route('products.show', ['product' => $product->tb1_id])
            ->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $product)
    {
        $product->delete();

        return Redirect::route('products.index')
            ->with('success', 'Produto removido com sucesso!');
    }

    public function toggleFavorite(Request $request, Produto $product)
    {
        $data = $request->validate([
            'favorite' => 'required|boolean',
        ]);

        $product->update([
            'tb1_favorito' => $data['favorite'],
        ]);

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
                    Rule::requiredIf(fn () => (int) $request->input('tb1_tipo') === 1 && $product === null),
                    'nullable',
                    'integer',
                    'min:1',
                    'max:' . self::MAX_SAFE_PRODUCT_ID,
                ],
                'tb1_nome' => 'required|string|max:45',
                'tb1_vlr_custo' => 'required|numeric|min:0',
                'tb1_vlr_venda' => 'required|numeric|min:0|gte:tb1_vlr_custo',
                'tb1_codbar' => [
                    Rule::requiredIf(fn () => (int) $request->input('tb1_tipo') !== 1 && ! $request->boolean('sem_codigo_barras')),
                    'nullable',
                    'string',
                    'max:64',
                    Rule::unique('tb1_produto', 'tb1_codbar')->ignore($product?->tb1_id, 'tb1_id'),
                ],
                'tb1_tipo' => [
                    'required',
                    'integer',
                    Rule::in(array_keys(self::TYPE_LABELS)),
                ],
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
                    Rule::requiredIf(fn () => (int) $request->input('tb1_tipo') === 3),
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'tb1_status' => [
                    'required',
                    'integer',
                    Rule::in(array_keys(self::STATUS_LABELS)),
                ],
                'tb1_vr_credit' => [
                    'nullable',
                    'boolean',
                ],
                'sem_codigo_barras' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'tb1_id.required' => 'Informe o ID do produto de balanca.',
                'tb1_id.integer' => 'O ID do produto deve ser numerico.',
                'tb1_id.min' => 'O ID do produto deve ser maior que zero.',
                'tb1_id.max' => 'O ID do produto deve ser no maximo :max.',
                'tb1_nome.required' => 'Informe o nome do produto.',
                'tb1_nome.max' => 'O nome nao pode exceder :max caracteres.',
                'tb1_vlr_custo.required' => 'Informe o valor de custo.',
                'tb1_vlr_custo.numeric' => 'O valor de custo deve ser numerico.',
                'tb1_vlr_custo.min' => 'O valor de custo deve ser maior ou igual a zero.',
                'tb1_vlr_venda.required' => 'Informe o valor de venda.',
                'tb1_vlr_venda.numeric' => 'O valor de venda deve ser numerico.',
                'tb1_vlr_venda.min' => 'O valor de venda deve ser maior ou igual a zero.',
                'tb1_vlr_venda.gte' => 'O valor de venda deve ser maior que o valor de custo.',
                'tb1_codbar.required' => 'Informe o codigo de barras.',
                'tb1_codbar.max' => 'O codigo de barras deve ter no maximo :max caracteres.',
                'tb1_codbar.unique' => 'Este codigo de barras ja esta cadastrado.',
                'tb1_tipo.required' => 'Selecione o tipo do produto.',
                'tb1_tipo.integer' => 'Tipo de produto invalido.',
                'tb1_tipo.in' => 'Tipo de produto nao reconhecido.',
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
                'tb1_qtd.required' => 'Informe a quantidade em estoque para o produto de Producao.',
                'tb1_qtd.integer' => 'A quantidade em estoque deve ser numerica e inteira.',
                'tb1_qtd.min' => 'A quantidade em estoque nao pode ser negativa.',
                'tb1_status.required' => 'Selecione o status do produto.',
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
                'tb1_ncm',
                'tb1_cfop',
                'tb1_csosn',
                'tb1_cst',
                'tb1_cst_ibscbs',
                'tb1_cclasstrib',
                'tb1_aliquota_ibs_uf',
                'tb1_aliquota_ibs_mun',
                'tb1_aliquota_cbs',
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
                    ->whereNull('tb1_ncm')
                    ->orWhere('tb1_ncm', '=', '')
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
                    ->orWhereNull('tb1_aliquota_cbs');
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

    private function prepareProductData(array $data, ?Produto $product = null): array
    {
        $type = (int) ($data['tb1_tipo'] ?? $product?->tb1_tipo ?? 0);

        $data['tb1_nome'] = $this->normalizeProductName($data['tb1_nome'] ?? $product?->tb1_nome ?? '');
        $data['tb1_codbar'] = $this->resolveProductBarcode($data, $product);
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

        $data['tb1_qtd'] = $type === 3
            ? (int) ($data['tb1_qtd'] ?? $product?->tb1_qtd ?? 0)
            : 0;

        unset($data['sem_codigo_barras']);

        if ($product) {
            unset($data['tb1_id']);
        }

        return $data;
    }

    private function resolveProductBarcode(array $data, ?Produto $product = null): string
    {
        if ($this->shouldUseOwnIdAsBarcode($data, $product)) {
            $productId = isset($data['tb1_id'])
                ? (int) $data['tb1_id']
                : (int) ($product?->tb1_id ?? 0);

            return $this->formatGeneratedBarcode($productId);
        }

        return trim((string) ($data['tb1_codbar'] ?? $product?->tb1_codbar ?? ''));
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
