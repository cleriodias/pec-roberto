<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureMaster($request->user());

        $suppliers = Supplier::orderBy('name')
            ->get(['id', 'name', 'dispute', 'access_code']);

        return Inertia::render('Settings/Suppliers', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureMaster($request->user());

        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('suppliers', 'name')],
            'dispute' => ['required', 'boolean'],
        ], [
            'name.unique' => 'Fornecedor ja cadastrado.',
        ]);

        $accessCode = $this->generateAccessCode();

        if (! $accessCode) {
            return back()->with('error', 'Nao foi possivel gerar um codigo de acesso unico.');
        }

        Supplier::create([
            'name' => $data['name'],
            'dispute' => (bool) $data['dispute'],
            'access_code' => $accessCode,
        ]);

        return redirect()
            ->route('settings.suppliers')
            ->with('success', 'Fornecedor cadastrado com sucesso!');
    }

    private function ensureMaster($user): void
    {
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao);
        if (! $user || $roleOriginal !== 0) {
            abort(403);
        }
    }

    private function generateAccessCode(): ?string
    {
        $maxCodes = 9000;
        if (Supplier::count() >= $maxCodes) {
            return null;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = (string) random_int(1000, 9999);

            if (! Supplier::where('access_code', $code)->exists()) {
                return $code;
            }
        }

        return null;
    }
}
