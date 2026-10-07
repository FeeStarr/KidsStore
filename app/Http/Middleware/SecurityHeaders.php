<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Conservative Content-Security-Policy for the storefront/admin.
     *
     * 'unsafe-inline' for scripts is a deliberate compromise: the app relies on
     * extensive inline JS. The policy still blocks every non-allowlisted
     * external script source, which is the realistic XSS vector.
     */
    private const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://datatables.net https://js.paystack.co; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://datatables.net; "
        . "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self' https://api.paystack.co; "
        . "frame-src https://checkout.paystack.com https://iframe.paystack.com https://js.paystack.co; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "form-action 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        return $response;
    }
}
