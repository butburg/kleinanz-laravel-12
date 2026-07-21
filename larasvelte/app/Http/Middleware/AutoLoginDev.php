<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginDev
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only active in local environment — never in staging or production
        if (app()->environment('local') && ! Auth::check()) {
            $userId = (int) config('app.dev_auto_login_user_id');

            if ($userId > 0) {
                Auth::loginUsingId($userId);
            }
        }

        return $next($request);
    }
}
