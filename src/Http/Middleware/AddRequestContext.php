<?php

namespace Mvd81\LaravelLogreader\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddRequestContext
{
    public function handle(Request $request, Closure $next)
    {
        Log::shareContext([
            'url'    => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        return $next($request);
    }
}
