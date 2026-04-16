<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\User;
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

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $vrCreditOnly = in_array(strtolower((string) $request->input('vr_credit', '0')), ['1', 'true'], true);
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

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        if ((int) ($data['tb1_tipo'] ?? 0) !== 1) {
            $data['tb1_id'] = $this->nextSafeProductId();
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

        $safeTerm = str_replace(['%', '_'], ['\\%', '\\_'], $term);
        $likeTerm = '%' . $safeTerm . '%';
        $numericTerm = $isNumeric ? (int) $term : null;
        $isLongNumeric = $isNumeric && mb_strlen($term) > 4;

        $productsQuery = Produto::query()
            ->where(function ($query) use ($isNumeric, $isLongNumeric, $likeTerm, $numericTerm) {
                if ($isNumeric) {
                    if ($isLongNumeric) {
                        $query->where('tb1_codbar', 'like', $likeTerm);
                    } else {
                        $query->where('tb1_id', $numericTerm);
                    }

                    return;
                }

                $query->where('tb1_nome', 'like', $likeTerm);
            });

        if ($typeFilter !== null && $typeFilter !== '') {
            $typeValue = (int) $typeFilter;
            $productsQuery->where('tb1_tipo', $typeValue);
        }

        $products = $productsQuery
            ->orderByDesc('tb1_status')
            ->orderBy('tb1_nome')
            ->limit(10)
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

        return response()->json($products);
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
                    Rule::requiredIf(fn () => (int) $request->input('tb1_tipo') !== 1),
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
                'tb1_qtd.required' => 'Informe a quantidade em estoque para o produto de Producao.',
                'tb1_qtd.integer' => 'A quantidade em estoque deve ser numerica e inteira.',
                'tb1_qtd.min' => 'A quantidade em estoque nao pode ser negativa.',
                'tb1_status.required' => 'Selecione o status do produto.',
                'tb1_status.integer' => 'Status invalido.',
                'tb1_status.in' => 'Status nao reconhecido.',
                'tb1_vr_credit.boolean' => 'Valor invalido para VR Credito.',
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

        if ((int) ($data['tb1_tipo'] ?? $product?->tb1_tipo ?? 0) === 1) {
            $balanceBarcode = $this->resolveBalanceBarcode($data, $product);

            $barcodeInUse = Produto::query()
                ->where('tb1_codbar', $balanceBarcode)
                ->when(
                    $product,
                    fn ($query) => $query->where('tb1_id', '!=', $product->tb1_id)
                )
                ->first();

            if ($barcodeInUse) {
                throw ValidationException::withMessages([
                    'tb1_id' => sprintf(
                        'O codigo interno %s ja esta em uso no produto %d.',
                        $balanceBarcode,
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
        ];
    }

    private function prepareProductData(array $data, ?Produto $product = null): array
    {
        $type = (int) ($data['tb1_tipo'] ?? $product?->tb1_tipo ?? 0);

        if ($type === 1) {
            $data['tb1_codbar'] = $this->resolveBalanceBarcode($data, $product);
        } else {
            $data['tb1_codbar'] = trim((string) ($data['tb1_codbar'] ?? ''));
        }

        $data['tb1_qtd'] = $type === 3
            ? (int) ($data['tb1_qtd'] ?? $product?->tb1_qtd ?? 0)
            : 0;

        if ($product) {
            unset($data['tb1_id']);
        }

        return $data;
    }

    private function resolveBalanceBarcode(array $data, ?Produto $product = null): string
    {
        $currentBarcode = trim((string) ($product?->tb1_codbar ?? ''));

        if ($currentBarcode !== '') {
            return $currentBarcode;
        }

        $balanceId = isset($data['tb1_id'])
            ? (int) $data['tb1_id']
            : (int) ($product?->tb1_id ?? 0);

        if ($balanceId > 0) {
            return 'SEM-' . $balanceId;
        }

        return 'SEM-PRODUTO-BALANCA';
    }

    private function nextSafeProductId(): int
    {
        $maxExistingId = (int) Produto::query()
            ->where('tb1_id', '<=', self::MAX_SAFE_PRODUCT_ID)
            ->where(function ($query) {
                $query->where('tb1_id', '<', self::RESERVED_PRODUCT_ID_START)
                    ->orWhere('tb1_id', '>', self::RESERVED_PRODUCT_ID_END);
            })
            ->max('tb1_id');

        $nextId = $maxExistingId + 1;

        if ($nextId >= self::RESERVED_PRODUCT_ID_START && $nextId <= self::RESERVED_PRODUCT_ID_END) {
            $nextId = self::RESERVED_PRODUCT_ID_END + 1;
        }

        if ($nextId > self::MAX_SAFE_PRODUCT_ID) {
            throw ValidationException::withMessages([
                'tb1_nome' => 'Nao ha mais IDs seguros disponiveis para novos produtos.',
            ]);
        }

        return $nextId;
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
}
