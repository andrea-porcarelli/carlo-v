<?php

use App\Http\Controllers\Backoffice\AllergenController;
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
use App\Http\Controllers\Backoffice\TableOrderLogController;
use App\Http\Controllers\Backoffice\UploadController;
use App\Http\Controllers\Backoffice\UserController;
use App\Http\Controllers\Frontoffice\AppController;
use App\Http\Controllers\Frontoffice\OperatorAuthController;
use App\Http\Controllers\Frontoffice\TableOrderController;
use Illuminate\Support\Facades\Route;


Route::get('/',[AppController::class, 'index'])->name('app');

// API Routes for Operator Authentication
Route::group(['prefix' => '/api/operators', 'as' => 'api.operators.'], function() {
    Route::get('/', [OperatorAuthController::class, 'getOperators'])->name('list');
    Route::post('/verify-password', [OperatorAuthController::class, 'verifyPassword'])->name('verifyPassword');
    Route::post('/verify-token', [OperatorAuthController::class, 'verifyToken'])->name('verifyToken');
});

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
    Route::post('/{table}/pay', [TableOrderController::class, 'payTable'])->name('pay');
    Route::post('/{table}/marcia', [TableOrderController::class, 'marciaTable'])->name('marcia');
    Route::post('/{table}/preconto', [TableOrderController::class, 'precontoTable'])->name('preconto');
    Route::post('/save', [TableOrderController::class, 'saveTable'])->name('save');
    Route::post('/add-batch', [TableOrderController::class, 'addTables'])->name('addBatch');
    Route::delete('/{table}', [TableOrderController::class, 'deleteTable'])->name('delete');
    Route::post('/comunica', [TableOrderController::class, 'comunica'])->name('comunica');
    Route::get('/printers', [TableOrderController::class, 'getPrinters'])->name('printers');
    Route::put('/items/{item}/price', [TableOrderController::class, 'updateItemPrice'])->name('updateItemPrice');
    Route::put('/{table}/covers', [TableOrderController::class, 'updateCovers'])->name('updateCovers');
});

Route::group(['prefix' => '/backoffice'], function() {
    Route::get('/login',[LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login']);

    Route::group(['middleware' => ['auth']], function() {
//        Route::impersonate();

        Route::post('/upload', [UploadController::class, 'start'])->name('upload');
        Route::get('/index', [DashboardController::class, 'index'])->name('dashboard');

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
    });
});
