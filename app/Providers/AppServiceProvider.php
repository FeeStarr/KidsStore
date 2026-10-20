<?php

namespace App\Providers;

use App\Database\SafeMysqlConnection;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\CartService;
use App\Services\Contracts\InventoryServiceInterface;
use App\Services\ImageOptimizationService;
use App\Services\InventoryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(ImageOptimizationService::class);
    }

    /**
     * Stop a blocked statement from hanging the test suite indefinitely.
     *
     * MariaDB's lock_wait_timeout defaults to 86400 seconds, and it covers metadata
     * locks. A DROP or ALTER that cannot take the lock therefore waits a day rather
     * than failing, which wedges the whole server: every later test then fails on a
     * dead connection, and the next run's migrate:fresh cannot get past the same
     * lock.
     *
     * A short timeout turns that into an ordinary, readable failure.
     */
    private function boundTestLockTimeouts(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        try {
            DB::statement('SET SESSION lock_wait_timeout = 15');
            DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
        } catch (\Throwable) {
            // No database yet, or the server is not reachable. Neither is a reason
            // to stop the application from booting.
        }
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->boundTestLockTimeouts();

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Log all authentication events (login, logout, failed, lockout)
        \Illuminate\Support\Facades\Event::listen(
            [\Illuminate\Auth\Events\Login::class, \Illuminate\Auth\Events\Logout::class,
             \Illuminate\Auth\Events\Failed::class, \Illuminate\Auth\Events\Lockout::class],
            \App\Listeners\LogAuthEvent::class
        );

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
            return new SafeMysqlConnection(
                $connection, $database, $prefix, $config
            );
        });

        View::composer('shop.*', function ($view) {
            $cart = app(CartService::class);
            $view->with('cartCount', $cart->count());
        });
    }
}
