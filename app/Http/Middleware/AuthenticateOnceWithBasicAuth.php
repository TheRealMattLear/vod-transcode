<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticateOnceWithBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     *
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $authenticationHasPassed = false;
        $username = $request->getUser();
        $password = $request->getPassword();

        if ($username && $password && $username === config('auth.basic_auth.username')
            && $password === config('auth.basic_auth.password')) {
            $authenticationHasPassed = true;
        }

        if ($authenticationHasPassed === false) {
            return response()->make('Invalid credentials.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
