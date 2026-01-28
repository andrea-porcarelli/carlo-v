<?php

namespace App\Providers;

use App\Services\StockService;
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
        View::composer('backoffice.components.nav-bar-restaurant', function ($view) {
            $lowStockCount = app(StockService::class)->getLowStockMaterials()->count();
            $view->with('lowStockCount', $lowStockCount);
        });
    }
}
