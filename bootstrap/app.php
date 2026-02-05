<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\PublicCors;
use App\Http\Middleware\PrivateCors;
use App\Http\Middleware\Cors; // <--- Ensure this is imported

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. GLOBAL CORS (This fixes the blocking)
        $middleware->append(Cors::class); 

        // Required for Sanctum
        $middleware->statefulApi();
        
        // Keep your existing preflight if you have it, 
        // but the Cors.php I gave you handles preflight already.
        // $middleware->prepend(\App\Http\Middleware\HandleCorsPreflight::class);

        // Route middleware aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'cors.public'  => PublicCors::class,
            'cors.private' => PrivateCors::class,
        ]);

        // CSRF exemption for API
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();