<?php

namespace App\Http\Controllers;

use App\Support\DiscardAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscardSettingsController extends Controller
{
    public function index(Request $request, DiscardAlertService $discardAlertService): Response
    {
        $this->ensureAdmin($request->user());

        $configuration = $discardAlertService->configuration();

        return Inertia::render('Settings/DiscardConfig', [
            'setting' => [
                'percentual_aceitavel' => round((float) $configuration->percentual_aceitavel, 2),
            ],
            'monitoring' => $discardAlertService->forRequest($request),
        ]);
    }

    public function update(Request $request, DiscardAlertService $discardAlertService): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $data = $request->validate([
            'percentual_aceitavel' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $configuration = $discardAlertService->configuration();
        $configuration->update([
            'percentual_aceitavel' => round((float) $data['percentual_aceitavel'], 2),
        ]);

        return back()->with('success', 'Configuracao do discarte atualizada com sucesso.');
    }

    private function ensureAdmin($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }
}
