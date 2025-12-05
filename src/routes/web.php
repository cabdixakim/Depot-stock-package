<?php

use Illuminate\Support\Facades\Route;

use Optima\DepotStock\Http\Controllers\DashboardController;
use Optima\DepotStock\Http\Controllers\DipController;
use Optima\DepotStock\Http\Controllers\ClientController;
use Optima\DepotStock\Http\Controllers\OffloadController;
use Optima\DepotStock\Http\Controllers\LoadController;
use Optima\DepotStock\Http\Controllers\AdjustmentController;
use Optima\DepotStock\Http\Controllers\InvoiceController;
use Optima\DepotStock\Http\Controllers\PaymentController;
use Optima\DepotStock\Http\Controllers\MovementsController;
use Optima\DepotStock\Http\Controllers\DepotPoolController;
use Optima\DepotStock\Http\Controllers\ClientExportController;
use Optima\DepotStock\Http\Controllers\BillingWaitingController;
use Optima\DepotStock\Http\Controllers\ClientCreditController;

use Optima\DepotStock\Http\Controllers\Settings\ProfitMarginController;
use Optima\DepotStock\Http\Controllers\Settings\UserController;

use Optima\DepotStock\Http\Controllers\Auth\PackageAuthController;
use Optima\DepotStock\Http\Controllers\StatementController;
use Optima\DepotStock\Http\Controllers\DepotController;
use Optima\DepotStock\Http\Controllers\TankController;
use Optima\DepotStock\Http\Controllers\Settings\AccountController;
use Optima\DepotStock\Http\Controllers\Portal\ClientPortalController;

// ✅ NEW: Depot operations controllers
use Optima\DepotStock\Http\Controllers\DepotOperationsController;
use Optima\DepotStock\Http\Controllers\DepotReconController;
use Optima\DepotStock\Http\Controllers\OperationsClientController;

// =========================================================
// STAFF AREA (/depot/...)
// =========================================================
Route::middleware(['web', 'auth'])
    ->prefix('depot')
    ->name('depot.')
    ->group(function () {

        // ---------------- Dashboard ----------------
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ---------------- Clients CRUD ----------------
        Route::get('/clients',            [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients',           [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}',   [ClientController::class, 'show'])->name('clients.show');
        Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}',[ClientController::class, 'destroy'])->name('clients.destroy');

        // ---------------- Movements (offloads/loads/adjustments combined UI) ----------------
        Route::get('/clients/{client}/movements/data',      [MovementsController::class, 'data'])->name('clients.movements.data');
        Route::post('/clients/{client}/movements/save',     [MovementsController::class, 'save'])->name('clients.movements.save');
        Route::delete(
            '/clients/{client}/movements/{kind}/{id}',
            [MovementsController::class, 'destroy']
        )->name('clients.movements.destroy');

        // ---------------- Offloads / Loads / Adjustments (single create) ----------------
        Route::post('/clients/{client}/offloads',    [OffloadController::class,    'store'])->name('clients.offloads.store');
        Route::post('/clients/{client}/loads',       [LoadController::class,       'store'])->name('clients.loads.store');
        Route::post('/clients/{client}/adjustments', [AdjustmentController::class, 'store'])->name('clients.adjustments.store');

        // Bulk updates
        Route::patch('/clients/{client}/offloads/bulk', [OffloadController::class, 'bulkUpdate'])->name('clients.offloads.bulkUpdate');
        Route::patch('/clients/{client}/loads/bulk',    [LoadController::class,   'bulkUpdate'])->name('clients.loads.bulkUpdate');

        // =================================================================
        // BILLING & MONEY (admin + accountant only)
        // =================================================================
        Route::middleware('role:admin,accountant')->group(function () {

            // 🔒 Client lock / block toggles (fixed to use lock() method)
            Route::post('/clients/{client}/lock', [ClientController::class, 'updatelock'])->name('clients.lock');
             // 💾 Storage charges (idle stock)
            Route::post('/clients/{client}/storage-charge', [ClientController::class, 'storeStorageCharge'])->name('clients.storage.charge');

            // 🧮 Storage fee preview & charge (idle stock)
           // Storage charges for idle stock
            Route::post('/clients/{client}/storage/charge', [ClientController::class, 'storeStorageCharge'])->name('clients.storage.charge');
            Route::post('/clients/{client}/storage/extend', [ClientController::class, 'extendStorageGrace'])->name('clients.storage.extend');

            // ---------------- Invoices ----------------
            Route::get('/invoices',           [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

            // Apply credit
            Route::post(
                '/invoices/{invoice}/apply-credit',
                [InvoiceController::class, 'applyCredit']
            )->name('invoices.apply_credit');

            // ---------------- Payments (global) ----------------
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments',[PaymentController::class, 'store'])->name('payments.store');

            // ---------------- Client-level Statements, Exports, Billing ----------------
            Route::prefix('clients/{client}')
                ->name('clients.')
                ->group(function () {

                    Route::get('/invoices', [ClientCreditController::class, 'index'])->name('invoices.index');
                    // Statements
                    Route::get('statement',        [StatementController::class,       'index'])->name('statement');
                    Route::get('statement/data',   [StatementController::class,       'data'])->name('statement.data');
                    Route::get('statement/export', [StatementController::class,       'export'])->name('statement.export');

                    // Staff exports (offloads / loads / adjustments / payments)
                    Route::get('offloads/export',    [ClientExportController::class, 'exportOffloads'])->name('offloads.export');
                    Route::get('loads/export',       [ClientExportController::class, 'exportLoads'])->name('loads.export');
                    Route::get('adjustments/export', [ClientExportController::class, 'exportAdjustments'])->name('adjustments.export');
                    Route::get('payments',           [ClientExportController::class, 'paymentsIndex'])->name('payments.index');

                    // Billing waiting
                    Route::get('billing/waiting',      [BillingWaitingController::class, 'index'])->name('billing.waiting');
                    Route::get('billing/waiting/data', [BillingWaitingController::class, 'data'])->name('billing.waiting.data');
                });
        });

        // =================================================================
        // DEPOT OPERATIONS SHELL (Dashboard + Daily dips + Simple history + Ops clients)
        // =================================================================
        Route::prefix('operations')->name('operations.')->group(function () {
            // Main shell / dashboard
            Route::get('/', [DepotOperationsController::class, 'index'])
                ->name('index');

            // Daily dips (new reconciliation engine)
            Route::get('/daily-dips', [DepotReconController::class, 'index'])
                ->name('daily-dips');

            // Save opening / closing dips for a depot + date (current tank is passed in form)
            Route::post('/daily-dips/{depot}/{date}/opening', [DepotReconController::class, 'storeOpening'])
                ->name('daily-dips.store-opening');

            Route::post('/daily-dips/{depot}/{date}/closing', [DepotReconController::class, 'storeClosing'])
                ->name('daily-dips.store-closing');
            
            Route::post('/daily-dips/{depot}/{date}/lock', [DepotReconController::class, 'lockDay'])
                ->name('daily-dips.lock');

            Route::get('/operations/dips-history', [DepotOperationsController::class, 'dipsHistory'])
             ->name('dips-history');
            // Operations client list (lean)
            Route::get('/clients', [OperationsClientController::class, 'index'])
                ->name('clients.index');
        });

        // ---------------- Dips (Daily tank dips per depot) ----------------
        Route::get('/dips',            [DipController::class, 'index'])->name('dips.index');
        Route::post('/dips',           [DipController::class, 'store'])->name('dips.store');
        Route::get('/dips/export',     [DipController::class, 'export'])->name('dips.export');
        Route::get('/dips/{dip}',      [DipController::class, 'show'])->name('dips.show');

        // =================================================================
        // DEPOT POOL – admin + accountant
        // =================================================================
        Route::middleware('role:admin,accountant')->group(function () {
            Route::get('/pool',           [DepotPoolController::class, 'index'])->name('pool.index');
            Route::post('/pool/transfer', [DepotPoolController::class, 'transfer'])->name('pool.transfer');
            Route::post('/pool/sell',     [DepotPoolController::class, 'sell'])->name('pool.sell');
        });

        // =================================================================
        // ADMIN-ONLY BLOCK (settings, depots, tanks, users)
        // =================================================================
        Route::middleware('role:admin')->group(function () {

            // ---------------- Settings: Profit Margins ----------------
            Route::get('/settings/margins/map',          [ProfitMarginController::class, 'map'])->name('settings.margins.map');
            Route::post('/settings/margins/save',        [ProfitMarginController::class, 'save'])->name('settings.margins.save');
            Route::get('/settings/margins/current',      [ProfitMarginController::class, 'current'])->name('settings.margins.current');
            Route::post('/settings/margins/set-current', [ProfitMarginController::class, 'setCurrent'])->name('settings.margins.setCurrent');

            // ---------------- Settings: Users & Roles ----------------
            Route::get('/settings/users',               [UserController::class, 'index'])->name('settings.users.index');
            Route::post('/settings/users',              [UserController::class, 'create'])->name('settings.users.create');
            Route::post('/settings/users/{user}/roles', [UserController::class, 'updateRoles'])->name('settings.users.roles');
            Route::post('/settings/users/{user}/reset', [UserController::class, 'resetPassword'])->name('settings.users.reset');
            Route::post('/settings/users/{user}/basic', [UserController::class, 'updateBasic'])->name('settings.users.basic');

            // ---------------- Depot policies (global settings) ----------------
            Route::post('/settings/depot-policies', [DepotController::class, 'savePolicies'])
                ->name('policies.save');

            // ---------------- Depots ----------------
            Route::get('/depots',  [DepotController::class, 'index'])->name('depots.index');
            Route::post('/depots', [DepotController::class, 'store'])->name('depots.store');
            Route::patch('/depots/{depot}',              [DepotController::class, 'update'])->name('depots.update');
            Route::delete('/depots/{depot}',             [DepotController::class, 'destroy'])->name('depots.destroy');
            Route::post('/depots/set-active',            [DepotController::class, 'setActive'])->name('depots.setActive');
            Route::post('/depots/{depot}/toggle-status', [DepotController::class, 'toggleStatus'])->name('depots.toggleStatus');

            // ---------------- Tanks ----------------
            Route::post('/tanks',                      [TankController::class, 'store'])->name('tanks.store');
            Route::patch('/tanks/{tank}',              [TankController::class, 'update'])->name('tanks.update');
            Route::post('/tanks/{tank}/toggle-status', [TankController::class, 'toggleStatus'])->name('tanks.toggleStatus');
        });

        // ---------------- Account (self-service) ----------------
        Route::post('/account/profile',  [AccountController::class, 'updateProfile'])->name('account.profile');
        Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

        // ---------------- Logout ----------------
        Route::post('/logout', [PackageAuthController::class, 'logout'])->name('logout');
    });


// =========================================================
// CLIENT PORTAL (/portal/...)
// =========================================================
Route::middleware(['web', 'auth', 'client.portal'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {

        // Home (dashboard)
        Route::get('/', [ClientPortalController::class, 'home'])->name('home');

        // Movements
        Route::get('/movements',        [ClientPortalController::class, 'movements'])->name('movements');
        Route::get('/movements/export', [ClientPortalController::class, 'exportMovements'])->name('movements.export');

        // Statements
        Route::get('/statements',        [ClientPortalController::class, 'statements'])->name('statements');
        Route::get('/statements/print',  [ClientPortalController::class, 'statementPrint'])->name('statements.print');
        Route::get('/statements/export', [ClientPortalController::class, 'statementExport'])->name('statements.export');

        // Invoices list + detail
        Route::get('/invoices',           [ClientPortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{invoice}', [ClientPortalController::class, 'invoiceShow'])->name('invoices.show');

        // Payments
        Route::get('/payments', [ClientPortalController::class, 'payments'])->name('payments');

        // Account (profile + password)
        Route::get('/account',           [ClientPortalController::class, 'account'])->name('account');
        Route::post('/account/profile',  [ClientPortalController::class, 'updateProfile'])->name('account.profile');
        Route::post('/account/password', [ClientPortalController::class, 'updatePassword'])->name('account.password');
    });