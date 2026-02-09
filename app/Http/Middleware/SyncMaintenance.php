<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SyncMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Cache::get('sync_in_progress')) {
            return $next($request);
        }

        $path = $request->path();

        // Let backoffice and sync API requests through
        if (str_starts_with($path, 'backoffice') || str_starts_with($path, 'api/sync')) {
            return $next($request);
        }

        // Frontoffice API calls get a JSON response
        if ($request->expectsJson() || str_starts_with($path, 'api/')) {
            return response()->json(['message' => 'Aggiornamento in corso, riprova tra poco.'], 503);
        }

        return response()->view('maintenance', [], 503);
    }
}
