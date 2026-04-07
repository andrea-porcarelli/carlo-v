<?php

namespace App\Providers;

use App\Models\ExternalInvoice;
use App\Observers\ExternalInvoiceObserver;
use App\Services\StockService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
