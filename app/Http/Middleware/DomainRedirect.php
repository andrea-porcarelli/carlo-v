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

        // Allow sync API requests through without domain restrictions
        if (str_starts_with($path, 'api/sync')) {
            return $next($request);
        }

        // Allow deploy webhooks through without domain restrictions
        if (str_starts_with($path, 'webhook/')) {
            return $next($request);
        }

        // Allow log-viewer through
        if (str_starts_with($path, 'log-viewer')) {
            return $next($request);
        }

        // Allow Livewire AJAX requests through
        if (str_starts_with($path, 'livewire')) {
            return $next($request);
        }

        // Check if we are NOT in the /backoffice path AND NOT on internal.carlov.it domain
        $isNotBackoffice = !str_starts_with($path, 'backoffice');
        $isNotInternalDomain = !in_array($host, ['carlov.local', 'carlov.local.internal', '192.168.8.110', '192.168.1.100', 'carlovristorante.share.zrok.io', 'carlov-ristorante', 'carlov-ristorante.tailecb1d9.ts.net']); ;

        // If both conditions are true, invoke welcome method
        if ($isNotBackoffice && $isNotInternalDomain) {
            return response()->view('welcome');
        }

        return $next($request);
    }
}
