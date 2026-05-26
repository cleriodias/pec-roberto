<?php

namespace App\Http\Controllers;

use App\Models\GrupoNcm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GrupoNcmController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Products/GrupoNcm', [
            'groups' => GrupoNcm::query()
                ->withCount('produtos')
                ->orderByDesc('tb33_ativo')
                ->orderBy('tb33_nome')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GrupoNcm::create($this->prepareData($this->validateGroup($request)));

        return redirect()
            ->route('products.grupos-ncm.index')
            ->with('success', 'Grupo NCM cadastrado com sucesso.');
    }

    public function update(Request $request, GrupoNcm $grupoNcm): RedirectResponse
    {
        $grupoNcm->update($this->prepareData($this->validateGroup($request, $grupoNcm)));

        return redirect()
            ->route('products.grupos-ncm.index')
            ->with('success', 'Grupo NCM atualizado com sucesso.');
    }

    public function destroy(GrupoNcm $grupoNcm): RedirectResponse
    {
        if ($grupoNcm->produtos()->exists()) {
            return redirect()
                ->route('products.grupos-ncm.index')
                ->with('error', 'Nao e possivel excluir grupo NCM com produto vinculado. Inative o grupo se necessario.');
        }

        $grupoNcm->delete();

        return redirect()
            ->route('products.grupos-ncm.index')
            ->with('success', 'Grupo NCM removido com sucesso.');
    }

    private function validateGroup(Request $request, ?GrupoNcm $group = null): array
    {
        return $request->validate([
            'tb33_codigo' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('tb33_grupos_ncm', 'tb33_codigo')->ignore($group?->tb33_id, 'tb33_id'),
            ],
            'tb33_nome' => ['required', 'string', 'max:120'],
            'tb33_descricao' => ['nullable', 'string'],
            'tb33_ncm' => ['nullable', 'string', 'size:8'],
            'tb33_cest' => ['nullable', 'string', 'size:7'],
            'tb33_cclass_trib' => ['nullable', 'string', 'size:6'],
            'tb33_ativo' => ['required', 'boolean'],
            'tb33_observacao_fiscal' => ['nullable', 'string'],
        ], [
            'tb33_nome.required' => 'Informe o nome do grupo NCM.',
            'tb33_ncm.size' => 'O NCM deve ter exatamente 8 digitos.',
            'tb33_cest.size' => 'O CEST deve ter exatamente 7 digitos.',
            'tb33_cclass_trib.size' => 'O cClassTrib deve ter exatamente 6 digitos.',
        ]);

        if ((bool) $data['tb33_ativo'] && blank($data['tb33_ncm'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tb33_ncm' => 'Informe o NCM antes de ativar o grupo.',
            ]);
        }

        return $data;
    }

    private function prepareData(array $data): array
    {
        foreach (['tb33_codigo', 'tb33_nome'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = mb_strtoupper(trim((string) $data[$field]), 'UTF-8');
            }
        }

        foreach ([
            'tb33_ncm' => 8,
            'tb33_cest' => 7,
            'tb33_cclass_trib' => 6,
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
}
