<?php

namespace App\Http\Controllers;

use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    private function ensureAuthorized(): void
    {
        $user = request()->user();

        if (!$user || !in_array((int) $user->funcao, [0, 1])) {
            abort(403, 'Acesso negado.');
        }
    }

    public function index(): Response
    {
        $this->ensureAuthorized();

        $units = Unidade::orderByDesc('tb2_id')->paginate(10);

        return Inertia::render('Units/UnitIndex', [
            'units' => $units,
        ]);
    }

    public function show(Unidade $unit): Response
    {
        $this->ensureAuthorized();

        return Inertia::render('Units/UnitShow', [
            'unit' => $unit,
        ]);
    }

    public function create(): Response
    {
        $this->ensureAuthorized();

        return Inertia::render('Units/UnitCreate');
    }

    public function store(Request $request)
    {
        $this->ensureAuthorized();

        $data = $this->validateUnit($request);

        $unit = Unidade::create([
            'tb2_nome' => $data['tb2_nome'],
            'tb2_endereco' => $data['tb2_endereco'],
            'tb2_cep' => $data['tb2_cep'],
            'tb2_fone' => $data['tb2_fone'],
            'tb2_cnpj' => $data['tb2_cnpj'],
            'tb2_localizacao' => $data['tb2_localizacao'],
        ]);

        return Redirect::route('units.show', ['unit' => $unit->tb2_id])
            ->with('success', 'Unidade cadastrada com sucesso!');
    }

    public function edit(Unidade $unit): Response
    {
        $this->ensureAuthorized();

        return Inertia::render('Units/UnitEdit', [
            'unit' => $unit,
        ]);
    }

    public function update(Request $request, Unidade $unit)
    {
        $this->ensureAuthorized();

        $data = $this->validateUnit($request);

        $unit->update([
            'tb2_nome' => $data['tb2_nome'],
            'tb2_endereco' => $data['tb2_endereco'],
            'tb2_cep' => $data['tb2_cep'],
            'tb2_fone' => $data['tb2_fone'],
            'tb2_cnpj' => $data['tb2_cnpj'],
            'tb2_localizacao' => $data['tb2_localizacao'],
        ]);

        return Redirect::route('units.show', ['unit' => $unit->tb2_id])
            ->with('success', 'Unidade atualizada com sucesso!');
    }

    public function destroy(Unidade $unit)
    {
        $this->ensureAuthorized();

        $unit->delete();

        return Redirect::route('units.index')
            ->with('success', 'Unidade removida com sucesso!');
    }

    private function validateUnit(Request $request): array
    {
        $request->merge([
            'tb2_localizacao' => $this->normalizeMapUrl($request->input('tb2_localizacao')),
        ]);

        return $request->validate(
            [
                'tb2_nome' => 'required|string|max:255',
                'tb2_endereco' => 'required|string|max:255',
                'tb2_cep' => 'required|string|max:20',
                'tb2_fone' => 'required|string|max:20',
                'tb2_cnpj' => 'required|string|max:20',
                'tb2_localizacao' => 'required|url|max:512',
            ],
            [
                'tb2_nome.required' => 'Informe o nome da unidade.',
                'tb2_endereco.required' => 'Informe o endere\u00E7o.',
                'tb2_cep.required' => 'Informe o CEP.',
                'tb2_fone.required' => 'Informe o telefone.',
                'tb2_cnpj.required' => 'Informe o CNPJ.',
                'tb2_localizacao.required' => 'Informe o link do Google Maps.',
                'tb2_localizacao.url' => 'O link de localiza\u00E7\u00E3o deve ser uma URL v\u00E1lida.',
            ]
        );
    }

    private function normalizeMapUrl(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $value;
        }

        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}
