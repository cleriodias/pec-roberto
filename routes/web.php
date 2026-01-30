<?php

use App\Http\Controllers\BoletoController;
use App\Http\Controllers\CashierClosureController;
use App\Http\Controllers\DatabaseToolsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDiscardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LanchoneteTerminalController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesDisputeController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPortalController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitSwitchController;
use App\Http\Controllers\UserController;
use App\Models\Unidade;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'units' => Unidade::orderBy('tb2_nome')->get([
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


Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/supplier/access', [SupplierPortalController::class, 'access'])->name('supplier.access');
Route::post('/supplier/access', [SupplierPortalController::class, 'authenticate'])->name('supplier.authenticate');
Route::post('/supplier/logout', [SupplierPortalController::class, 'logout'])->name('supplier.logout');
Route::get('/supplier/disputes', [SupplierPortalController::class, 'disputes'])->name('supplier.disputes');
Route::put('/supplier/disputes/{bid}', [SupplierPortalController::class, 'updateBid'])->name('supplier.disputes.update');
Route::post('/supplier/disputes/{bid}/invoice', [SupplierPortalController::class, 'invoice'])->name('supplier.disputes.invoice');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/settings', function () {
        $user = auth()->user();
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao);
        if (! $user || $roleOriginal !== 0) {
            abort(403);
        }

        return Inertia::render('Settings/Config');
    })->name('settings.config');
    Route::get('/settings/menu', function () {
        $user = auth()->user();
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao);
        if (! $user || $roleOriginal !== 0) {
            abort(403);
        }

        return Inertia::render('Settings/Menu');
    })->name('settings.menu');
    Route::get('/settings/profile-access', function () {
        $user = auth()->user();
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao);
        if (! $user || $roleOriginal !== 0) {
            abort(403);
        }

        return Inertia::render('Settings/ProfileAccess');
    })->name('settings.profile-access');
    Route::get('/settings/menu-order', function () {
        $user = auth()->user();
        $roleOriginal = (int) ($user?->funcao_original ?? $user?->funcao);
        if (! $user || $roleOriginal !== 0) {
            abort(403);
        }

        return Inertia::render('Settings/MenuOrder');
    })->name('settings.menu-order');
    Route::get('/settings/database', [DatabaseToolsController::class, 'index'])
        ->name('settings.database');
    Route::post('/settings/database', [DatabaseToolsController::class, 'run'])
        ->name('settings.database.run');
    Route::get('/settings/avisos', [NoticeController::class, 'index'])
        ->name('settings.notices');
    Route::post('/settings/avisos', [NoticeController::class, 'store'])
        ->name('settings.notices.store');
    Route::get('/settings/suppliers', [SupplierController::class, 'index'])
        ->name('settings.suppliers');
    Route::post('/settings/suppliers', [SupplierController::class, 'store'])
        ->name('settings.suppliers.store');
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
    route::delete('/destroy-user/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    Route::get('/products/discard', [ProductDiscardController::class, 'index'])->name('products.discard');
    Route::post('/products/discard', [ProductDiscardController::class, 'store'])->name('products.discard.store');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/favorites', [ProductController::class, 'favorites'])->name('products.favorites');
    Route::post('/products/{product}/favorite', [ProductController::class, 'toggleFavorite'])->name('products.favorite');
    Route::resource('products', ProductController::class);
    Route::get('/sales/open-comandas', [SaleController::class, 'openComandas'])->name('sales.open-comandas');
    Route::get('/sales/restrictions', [SaleController::class, 'restrictions'])->name('sales.restrictions');
    Route::get('/sales/comandas/{codigo}/items', [SaleController::class, 'comandaItems'])->name('sales.comandas.items');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::post('/sales/comandas/{codigo}/items', [SaleController::class, 'addComandaItem'])->name('sales.comandas.add-item');
    Route::put('/sales/comandas/{codigo}/items/{productId}', [SaleController::class, 'updateComandaItem'])->name('sales.comandas.update-item');
    Route::get('/reports', [SalesReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/sales-today', [SalesReportController::class, 'today'])->name('reports.sales.today');
    Route::get('/reports/sales-period', [SalesReportController::class, 'period'])->name('reports.sales.period');
    Route::get('/reports/sales-detailed', [SalesReportController::class, 'detailed'])->name('reports.sales.detailed');
    Route::get('/reports/lanchonete', [SalesReportController::class, 'lanchonete'])->name('reports.lanchonete');
    Route::get('/reports/vale', [SalesReportController::class, 'vale'])->name('reports.vale');
    Route::get('/reports/refeicao', [SalesReportController::class, 'refeicao'])->name('reports.refeicao');
    Route::get('/reports/adiantamentos', [SalesReportController::class, 'adiantamentos'])->name('reports.adiantamentos');
    Route::get('/reports/fornecedores', [SalesReportController::class, 'fornecedores'])->name('reports.fornecedores');
    Route::get('/reports/gastos', [SalesReportController::class, 'gastos'])->name('reports.gastos');
    Route::get('/reports/descarte', [SalesReportController::class, 'descarte'])->name('reports.descarte');
    Route::get('/reports/cash-closure', [SalesReportController::class, 'cashClosure'])->name('reports.cash.closure');
    Route::get('/reports/cash-discrepancies', [SalesReportController::class, 'cashDiscrepancies'])->name('reports.cash.discrepancies');
    Route::get('/reports/control', [SalesReportController::class, 'control'])->name('reports.control');
    Route::get('/reports/switch-unit', [UnitSwitchController::class, 'index'])->name('reports.switch-unit');
    Route::post('/reports/switch-unit', [UnitSwitchController::class, 'update'])->name('reports.switch-unit.update');
    Route::get('/reports/switch-role', [RoleSwitchController::class, 'index'])->name('reports.switch-role');
    Route::post('/reports/switch-role', [RoleSwitchController::class, 'update'])->name('reports.switch-role.update');
    Route::get('/salary-advances', [SalaryAdvanceController::class, 'index'])->name('salary-advances.index');
    Route::get('/salary-advances/create', [SalaryAdvanceController::class, 'create'])->name('salary-advances.create');
    Route::post('/salary-advances', [SalaryAdvanceController::class, 'store'])->name('salary-advances.store');
    Route::delete('/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'destroy'])->name('salary-advances.destroy');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::get('/boletos', [BoletoController::class, 'index'])->name('boletos.index');
    Route::post('/boletos', [BoletoController::class, 'store'])->name('boletos.store');
    Route::put('/boletos/{boleto}/pay', [BoletoController::class, 'pay'])->name('boletos.pay');
    Route::get('/cashier/close', [CashierClosureController::class, 'index'])->name('cashier.close');
    Route::post('/cashier/close', [CashierClosureController::class, 'store'])->name('cashier.close.store');

    Route::get('/lanchonete/terminal', [LanchoneteTerminalController::class, 'index'])->name('lanchonete.terminal');
/*  */    Route::post('/lanchonete/terminal/access', [LanchoneteTerminalController::class, 'validateAccess'])->name('lanchonete.terminal.access');
});

require __DIR__.'/auth.php';
