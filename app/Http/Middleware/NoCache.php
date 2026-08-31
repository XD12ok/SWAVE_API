<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencegah GET API di-cache oleh browser.
 *
 * Logger: beberapa GET (mis. /orders) sempat di-cache browser berisi data lama
 * (total: 0) kendati DB sudah terisi. Dengan 'Cache-Control: no-store' respons
 * selalu diambil fresh dari server.
 */
class NoCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
