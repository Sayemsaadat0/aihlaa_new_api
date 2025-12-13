<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonParsing
{
    /**
     * Handle an incoming request.
     * 
     * Ensures JSON request body is properly parsed, especially for production
     * environments where Content-Type headers might not be set correctly.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process for API routes and non-GET requests (POST, PUT, PATCH, DELETE)
        if ($request->is('api/*') && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            $content = $request->getContent();
            
            // Only process if there's content and request data hasn't been parsed yet
            if (!empty($content) && empty($request->all())) {
                $jsonData = json_decode($content, true);
                
                // If valid JSON, merge it into the request
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $request->merge($jsonData);
                }
            }
            // Also handle case where Content-Type might be missing/incorrect but body is JSON
            elseif (!empty($content)) {
                $contentType = $request->header('Content-Type', '');
                // If Content-Type is missing or doesn't contain 'json', try parsing anyway
                if (empty($contentType) || !str_contains(strtolower($contentType), 'json')) {
                    $jsonData = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        // Only merge if request data is empty (not already parsed by Laravel)
                        if (empty($request->all())) {
                            $request->merge($jsonData);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}

