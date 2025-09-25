<?php

use App\Http\Controllers\Backoffice\SupplierInvoiceController;
use App\Http\Controllers\Backoffice\SupplierOrderController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\LoginController;
use App\Http\Controllers\Backoffice\SupplierController;
use App\Http\Controllers\Frontoffice\AppController;
use Illuminate\Support\Facades\Route;


Route::get('/',[AppController::class, 'index'])->name('app');

Route::group(['prefix' => '/backoffice'], function() {
    Route::get('/login',[LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login']);

    Route::group(['middleware' => ['auth']], function() {
//        Route::impersonate();
        Route::get('/index', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('companies', UserController::class);
        Route::group(['prefix' => '/suppliers'], function() {
            Route::get('/', [SupplierController::class, 'index'])->name('suppliers');
            Route::get('/datatable', [SupplierController::class, 'datatable'])->name('suppliers.datatable');
            Route::get('/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/create', [SupplierController::class, 'store']);
            Route::get('/{id}', [SupplierController::class, 'show'])->name('suppliers.show')->whereNumber('id');
            Route::put('/{id}', [SupplierController::class, 'edit'])->whereNumber('id');

            Route::group(['prefix' => '/orders'], function() {
                Route::get('/', [SupplierOrderController::class, 'index'])->name('suppliers.orders');
            });

            Route::group(['prefix' => '/invoices'], function() {
                Route::get('/', [SupplierInvoiceController::class, 'index'])->name('suppliers.invoices');
            });
        });
    });
});
