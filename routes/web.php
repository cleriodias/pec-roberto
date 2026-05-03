<?php

use App\Http\Controllers\AnyDesckController;
use App\Http\Controllers\BoletoController;
use App\Http\Controllers\CashierClosureController;
use App\Http\Controllers\ControlePagamentoController;
use App\Http\Controllers\DatabaseToolsController;
use App\Http\Controllers\DiscardSettingsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FiscalConfigurationController;
use App\Http\Controllers\FiscalReferenceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDiscardController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LanchoneteTerminalController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\OnlineController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesDisputeController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitSwitchController;
use App\Http\Controllers\UserController;
use App\Models\User;
use App\Models\Unidade;
use App\Support\ManagementScope;
use App\Support\ProductQuickLookupCache;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    $requestedUnitId = (int) $request->query('l', 0);
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'selectedUnitId' => $requestedUnitId > 0 ? $requestedUnitId : null,
        'units' => Unidade::active()->orderBy('tb2_nome')->get([
            'tb2_id',
            'tb2_nome',
            'tb2_endereco',
            'tb2_cep',
            'tb2_fone',
            'tb2_localizacao',
        ]),
    ]);
});

Route::post('/newsletter', [NewsletterSubscriptionController::class, 'store'])
    ->name('newsletter.store');

if (class_exists(\App\Http\Controllers\MobileRevenueController::class)) {
    Route::get('/app/endpoints/mobile/revenue/dashboard', [\App\Http\Controllers\MobileRevenueController::class, 'dashboard'])
        ->name('mobile.revenue.dashboard');
    Route::get('/app/endpoints/mobile/revenue/daily', [\App\Http\Controllers\MobileRevenueController::class, 'daily'])
        ->name('mobile.revenue.daily');
    Route::get('/app/endpoints/mobile/revenue/monthly', [\App\Http\Controllers\MobileRevenueController::class, 'monthly'])
        ->name('mobile.revenue.monthly');
}


Route::get('/dashboard', function (Request $request, ProductQuickLookupCache $quickLookupCache) {
    $contraChequeShortcutSummary = null;
    $authUser = $request->user();

    if ($authUser) {
        $salaryTotalsByPaymentDay = User::query()
            ->where('funcao', '!=', 6)
            ->where('is_active', true)
            ->where('salario', '>', 0)
            ->whereNotNull('payment_day')
            ->selectRaw('payment_day, SUM(salario) as salary_total')
            ->groupBy('payment_day')
            ->orderBy('payment_day');

        ManagementScope::applyManagedUserScope($salaryTotalsByPaymentDay, $authUser);

        $paymentDaySummaries = $salaryTotalsByPaymentDay
            ->get()
            ->map(fn (User $user) => [
                'payment_day' => (int) $user->payment_day,
                'salary_total' => round((float) $user->salary_total, 2),
            ])
            ->filter(fn (array $summary) => $summary['payment_day'] >= 1 && $summary['payment_day'] <= 31 && $summary['salary_total'] > 0)
            ->values();

        if ($paymentDaySummaries->isNotEmpty()) {
            $todayPaymentDay = (int) Carbon::today()->day;
            $selectedSummary = $paymentDaySummaries->firstWhere('payment_day', $todayPaymentDay);

            if (! $selectedSummary) {
                $selectedSummary = $paymentDaySummaries->first(
                    fn (array $summary) => $summary['payment_day'] > $todayPaymentDay
                ) ?? $paymentDaySummaries->first();
            }

            if ($selectedSummary) {
                $contraChequeShortcutSummary = [
                    'paymentDay' => $selectedSummary['payment_day'],
                    'salaryTotal' => $selectedSummary['salary_total'],
                    'isToday' => $selectedSummary['payment_day'] === $todayPaymentDay,
                ];
            }
        }
    }

    return Inertia::render('Dashboard', [
        'quickLookupProducts' => fn () => $quickLookupCache->forRequest($request),
        'contraChequeShortcutSummary' => $contraChequeShortcutSummary,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/supplier/access', [SupplierPortalController::class, 'access'])->name('supplier.access');
Route::post('/supplier/access', [SupplierPortalController::class, 'authenticate'])->name('supplier.authenticate');
Route::post('/supplier/logout', [SupplierPortalController::class, 'logout'])->name('supplier.logout');
Route::get('/supplier/disputes', [SupplierPortalController::class, 'disputes'])->name('supplier.disputes');
Route::put('/supplier/disputes/{bid}', [SupplierPortalController::class, 'updateBid'])->name('supplier.disputes.update');
Route::post('/supplier/disputes/{bid}/invoice', [SupplierPortalController::class, 'invoice'])->name('supplier.disputes.invoice');

Route::middleware('auth')->group(function () {
    Route::get('/chamados', [SupportTicketController::class, 'index'])->name('support.tickets.index');
    Route::post('/chamados', [SupportTicketController::class, 'store'])->name('support.tickets.store');
    Route::post('/chamados/{ticket}/interacoes', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
    Route::put('/chamados/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.tickets.update-status');
    Route::delete('/chamados/{ticket}', [SupportTicketController::class, 'destroy'])->name('support.tickets.destroy');
    Route::get('/chamados/{ticket}/video', [SupportTicketController::class, 'video'])->name('support.tickets.video');
    Route::get('/chamados/anexos/{attachment}', [SupportTicketController::class, 'attachment'])->name('support.tickets.attachments.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/access-code', [ProfileController::class, 'updateAccessCode'])->name('profile.access-code.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/on-line', [OnlineController::class, 'index'])->name('online.index');
    Route::get('/on-line/snapshot', [OnlineController::class, 'snapshot'])->name('online.snapshot');
    Route::get('/on-line/summary', [OnlineController::class, 'summary'])->name('online.summary');
    Route::get('/on-line/anydesck', [OnlineController::class, 'anydesck'])->name('online.anydesck.show');
    Route::put('/on-line/anydesck', [OnlineController::class, 'updateAnydesck'])->name('online.anydesck.update');
    Route::post('/on-line/heartbeat', [OnlineController::class, 'heartbeat'])->name('online.heartbeat');
    Route::post('/on-line/messages', [OnlineController::class, 'storeMessage'])->name('online.messages.store');
    Route::put('/on-line/messages/{message}', [OnlineController::class, 'updateMessage'])->name('online.messages.update');
    Route::delete('/on-line/messages/{message}', [OnlineController::class, 'destroyMessage'])->name('online.messages.destroy');
    Route::get('/settings', function () {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }

        return Inertia::render('Settings/Config');
    })->name('settings.config');
    Route::get('/settings/menu', function () {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }

        return Inertia::render('Settings/Menu');
    })->name('settings.menu');
    Route::get('/settings/profile-access', function () {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }

        return Inertia::render('Settings/ProfileAccess');
    })->name('settings.profile-access');
    Route::get('/settings/menu-order', function () {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->funcao, [0, 1], true)) {
            abort(403);
        }

        return Inertia::render('Settings/MenuOrder');
    })->name('settings.menu-order');
    Route::get('/settings/database', [DatabaseToolsController::class, 'index'])
        ->name('settings.database');
    Route::post('/settings/database', [DatabaseToolsController::class, 'run'])
        ->name('settings.database.run');
    Route::get('/settings/discard-config', [DiscardSettingsController::class, 'index'])
        ->name('settings.discard-config');
    Route::put('/settings/discard-config', [DiscardSettingsController::class, 'update'])
        ->name('settings.discard-config.update');
    Route::get('/settings/controle-pagamentos', [ControlePagamentoController::class, 'index'])
        ->name('settings.payment-control');
    Route::post('/settings/controle-pagamentos', [ControlePagamentoController::class, 'store'])
        ->name('settings.payment-control.store');
    Route::delete('/settings/controle-pagamentos/{controlePagamento}', [ControlePagamentoController::class, 'destroy'])
        ->name('settings.payment-control.destroy');
    Route::get('/settings/folha-pagamento', [PayrollController::class, 'index'])
        ->name('settings.payroll');
    Route::get('/settings/fiscal', [FiscalConfigurationController::class, 'index'])
        ->name('settings.fiscal');
    Route::post('/settings/fiscal', [FiscalConfigurationController::class, 'update'])
        ->name('settings.fiscal.update');
    Route::post('/settings/fiscal/reprocess', [FiscalConfigurationController::class, 'reprocess'])
        ->name('settings.fiscal.reprocess');
    Route::post('/settings/fiscal/notas/{notaFiscal}/regenerate', [FiscalConfigurationController::class, 'regenerateInvoice'])
        ->name('settings.fiscal.invoices.regenerate');
    Route::delete('/settings/fiscal/notas/{notaFiscal}', [FiscalConfigurationController::class, 'destroyInvoice'])
        ->name('settings.fiscal.invoices.destroy');
    Route::get('/settings/fiscal/notas/{notaFiscal}/xml', [FiscalConfigurationController::class, 'downloadXml'])
        ->name('settings.fiscal.invoices.xml');
    Route::post('/settings/fiscal/notas/{notaFiscal}/transmit', [FiscalConfigurationController::class, 'transmit'])
        ->name('settings.fiscal.invoices.transmit');
    Route::get('/settings/contra-cheque', [PayrollController::class, 'contraCheque'])
        ->name('settings.contra-cheque');
    Route::post('/settings/contra-cheque/{user}/creditos', [PayrollController::class, 'storeContraChequeCredit'])
        ->name('settings.contra-cheque.creditos.store');
    Route::get('/settings/avisos', [NoticeController::class, 'index'])
        ->name('settings.notices');
    Route::post('/settings/avisos', [NoticeController::class, 'store'])
        ->name('settings.notices.store');
    Route::get('/settings/suppliers', [SupplierController::class, 'index'])
        ->name('settings.suppliers');
    Route::post('/settings/suppliers', [SupplierController::class, 'store'])
        ->name('settings.suppliers.store');
    Route::put('/settings/suppliers/{supplier}/dispute', [SupplierController::class, 'toggleDispute'])
        ->name('settings.suppliers.toggle-dispute');
    Route::get('/settings/suppliers/{supplier}/disputes', [SupplierController::class, 'showDisputes'])
        ->name('settings.suppliers.disputes');
    Route::get('/settings/anydesck', [AnyDesckController::class, 'index'])
        ->name('settings.anydesck');
    Route::post('/settings/anydesck', [AnyDesckController::class, 'store'])
        ->name('settings.anydesck.store');
    Route::put('/settings/anydesck/{anydesck}', [AnyDesckController::class, 'update'])
        ->name('settings.anydesck.update');
    Route::get('/settings/sales-disputes', [SalesDisputeController::class, 'index'])
        ->name('settings.sales-disputes');
    Route::post('/settings/sales-disputes', [SalesDisputeController::class, 'store'])
        ->name('settings.sales-disputes.store');
    Route::delete('/settings/sales-disputes/{salesDispute}', [SalesDisputeController::class, 'destroy'])
        ->name('settings.sales-disputes.destroy');
    Route::delete('/settings/sales-disputes/bids/{bid}', [SalesDisputeController::class, 'destroyBid'])
        ->name('settings.sales-disputes.bids.destroy');
    Route::put('/settings/sales-disputes/bids/{bid}/approve', [SalesDisputeController::class, 'approveBid'])
        ->name('settings.sales-disputes.bids.approve');
    Route::get('/settings/sales-disputes/bids/{bid}/invoice', [SalesDisputeController::class, 'downloadInvoice'])
        ->name('settings.sales-disputes.bids.invoice');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/show-user/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/create-user', [UserController::class, 'create'])->name('users.create');
    route::post('/store-user', [UserController::class, 'store'])->name('users.store');
    Route::get('/edit-user/{user}', [UserController::class, 'edit'])->name('users.edit');
    route::put('/update-user/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    route::delete('/destroy-user/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::patch('/units/{unit}/fiscal-generation', [UnitController::class, 'toggleFiscalGeneration'])->name('units.fiscal-generation.toggle');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    Route::get('/products/discard', [ProductDiscardController::class, 'index'])->name('products.discard');
    Route::post('/products/discard', [ProductDiscardController::class, 'store'])->name('products.discard.store');
    Route::get('/products/fiscal-queue', [ProductController::class, 'fiscalQueue'])->name('products.fiscal-queue');
    Route::post('/products/fiscal-queue/apply-reference', [ProductController::class, 'applyFiscalReference'])->name('products.fiscal-queue.apply-reference');
    Route::get('/products/fiscal-references', [FiscalReferenceController::class, 'index'])->name('products.fiscal-references.index');
    Route::post('/products/fiscal-references', [FiscalReferenceController::class, 'store'])->name('products.fiscal-references.store');
    Route::get('/products/production-stock', [ProductStockController::class, 'index'])->name('products.production-stock');
    Route::post('/products/production-stock', [ProductStockController::class, 'store'])->name('products.production-stock.store');
    Route::get('/products/quick-lookup', [ProductController::class, 'quickLookup'])->name('products.quick-lookup');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/favorites', [ProductController::class, 'favorites'])->name('products.favorites');
    Route::post('/products/{product}/favorite', [ProductController::class, 'toggleFavorite'])->name('products.favorite');
    Route::resource('products', ProductController::class);
    Route::get('/sales/open-comandas', [SaleController::class, 'openComandas'])->name('sales.open-comandas');
    Route::get('/sales/restrictions', [SaleController::class, 'restrictions'])->name('sales.restrictions');
    Route::get('/sales/dashboard-status', [SaleController::class, 'dashboardStatus'])->name('sales.dashboard-status');
    Route::get('/sales/comandas/{codigo}/items', [SaleController::class, 'comandaItems'])->name('sales.comandas.items');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::post('/sales/fiscal/{notaFiscal}/transmit', [SaleController::class, 'transmitFiscalInvoice'])->name('sales.fiscal.transmit');
    Route::post('/sales/fiscal/{notaFiscal}/consumer', [SaleController::class, 'updateConsumerFiscalInvoice'])->name('sales.fiscal.consumer');
    Route::post('/sales/comandas/{codigo}/items', [SaleController::class, 'addComandaItem'])->name('sales.comandas.add-item');
    Route::put('/sales/comandas/{codigo}/items/{productId}', [SaleController::class, 'updateComandaItem'])->name('sales.comandas.update-item');
    Route::get('/reports', [SalesReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/sales-today', [SalesReportController::class, 'today'])->name('reports.sales.today');
    Route::delete('/reports/sales-today/{payment}', [SalesReportController::class, 'destroyTodayReceipt'])->name('reports.sales.today.destroy');
    Route::get('/reports/sales-period', [SalesReportController::class, 'period'])->name('reports.sales.period');
    Route::get('/reports/sales-detailed', [SalesReportController::class, 'detailed'])->name('reports.sales.detailed');
    Route::get('/reports/lanchonete', [SalesReportController::class, 'lanchonete'])->name('reports.lanchonete');
    Route::get('/reports/comandas-em-aberto', [SalesReportController::class, 'comandasEmAberto'])->name('reports.comandas-aberto');
    Route::get('/reports/vale', [SalesReportController::class, 'vale'])->name('reports.vale');
    Route::get('/reports/refeicao', [SalesReportController::class, 'refeicao'])->name('reports.refeicao');
    Route::get('/reports/faturar', [SalesReportController::class, 'faturar'])->name('reports.faturar');
    Route::get('/reports/notas-fiscais-emitidas', [SalesReportController::class, 'notasFiscaisEmitidas'])->name('reports.notas-fiscais-emitidas');
    Route::get('/reports/adiantamentos', [SalesReportController::class, 'adiantamentos'])->name('reports.adiantamentos');
    Route::get('/reports/fornecedores', [SalesReportController::class, 'fornecedores'])->name('reports.fornecedores');
    Route::get('/reports/gastos', [SalesReportController::class, 'gastos'])->name('reports.gastos');
    Route::get('/reports/descarte', [SalesReportController::class, 'descarte'])->name('reports.descarte');
    Route::get('/reports/descarte-consolidado', [SalesReportController::class, 'descarteConsolidado'])->name('reports.descarte.consolidado');
    Route::get('/reports/pdr-cache', [SalesReportController::class, 'pdrCache'])->name('reports.pdr-cache');
    Route::delete('/reports/descarte/{discard}', [ProductDiscardController::class, 'destroy'])->name('reports.descarte.destroy');
    Route::get('/reports/hoje', [SalesReportController::class, 'hoje'])->name('reports.hoje');
    Route::get('/reports/cash-closure', [SalesReportController::class, 'cashClosure'])->name('reports.cash.closure');
    Route::patch('/reports/cash-closure/{closure}/master-review', [SalesReportController::class, 'updateCashClosureMasterReview'])->name('reports.cash.closure.master-review');
    Route::get('/reports/cash-discrepancies', [SalesReportController::class, 'cashDiscrepancies'])->name('reports.cash.discrepancies');
    Route::get('/reports/control', [SalesReportController::class, 'control'])->name('reports.control');
    Route::get('/reports/switch-unit', [UnitSwitchController::class, 'index'])->name('reports.switch-unit');
    Route::post('/reports/switch-unit', [UnitSwitchController::class, 'update'])->name('reports.switch-unit.update');
    Route::get('/reports/switch-role', [RoleSwitchController::class, 'index'])->name('reports.switch-role');
    Route::post('/reports/switch-role', [RoleSwitchController::class, 'update'])->name('reports.switch-role.update');
    Route::get('/salary-advances', [SalaryAdvanceController::class, 'index'])->name('salary-advances.index');
    Route::get('/salary-advances/create', [SalaryAdvanceController::class, 'create'])->name('salary-advances.create');
    Route::get('/salary-advances/{salaryAdvance}/edit', [SalaryAdvanceController::class, 'edit'])->name('salary-advances.edit');
    Route::post('/salary-advances', [SalaryAdvanceController::class, 'store'])->name('salary-advances.store');
    Route::put('/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'update'])->name('salary-advances.update');
    Route::delete('/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'destroy'])->name('salary-advances.destroy');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::get('/boletos', [BoletoController::class, 'index'])->name('boletos.index');
    Route::post('/boletos', [BoletoController::class, 'store'])->name('boletos.store');
    Route::put('/boletos/{boleto}', [BoletoController::class, 'update'])->name('boletos.update');
    Route::put('/boletos/{boleto}/pay', [BoletoController::class, 'pay'])->name('boletos.pay');
    Route::get('/cashier/close', [CashierClosureController::class, 'index'])->name('cashier.close');
    Route::post('/cashier/close', [CashierClosureController::class, 'store'])->name('cashier.close.store');

    Route::get('/lanchonete/terminal', [LanchoneteTerminalController::class, 'index'])->name('lanchonete.terminal');
/*  */    Route::post('/lanchonete/terminal/access', [LanchoneteTerminalController::class, 'validateAccess'])->name('lanchonete.terminal.access');
});

require __DIR__.'/auth.php';
