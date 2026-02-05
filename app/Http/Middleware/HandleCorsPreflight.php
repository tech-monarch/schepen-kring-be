<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCorsPreflight
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            $origin = $request->headers->get('Origin');

            $allowed = [
                'http://localhost:3000',
                'https://schepen-kring.nl',
                'https://www.schepen-kring.nl',
            ];

            // PUBLIC endpoints → allow *
            if (str_starts_with($request->path(), 'api/analytics') ||
                str_starts_with($request->path(), 'api/yachts') ||
                str_starts_with($request->path(), 'api/bids') ||
                str_starts_with($request->path(), 'api/ai')) {

                return response('', 204, [
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                ]);
            }

            // PRIVATE endpoints → specific origin + credentials
            if ($origin && in_array($origin, $allowed)) {
                return response('', 204, [
                    'Access-Control-Allow-Origin' => $origin,
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                ]);
            }

            return response('', 204);
        }

        return $next($request);
    }
}
