<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to responses.
 * 
 * Headers included:
 * - Strict-Transport-Security (HSTS): Forces HTTPS
 * - X-Content-Type-Options: Prevents MIME-type sniffing
 * - X-Frame-Options: Prevents clickjacking
 * - X-XSS-Protection: Legacy XSS filter
 * - Referrer-Policy: Controls referrer information
 * - Content-Security-Policy: Controls resource loading
 * - Permissions-Policy: Controls browser features
 */
class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip for non-production environments unless explicitly enabled
        if (!$this->shouldApplyHeaders()) {
            return $response;
        }

        // HSTS - Force HTTPS for 1 year, include subdomains
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking - page cannot be embedded in iframe
        $response->headers->set('X-Frame-Options', 'DENY');

        // Legacy XSS protection (modern browsers use CSP instead)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information sent with requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        // Permissions Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', $this->buildPermissionsPolicy());

        return $response;
    }

    /**
     * Determine if security headers should be applied.
     */
    protected function shouldApplyHeaders(): bool
    {
        // Apply in production and staging
        if (app()->environment('production', 'staging')) {
            return true;
        }

        // Allow forcing headers in development via env
        return config('app.force_security_headers', false);
    }

    /**
     * Build the Content-Security-Policy header value.
     */
    protected function buildCsp(): string
    {
        $frontendUrl = config('app.frontend_url', 'https://dadisilab.com');
        $apiUrl = config('app.url', 'https://api.dadisilab.com');

        $directives = [
            // Default fallback
            "default-src 'self'",

            // Scripts - allow self, inline (needed for Next.js), TinyMCE, and Builder.io
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tiny.cloud https://cdn.builder.io",

            // Styles - allow self, inline (needed for Tailwind), Google Fonts, and TinyMCE
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tiny.cloud",

            // Images - allow self, data URIs, blob, localhost, HTTPS sources, TinyMCE, and R2 storage
            "img-src 'self' data: blob: http://localhost:8000 http://127.0.0.1:8000 https: https://cdn.tiny.cloud https://pub-b8aa4c23b1a44c1e9746f44877e8a888.r2.dev",

            // Fonts - allow self, Google Fonts, and TinyMCE
            "font-src 'self' https://fonts.gstatic.com https://cdn.tiny.cloud",

            // API connections - allow self, API, frontend, localhost, exchange rate API, Sentry, and TinyMCE
            "connect-src 'self' {$apiUrl} {$frontendUrl} http://localhost:8000 http://127.0.0.1:8000 https://api.exchangerate-api.com https://*.ingest.de.sentry.io https://*.ingest.sentry.io https://cdn.tiny.cloud",

            // Workers - allow blob for Sentry Replay
            "worker-src 'self' blob:",

            // Forms
            "form-action 'self'",

            // Frames - prevent embedding except for TinyMCE dialogs
            "frame-ancestors 'none'",

            // Frame sources - allow TinyMCE dialogs
            "frame-src 'self' https://cdn.tiny.cloud",

            // Base URI
            "base-uri 'self'",

            // Object/embed/applet
            "object-src 'none'",
        ];

        // Upgrade insecure requests in production/staging
        if (app()->environment('production', 'staging')) {
            $directives[] = "upgrade-insecure-requests";
        }

        return implode('; ', $directives);
    }

    /**
     * Build the Permissions-Policy header value.
     */
    protected function buildPermissionsPolicy(): string
    {
        $policies = [
            'camera=()',           // Disable camera
            'microphone=()',       // Disable microphone
            'geolocation=()',      // Disable geolocation
            'payment=()',          // Disable payment API
            'usb=()',              // Disable USB
            'magnetometer=()',     // Disable magnetometer
            'gyroscope=()',        // Disable gyroscope
            'accelerometer=()',    // Disable accelerometer
        ];

        return implode(', ', $policies);
    }
}
