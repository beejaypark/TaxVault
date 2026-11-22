<?php

namespace App\Http\Middleware;

use App\Support\SessionActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireActiveSession
{
    public function __construct(private readonly SessionActivity $activity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $request->session()->has($guard->getName())
            || $this->activity->active($request->session())) {
            return $next($request);
        }

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Your session has expired.', 'code' => 'session_expired'], 401);
    }
}
