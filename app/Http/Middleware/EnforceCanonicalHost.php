<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCanonicalHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '') {
            return $next($request);
        }

        $parts = parse_url($appUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $next($request);
        }

        $expectedScheme = $parts['scheme'];
        $expectedHost = $parts['host'];
        $expectedPort = $parts['port'] ?? ($expectedScheme === 'https' ? 443 : 80);

        $currentHost = $request->getHost();
        $currentPort = $request->getPort();
        $currentScheme = $request->getScheme();

        if (
            strcasecmp($currentHost, $expectedHost) === 0
            && $currentPort === $expectedPort
            && $currentScheme === $expectedScheme
        ) {
            return $next($request);
        }

        $targetUrl = $appUrl.$request->getRequestUri();
        $statusCode = $request->isMethodSafe() ? 302 : 307;

        return redirect()->away($targetUrl, $statusCode);
    }
}
