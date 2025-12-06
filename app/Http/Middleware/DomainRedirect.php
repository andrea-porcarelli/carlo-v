<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the current host
        $host = $request->getHost();

        // Get the current path
        $path = $request->path();

        // Check if we are NOT in the /backoffice path AND NOT on internal.carlov.it domain
        $isNotBackoffice = !str_starts_with($path, 'backoffice');
        $isNotInternalDomain = $host !== 'internal.carlov.it';

        // If both conditions are true, invoke welcome method
        if ($isNotBackoffice && $isNotInternalDomain) {
            return response()->view('welcome');
        }

        return $next($request);
    }
}
