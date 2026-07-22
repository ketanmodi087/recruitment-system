<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAgencyStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if (Auth::check()) {
            // Get the authenticated user
            $user = Auth::user();
            // Check if the user belongs to an agency and if the agency is disabled
            if ($user->is_disabled == 1) {
                $user->tokens()->delete();
                Auth::guard('web')->logout();
            }
        }

        return $next($request);
    }
}
