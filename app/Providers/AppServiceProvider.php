<?php

namespace App\Providers;

use App\Models\ExternalInvoice;
use App\Models\SupplierInvoiceProduct;
use App\Observers\ExternalInvoiceObserver;
use App\Services\StockService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Interfaces\PrinterServiceInterface::class,
            \App\Services\PrinterService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ExternalInvoice::observe(ExternalInvoiceObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600);
        });

        View::composer('backoffice.components.nav-bar-restaurant', function ($view) {
            $lowStockCount = app(StockService::class)->getLowStockMaterials()->count();
            $view->with('lowStockCount', $lowStockCount);
        });

        View::composer('backoffice.components.nav-bar-supplier', function ($view) {
            $lowStockCount = app(StockService::class)->getLowStockMaterials()->count();

            $productsToMapCount = SupplierInvoiceProduct::query()
                ->where('supplier_invoice_products.ignore_mapping', 0)
                ->whereHas('invoice', fn($q) => $q->whereNull('ignored_at'))
                ->whereDoesntHave('material', function ($query) {
                    $query->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                        ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                })
                ->count();

            $productsToImportCount = SupplierInvoiceProduct::query()
                ->where('supplier_invoice_products.ignore_mapping', 0)
                ->whereHas('invoice', fn($q) => $q->whereNull('ignored_at'))
                ->whereHas('material', function ($query) {
                    $query->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                        ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                })
                ->whereDoesntHave('stock')
                ->count();

            $view->with([
                'lowStockCount'         => $lowStockCount,
                'productsToMapCount'    => $productsToMapCount,
                'productsToImportCount' => $productsToImportCount,
            ]);
        });

        Gate::define('viewLogViewer', function ($user = null) {
            if (!$user) {
                return false;
            }
            return $user->role === 'admin';
        });
    }
}
