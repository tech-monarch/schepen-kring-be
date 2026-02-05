<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. We disable the custom Cors::class to stop the "*, *" duplicate error.
        // Laravel 11 handles CORS automatically via config/cors.php.
        // $middleware->append(\App\Http\Middleware\Cors::class); 

        // 2. Required for Sanctum authentication
        $middleware->statefulApi();

        // 3. Register your Role/Permission aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            
            // We comment these out because if the class files have errors, 
            // they will throw a 500 Internal Server Error.
            // 'cors.public'  => \App\Http\Middleware\PublicCors::class,
            // 'cors.private' => \App\Http\Middleware\PrivateCors::class,
        ]);

        // 4. Ensure API doesn't trip over CSRF tokens
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();