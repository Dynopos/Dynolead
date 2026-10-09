<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\SetCurrentWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(RedirectToCanonicalHost::class);
        $middleware->alias([
            'workspace' => SetCurrentWorkspace::class,
            'admin' => EnsureAdmin::class,
            'onboarded' => EnsureOnboarded::class,
        ]);
        $middleware->validateCsrfTokens(except: ['chip/callback']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('leads'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
