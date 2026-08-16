<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\CartService;
use App\Services\Contracts\InventoryServiceInterface;
use App\Services\InventoryService;
use Illuminate\Database\Connection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // Superadmin bypasses all authorization checks
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
        });

        Order::observe(OrderObserver::class);

        // Use a guarded connection that refuses destructive queries
        // (TRUNCATE / DROP / unbounded DELETE) outside test environments,
        // so live data can never be wiped by accident.
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new \App\Database\SafeMysqlConnection(
                $connection, $database, $prefix, $config
            );
        });

        View::composer('shop.*', function ($view) {
            $cart = app(CartService::class);
            $view->with('cartCount', $cart->count());
        });
    }
}
