<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordSetForTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('post')
            && ltrim($request->path(), '/') === 'user/two-factor-authentication'
        ) {
            $user = $request->user();

            if ($user instanceof User && ! $user->has_password_set) {
                $message = 'Set a password before enabling two-factor authentication.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return to_route('security.edit')->with('error', $message);
            }
        }

        return $next($request);
    }
}
