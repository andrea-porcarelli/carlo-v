<?php

namespace App\Providers;

use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierInvoiceInterface;
use App\Interfaces\SupplierOrderInterface;
use App\Interfaces\SupplierOrderProductInterface;
use App\Interfaces\UserInterface;
use App\Repositories\SupplierInvoiceRepository;
use App\Repositories\SupplierOrderProductRepository;
use App\Repositories\SupplierOrderRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register() : void
    {
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(SupplierInterface::class, SupplierRepository::class);
        $this->app->bind(SupplierOrderInterface::class, SupplierOrderRepository::class);
        $this->app->bind(SupplierInvoiceInterface::class, SupplierInvoiceRepository::class);
        $this->app->bind(SupplierOrderProductInterface::class, SupplierOrderProductRepository::class);

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
