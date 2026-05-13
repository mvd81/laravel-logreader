<?php

namespace Mvd81\LaravelLogreader\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GzipResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || strlen($content) < 1024) {
            return $response;
        }

        $compressed = gzencode($content, 6);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
