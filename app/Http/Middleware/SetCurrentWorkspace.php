<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every signed-in request works inside the user's own workspace. */
class SetCurrentWorkspace
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->workspace;

        if ($workspace === null) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akaun ini tiada workspace. Hubungi admin.']);
        }

        $this->current->set($workspace);

        return $next($request);
    }
}
