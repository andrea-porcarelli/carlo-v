<?php

use App\Http\Controllers\Backoffice\AllergenController;
use App\Http\Controllers\Backoffice\CategoryController;
use App\Http\Controllers\Backoffice\DishController;
use App\Http\Controllers\Backoffice\InvoiceController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\LoginController;
use App\Http\Controllers\Backoffice\MaterialController;
use App\Http\Controllers\Backoffice\PrinterController;
use App\Http\Controllers\Backoffice\SupplierController;
use App\Http\Controllers\Backoffice\UploadController;
use App\Http\Controllers\Frontoffice\AppController;
use Illuminate\Support\Facades\Route;


Route::get('/',[AppController::class, 'index'])->name('app');

Route::group(['prefix' => '/backoffice'], function() {
    Route::get('/login',[LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login']);

    Route::group(['middleware' => ['auth']], function() {
//        Route::impersonate();

        Route::post('/upload', [UploadController::class, 'start'])->name('upload');
        Route::get('/index', [DashboardController::class, 'index'])->name('dashboard');

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
    });
});
