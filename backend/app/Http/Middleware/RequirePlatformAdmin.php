<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'Platform administrator access required.');
        return $next($request);
    }
}
