<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Sync-Token');
        $expected = config('sync.api_token');

        if (empty($expected) || empty($token) || !hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
