<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('payments:check-delayed')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\LogUserActivity::class);

        $middleware->alias([
            'auth.admin'   => \App\Http\Middleware\AuthenticateAdmin::class,
            'auth.portal'  => \App\Http\Middleware\AuthenticatePickupPortal::class,
            'role:customer' => \App\Http\Middleware\EnsureCustomerRole::class,
            'role:admin' => \App\Http\Middleware\EnsureAdminRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('shop.login'));

        // OPay webhook is an unauthenticated server-to-server POST — exempt from CSRF
        $middleware->validateCsrfTokens(except: [
            'opay/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
