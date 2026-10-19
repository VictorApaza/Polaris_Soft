<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccesoSeguro
{
    public function handle(Request $request, Closure $next): Response
    {
        $base = (string) config('app.url');
        if (config('app.env') === 'production' && parse_url($base, PHP_URL_SCHEME) === 'https'
            && ! $request->isSecure()) {
            return redirect()->away(rtrim($base, '/').'/'.ltrim($request->getRequestUri(), '/'), 308);
        }

        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
