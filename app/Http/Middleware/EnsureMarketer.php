<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketer
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $marketer = $user->marketer;

        if ($marketer === null) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->guest(route('marketer.application.create'));
        }

        if (! $marketer->isActive()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->guest(route('marketer.application.status'));
        }

        return $next($request);
    }
}
