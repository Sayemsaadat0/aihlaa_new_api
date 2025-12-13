<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get allowed origins from config
        $allowedOrigins = config('cors.allowed_origins', []);
        $origin = $request->header('Origin');
        
        // Check if origin is allowed
        $isAllowed = false;
        if ($origin) {
            $isAllowed = in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins);
        }
        
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }
        
        // Add CORS headers only for API routes or if origin is present
        if ($request->is('api/*') || $origin) {
            // Set Access-Control-Allow-Origin
            if ($isAllowed && $origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            } elseif (in_array('*', $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }
            
            // Set allowed methods
            $allowedMethods = config('cors.allowed_methods', ['*']);
            if (in_array('*', $allowedMethods)) {
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            } else {
                $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
            }
            
            // Set allowed headers
            $allowedHeaders = config('cors.allowed_headers', ['*']);
            if (in_array('*', $allowedHeaders)) {
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            } else {
                $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
            }
            
            // Set credentials support
            if (config('cors.supports_credentials', false)) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            // Set max age
            $maxAge = config('cors.max_age', 0);
            if ($maxAge > 0) {
                $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
            }
            
            // Expose headers if configured
            $exposedHeaders = config('cors.exposed_headers', []);
            if (!empty($exposedHeaders)) {
                $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
            }
        }
        
        return $response;
    }
}

