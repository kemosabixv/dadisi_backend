<?php

namespace App\Http\Middleware;

use App\Support\AdminAccessResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $key = 'admin_access:'.$user->id;

        // Rate limit (10 attempts per minute)
        if (RateLimiter::tooManyAttempts($key, 60)) {
            Log::warning('Admin rate limit exceeded', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            abort(429, 'Too many admin requests.');
        }

        RateLimiter::hit($key, 60);

        // Authorization check
        if (! AdminAccessResolver::canAccessAdmin($user)) {
            Log::warning('Unauthorized admin access attempt', [
                'user_id' => $user->id,
                'username' => $user->username ?? 'unknown',
                'ip' => $request->ip(),
                'path' => $request->path(),
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [],
            ]);

            abort(403, 'Unauthorized access to admin area.');
        }

        return $next($request);
    }
}
