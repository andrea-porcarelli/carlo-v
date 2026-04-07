<?php

use App\Http\Controllers\Backoffice\AllergenController;
use App\Http\Controllers\Backoffice\ExternalInvoiceController;
use App\Http\Controllers\Backoffice\MenuOptionController;
use App\Http\Controllers\Backoffice\DeployController;
use App\Http\Controllers\Backoffice\CategoryController;
use App\Http\Controllers\Backoffice\DishController;
use App\Http\Controllers\Backoffice\InvoiceController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\LoginController;
use App\Http\Controllers\Backoffice\MaterialController;
use App\Http\Controllers\Backoffice\PrinterController;
use App\Http\Controllers\Backoffice\SalesController;
use App\Http\Controllers\Backoffice\StockController;
use App\Http\Controllers\Backoffice\SettingController;
use App\Http\Controllers\Backoffice\SupplierController;
use App\Http\Controllers\Backoffice\PrintLogController;
use App\Http\Controllers\Backoffice\SyncController;
use App\Http\Controllers\Backoffice\TableOrderLogController;
use App\Http\Controllers\Backoffice\UploadController;
use App\Http\Controllers\Backoffice\UserController;
use App\Http\Controllers\Frontoffice\AppController;
use App\Http\Controllers\Frontoffice\OperatorAuthController;
use App\Http\Controllers\Frontoffice\TableOrderController;
use Illuminate\Support\Facades\Route;


Route::get('/',[AppController::class, 'index'])->name('app');

// ── Deploy webhook — protected by hardcoded key, no auth middleware ────────────
Route::match(['GET', 'POST'], '/webhook/deploy', [DeployController::class, 'trigger'])->name('webhook.deploy');
Route::match(['GET', 'POST'], '/webhook/migrate', [DeployController::class, 'migrate'])->name('webhook.migrate');

// API Routes for Operator Authentication
Route::group(['prefix' => '/api/operators', 'as' => 'api.operators.'], function() {
    Route::get('/', [OperatorAuthController::class, 'getOperators'])->name('list');
    Route::post('/verify-password', [OperatorAuthController::class, 'verifyPassword'])->name('verifyPassword');
    Route::post('/verify-token', [OperatorAuthController::class, 'verifyToken'])->name('verifyToken');
});

// Admin login from frontoffice → redirect to backoffice
Route::post('/api/admin/login-redirect', [OperatorAuthController::class, 'adminLoginRedirect'])->name('api.admin.loginRedirect');

// Operator token for already-authenticated backoffice admin
Route::get('/api/admin/operator-token', [OperatorAuthController::class, 'getAdminToken'])->middleware('auth')->name('api.admin.operatorToken');

// API Routes for Menu Options
Route::get('/api/menu-options', [TableOrderController::class, 'getMenuOptions'])->name('api.menu-options');
Route::get('/api/dishes', [TableOrderController::class, 'getDishes'])->name('api.dishes');
Route::get('/api/banco', [TableOrderController::class, 'getBanco'])->name('api.banco');
Route::post('/api/banco/open', [TableOrderController::class, 'openBanco'])->name('api.banco.open');
Route::get('/api/order/{order}', [TableOrderController::class, 'getOrderDetails'])->name('api.order.details');

// API Routes for Table Management
Route::group(['prefix' => '/api/tables', 'as' => 'api.tables.'], function() {
    Route::get('/', [TableOrderController::class, 'getTables'])->name('index');
    Route::get('/{table}', [TableOrderController::class, 'getTable'])->name('show');
    Route::post('/{table}/open', [TableOrderController::class, 'openTable'])->name('open');
    Route::post('/{table}/items', [TableOrderController::class, 'addItem'])->name('addItem');
    Route::post('/{table}/items-multiple', [TableOrderController::class, 'addMultipleItems'])->name('addMultipleItems');
    Route::put('/items/{item}/quantity', [TableOrderController::class, 'updateItemQuantity'])->name('updateItemQuantity');
    Route::delete('/items/{item}', [TableOrderController::class, 'removeItem'])->name('removeItem');
    Route::post('/{table}/clear', [TableOrderController::class, 'clearTable'])->name('clear');
    Route::post('/{table}/free-amount', [TableOrderController::class, 'freeAmount'])->name('free-amount');
    Route::post('/{table}/pos-charge', [TableOrderController::class, 'posCharge'])->name('posCharge');
    Route::post('/{table}/pay', [TableOrderController::class, 'payTable'])->name('pay');
    Route::post('/{table}/pay-invoice', [TableOrderController::class, 'payTableInvoice'])->name('payInvoice');
    Route::post('/{table}/cancel-autoconsumo', [TableOrderController::class, 'cancelAutoconsumo'])->name('cancelAutoconsumo');
    Route::post('/{table}/marcia', [TableOrderController::class, 'marciaTable'])->name('marcia');
    Route::post('/{table}/preconto', [TableOrderController::class, 'precontoTable'])->name('preconto');
    Route::get('/{table}/preconto-splits', [TableOrderController::class, 'getPrecontoSplits'])->name('precontoSplits');
    Route::post('/{table}/pay-split/{split}', [TableOrderController::class, 'payPrecontoSplit'])->name('payPrecontoSplit');
    Route::delete('/{table}/preconto-splits/{split}', [TableOrderController::class, 'deletePrecontoSplit'])->name('deletePrecontoSplit');
    Route::post('/{table}/apply-discount', [TableOrderController::class, 'applyDiscount'])->name('applyDiscount');
    Route::post('/{table}/reset-discount', [TableOrderController::class, 'resetDiscount'])->name('resetDiscount');
    Route::post('/open-cash-drawer', [TableOrderController::class, 'openCashDrawer'])->name('openCashDrawer');
    Route::post('/cash-drawer/poll', [TableOrderController::class, 'pollCashDrawer'])->name('cashDrawer.poll');
    Route::post('/cash-drawer/cancel', [TableOrderController::class, 'cancelCashDrawer'])->name('cashDrawer.cancel');
    Route::post('/{table}/move', [TableOrderController::class, 'moveTable'])->name('move');
    Route::post('/{table}/reprint', [TableOrderController::class, 'reprintOrder'])->name('reprint');
    Route::post('/{table}/print-session', [TableOrderController::class, 'printSession'])->name('printSession');
    Route::post('/save', [TableOrderController::class, 'saveTable'])->name('save');
    Route::post('/add-batch', [TableOrderController::class, 'addTables'])->name('addBatch');
    Route::delete('/{table}', [TableOrderController::class, 'deleteTable'])->name('delete');
    Route::post('/comunica', [TableOrderController::class, 'comunica'])->name('comunica');
    Route::get('/printers', [TableOrderController::class, 'getPrinters'])->name('printers');
    Route::put('/items/{item}/price', [TableOrderController::class, 'updateItemPrice'])->name('updateItemPrice');
    Route::put('/items/{item}/details', [TableOrderController::class, 'updateItemDetails'])->name('updateItemDetails');
    Route::post('/{table}/add-segue', [TableOrderController::class, 'addSegueItem'])->name('addSegueItem');
    Route::delete('/segue-items/{item}', [TableOrderController::class, 'removeSegueItem'])->name('removeSegueItem');
    Route::put('/items/{item}/dish', [TableOrderController::class, 'updateItemDish'])->name('updateItemDish');
    Route::put('/{table}/covers', [TableOrderController::class, 'updateCovers'])->name('updateCovers');
});

Route::group(['prefix' => '/backoffice'], function() {
    Route::get('/login',[LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login']);

    Route::group(['middleware' => ['auth']], function() {
//        Route::impersonate();
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/index', fn() => redirect()->route('dashboard'));
        Route::get('/daily-stats', [DashboardController::class, 'dailyStats'])->name('dashboard.daily-stats');
        Route::post('/clear-cache', [DashboardController::class, 'clearCache'])->name('dashboard.clear-cache');

        // Remote deploy/migrate proxy (web role only — triggers commands on carlov via carlov_url setting)
        Route::post('/remote-deploy', [DeployController::class, 'remoteTrigger'])->name('backoffice.remote.deploy');
        Route::post('/remote-migrate', [DeployController::class, 'remoteMigrate'])->name('backoffice.remote.migrate');

        Route::post('/upload', [UploadController::class, 'start'])->name('upload');

        // Users Management
        Route::group(['prefix' => '/users', 'as' => 'users.'], function() {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/datatable', [UserController::class, 'datatable'])->name('datatable');
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{id}', [UserController::class, 'show'])->name('show');
            Route::put('/{id}', [UserController::class, 'edit'])->name('edit');
            Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
        });

        Route::group(['prefix' => '/suppliers'], function() {
            Route::get('/datatable', [SupplierController::class, 'datatable'])->name('suppliers.datatable');
        });
        Route::resource('suppliers', SupplierController::class);

        Route::group(['prefix' => '/invoices'], function() {
            Route::get('/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
            Route::get('/import', [InvoiceController::class, 'import_form'])->name('invoices.import');
            Route::post('/import', [InvoiceController::class, 'import_invoice']);
            Route::get('/{id}/mapping-products', [InvoiceController::class, 'mapping_products'])->name('invoices.mapping_products');
            Route::post('/{id}/store-mapping-products', [InvoiceController::class, 'store_mapping_products']);
            Route::get('/failed/{id}/download', [InvoiceController::class, 'download_failed_file'])->name('invoices.download-failed-file');
        });

        Route::resource('invoices', InvoiceController::class);

        Route::group(['prefix' => '/restaurant', 'as' => 'restaurant.'], function() {
            Route::group(['prefix' => '/sales', 'as' => 'sales.'], function() {
                Route::get('/', [SalesController::class, 'index'])->name('index');
                Route::get('/datatable', [SalesController::class, 'datatable'])->name('datatable');
                Route::get('/tables', [SalesController::class, 'tables'])->name('tables');
                Route::get('/datatable-tables', [SalesController::class, 'datatable_tables'])->name('datatable.tables');
                Route::get('/{id}', [SalesController::class, 'show'])->name('show');
                Route::post('/export', [SalesController::class, 'export'])->name('export');
            });
            Route::group(['prefix' => '/printers', 'as' => 'printers.'], function() {
                Route::get('/', [PrinterController::class, 'index'])->name('index');
                Route::get('/datatable', [PrinterController::class, 'datatable'])->name('datatable');
                Route::get('/create', [PrinterController::class, 'create'])->name('create');
                Route::post('/', [PrinterController::class, 'store']);
                Route::get('/{id}', [PrinterController::class, 'show'])->name('show');
                Route::put('/{id}', [PrinterController::class, 'edit']);
                Route::put('/{id}/status', [PrinterController::class, 'status']);
            });
            Route::group(['prefix' => '/categories', 'as' => 'categories.'], function() {
                Route::get('/', [CategoryController::class, 'index'])->name('index');
                Route::get('/datatable', [CategoryController::class, 'datatable'])->name('datatable');
                Route::get('/create', [CategoryController::class, 'create'])->name('create');
                Route::post('/', [CategoryController::class, 'store']);
                Route::put('/reorder', [CategoryController::class, 'reorder'])->name('reorder');
                Route::get('/{id}', [CategoryController::class, 'show'])->name('show');
                Route::put('/{id}', [CategoryController::class, 'edit']);
                Route::put('/{id}/status', [CategoryController::class, 'status']);
            });
            Route::group(['prefix' => '/materials', 'as' => 'materials.'], function() {
                Route::get('/', [MaterialController::class, 'index'])->name('index');
                Route::get('/datatable', [MaterialController::class, 'datatable'])->name('datatable');
                Route::get('/create', [MaterialController::class, 'create'])->name('create');
                Route::post('/', [MaterialController::class, 'store']);
                Route::get('/{id}', [MaterialController::class, 'show'])->name('show');
                Route::put('/{id}', [MaterialController::class, 'edit']);
                Route::post('/{id}/stock', [MaterialController::class, 'storeStock'])->name('store-stock');
            });
            Route::group(['prefix' => '/stock', 'as' => 'stock.'], function() {
                Route::get('/', [StockController::class, 'index'])->name('index');
                Route::post('/{material}/threshold', [StockController::class, 'updateThreshold'])->name('update-threshold');
            });
            Route::group(['prefix' => '/settings', 'as' => 'settings.'], function() {
                Route::get('/', [SettingController::class, 'index'])->name('index');
                Route::post('/', [SettingController::class, 'store']);
            });
            Route::group(['prefix' => '/allergens', 'as' => 'allergens.'], function() {
                Route::get('/', [AllergenController::class, 'index'])->name('index');
                Route::get('/datatable', [AllergenController::class, 'datatable'])->name('datatable');
                Route::get('/create', [AllergenController::class, 'create'])->name('create');
                Route::post('/', [AllergenController::class, 'store']);
                Route::get('/{id}', [AllergenController::class, 'show'])->name('show');
                Route::put('/{id}', [AllergenController::class, 'edit']);
            });
            Route::group(['prefix' => '/menu-options', 'as' => 'menu-options.'], function() {
                Route::get('/', [MenuOptionController::class, 'index'])->name('index');
                Route::get('/datatable', [MenuOptionController::class, 'datatable'])->name('datatable');
                Route::get('/create', [MenuOptionController::class, 'create'])->name('create');
                Route::post('/', [MenuOptionController::class, 'store']);
                Route::get('/{id}', [MenuOptionController::class, 'show'])->name('show');
                Route::put('/{id}', [MenuOptionController::class, 'edit']);
                Route::put('/{id}/status', [MenuOptionController::class, 'status']);
            });
            Route::group(['prefix' => '/dishes', 'as' => 'dishes.'], function() {
                Route::get('/', [DishController::class, 'index'])->name('index');
                Route::get('/datatable', [DishController::class, 'datatable'])->name('datatable');
                Route::get('/create', [DishController::class, 'create'])->name('create');
                Route::post('/', [DishController::class, 'store']);
                Route::get('/{id}', [DishController::class, 'show'])->name('show');
                Route::put('/{id}', [DishController::class, 'edit']);
                Route::put('/{id}/status', [DishController::class, 'status']);
            });
        });

        // Sync
        Route::post('/sync/trigger', [SyncController::class, 'trigger'])->name('backoffice.sync.trigger');
        Route::get('/sync/status', [SyncController::class, 'status'])->name('backoffice.sync.status');

        // Table Order Logs
        Route::group(['prefix' => '/logs', 'as' => 'backoffice.logs.'], function() {
            Route::get('/table-orders', [TableOrderLogController::class, 'index'])->name('table-orders');
            Route::get('/table-order/{tableOrder}', [TableOrderLogController::class, 'show'])->name('table-order');
            Route::get('/table-order/{tableOrder}/prints', [PrintLogController::class, 'index'])->name('print-logs');
            Route::get('/print/{printLog}/preview', [PrintLogController::class, 'preview'])->name('print-preview');
            Route::post('/print/{printLog}/reprint', [PrintLogController::class, 'reprint'])->name('print-reprint');
            Route::post('/print-history', [PrintLogController::class, 'printHistory'])->name('print-history');
            Route::post('/print-logs-filtered', [PrintLogController::class, 'printLogsFiltered'])->name('print-logs-filtered');
            Route::get('/user/{user}', [TableOrderLogController::class, 'userLogs'])->name('user');
            Route::get('/export', [TableOrderLogController::class, 'export'])->name('export');
            Route::get('/activity-summary', [TableOrderLogController::class, 'activitySummary'])->name('activity-summary');
            Route::get('/category-stats', [TableOrderLogController::class, 'categoryStats'])->name('category-stats');
        });



        Route::prefix('external-invoices')->group(function () {
            Route::get('/', [ExternalInvoiceController::class, 'index'])->name('external-invoices.index');
            Route::get('/datatable', [ExternalInvoiceController::class, 'datatable'])->name('external-invoices.datatable');
            Route::get('/{id}/mapping', [ExternalInvoiceController::class, 'mapping'])->name('external-invoices.mapping');
            Route::post('/{id}/mapping', [ExternalInvoiceController::class, 'store_mapping'])->name('external-invoices.store-mapping');
            Route::get('/{id}/confirm-stock', [ExternalInvoiceController::class, 'confirm_stock'])->name('external-invoices.confirm-stock');
            Route::post('/{id}/confirm-stock', [ExternalInvoiceController::class, 'store_stock'])->name('external-invoices.store-stock');
        });
    });
});
