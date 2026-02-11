<?php

namespace Mvd81\LaravelLogreader\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateLogreaderToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('logreader.token');

        if (empty($token)) {
            return response()->json(['error' => 'Logreader token not configured'], 403);
        }

        if ($request->bearerToken() !== $token) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
