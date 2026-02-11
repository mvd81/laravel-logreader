<?php

namespace Mvd81\LaravelLogreader\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogreaderEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('logreader.enabled', true)) {
            return response()->json(['error' => 'Logreader is disabled'], 403);
        }

        return $next($request);
    }
}
