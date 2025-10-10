<?php

namespace App\Providers;

use App\Interfaces\MediaInterface;
use App\Interfaces\SupplierInterface;
use App\Interfaces\SupplierInvoiceInterface;
use App\Interfaces\SupplierInvoiceProductInterface;
use App\Interfaces\UserInterface;
use App\Repositories\MediaRepository;
use App\Repositories\SupplierInvoiceRepository;
use App\Repositories\SupplierInvoiceProductRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

#namespace here

use App\Interfaces\AllergenInterface;
use App\Repositories\AllergenRepository;

use App\Interfaces\DishInterface;
use App\Repositories\DishRepository;

use App\Interfaces\CategoryInterface;
use App\Repositories\CategoryRepository;

use App\Interfaces\PrinterInterface;
use App\Repositories\PrinterRepository;

use App\Interfaces\MaterialInterface;
use App\Repositories\MaterialRepository;
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register() : void
    {
        #register here
        $this->app->bind(AllergenInterface::class, AllergenRepository::class);
        $this->app->bind(DishInterface::class, DishRepository::class);
        $this->app->bind(CategoryInterface::class, CategoryRepository::class);
        $this->app->bind(PrinterInterface::class, PrinterRepository::class);
        $this->app->bind(MaterialInterface::class, MaterialRepository::class);
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(SupplierInterface::class, SupplierRepository::class);
        $this->app->bind(SupplierInvoiceInterface::class, SupplierInvoiceRepository::class);
        $this->app->bind(SupplierInvoiceProductInterface::class, SupplierInvoiceProductRepository::class);
        $this->app->bind(MediaInterface::class, MediaRepository::class);

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
