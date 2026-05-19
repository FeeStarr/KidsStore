<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\Contracts\InventoryServiceInterface;
use App\Services\InventoryService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->singleton(CartService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('shop.*', function ($view) {
            $cart = app(CartService::class);
            $view->with('cartCount', $cart->count());
        });
    }
}
