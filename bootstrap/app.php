<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply CORS middleware globally to handle cross-origin requests
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\EnsureJsonParsing::class,
        ]);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API authentication errors FIRST (before route not found)
        // This prevents redirect to login route which causes "Route [login] not found"
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Unauthenticated',
                ], 401);
            }
        });

        // Handle API route not found errors (404)
        // Check if it's an authentication redirect issue
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $e->getMessage();
                
                // If the error is about login route not found, it's actually an auth issue
                if (str_contains($message, 'Route [login]') || str_contains($message, 'login')) {
                    return response()->json([
                        'success' => false,
                        'status' => 401,
                        'message' => 'Unauthenticated',
                    ], 401);
                }
                
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Route not found',
                ], 404);
            }
        });

        // Handle API method not allowed errors (405)
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 405,
                    'message' => 'Method not allowed',
                ], 405);
            }
        });

        // Handle API validation errors
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Handle API model not found errors
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        // Handle all other API errors
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $e->getMessage();
                
                // Check if it's a route/login error that should be 401
                if (str_contains($message, 'Route [login]') || 
                    str_contains($message, 'login') && str_contains($message, 'not found')) {
                    return response()->json([
                        'success' => false,
                        'status' => 401,
                        'message' => 'Unauthenticated',
                    ], 401);
                }
                
                // Determine status code
                $statusCode = 500;
                if (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
                    $statusCode = $e->getCode();
                }

                // In production, don't expose detailed error messages
                if (app()->environment('production')) {
                    if ($statusCode === 500) {
                        $message = 'Internal server error';
                    }
                }

                return response()->json([
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $message ?: 'Internal server error',
                ], $statusCode);
            }
        });
    })->create();
