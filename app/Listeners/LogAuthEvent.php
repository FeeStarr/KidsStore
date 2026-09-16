<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogAuthEvent
{
    public function handle(Login|Logout|Failed|Lockout $event): void
    {
        try {
            $guard = match (true) {
                $event instanceof Login  => $event->guard,
                $event instanceof Logout => $event->guard,
                $event instanceof Failed => $event->guard,
                default                  => 'web',
            };

            $user = match (true) {
                $event instanceof Login  => $event->user,
                $event instanceof Logout => $event->user,
                default                  => null,
            };

            $request = request();
            $sessionId = null;
            $ip = null;
            $userAgent = null;
            $route = null;

            try {
                $sessionId = $request->session()->getId();
                $ip = $request->ip();
                $userAgent = $request->userAgent();
                $route = $request->route()?->getName();
            } catch (\Throwable $e) {
                // Request context may not be available in tests
            }

            DB::table('auth_events')->insert([
                'event'       => class_basename($event),
                'guard'       => $guard,
                'user_id'     => $user?->id,
                'role'        => $user?->role ?? null,
                'email_masked' => $user ? $this->maskEmail($user->email) : null,
                'session_id'  => $sessionId,
                'ip'          => $ip,
                'user_agent'  => $userAgent,
                'route'       => $route,
                'meta'        => json_encode(array_filter([
                    'email_attempted' => $event instanceof Failed ? $this->maskEmail($event->credentials['email'] ?? '') : null,
                ])),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Auth event logging failed: ' . $e->getMessage());
        }
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        $name = $parts[0];
        $masked = mb_strlen($name) > 1 ? mb_substr($name, 0, 1) . str_repeat('*', mb_strlen($name) - 1) : '*';
        return $masked . '@' . $parts[1];
    }
}
