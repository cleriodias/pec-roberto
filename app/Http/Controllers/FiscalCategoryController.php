<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFiscal;
use App\Models\CategoriaFiscalHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FiscalCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedCategoryId = (int) $request->query('categoria', 0);
        $selectedCategory = $selectedCategoryId > 0
            ? CategoriaFiscal::query()->find($selectedCategoryId)
            : null;

        return Inertia::render('Products/FiscalCategories', [
            'categories' => CategoriaFiscal::query()
                ->withCount('produtos')
                ->orderByDesc('tb30_ativo')
                ->orderBy('tb30_nome')
                ->get(),
            'linkedProducts' => $selectedCategory
                ? $selectedCategory
                    ->produtos()
                    ->orderBy('tb1_nome')
                    ->limit(100)
                    ->get(['tb1_id', 'tb1_nome', 'tb1_codbar', 'tb1_status'])
                : collect(),
            'selectedCategoryId' => $selectedCategoryId > 0 ? $selectedCategoryId : null,
            'originOptions' => $this->originOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        $category = CategoriaFiscal::create($this->prepareCategoryData($data));

        $this->logChange($request, $category, 'criacao', null, null, $category->tb30_nome);

        return redirect()
            ->route('products.fiscal-categories.index', ['categoria' => $category->tb30_id])
            ->with('success', 'Categoria fiscal cadastrada com sucesso.');
    }

    public function update(Request $request, CategoriaFiscal $categoriaFiscal): RedirectResponse
    {
        $data = $this->validateCategory($request, $categoriaFiscal);
        $prepared = $this->prepareCategoryData($data);
        $original = $categoriaFiscal->getOriginal();

        $categoriaFiscal->update($prepared);

        foreach ($prepared as $field => $value) {
            $oldValue = $original[$field] ?? null;

            if ((string) $oldValue !== (string) $value) {
                $this->logChange($request, $categoriaFiscal, 'alteracao_categoria', $field, $oldValue, $value);
            }
        }

        return redirect()
            ->route('products.fiscal-categories.index', ['categoria' => $categoriaFiscal->tb30_id])
            ->with('success', 'Categoria fiscal atualizada com sucesso.');
    }

    public function destroy(CategoriaFiscal $categoriaFiscal): RedirectResponse
    {
        if ($categoriaFiscal->produtos()->exists()) {
            return redirect()
                ->route('products.fiscal-categories.index', ['categoria' => $categoriaFiscal->tb30_id])
                ->with('error', 'Nao e possivel excluir categoria fiscal com produto vinculado. Inative a categoria se necessario.');
        }

        $categoriaFiscal->delete();

        return redirect()
            ->route('products.fiscal-categories.index')
            ->with('success', 'Categoria fiscal removida com sucesso.');
    }

    private function validateCategory(Request $request, ?CategoriaFiscal $category = null): array
    {
        $data = $request->validate([
            'tb30_codigo' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('tb30_categorias_fiscais', 'tb30_codigo')->ignore($category?->tb30_id, 'tb30_id'),
            ],
            'tb30_nome' => ['required', 'string', 'max:120'],
            'tb30_descricao' => ['nullable', 'string'],
            'tb30_origem_mercadoria' => ['required', Rule::in(CategoriaFiscal::ORIGENS)],
            'tb30_ativo' => ['required', 'boolean'],
            'tb30_data_inicio_vigencia' => ['nullable', 'date'],
            'tb30_data_fim_vigencia' => ['nullable', 'date', 'after_or_equal:tb30_data_inicio_vigencia'],
            'tb30_ncm_padrao' => ['nullable', 'string', 'size:8'],
            'tb30_cest' => ['nullable', 'string', 'size:7'],
            'tb30_cclass_trib' => ['nullable', 'string', 'size:6'],
            'tb30_cst_ibs' => ['nullable', 'string', 'size:3'],
            'tb30_cst_cbs' => ['nullable', 'string', 'size:3'],
            'tb30_aliquota_ibs_uf' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_aliquota_ibs_municipio' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_aliquota_cbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_aliquota_is' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_cfop_venda_interna' => ['nullable', 'string', 'size:4'],
            'tb30_cfop_venda_interestadual' => ['nullable', 'string', 'size:4'],
            'tb30_cfop_consumo_local' => ['nullable', 'string', 'size:4'],
            'tb30_cfop_entrega' => ['nullable', 'string', 'size:4'],
            'tb30_csosn' => ['nullable', 'string', 'max:4'],
            'tb30_cst_icms' => ['nullable', 'string', 'max:3'],
            'tb30_cst_pis' => ['nullable', 'string', 'max:3'],
            'tb30_cst_cofins' => ['nullable', 'string', 'max:3'],
            'tb30_aliquota_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_aliquota_pis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_aliquota_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tb30_regra_icms' => ['nullable', 'string', 'max:120'],
            'tb30_natureza_receita' => ['nullable', 'string', 'max:60'],
            'tb30_aplica_balcao' => ['required', 'boolean'],
            'tb30_aplica_delivery' => ['required', 'boolean'],
            'tb30_aplica_consumo_local' => ['required', 'boolean'],
            'tb30_permite_excecao_produto' => ['required', 'boolean'],
            'tb30_observacao_fiscal' => ['nullable', 'string'],
        ]);

        if ((bool) $data['tb30_ativo']) {
            $errors = [];

            foreach ([
                'tb30_codigo' => 'Codigo',
                'tb30_data_inicio_vigencia' => 'Inicio da vigencia',
                'tb30_cfop_venda_interna' => 'CFOP venda interna',
                'tb30_cst_pis' => 'CST PIS',
                'tb30_cst_cofins' => 'CST COFINS',
            ] as $field => $label) {
                if (blank($data[$field] ?? null)) {
                    $errors[$field] = sprintf('%s e obrigatorio para categoria ativa.', $label);
                }
            }

            if (($data['tb30_aplica_consumo_local'] ?? false) && blank($data['tb30_cfop_consumo_local'] ?? null)) {
                $errors['tb30_cfop_consumo_local'] = 'CFOP consumo local e obrigatorio quando a categoria aplica consumo local.';
            }

            if (($data['tb30_aplica_delivery'] ?? false) && blank($data['tb30_cfop_entrega'] ?? null)) {
                $errors['tb30_cfop_entrega'] = 'CFOP entrega/delivery e obrigatorio quando a categoria aplica delivery.';
            }

            if (blank($data['tb30_csosn'] ?? null) && blank($data['tb30_cst_icms'] ?? null)) {
                $errors['tb30_csosn'] = 'Informe CSOSN ou CST ICMS para categoria ativa.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return $data;
    }

    private function prepareCategoryData(array $data): array
    {
        foreach ([
            'tb30_codigo',
            'tb30_nome',
            'tb30_origem_mercadoria',
            'tb30_regra_icms',
            'tb30_natureza_receita',
        ] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = mb_strtoupper(trim((string) $data[$field]), 'UTF-8');
            }
        }

        foreach ([
            'tb30_ncm_padrao' => 8,
            'tb30_cest' => 7,
            'tb30_cclass_trib' => 6,
            'tb30_cst_ibs' => 3,
            'tb30_cst_cbs' => 3,
            'tb30_cfop_venda_interna' => 4,
            'tb30_cfop_venda_interestadual' => 4,
            'tb30_cfop_consumo_local' => 4,
            'tb30_cfop_entrega' => 4,
            'tb30_csosn' => 4,
            'tb30_cst_icms' => 3,
            'tb30_cst_pis' => 3,
            'tb30_cst_cofins' => 3,
        ] as $field => $size) {
            $data[$field] = $this->normalizeDigits($data[$field] ?? null, $size);
        }

        return $data;
    }

    private function normalizeDigits(mixed $value, int $size): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : mb_substr($digits, 0, $size);
    }

    private function originOptions(): array
    {
        return collect(CategoriaFiscal::ORIGENS)
            ->map(fn (string $origin) => ['value' => $origin, 'label' => str_replace('_', ' ', $origin)])
            ->values()
            ->all();
    }

    private function logChange(Request $request, CategoriaFiscal $category, string $action, ?string $field, mixed $oldValue, mixed $newValue): void
    {
        CategoriaFiscalHistorico::create([
            'tb30_categoria_fiscal_id' => $category->tb30_id,
            'user_id' => $request->user()?->id,
            'tb32_acao' => $action,
            'tb32_campo' => $field,
            'tb32_valor_anterior' => $oldValue === null ? null : (string) $oldValue,
            'tb32_valor_novo' => $newValue === null ? null : (string) $newValue,
            'tb32_registros_afetados' => 1,
        ]);
    }
}
