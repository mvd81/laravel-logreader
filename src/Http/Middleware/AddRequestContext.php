<?php

namespace Mvd81\LaravelLogreader\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddRequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = [
            'url'        => $request->fullUrl(),
            'method'     => $request->method(),
            'request_id' => uniqid('req_', true),
        ];

        if (config('logreader.context.include_user_id', true)) {
            $context['user_id'] = auth()->id();
        }

        Log::shareContext($context);

        return $next($request);
    }
}
