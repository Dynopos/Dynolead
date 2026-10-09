<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends visitors on any other host (www.dynolead.my, the Forge test domain, an old
 * domain) to the same path on APP_URL, so Google and visitors see one address.
 * Only GET/HEAD are redirected: posts such as CHIP callbacks and Livewire updates
 * are never bounced. The health check (/up) and CHIP callback are left alone.
 */
class RedirectToCanonicalHost
{
    private const EXCEPT = ['up', 'chip/callback'];

    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'));
        $host = strtolower((string) ($canonical['host'] ?? ''));

        if (! config('dynoleads.canonical_redirect')
            || $host === ''
            || $host === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP)
            || strtolower($request->getHost()) === $host
            || (! $request->isMethod('GET') && ! $request->isMethod('HEAD'))
            || $request->is(...self::EXCEPT)) {
            return $next($request);
        }

        $scheme = $canonical['scheme'] ?? 'https';
        $port = isset($canonical['port']) ? ':'.$canonical['port'] : '';

        return redirect()->to($scheme.'://'.$host.$port.$request->getRequestUri(), 301);
    }
}
