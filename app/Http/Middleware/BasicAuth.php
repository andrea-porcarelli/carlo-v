<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) config('auth.basic.user');
        $expectedPass = (string) config('auth.basic.password');

        $user = (string) $request->getUser();
        $pass = (string) $request->getPassword();

        if ($expectedUser !== '' && $expectedPass !== ''
            && hash_equals($expectedUser, $user)
            && hash_equals($expectedPass, $pass)) {
            return $next($request);
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Restricted"',
        ]);
    }
}