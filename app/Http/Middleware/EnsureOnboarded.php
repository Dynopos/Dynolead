<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** New customers set up their first product before using the app. */
class EnsureOnboarded
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->current->get()?->onboarded_at === null) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
