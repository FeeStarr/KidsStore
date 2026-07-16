<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log authenticated users and mutating requests
        if ($request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => $request->method() . ' ' . $request->path(),
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'meta' => json_encode(['input' => $request->except(['_token', 'password', 'password_confirmation'])]),
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // don't break the request on logging errors
                \Illuminate\Support\Facades\Log::error('Audit log failed: ' . $e->getMessage());
            }
        }

        return $response;
    }
}