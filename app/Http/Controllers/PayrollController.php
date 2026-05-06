<?php

namespace App\Http\Controllers;

use App\Models\ContraChequeCredito;
use App\Models\ContraChequePagamento;
use App\Models\SalaryAdvance;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaPagamento;
use App\Support\ManagementScope;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    private const ROLE_LABELS = [
        0 => 'Master',
        1 => 'Gerente',
        2 => 'Sub-Gerente',
        3 => 'Caixa',
        4 => 'Lanchonete',
        5 => 'Funcionario',
        6 => 'Cliente',
    ];

    private const EXTRA_CREDIT_TYPE_LABELS = [
        'primeiro_domingo' => 'Primeiro Domingo',
        'feriado' => 'Feriado',
        'bonificacao' => 'Bonificacao',
        'inss' => 'INSS',
        'outros' => 'Outros',
    ];

    private const EXTRA_DEDUCTION_TYPES = [
        'inss',
    ];

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request->user());

        return Inertia::render('Settings/FolhaPagamento', $this->buildPayrollPayload($request));
    }

    public function contraCheque(Request $request): Response
    {
        $this->ensureAdmin($request->user());

        return Inertia::render('Settings/ContraCheque', $this->buildContraChequePayload($request));
    }

    public function storeContraChequeCredit(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'credit_type' => ['required', 'string', Rule::in(array_keys(self::EXTRA_CREDIT_TYPE_LABELS))],
            'other_description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
            'credit_type.required' => 'Selecione o tipo do credito.',
            'credit_type.in' => 'O tipo do credito selecionado e invalido.',
            'amount.required' => 'Informe o valor do credito.',
            'amount.numeric' => 'O valor do credito deve ser numerico.',
            'amount.min' => 'O valor do credito deve ser maior que zero.',
            'other_description.max' => 'A descricao de Outros deve ter no maximo 255 caracteres.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'A data final nao pode ser menor que a data inicial.',
            ]);
        }

        $creditType = (string) $data['credit_type'];
        $otherDescription = trim((string) ($data['other_description'] ?? ''));

        if ($creditType === 'outros' && $otherDescription === '') {
            throw ValidationException::withMessages([
                'other_description' => 'Informe a descricao para o tipo Outros.',
            ]);
        }

        ContraChequeCredito::create([
            'user_id' => (int) $user->id,
            'tb28_periodo_inicio' => $startDate->toDateString(),
            'tb28_periodo_fim' => $endDate->toDateString(),
            'tb28_tipo' => $creditType,
            'tb28_descricao' => $creditType === 'outros' ? $otherDescription : null,
            'tb28_valor' => round(($creditType === 'inss' ? -1 : 1) * (float) $data['amount'], 2),
        ]);

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', 'Credito adicional do contra-cheque cadastrado com sucesso.');
    }

    public function updateContraChequeSalary(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'salary' => ['required', 'numeric', 'min:0'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
            'salary.required' => 'Informe o salario.',
            'salary.numeric' => 'O salario deve ser numerico.',
            'salary.min' => 'O salario deve ser maior ou igual a zero.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'A data final nao pode ser menor que a data inicial.',
            ]);
        }

        $user->update([
            'salario' => round((float) $data['salary'], 2),
        ]);

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', sprintf('Salario de %s atualizado com sucesso.', $user->name));
    }

    public function storeContraChequePayment(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'payment_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
            'payment_date.required' => 'Informe a data do pagamento.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');
        $paymentDate = $this->parseRequiredDate((string) $data['payment_date'], 'payment_date');

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'A data final nao pode ser menor que a data inicial.',
            ]);
        }

        $existingPayment = ContraChequePagamento::query()
            ->where('user_id', $user->id)
            ->whereDate('tb29_periodo_inicio', $startDate)
            ->whereDate('tb29_periodo_fim', $endDate)
            ->first();

        if ($existingPayment) {
            return redirect()
                ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
                ->with('error', sprintf(
                    'O contra-cheque de %s ja foi marcado como pago em %s.',
                    $user->name,
                    $existingPayment->tb29_data_pagamento?->format('d/m/y') ?? $paymentDate->format('d/m/y')
                ));
        }

        ContraChequePagamento::create([
            'user_id' => (int) $user->id,
            'tb29_registrado_por' => (int) $request->user()->id,
            'tb29_periodo_inicio' => $startDate->toDateString(),
            'tb29_periodo_fim' => $endDate->toDateString(),
            'tb29_data_pagamento' => $paymentDate->toDateString(),
        ]);

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', sprintf(
                'Pagamento do contra-cheque de %s registrado em %s.',
                $user->name,
                $paymentDate->format('d/m/y')
            ));
    }

    public function destroyContraChequeAdvance(
        Request $request,
        User $user,
        SalaryAdvance $salaryAdvance
    ): RedirectResponse {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        if ((int) $salaryAdvance->user_id !== (int) $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');

        $salaryAdvance->delete();

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', sprintf('Adiantamento de %s excluido com sucesso.', $user->name));
    }

    public function destroyContraChequeCredit(
        Request $request,
        User $user,
        ContraChequeCredito $contraChequeCredito
    ): RedirectResponse {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        if ((int) $contraChequeCredito->user_id !== (int) $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');

        $contraChequeCredito->delete();

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', sprintf('Lancamento extra de %s excluido com sucesso.', $user->name));
    }

    public function destroyContraChequeVale(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request->user());
        $this->ensureManagedPayrollUser($request->user(), $user);

        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'end_date' => ['required', 'string'],
            'unit_id' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'receipt_id' => ['nullable', 'integer'],
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['required', 'integer'],
        ], [
            'start_date.required' => 'Informe o inicio do periodo.',
            'end_date.required' => 'Informe o fim do periodo.',
            'sale_ids.required' => 'Nenhum vale foi informado para exclusao.',
            'sale_ids.array' => 'Os vales informados sao invalidos.',
            'sale_ids.min' => 'Nenhum vale foi informado para exclusao.',
        ]);

        $startDate = $this->parseRequiredDate((string) $data['start_date'], 'start_date');
        $endDate = $this->parseRequiredDate((string) $data['end_date'], 'end_date');
        $saleIds = collect($data['sale_ids'])
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();

        $sales = Venda::query()
            ->whereIn('tb3_id', $saleIds)
            ->where('id_user_vale', $user->id)
            ->where('tipo_pago', 'vale')
            ->get(['tb3_id', 'tb4_id']);

        if ($sales->count() !== $saleIds->count()) {
            abort(403);
        }

        $receiptId = isset($data['receipt_id']) ? (int) $data['receipt_id'] : null;

        DB::transaction(function () use ($saleIds, $receiptId) {
            Venda::query()->whereIn('tb3_id', $saleIds)->delete();

            if ($receiptId && ! Venda::query()->where('tb4_id', $receiptId)->exists()) {
                VendaPagamento::query()->where('tb4_id', $receiptId)->delete();
            }
        });

        return redirect()
            ->route('settings.contra-cheque', $this->buildContraChequeRedirectFilters($data, $startDate, $endDate))
            ->with('success', sprintf('Vale(s) de %s excluido(s) com sucesso.', $user->name));
    }

    private function buildContraChequePayload(Request $request): array
    {
        [$start, $end, $startDate, $endDate] = $this->resolveDateRange($request);
        $filterUnits = ManagementScope::managedUnits($request->user(), ['tb2_id', 'tb2_nome'])
            ->map(fn (Unidade $unit) => [
                'id' => (int) $unit->tb2_id,
                'name' => $unit->tb2_nome,
            ])
            ->values();
        $allowedUnitIds = $this->reportUnitIds($filterUnits);
        $selectedUnitId = $this->resolveSelectedUnitId($request->query('unit_id'), $allowedUnitIds);
        $selectedRole = $this->resolveSelectedRole($request->query('role'));
        $selectedPaymentDay = $this->resolveSelectedPaymentDay($request->query('payment_day'));
        $selectedUserId = $this->resolveSelectedUserId($request->query('user_id'));
        $selectedPaymentStatus = $this->resolveSelectedPaymentStatus($request->query('payment_status'));
        $selectedUnit = $selectedUnitId
            ? $filterUnits->firstWhere('id', $selectedUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];
        $roleOptions = collect(self::ROLE_LABELS)
            ->reject(fn (string $label, int $role) => $role === 6)
            ->map(fn (string $label, int $role) => [
                'id' => (int) $role,
                'label' => $label,
            ])
            ->values();

        $paymentDayOptions = $this->newPayrollUsersQuery($request, false)
            ->when($selectedUnitId, function ($query, int $unitId) {
                $this->applyUnitFilterToUsersQuery($query, $unitId);
            })
            ->when($selectedRole !== null, fn ($query) => $query->where('funcao', $selectedRole))
            ->where('salario', '>', 0)
            ->whereNotNull('payment_day')
            ->reorder()
            ->select('payment_day')
            ->distinct()
            ->orderBy('payment_day')
            ->pluck('payment_day')
            ->map(fn ($paymentDay) => [
                'id' => (int) $paymentDay,
                'label' => str_pad((string) $paymentDay, 2, '0', STR_PAD_LEFT),
            ])
            ->values();

        if (
            ! array_key_exists('payment_day', $request->query())
            && $selectedPaymentDay === null
        ) {
            $selectedPaymentDay = $this->resolveDefaultPaymentDayOption($paymentDayOptions);
        }

        $baseUsersQuery = $this->newPayrollUsersQuery($request, false);

        if ($selectedUnitId) {
            $this->applyUnitFilterToUsersQuery($baseUsersQuery, $selectedUnitId);
        }

        if ($selectedRole !== null) {
            $baseUsersQuery->where('funcao', $selectedRole);
        }

        if ($selectedPaymentDay !== null) {
            $baseUsersQuery->where('payment_day', $selectedPaymentDay);
        }

        if ($selectedUserId !== null) {
            $baseUsersQuery->where('id', $selectedUserId);
        }

        $baseUsersQuery->where('salario', '>', 0);

        $usersQuery = clone $baseUsersQuery;
        $filterUsersQuery = clone $baseUsersQuery;

        if ($selectedUserId !== null) {
            $filterUsersQuery = User::query()
                ->with([
                    'units:tb2_id,tb2_nome',
                    'primaryUnit:tb2_id,tb2_nome',
                ])
                ->where('funcao', '!=', 6)
                ->orderBy('name');

            ManagementScope::applyManagedUserScope($filterUsersQuery, $request->user());

            if ($selectedUnitId) {
                $this->applyUnitFilterToUsersQuery($filterUsersQuery, $selectedUnitId);
            }

            if ($selectedRole !== null) {
                $filterUsersQuery->where('funcao', $selectedRole);
            }

            if ($selectedPaymentDay !== null) {
                $filterUsersQuery->where('payment_day', $selectedPaymentDay);
            }

            $filterUsersQuery->where('salario', '>', 0);
        }

        if ($selectedUserId === null && $selectedPaymentDay !== null) {
            $filterUsersQuery->where('payment_day', $selectedPaymentDay);
        }

        $filterUsers = $filterUsersQuery
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
            ])
            ->values();

        if ($selectedUserId !== null && ! $filterUsers->contains(fn (array $user) => $user['id'] === $selectedUserId)) {
            $selectedUserId = null;
        }

        $users = $usersQuery->get(['id', 'name', 'phone', 'funcao', 'salario', 'tb2_id']);
        $userIds = $users->pluck('id')->map(fn ($value) => (int) $value)->values();

        $advances = $userIds->isEmpty()
            ? collect()
            : $this->advanceQuery($userIds, $startDate, $endDate, $selectedUnitId, $allowedUnitIds)
                ->with(['unit:tb2_id,tb2_nome'])
                ->orderBy('advance_date')
                ->orderBy('id')
                ->get(['id', 'user_id', 'unit_id', 'advance_date', 'amount', 'reason']);

        $valeSales = $userIds->isEmpty()
            ? collect()
            : $this->valeQuery($userIds, $start, $end, $selectedUnitId, $allowedUnitIds)
                ->with(['unidade:tb2_id,tb2_nome'])
                ->orderBy('data_hora')
                ->orderBy('tb3_id')
                ->get([
                    'tb3_id',
                    'tb4_id',
                    'id_user_vale',
                    'id_unidade',
                    'produto_nome',
                    'quantidade',
                    'valor_total',
                    'data_hora',
                ]);

        $extraCredits = $userIds->isEmpty()
            ? collect()
            : ContraChequeCredito::query()
                ->whereIn('user_id', $userIds)
                ->whereDate('tb28_periodo_inicio', $startDate)
                ->whereDate('tb28_periodo_fim', $endDate)
                ->orderBy('created_at')
                ->orderBy('tb28_id')
                ->get([
                    'tb28_id',
                    'user_id',
                    'tb28_tipo',
                    'tb28_descricao',
                    'tb28_valor',
                ]);

        $payments = $userIds->isEmpty()
            ? collect()
            : ContraChequePagamento::query()
                ->whereIn('user_id', $userIds)
                ->whereDate('tb29_periodo_inicio', $startDate)
                ->whereDate('tb29_periodo_fim', $endDate)
                ->orderBy('tb29_data_pagamento')
                ->orderBy('tb29_id')
                ->get([
                    'tb29_id',
                    'user_id',
                    'tb29_data_pagamento',
                ]);

        $advancesByUser = $advances->groupBy('user_id');
        $valeSalesByUser = $valeSales->groupBy('id_user_vale');
        $extraCreditsByUser = $extraCredits->groupBy('user_id');
        $paymentsByUser = $payments->keyBy('user_id');

        $rows = $users
            ->map(function (User $user) use ($advancesByUser, $valeSalesByUser, $extraCreditsByUser, $paymentsByUser, $startDate, $endDate) {
                $advanceRecords = $advancesByUser
                    ->get($user->id, collect())
                    ->map(function (SalaryAdvance $advance) {
                        return [
                            'id' => (int) $advance->id,
                            'advance_date' => $advance->advance_date?->toDateString(),
                            'amount' => round((float) $advance->amount, 2),
                            'reason' => $advance->reason,
                            'unit_name' => $advance->unit?->tb2_nome ?? '---',
                        ];
                    })
                    ->values();

                $valeRecords = $valeSalesByUser
                    ->get($user->id, collect())
                    ->groupBy('tb4_id')
                    ->map(function (Collection $group, $receiptId) {
                        /** @var Venda|null $first */
                        $first = $group->first();

                        return [
                            'id' => (int) $receiptId,
                            'receipt_id' => $first?->tb4_id ? (int) $first->tb4_id : null,
                            'date_time' => $first?->data_hora?->toIso8601String(),
                            'unit_name' => $first?->unidade?->tb2_nome ?? '---',
                            'items_count' => (int) $group->sum('quantidade'),
                            'items_label' => $group
                                ->map(fn (Venda $sale) => trim(sprintf('%sx %s', (int) $sale->quantidade, $sale->produto_nome)))
                                ->implode(', '),
                            'sale_ids' => $group
                                ->pluck('tb3_id')
                                ->map(fn ($value) => (int) $value)
                                ->values()
                                ->all(),
                            'total' => round((float) $group->sum('valor_total'), 2),
                        ];
                    })
                    ->sortBy('date_time')
                    ->values();

                $extraCreditRecords = $extraCreditsByUser
                    ->get($user->id, collect())
                    ->map(function (ContraChequeCredito $credit) {
                        $typeLabel = self::EXTRA_CREDIT_TYPE_LABELS[$credit->tb28_tipo] ?? 'Outros';
                        $description = $credit->tb28_tipo === 'outros' && filled($credit->tb28_descricao)
                            ? sprintf('%s: %s', $typeLabel, trim((string) $credit->tb28_descricao))
                            : $typeLabel;

                        return [
                            'id' => (int) $credit->tb28_id,
                            'type' => $credit->tb28_tipo,
                            'type_label' => $typeLabel,
                            'description' => $description,
                            'amount' => round((float) $credit->tb28_valor, 2),
                        ];
                    })
                    ->values();

                $advanceTotal = round((float) $advanceRecords->sum('amount'), 2);
                $valeTotal = round((float) $valeRecords->sum('total'), 2);
                $extraCreditTotal = round((float) $extraCreditRecords->sum('amount'), 2);
                $salary = round((float) ($user->salario ?? 0), 2);
                $balance = round($salary + $extraCreditTotal - $advanceTotal - $valeTotal, 2);
                $unitNames = $this->resolveUserUnitNames($user);
                /** @var ContraChequePagamento|null $paymentRecord */
                $paymentRecord = $paymentsByUser->get($user->id);
                $paymentDate = $paymentRecord?->tb29_data_pagamento?->toDateString();
                $isPaid = $paymentDate !== null;

                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                    'salary' => $salary,
                    'advances_total' => $advanceTotal,
                    'vales_total' => $valeTotal,
                    'extra_credits_total' => $extraCreditTotal,
                    'balance' => $balance,
                    'unit_names' => $unitNames->values()->all(),
                    'payment_status' => $isPaid ? 'paid' : 'pending',
                    'payment_date' => $paymentDate,
                    'detail' => [
                        'user_id' => (int) $user->id,
                        'user_name' => $user->name,
                        'phone' => $user->phone,
                        'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                        'unit_names' => $unitNames->values()->all(),
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'salary' => $salary,
                        'advances_total' => $advanceTotal,
                        'vales_total' => $valeTotal,
                        'extra_credits_total' => $extraCreditTotal,
                        'balance' => $balance,
                        'payment_status' => $isPaid ? 'paid' : 'pending',
                        'payment_date' => $paymentDate,
                        'advances_count' => $advanceRecords->count(),
                        'vales_count' => $valeRecords->count(),
                        'extra_credits_count' => $extraCreditRecords->count(),
                        'advances' => $advanceRecords->all(),
                        'vales' => $valeRecords->all(),
                        'extra_credits' => $extraCreditRecords->all(),
                    ],
                ];
            })
            ->when($selectedPaymentStatus === 'paid', fn (Collection $collection) => $collection->where('payment_status', 'paid'))
            ->when($selectedPaymentStatus === 'pending', fn (Collection $collection) => $collection->where('payment_status', 'pending'))
            ->values();

        $summary = [
            'employees_count' => $rows->count(),
            'salary_total' => round((float) $rows->sum('salary'), 2),
            'advances_total' => round((float) $rows->sum('advances_total'), 2),
            'vales_total' => round((float) $rows->sum('vales_total'), 2),
            'extra_credits_total' => round((float) $rows->sum('extra_credits_total'), 2),
            'balance_total' => round((float) $rows->sum('balance'), 2),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterUnits' => $filterUnits,
            'filterUsers' => $filterUsers,
            'paymentDayOptions' => $paymentDayOptions,
            'roleOptions' => $roleOptions,
            'selectedUnitId' => $selectedUnitId,
            'selectedPaymentDay' => $selectedPaymentDay,
            'selectedRole' => $selectedRole,
            'selectedUserId' => $selectedUserId,
            'selectedPaymentStatus' => $selectedPaymentStatus,
            'unit' => $selectedUnit,
        ];
    }

    private function buildPayrollPayload(Request $request, bool $onlyWithSalary = false, bool $onlyActiveUsers = false): array
    {
        [$windowStart, $windowEnd, $windowStartDate, $windowEndDate] = $this->resolveDateRange($request);
        $filterUnits = ManagementScope::managedUnits($request->user(), ['tb2_id', 'tb2_nome'])
            ->map(fn (Unidade $unit) => [
                'id' => (int) $unit->tb2_id,
                'name' => $unit->tb2_nome,
            ])
            ->values();
        $allowedUnitIds = $this->reportUnitIds($filterUnits);
        $selectedUnitId = $this->resolveSelectedUnitId($request->query('unit_id'), $allowedUnitIds);
        $selectedRole = $this->resolveSelectedRole($request->query('role'));
        $selectedPaymentDay = $this->resolveSelectedPaymentDay($request->query('payment_day'));
        $selectedUserId = $this->resolveSelectedUserId($request->query('user_id'));
        $selectedUnit = $selectedUnitId
            ? $filterUnits->firstWhere('id', $selectedUnitId)
            : ['id' => null, 'name' => 'Todas as unidades'];
        $roleOptions = collect(self::ROLE_LABELS)
            ->reject(fn (string $label, int $role) => $role === 6)
            ->map(fn (string $label, int $role) => [
                'id' => (int) $role,
                'label' => $label,
            ])
            ->values();

        $paymentDayOptions = $this->newPayrollUsersQuery($request, $onlyActiveUsers)
            ->when($selectedUnitId, function ($query, int $unitId) {
                $this->applyUnitFilterToUsersQuery($query, $unitId);
            })
            ->when($selectedRole !== null, fn ($query) => $query->where('funcao', $selectedRole))
            ->when($onlyWithSalary, fn ($query) => $query->where('salario', '>', 0))
            ->whereNotNull('payment_day')
            ->reorder()
            ->select('payment_day')
            ->distinct()
            ->orderBy('payment_day')
            ->pluck('payment_day')
            ->map(fn ($paymentDay) => [
                'id' => (int) $paymentDay,
                'label' => str_pad((string) $paymentDay, 2, '0', STR_PAD_LEFT),
            ])
            ->values();

        if (
            $onlyWithSalary
            && $onlyActiveUsers
            && ! array_key_exists('payment_day', $request->query())
            && $selectedPaymentDay === null
        ) {
            $selectedPaymentDay = $this->resolveDefaultPaymentDayOption($paymentDayOptions);
        }

        $baseUsersQuery = $this->newPayrollUsersQuery($request, $onlyActiveUsers);

        if ($selectedUnitId) {
            $this->applyUnitFilterToUsersQuery($baseUsersQuery, $selectedUnitId);
        }

        if ($selectedRole !== null) {
            $baseUsersQuery->where('funcao', $selectedRole);
        }

        if ($selectedPaymentDay !== null) {
            $baseUsersQuery->where('payment_day', $selectedPaymentDay);
        }

        if ($selectedUserId !== null) {
            $baseUsersQuery->where('id', $selectedUserId);
        }

        if ($onlyWithSalary) {
            $baseUsersQuery->where('salario', '>', 0);
        }

        $usersQuery = clone $baseUsersQuery;
        $filterUsersQuery = clone $baseUsersQuery;

        if ($selectedUserId !== null) {
            $filterUsersQuery = User::query()
                ->with([
                    'units:tb2_id,tb2_nome',
                    'primaryUnit:tb2_id,tb2_nome',
                ])
                ->where('funcao', '!=', 6)
                ->orderBy('name');

            ManagementScope::applyManagedUserScope($filterUsersQuery, $request->user());

            if ($onlyActiveUsers) {
                $filterUsersQuery->where('is_active', true);
            }

            if ($selectedUnitId) {
                $this->applyUnitFilterToUsersQuery($filterUsersQuery, $selectedUnitId);
            }

            if ($selectedRole !== null) {
                $filterUsersQuery->where('funcao', $selectedRole);
            }

            if ($selectedPaymentDay !== null) {
                $filterUsersQuery->where('payment_day', $selectedPaymentDay);
            }

            if ($onlyWithSalary) {
                $filterUsersQuery->where('salario', '>', 0);
            }
        }

        if ($selectedUserId === null && $selectedPaymentDay !== null) {
            $filterUsersQuery->where('payment_day', $selectedPaymentDay);
        }

        $filterUsers = $filterUsersQuery
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
            ])
            ->values();

        if ($selectedUserId !== null && ! $filterUsers->contains(fn (array $user) => $user['id'] === $selectedUserId)) {
            $selectedUserId = null;
        }

        $users = $usersQuery->get(['id', 'name', 'phone', 'funcao', 'salario', 'payment_day', 'tb2_id']);
        $userIds = $users->pluck('id')->map(fn ($value) => (int) $value)->values();

        $payrollPeriods = $users
            ->mapWithKeys(fn (User $user) => [
                (int) $user->id => $this->resolveUserPayrollPeriod($user, $windowStart, $windowEnd),
            ]);

        $overallStart = $payrollPeriods->reduce(
            fn (?Carbon $carry, array $period) => $carry === null || $period['period_start_at']->lt($carry)
                ? $period['period_start_at']->copy()
                : $carry,
            null,
        ) ?? $windowStart->copy();
        $overallEnd = $payrollPeriods->reduce(
            fn (?Carbon $carry, array $period) => $carry === null || $period['period_end_at']->gt($carry)
                ? $period['period_end_at']->copy()
                : $carry,
            null,
        ) ?? $windowEnd->copy();

        $advances = $userIds->isEmpty()
            ? collect()
            : $this->advanceQuery($userIds, $overallStart->toDateString(), $overallEnd->toDateString(), $selectedUnitId, $allowedUnitIds)
                ->with(['unit:tb2_id,tb2_nome'])
                ->orderBy('advance_date')
                ->orderBy('id')
                ->get(['id', 'user_id', 'unit_id', 'advance_date', 'amount', 'reason']);

        $valeSales = $userIds->isEmpty()
            ? collect()
            : $this->valeQuery($userIds, $overallStart, $overallEnd, $selectedUnitId, $allowedUnitIds)
                ->with(['unidade:tb2_id,tb2_nome'])
                ->orderBy('data_hora')
                ->orderBy('tb3_id')
                ->get([
                    'tb3_id',
                    'tb4_id',
                    'id_user_vale',
                    'id_unidade',
                    'produto_nome',
                    'quantidade',
                    'valor_total',
                    'data_hora',
                ]);

        $extraCredits = $userIds->isEmpty()
            ? collect()
            : ContraChequeCredito::query()
                ->whereIn('user_id', $userIds)
                ->whereDate('tb28_periodo_inicio', '<=', $overallEnd->toDateString())
                ->whereDate('tb28_periodo_fim', '>=', $overallStart->toDateString())
                ->orderBy('created_at')
                ->orderBy('tb28_id')
                ->get([
                    'tb28_id',
                    'user_id',
                    'tb28_periodo_inicio',
                    'tb28_periodo_fim',
                    'tb28_tipo',
                    'tb28_descricao',
                    'tb28_valor',
                ]);

        $advancesByUser = $advances->groupBy('user_id');
        $valeSalesByUser = $valeSales->groupBy('id_user_vale');
        $extraCreditsByUser = $extraCredits->groupBy('user_id');

        $rows = $users
            ->map(function (User $user) use ($advancesByUser, $valeSalesByUser, $extraCreditsByUser, $payrollPeriods, $windowStart, $windowEnd) {
                $period = $payrollPeriods->get((int) $user->id);

                if ($period === null) {
                    $period = $this->resolveUserPayrollPeriod($user, $windowStart, $windowEnd);
                }

                $periodStart = $period['period_start_at'];
                $periodEnd = $period['period_end_at'];
                $periodStartDate = $period['period_start_date'];
                $periodEndDate = $period['period_end_date'];

                $advanceRecords = $advancesByUser
                    ->get($user->id, collect())
                    ->filter(fn (SalaryAdvance $advance) => $this->dateFallsWithinPayrollPeriod($advance->advance_date, $periodStart, $periodEnd))
                    ->map(function (SalaryAdvance $advance) {
                        return [
                            'id' => (int) $advance->id,
                            'advance_date' => $advance->advance_date?->toDateString(),
                            'amount' => round((float) $advance->amount, 2),
                            'reason' => $advance->reason,
                            'unit_name' => $advance->unit?->tb2_nome ?? '---',
                        ];
                    })
                    ->values();

                $valeRecords = $valeSalesByUser
                    ->get($user->id, collect())
                    ->filter(fn (Venda $sale) => $this->dateFallsWithinPayrollPeriod($sale->data_hora, $periodStart, $periodEnd))
                    ->groupBy('tb4_id')
                    ->map(function (Collection $group, $receiptId) {
                        /** @var Venda|null $first */
                        $first = $group->first();

                        return [
                            'id' => (int) $receiptId,
                            'date_time' => $first?->data_hora?->toIso8601String(),
                            'unit_name' => $first?->unidade?->tb2_nome ?? '---',
                            'items_count' => (int) $group->sum('quantidade'),
                            'items_label' => $group
                                ->map(fn (Venda $sale) => trim(sprintf('%sx %s', (int) $sale->quantidade, $sale->produto_nome)))
                                ->implode(', '),
                            'total' => round((float) $group->sum('valor_total'), 2),
                        ];
                    })
                    ->sortBy('date_time')
                    ->values();

                $extraEntryRecords = $extraCreditsByUser
                    ->get($user->id, collect())
                    ->filter(function (ContraChequeCredito $credit) use ($periodStartDate, $periodEndDate) {
                        return $credit->tb28_periodo_inicio?->toDateString() === $periodStartDate
                            && $credit->tb28_periodo_fim?->toDateString() === $periodEndDate;
                    })
                    ->map(function (ContraChequeCredito $credit) {
                        $typeLabel = self::EXTRA_CREDIT_TYPE_LABELS[$credit->tb28_tipo] ?? 'Outros';
                        $isDeduction = in_array($credit->tb28_tipo, self::EXTRA_DEDUCTION_TYPES, true);
                        $description = $credit->tb28_tipo === 'outros' && filled($credit->tb28_descricao)
                            ? sprintf('%s: %s', $typeLabel, trim((string) $credit->tb28_descricao))
                            : $typeLabel;

                        return [
                            'id' => (int) $credit->tb28_id,
                            'type' => $credit->tb28_tipo,
                            'type_label' => $typeLabel,
                            'description' => $description,
                            'amount' => round((float) $credit->tb28_valor, 2),
                            'kind' => $isDeduction ? 'deduction' : 'credit',
                            'is_deduction' => $isDeduction,
                        ];
                    })
                    ->values();

                $extraCreditRecords = $extraEntryRecords
                    ->filter(fn (array $entry) => ! $entry['is_deduction'])
                    ->values();
                $extraDiscountRecords = $extraEntryRecords
                    ->filter(fn (array $entry) => $entry['is_deduction'])
                    ->values();
                $advanceTotal = round((float) $advanceRecords->sum('amount'), 2);
                $valeTotal = round((float) $valeRecords->sum('total'), 2);
                $extraCreditTotal = round((float) $extraCreditRecords->sum('amount'), 2);
                $extraDiscountTotal = round((float) $extraDiscountRecords->sum('amount'), 2);
                $salary = round((float) ($user->salario ?? 0), 2);
                $balance = round($salary + $extraCreditTotal - $extraDiscountTotal - $advanceTotal - $valeTotal, 2);
                $unitNames = $this->resolveUserUnitNames($user);

                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                    'salary' => $salary,
                    'payment_day' => $period['payment_day'],
                    'payment_day_label' => $period['payment_day_label'],
                    'period_label' => sprintf('%s a %s', $periodStart->format('d/m/Y'), $periodEnd->format('d/m/Y')),
                    'advances_total' => $advanceTotal,
                    'vales_total' => $valeTotal,
                    'extra_credits_total' => $extraCreditTotal,
                    'extra_discounts_total' => $extraDiscountTotal,
                    'balance' => $balance,
                    'unit_names' => $unitNames->values()->all(),
                    'detail' => [
                        'user_id' => (int) $user->id,
                        'user_name' => $user->name,
                        'phone' => $user->phone,
                        'role_label' => self::ROLE_LABELS[(int) $user->funcao] ?? '---',
                        'unit_names' => $unitNames->values()->all(),
                        'start_date' => $periodStartDate,
                        'end_date' => $periodEndDate,
                        'payment_day' => $period['payment_day'],
                        'payment_day_label' => $period['payment_day_label'],
                        'uses_payment_day' => $period['uses_payment_day'],
                        'salary' => $salary,
                        'advances_total' => $advanceTotal,
                        'vales_total' => $valeTotal,
                        'extra_credits_total' => $extraCreditTotal,
                        'extra_discounts_total' => $extraDiscountTotal,
                        'balance' => $balance,
                        'advances_count' => $advanceRecords->count(),
                        'vales_count' => $valeRecords->count(),
                        'extra_credits_count' => $extraCreditRecords->count(),
                        'extra_discounts_count' => $extraDiscountRecords->count(),
                        'advances' => $advanceRecords->all(),
                        'vales' => $valeRecords->all(),
                        'extra_credits' => $extraCreditRecords->all(),
                        'extra_discounts' => $extraDiscountRecords->all(),
                    ],
                ];
            })
            ->values();

        $summary = [
            'employees_count' => $rows->count(),
            'salary_total' => round((float) $rows->sum('salary'), 2),
            'advances_total' => round((float) $rows->sum('advances_total'), 2),
            'vales_total' => round((float) $rows->sum('vales_total'), 2),
            'extra_credits_total' => round((float) $rows->sum('extra_credits_total'), 2),
            'extra_discounts_total' => round((float) $rows->sum('extra_discounts_total'), 2),
            'balance_total' => round((float) $rows->sum('balance'), 2),
        ];

        return [
            'rows' => $rows,
            'summary' => $summary,
            'startDate' => $windowStartDate,
            'endDate' => $windowEndDate,
            'filterUnits' => $filterUnits,
            'filterUsers' => $filterUsers,
            'paymentDayOptions' => $paymentDayOptions,
            'roleOptions' => $roleOptions,
            'selectedUnitId' => $selectedUnitId,
            'selectedPaymentDay' => $selectedPaymentDay,
            'selectedRole' => $selectedRole,
            'selectedUserId' => $selectedUserId,
            'unit' => $selectedUnit,
        ];
    }

    private function resolveDefaultPaymentDayOption(Collection $paymentDayOptions): ?int
    {
        if ($paymentDayOptions->isEmpty()) {
            return null;
        }

        $todayPaymentDay = (int) Carbon::today()->day;
        $currentOrNextPaymentDay = $paymentDayOptions->first(
            fn (array $option) => (int) ($option['id'] ?? 0) >= $todayPaymentDay
        );

        if ($currentOrNextPaymentDay) {
            return (int) $currentOrNextPaymentDay['id'];
        }

        $firstPaymentDay = $paymentDayOptions->first();

        return $firstPaymentDay ? (int) $firstPaymentDay['id'] : null;
    }

    private function resolveUserPayrollPeriod(User $user, Carbon $windowStart, Carbon $windowEnd): array
    {
        $paymentDay = $this->normalizePaymentDay($user->payment_day);

        if ($paymentDay === null) {
            return [
                'payment_day' => null,
                'payment_day_label' => null,
                'uses_payment_day' => false,
                'period_start_at' => $windowStart->copy()->startOfDay(),
                'period_end_at' => $windowEnd->copy()->endOfDay(),
                'period_start_date' => $windowStart->toDateString(),
                'period_end_date' => $windowEnd->toDateString(),
            ];
        }

        $paymentDate = $this->resolvePaymentDateForWindow($paymentDay, $windowStart, $windowEnd);
        $previousPaymentDate = $this->scheduledPaymentDateForMonth(
            $paymentDate->copy()->subMonthNoOverflow(),
            $paymentDay,
        );

        return [
            'payment_day' => $paymentDay,
            'payment_day_label' => str_pad((string) $paymentDay, 2, '0', STR_PAD_LEFT),
            'uses_payment_day' => true,
            'period_start_at' => $previousPaymentDate->copy()->startOfDay(),
            'period_end_at' => $paymentDate->copy()->endOfDay(),
            'period_start_date' => $previousPaymentDate->toDateString(),
            'period_end_date' => $paymentDate->toDateString(),
        ];
    }

    private function normalizePaymentDay(mixed $value): ?int
    {
        $paymentDay = (int) $value;

        if ($paymentDay < 1 || $paymentDay > 31) {
            return null;
        }

        return $paymentDay;
    }

    private function resolvePaymentDateForWindow(int $paymentDay, Carbon $windowStart, Carbon $windowEnd): Carbon
    {
        $currentMonth = $windowStart->copy()->startOfMonth();
        $lastMonth = $windowEnd->copy()->startOfMonth();
        $candidates = collect();

        while ($currentMonth->lte($lastMonth)) {
            $candidate = $this->scheduledPaymentDateForMonth($currentMonth, $paymentDay);

            if ($candidate->between($windowStart, $windowEnd, true)) {
                $candidates->push($candidate->copy());
            }

            $currentMonth->addMonthNoOverflow()->startOfMonth();
        }

        if ($candidates->isNotEmpty()) {
            return $candidates->last()->copy()->startOfDay();
        }

        $fallback = $this->scheduledPaymentDateForMonth($windowEnd, $paymentDay);

        if ($fallback->gt($windowEnd)) {
            $fallback = $this->scheduledPaymentDateForMonth($windowEnd->copy()->subMonthNoOverflow(), $paymentDay);
        }

        return $fallback->startOfDay();
    }

    private function scheduledPaymentDateForMonth(Carbon $monthReference, int $paymentDay): Carbon
    {
        $month = $monthReference->copy()->startOfMonth();
        $day = min($paymentDay, $month->daysInMonth);

        return $month->copy()->day($day)->startOfDay();
    }

    private function dateFallsWithinPayrollPeriod(?Carbon $date, Carbon $periodStart, Carbon $periodEnd): bool
    {
        if (! $date) {
            return false;
        }

        return $date->between($periodStart, $periodEnd, true);
    }

    private function ensureAdmin($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }

    private function resolveDateRange(Request $request): array
    {
        $defaultStart = Carbon::today()->startOfMonth();
        $defaultEnd = Carbon::today()->endOfMonth();

        $start = $this->parseFlexibleDate($request->query('start_date'), $defaultStart)->startOfDay();
        $end = $this->parseFlexibleDate($request->query('end_date'), $defaultEnd)->endOfDay();

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, $start->toDateString(), $end->toDateString()];
    }

    private function parseFlexibleDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy();
        }

        foreach (['d/m/y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (InvalidFormatException $exception) {
                continue;
            }
        }

        return $fallback->copy();
    }

    private function parseRequiredDate(string $value, string $field): Carbon
    {
        $normalized = trim($value);

        foreach (['d/m/y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalized);
            } catch (\Throwable $exception) {
                continue;
            }

            if ($date && $date->format($format) === $normalized) {
                return $date->startOfDay();
            }
        }

        throw ValidationException::withMessages([
            $field => 'Informe a data no formato DD/MM/AA.',
        ]);
    }

    private function resolveSelectedUnitId(mixed $requestedUnitId, Collection $allowedUnitIds): ?int
    {
        if ($requestedUnitId === null || $requestedUnitId === '' || $requestedUnitId === 'all') {
            return null;
        }

        $unitId = (int) $requestedUnitId;

        if ($unitId <= 0 || ! $allowedUnitIds->contains($unitId)) {
            return null;
        }

        return $unitId;
    }

    private function resolveSelectedRole(mixed $requestedRole): ?int
    {
        if ($requestedRole === null || $requestedRole === '' || $requestedRole === 'all') {
            return null;
        }

        $role = (int) $requestedRole;

        if (! array_key_exists($role, self::ROLE_LABELS) || $role === 6) {
            return null;
        }

        return $role;
    }

    private function resolveSelectedPaymentDay(mixed $requestedPaymentDay): ?int
    {
        if ($requestedPaymentDay === null || $requestedPaymentDay === '' || $requestedPaymentDay === 'all') {
            return null;
        }

        return $this->normalizePaymentDay($requestedPaymentDay);
    }

    private function resolveSelectedUserId(mixed $requestedUserId): ?int
    {
        if ($requestedUserId === null || $requestedUserId === '' || $requestedUserId === 'all') {
            return null;
        }

        $userId = (int) $requestedUserId;

        return $userId > 0 ? $userId : null;
    }

    private function resolveSelectedPaymentStatus(mixed $requestedStatus): string
    {
        if (! is_string($requestedStatus) || $requestedStatus === '') {
            return 'pending';
        }

        return in_array($requestedStatus, ['all', 'paid', 'pending'], true)
            ? $requestedStatus
            : 'pending';
    }

    private function reportUnitIds(iterable $units): Collection
    {
        return collect($units)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
    }

    private function advanceQuery(
        Collection $userIds,
        string $startDate,
        string $endDate,
        ?int $selectedUnitId,
        Collection $allowedUnitIds
    ) {
        $query = SalaryAdvance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('advance_date', [$startDate, $endDate]);

        if ($selectedUnitId) {
            $query->where(function ($sub) use ($selectedUnitId) {
                $sub->where('unit_id', $selectedUnitId)
                    ->orWhere(function ($legacy) use ($selectedUnitId) {
                        $legacy->whereNull('unit_id')
                            ->where(function ($legacyUnit) use ($selectedUnitId) {
                                $legacyUnit->whereHas('user', function ($userQuery) use ($selectedUnitId) {
                                    $userQuery->where('tb2_id', $selectedUnitId);
                                })->orWhereHas('user.units', function ($unitQuery) use ($selectedUnitId) {
                                    $unitQuery->where('tb2_unidades.tb2_id', $selectedUnitId);
                                });
                            });
                    });
            });

            return $query;
        }

        if ($allowedUnitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($sub) use ($allowedUnitIds) {
            $sub->whereIn('unit_id', $allowedUnitIds)
                ->orWhere(function ($legacy) use ($allowedUnitIds) {
                    $legacy->whereNull('unit_id')
                        ->where(function ($legacyUnit) use ($allowedUnitIds) {
                            $legacyUnit->whereHas('user', function ($userQuery) use ($allowedUnitIds) {
                                $userQuery->whereIn('tb2_id', $allowedUnitIds);
                            })->orWhereHas('user.units', function ($unitQuery) use ($allowedUnitIds) {
                                $unitQuery->whereIn('tb2_unidades.tb2_id', $allowedUnitIds);
                            });
                        });
                });
        });
    }

    private function valeQuery(
        Collection $userIds,
        Carbon $start,
        Carbon $end,
        ?int $selectedUnitId,
        Collection $allowedUnitIds
    ) {
        $query = Venda::query()
            ->whereIn('id_user_vale', $userIds)
            ->where('tipo_pago', 'vale')
            ->whereBetween('data_hora', [$start, $end]);

        if ($selectedUnitId) {
            return $query->where('id_unidade', $selectedUnitId);
        }

        if ($allowedUnitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id_unidade', $allowedUnitIds);
    }

    private function resolveUserUnitNames(User $user): Collection
    {
        $units = collect($user->units ?? [])
            ->pluck('tb2_nome')
            ->filter();

        if ($user->primaryUnit?->tb2_nome && ! $units->contains($user->primaryUnit->tb2_nome)) {
            $units->push($user->primaryUnit->tb2_nome);
        }

        $uniqueUnits = $units->unique()->sort()->values();

        if ($uniqueUnits->count() > 1) {
            return collect();
        }

        return $uniqueUnits;
    }

    private function newPayrollUsersQuery(Request $request, bool $onlyActiveUsers)
    {
        $query = User::query()
            ->with([
                'units:tb2_id,tb2_nome',
                'primaryUnit:tb2_id,tb2_nome',
            ])
            ->where('funcao', '!=', 6)
            ->orderBy('name');

        ManagementScope::applyManagedUserScope($query, $request->user());

        if ($onlyActiveUsers) {
            $query->where('is_active', true);
        }

        return $query;
    }

    private function applyUnitFilterToUsersQuery($query, int $selectedUnitId): void
    {
        $query->where(function ($unitScopeQuery) use ($selectedUnitId) {
            $unitScopeQuery
                ->where('tb2_id', $selectedUnitId)
                ->orWhereHas('units', function ($unitQuery) use ($selectedUnitId) {
                    $unitQuery->where('tb2_unidades.tb2_id', $selectedUnitId);
                });
        });
    }

    private function ensureManagedPayrollUser($authUser, User $user): void
    {
        $query = User::query()
            ->whereKey($user->id)
            ->where('funcao', '!=', 6);

        ManagementScope::applyManagedUserScope($query, $authUser);

        if (! $query->exists()) {
            abort(403);
        }
    }

    private function buildContraChequeRedirectFilters(array $data, Carbon $startDate, Carbon $endDate): array
    {
        $filters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ];

        foreach (['unit_id', 'role', 'user_id', 'payment_day'] as $field) {
            $value = $data[$field] ?? null;

            if ($value !== null && $value !== '' && $value !== 'all') {
                $filters[$field] = $value;
            }
        }

        $paymentStatus = $data['payment_status'] ?? null;

        if ($paymentStatus !== null && $paymentStatus !== '' && $paymentStatus !== 'all') {
            $filters['payment_status'] = $paymentStatus;
        }

        return $filters;
    }
}
