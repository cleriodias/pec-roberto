<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\SalaryAdvance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user) {
                abort(403);
            }

            $routeName = $request->route()?->getName();
            $funcao = (int) $user->funcao;
            $funcaoOriginal = $user->funcao_original;
            $isMasterOriginal = (int) ($funcaoOriginal ?? $user->funcao) === 0;

            if ($funcao === 3 && $routeName === 'users.search') {
                return $next($request);
            }

            if ($isMasterOriginal || in_array($funcao, [0, 1], true)) {
                return $next($request);
            }

            abort(403);
        });
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['unit', 'funcao']);

        $users = User::with('units:tb2_id,tb2_nome')
            ->when($request->filled('funcao'), function ($query) use ($request) {
                $funcao = (int) $request->input('funcao');
                $query->where('funcao', $funcao);
            })
            ->when($request->filled('unit'), function ($query) use ($request) {
                $unitId = (int) $request->input('unit');
                $query->where(function ($sub) use ($unitId) {
                    $sub->where('tb2_id', $unitId)
                        ->orWhereHas('units', function ($q) use ($unitId) {
                            $q->where('tb2_unidades.tb2_id', $unitId);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $units = Unidade::orderBy('tb2_nome')->get(['tb2_id', 'tb2_nome']);

        return Inertia::render('Users/UserIndex', [
            'users' => $users,
            'units' => $units,
            'filters' => $filters,
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('units:tb2_id,tb2_nome');

        $valeSales = Venda::query()
            ->select(['tb3_id', 'tb4_id', 'valor_total', 'data_hora', 'tipo_pago', 'id_unidade'])
            ->with('unidade:tb2_id,tb2_nome')
            ->where('id_user_vale', $user->id)
            ->where('tipo_pago', 'vale')
            ->orderByDesc('data_hora')
            ->get();

        $vrSales = Venda::query()
            ->select(['tb3_id', 'tb4_id', 'valor_total', 'data_hora', 'tipo_pago', 'id_unidade'])
            ->with('unidade:tb2_id,tb2_nome')
            ->where('id_user_vale', $user->id)
            ->where('tipo_pago', 'refeicao')
            ->orderByDesc('data_hora')
            ->get();

        $advances = SalaryAdvance::query()
            ->where('user_id', $user->id)
            ->orderByDesc('advance_date')
            ->get(['id', 'advance_date', 'amount', 'reason']);

        $valeUsage = $this->groupSalesByPeriod($valeSales);
        $vrUsage = $this->groupSalesByPeriod($vrSales);
        $advanceUsage = $this->groupAdvancesByPeriod($advances);

        $valeTotal = (float) $valeSales->sum('valor_total');
        $vrTotal = (float) $vrSales->sum('valor_total');
        $advancesTotal = (float) $advances->sum('amount');

        $financialSummary = [
            'valeTotal' => round($valeTotal, 2),
            'vrCreditTotal' => round($vrTotal, 2),
            'advanceTotal' => round($advancesTotal, 2),
            'balance' => round((float) $user->salario - $advancesTotal, 2),
        ];

        return Inertia::render('Users/UserShow', [
            'user' => $user,
            'valeUsage' => $valeUsage,
            'vrUsage' => $vrUsage,
            'advanceUsage' => $advanceUsage,
            'financialSummary' => $financialSummary,
        ]);
    }

    public function create(): Response
    {
        $units = Unidade::orderBy('tb2_nome')->get(['tb2_id', 'tb2_nome']);

        return Inertia::render('Users/UserCreate', [
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|max:255|confirmed',
                'funcao' => 'required|integer|between:0,6',
                'hr_ini' => 'required|date_format:H:i',
                'hr_fim' => 'required|date_format:H:i|after:hr_ini',
                'salario' => 'required|numeric|min:0',
                'vr_cred' => 'required|numeric|min:0',
                'tb2_id' => 'required|array|min:1',
                'tb2_id.*' => 'integer|exists:tb2_unidades,tb2_id',
            ],
            [
                'name.required' => 'O campo nome é obrigatório!',
                'name.string' => 'O nome deve ser uma string válida.',
                'name.max' => 'O nome não pode ter mais que :max caracteres.',
                'email.required' => 'O campo e-mail é obrigatório.',
                'email.string' => 'O e-mail deve ser uma string válida.',
                'email.email' => 'O e-mail deve ser um endereço válido.',
                'email.max' => 'O e-mail não pode ter mais que :max caracteres.',
                'email.unique' => 'Este e-mail já está cadastrado.',
                'password.required' => 'O campo senha é obrigatório.',
                'password.string' => 'A senha deve ser uma string válida.',
                'password.min' => 'A senha não pode ter menos que :min caracteres.',
                'password.max' => 'A senha não pode ter mais que :max caracteres.',
                'password.confirmed' => 'A confirmação da senha não corresponde.',
                'funcao.required' => 'Selecione a função do usuário.',
                'funcao.integer' => 'Função inválida.',
                'funcao.between' => 'Função não reconhecida.',
                'hr_ini.required' => 'Informe o início da jornada.',
                'hr_ini.date_format' => 'Horário inicial inválido (HH:MM).',
                'hr_fim.required' => 'Informe o fim da jornada.',
                'hr_fim.date_format' => 'Horário final inválido (HH:MM).',
                'hr_fim.after' => 'O fim da jornada deve ser após o início.',
                'salario.required' => 'Informe o salário.',
                'salario.numeric' => 'O salário deve ser numérico.',
                'salario.min' => 'O salário deve ser maior ou igual a zero.',
                'vr_cred.required' => 'Informe o crédito de refeição.',
                'vr_cred.numeric' => 'O crédito deve ser numérico.',
                'vr_cred.min' => 'O crédito deve ser maior ou igual a zero.',
                'tb2_id.required' => 'Selecione pelo menos uma unidade.',
                'tb2_id.array' => 'Selecao de unidades invalida.',
                'tb2_id.min' => 'Selecione pelo menos uma unidade.',
                'tb2_id.*.integer' => 'Unidade selecionada invalida.',
                'tb2_id.*.exists' => 'Unidade selecionada nao existe.',
            ]
        );

        $unitIds = collect($request->input('tb2_id', []))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $primaryUnit = $unitIds[0] ?? 1;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'funcao' => $request->funcao,
            'hr_ini' => $request->hr_ini,
            'hr_fim' => $request->hr_fim,
            'salario' => $request->salario,
            'vr_cred' => $request->vr_cred,
            'tb2_id' => $primaryUnit,
        ]);

        $user->units()->sync($unitIds);

        return Redirect::route('users.show', ['user' => $user->id])->with('success', 'Usuário cadastrado com sucesso!');
    }

    public function edit(User $user): Response
    {
        $units = Unidade::orderBy('tb2_nome')->get(['tb2_id', 'tb2_nome']);
        $user->load('units:tb2_id,tb2_nome');

        return Inertia::render('Users/UserEdit', [
            'user' => $user,
            'units' => $units,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate(
            [
                'name' => 'required|string|max:255',
                'email' => "required|string|email|max:255|unique:users,email,{$user->id}",
                'funcao' => 'required|integer|between:0,6',
                'hr_ini' => 'required|date_format:H:i',
                'hr_fim' => 'required|date_format:H:i|after:hr_ini',
                'salario' => 'required|numeric|min:0',
                'vr_cred' => 'required|numeric|min:0',
                'tb2_id' => 'required|array|min:1',
                'tb2_id.*' => 'integer|exists:tb2_unidades,tb2_id',
            ],
            [
                'name.required' => 'O campo nome é obrigatório!',
                'name.string' => 'O nome deve ser uma string válida.',
                'name.max' => 'O nome não pode ter mais que :max caracteres.',
                'email.required' => 'O campo e-mail é obrigatório.',
                'email.string' => 'O e-mail deve ser uma string válida.',
                'email.email' => 'O e-mail deve ser um endereço válido.',
                'email.max' => 'O e-mail não pode ter mais que :max caracteres.',
                'email.unique' => 'Este e-mail já está cadastrado.',
                'funcao.required' => 'Selecione a função do usuário.',
                'funcao.integer' => 'Função inválida.',
                'funcao.between' => 'Função não reconhecida.',
                'hr_ini.required' => 'Informe o início da jornada.',
                'hr_ini.date_format' => 'Horário inicial inválido (HH:MM).',
                'hr_fim.required' => 'Informe o fim da jornada.',
                'hr_fim.date_format' => 'Horário final inválido (HH:MM).',
                'hr_fim.after' => 'O fim da jornada deve ser após o início.',
                'salario.required' => 'Informe o salário.',
                'salario.numeric' => 'O salário deve ser numérico.',
                'salario.min' => 'O salário deve ser maior ou igual a zero.',
                'vr_cred.required' => 'Informe o crédito de refeição.',
                'vr_cred.numeric' => 'O crédito deve ser numérico.',
                'vr_cred.min' => 'O crédito deve ser maior ou igual a zero.',
                'tb2_id.required' => 'Selecione pelo menos uma unidade.',
                'tb2_id.array' => 'Selecao de unidades invalida.',
                'tb2_id.min' => 'Selecione pelo menos uma unidade.',
                'tb2_id.*.integer' => 'Unidade selecionada invalida.',
                'tb2_id.*.exists' => 'Unidade selecionada nao existe.',
            ]
        );

        $unitIds = collect($request->input('tb2_id', []))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $primaryUnit = $unitIds[0] ?? 1;

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'funcao' => $request->funcao,
            'hr_ini' => $request->hr_ini,
            'hr_fim' => $request->hr_fim,
            'salario' => $request->salario,
            'vr_cred' => $request->vr_cred,
            'tb2_id' => $primaryUnit,
        ]);

        $user->units()->sync($unitIds);

        return Redirect::route('users.show', ['user' => $user->id])->with('success', 'Usuário editado com sucesso!');
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $safeTerm = str_replace(['%', '_'], ['\\%', '\\_'], $term);
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $users = User::query()
            ->where('name', 'like', '%' . $safeTerm . '%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'vr_cred']);

        if ($users->isEmpty()) {
            return response()->json([]);
        }

        $usagePerUser = Venda::query()
            ->selectRaw('id_user_vale, SUM(valor_total) as total')
            ->where('tipo_pago', 'refeicao')
            ->whereBetween('data_hora', [$monthStart, $monthEnd])
            ->whereIn('id_user_vale', $users->pluck('id'))
            ->groupBy('id_user_vale')
            ->pluck('total', 'id_user_vale');

        $response = $users->map(function ($user) use ($usagePerUser) {
            $used = (float) ($usagePerUser[$user->id] ?? 0);
            $balance = max(0, (float) $user->vr_cred - $used);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'vr_cred' => (float) $user->vr_cred,
                'refeicao_used' => $used,
                'refeicao_balance' => round($balance, 2),
            ];
        });

        return response()->json($response);
    }

    public function destroy(User $user)
    {
        if ($this->userHasTransactions($user)) {
            return Redirect::back()->with('error', 'Não é possível excluir usuários com lançamentos associados.');
        }

        $user->delete();

        return Redirect::route('users.index')->with('success', 'Usuário apagado com sucesso!');
    }

    public function resetPassword(Request $request, User $user)
    {
        $newPassword = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        return Redirect::back()->with('success', "Nova senha temporária: {$newPassword}");
    }

    private function groupSalesByPeriod(Collection $sales): array
    {
        return $sales
            ->groupBy(fn ($sale) => optional($sale->data_hora)->format('Y-m') ?? 'sem-data')
            ->map(function ($group, $period) {
                $referenceDate = optional($group->first()->data_hora) ?: Carbon::now();

                return [
                    'period' => $period,
                    'label' => Str::title($referenceDate->translatedFormat('F Y')),
                    'total' => round($group->sum('valor_total'), 2),
                    'count' => $group->count(),
                    'items' => $group->map(function ($sale) {
                        return [
                            'id' => $sale->tb3_id,
                            'cupom' => $sale->tb4_id,
                            'date_time' => optional($sale->data_hora)->toDateTimeString(),
                            'total' => (float) $sale->valor_total,
                            'type' => $sale->tipo_pago,
                            'unit' => optional($sale->unidade)->tb2_nome,
                        ];
                    })->values()->all(),
                ];
            })
            ->sortByDesc('period')
            ->values()
            ->all();
    }

    private function groupAdvancesByPeriod(Collection $advances): array
    {
        return $advances
            ->groupBy(fn ($advance) => optional($advance->advance_date)->format('Y-m') ?? 'sem-data')
            ->map(function ($group, $period) {
                $referenceDate = optional($group->first()->advance_date) ?: Carbon::now();

                return [
                    'period' => $period,
                    'label' => Str::title($referenceDate->translatedFormat('F Y')),
                    'total' => round($group->sum('amount'), 2),
                    'count' => $group->count(),
                    'items' => $group->map(function ($advance) {
                        return [
                            'id' => $advance->id,
                            'date' => optional($advance->advance_date)->toDateString(),
                            'amount' => (float) $advance->amount,
                            'reason' => $advance->reason,
                        ];
                    })->values()->all(),
                ];
            })
            ->sortByDesc('period')
            ->values()
            ->all();
    }

    private function userHasTransactions(User $user): bool
    {
        $hasSales = Venda::query()
            ->where('id_user_caixa', $user->id)
            ->orWhere('id_user_vale', $user->id)
            ->exists();

        $hasAdvances = SalaryAdvance::where('user_id', $user->id)->exists();

        return $hasSales || $hasAdvances;
    }
}
