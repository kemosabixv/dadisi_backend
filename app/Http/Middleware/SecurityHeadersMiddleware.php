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

            // Scripts - allow self and inline (needed for Next.js)
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",

            // Styles - allow self and inline (needed for Tailwind)
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",

            // Images - allow self, data URIs, and common image hosts
            "img-src 'self' data: blob: https:",

            // Fonts - allow self and Google Fonts
            "font-src 'self' https://fonts.gstatic.com",

            // API connections
            "connect-src 'self' {$apiUrl} {$frontendUrl} https://api.exchangerate-api.com",

            // Forms
            "form-action 'self'",

            // Frames - prevent embedding
            "frame-ancestors 'none'",

            // Base URI
            "base-uri 'self'",

            // Object/embed/applet
            "object-src 'none'",

            // Upgrade insecure requests in production
            "upgrade-insecure-requests",
        ];

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
