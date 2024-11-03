<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Return null for API requests to avoid redirect
        if ($request->is('api/*')) {
            return null; // No redirect for API routes
        }

        return route('login');
    }
}
