<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        $corsConfig = config('cors');

        $origin = $request->header('Origin');

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            if ($this->isAllowedOrigin($origin, $corsConfig)) {
                return response()
                    ->noContent()
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Access-Control-Allow-Credentials', 'true')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                    ->header('Access-Control-Allow-Headers', $request->header('Access-Control-Request-Headers', '*'))
                    ->header('Access-Control-Max-Age', '3600');
            }
            return response()->noContent();
        }

        // Handle actual requests
        $response = $next($request);

        if ($this->isAllowedOrigin($origin, $corsConfig)) {
            return $response
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', $request->header('Access-Control-Request-Headers', '*'))
                ->header('Access-Control-Max-Age', '3600');
        }

        return $response;
    }

    protected function isAllowedOrigin(?string $origin, array $corsConfig): bool
    {
        if (!$origin) {
            return true;
        }

        $allowedOrigins = $corsConfig['allowed_origins'] ?? [];
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        foreach ($corsConfig['allowed_origins_patterns'] ?? [] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}
