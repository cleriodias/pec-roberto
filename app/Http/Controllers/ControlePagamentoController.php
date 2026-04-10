<?php

namespace App\Http\Controllers;

use App\Models\ControlePagamento;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ControlePagamentoController extends Controller
{
    private const FREQUENCY_LABELS = [
        'semanal' => 'Semanal',
        'quinzenal' => 'Quinzenal',
        'mensal' => 'Mensal',
    ];

    private const WEEKDAY_LABELS = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
    ];

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request->user());
        $today = Carbon::today();

        $paymentControls = ControlePagamento::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (ControlePagamento $item) => $this->mapPaymentControl($item, $today))
            ->values();

        return Inertia::render('Settings/ControlePagamentos', [
            'paymentControls' => $paymentControls,
            'timelineReferenceDate' => $today->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:255'],
            'frequencia' => ['required', 'string', Rule::in(array_keys(self::FREQUENCY_LABELS))],
            'dia_semana' => ['nullable', 'integer', 'between:0,6'],
            'dia_mes' => ['nullable', 'integer', 'between:1,31'],
            'valor_total' => ['required', 'numeric', 'min:0.01'],
            'quantidade_parcelas' => ['required', 'integer', 'min:1', 'max:480'],
            'data_inicio' => ['required', 'string'],
        ], [
            'descricao.required' => 'Informe a descricao.',
            'frequencia.required' => 'Selecione a frequencia.',
            'frequencia.in' => 'A frequencia selecionada e invalida.',
            'dia_semana.between' => 'Selecione um dia da semana valido.',
            'dia_mes.between' => 'Selecione um dia do mes valido.',
            'valor_total.required' => 'Informe o valor total.',
            'valor_total.numeric' => 'O valor total deve ser numerico.',
            'valor_total.min' => 'O valor total deve ser maior que zero.',
            'quantidade_parcelas.required' => 'Informe a quantidade de parcelas.',
            'quantidade_parcelas.integer' => 'A quantidade de parcelas deve ser numerica.',
            'quantidade_parcelas.min' => 'A quantidade de parcelas deve ser maior que zero.',
            'data_inicio.required' => 'Informe a data de inicio.',
        ]);

        $startDate = $this->parseDate((string) $data['data_inicio']);
        $frequency = (string) $data['frequencia'];
        $weekday = array_key_exists('dia_semana', $data) && $data['dia_semana'] !== null
            ? (int) $data['dia_semana']
            : null;
        $monthDay = array_key_exists('dia_mes', $data) && $data['dia_mes'] !== null
            ? (int) $data['dia_mes']
            : null;

        $this->validateFrequencyFields($frequency, $weekday, $monthDay, $startDate);

        $installments = (int) $data['quantidade_parcelas'];
        $totalAmount = round((float) $data['valor_total'], 2);
        $installmentAmount = round($totalAmount / $installments, 2);
        $endDate = $this->calculateEndDate($frequency, $startDate, $installments, $monthDay);

        ControlePagamento::create([
            'descricao' => trim((string) $data['descricao']),
            'frequencia' => $frequency,
            'dia_semana' => $frequency === 'semanal' ? $weekday : null,
            'dia_mes' => $frequency === 'mensal' ? $monthDay : null,
            'valor_total' => $totalAmount,
            'quantidade_parcelas' => $installments,
            'valor_parcela' => $installmentAmount,
            'data_inicio' => $startDate->toDateString(),
            'data_fim' => $endDate->toDateString(),
        ]);

        return redirect()
            ->route('settings.payment-control')
            ->with('success', 'Controle de pagamentos cadastrado com sucesso.');
    }

    public function destroy(Request $request, ControlePagamento $controlePagamento): RedirectResponse
    {
        $this->ensureAdmin($request->user());

        $controlePagamento->delete();

        return redirect()
            ->route('settings.payment-control')
            ->with('success', 'Controle de pagamentos removido com sucesso.');
    }

    private function ensureAdmin($user): void
    {
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }
    }

    private function parseDate(string $value): Carbon
    {
        $normalized = trim($value);

        foreach (['d/m/y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalized);
            } catch (\Throwable $e) {
                continue;
            }

            if ($date && $date->format($format) === $normalized) {
                return $date->startOfDay();
            }
        }

        throw ValidationException::withMessages([
            'data_inicio' => 'Informe a data no formato DD/MM/AA.',
        ]);
    }

    private function validateFrequencyFields(
        string $frequency,
        ?int $weekday,
        ?int $monthDay,
        Carbon $startDate
    ): void {
        if ($frequency === 'semanal') {
            if ($weekday === null) {
                throw ValidationException::withMessages([
                    'dia_semana' => 'Selecione o dia da semana para a recorrencia semanal.',
                ]);
            }

            if ($startDate->dayOfWeek !== $weekday) {
                throw ValidationException::withMessages([
                    'dia_semana' => 'A data de inicio precisa cair no dia da semana selecionado.',
                ]);
            }
        }

        if ($frequency === 'mensal') {
            if ($monthDay === null) {
                throw ValidationException::withMessages([
                    'dia_mes' => 'Selecione o dia do mes para a recorrencia mensal.',
                ]);
            }

            if ((int) $startDate->day !== $monthDay) {
                throw ValidationException::withMessages([
                    'dia_mes' => 'A data de inicio precisa usar o mesmo dia do mes informado.',
                ]);
            }
        }
    }

    private function calculateEndDate(
        string $frequency,
        Carbon $startDate,
        int $installments,
        ?int $monthDay
    ): Carbon {
        if ($installments <= 1) {
            return $startDate->copy();
        }

        if ($frequency === 'semanal') {
            return $startDate->copy()->addDays(($installments - 1) * 7);
        }

        if ($frequency === 'quinzenal') {
            return $startDate->copy()->addDays(($installments - 1) * 14);
        }

        $current = $startDate->copy();
        for ($index = 1; $index < $installments; $index++) {
            $current = $this->addNextMonthlyOccurrence($current, (int) $monthDay);
        }

        return $current;
    }

    private function addNextMonthlyOccurrence(Carbon $current, int $targetDay): Carbon
    {
        $nextMonthStart = $current->copy()->startOfDay()->addMonthNoOverflow()->startOfMonth();
        $day = min($targetDay, $nextMonthStart->daysInMonth);

        return $nextMonthStart->copy()->day($day);
    }

    private function mapPaymentControl(ControlePagamento $item, Carbon $today): array
    {
        $timeline = $this->buildInstallmentTimeline($item, $today);

        return [
            'id' => $item->id,
            'descricao' => $item->descricao,
            'frequencia' => $item->frequencia,
            'frequencia_label' => self::FREQUENCY_LABELS[$item->frequencia] ?? $item->frequencia,
            'dia_semana' => $item->dia_semana,
            'dia_semana_label' => $item->dia_semana !== null
                ? (self::WEEKDAY_LABELS[$item->dia_semana] ?? '---')
                : null,
            'dia_mes' => $item->dia_mes,
            'valor_total' => round((float) $item->valor_total, 2),
            'quantidade_parcelas' => (int) $item->quantidade_parcelas,
            'valor_parcela' => round((float) $item->valor_parcela, 2),
            'data_inicio' => $item->data_inicio?->toDateString(),
            'data_fim' => $item->data_fim?->toDateString(),
            'timeline' => $timeline,
        ];
    }

    private function buildInstallmentTimeline(ControlePagamento $item, Carbon $today): array
    {
        $records = collect();

        for ($installmentNumber = 1; $installmentNumber <= (int) $item->quantidade_parcelas; $installmentNumber++) {
            $dueDate = $this->calculateInstallmentDate($item, $installmentNumber);
            $status = $this->classifyInstallmentStatus($dueDate, $today);

            $records->push([
                'numero' => $installmentNumber,
                'data_vencimento' => $dueDate->toDateString(),
                'valor' => round((float) $item->valor_parcela, 2),
                'status' => $status['key'],
                'status_label' => $status['label'],
                'dias_para_vencer' => $today->diffInDays($dueDate, false),
            ]);
        }

        return [
            'summary' => [
                'total_parcelas' => $records->count(),
                'vencido' => $records->where('status', 'vencido')->count(),
                'por_vencer' => $records->where('status', 'por_vencer')->count(),
                'nao_venceu' => $records->where('status', 'nao_venceu')->count(),
            ],
            'records' => $records->values()->all(),
        ];
    }

    private function calculateInstallmentDate(ControlePagamento $item, int $installmentNumber): Carbon
    {
        $startDate = $item->data_inicio instanceof Carbon
            ? $item->data_inicio->copy()->startOfDay()
            : Carbon::parse($item->data_inicio)->startOfDay();

        if ($installmentNumber <= 1) {
            return $startDate;
        }

        if ($item->frequencia === 'semanal') {
            return $startDate->addDays(($installmentNumber - 1) * 7);
        }

        if ($item->frequencia === 'quinzenal') {
            return $startDate->addDays(($installmentNumber - 1) * 14);
        }

        $current = $startDate;
        for ($index = 1; $index < $installmentNumber; $index++) {
            $current = $this->addNextMonthlyOccurrence($current, (int) $item->dia_mes);
        }

        return $current;
    }

    private function classifyInstallmentStatus(Carbon $dueDate, Carbon $today): array
    {
        $daysUntilDue = $today->diffInDays($dueDate, false);

        if ($daysUntilDue < 0) {
            return [
                'key' => 'vencido',
                'label' => 'Vencido',
            ];
        }

        if ($daysUntilDue <= 7) {
            return [
                'key' => 'por_vencer',
                'label' => 'Por vencer',
            ];
        }

        return [
            'key' => 'nao_venceu',
            'label' => 'Nao venceu',
        ];
    }
}
