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
        $schedule->command('payments:expire-pending')->daily();
        $schedule->command('pickups:check-expired')->dailyAt('23:00');
        $schedule->command('pickups:send-reminders')->dailyAt('09:00');
        $schedule->command('returns:check-sla')->dailyAt('08:00');
        $schedule->command('deals:sync-status')->everyFiveMinutes();
        $schedule->command('custom-quotes:check-expiry')->daily();
        $schedule->command('app:backup --db-only')->dailyAt('03:30')->withoutOverlapping();
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

        // Paystack webhook is an unauthenticated server-to-server POST - exempt from CSRF
        $middleware->validateCsrfTokens(except: [
            'paystack/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Convert all exceptions to JSON for API/AJAX requests
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                // Never expose internal details
                $message = match (true) {
                    $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 'Resource not found.',
                    $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException => 'Forbidden.',
                    $e instanceof \Illuminate\Auth\AuthenticationException => 'Unauthenticated.',
                    $e instanceof \Illuminate\Validation\ValidationException => 'Validation failed.',
                    $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 'Too many requests. Please slow down.',
                    $status === 404 => 'Resource not found.',
                    $status === 403 => 'Forbidden.',
                    $status === 405 => 'Method not allowed.',
                    $status === 419 => 'Session expired. Please refresh.',
                    $status === 429 => 'Too many requests. Please slow down.',
                    $status >= 500 => 'Something went wrong. Please try again.',
                    default => 'An error occurred.',
                };

                $response = response()->json(['error' => $message], $status);

                // Include validation errors for 422 responses
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $response = response()->json([
                        'error' => 'Validation failed.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                return $response;
            }
        });

        // Render user-friendly error pages for web requests
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                return response()->view('errors.404', [], 404);
            }
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                return response()->view('errors.403', [], 403);
            }
        });

        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return redirect()->guest(route('shop.login'));
            }
        });

        // 419 Page Expired (CSRF token mismatch)
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Session expired. Please refresh.'], 419);
            }
            return response()->view('errors.419', [], 419);
        });

        // 429 Too Many Requests
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Too many requests. Please slow down.'], 429);
            }
            return response()->view('errors.429', [], 429);
        });

        // Global fallback - log everything, show generic page
        $exceptions->reportable(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });

        // Don't report unauthenticated or not-found to error monitoring
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
        ]);
    })->create();
