<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Fasa 0: single owner session (password from APP_LOGIN_PASSWORD). */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('owner') !== true) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
