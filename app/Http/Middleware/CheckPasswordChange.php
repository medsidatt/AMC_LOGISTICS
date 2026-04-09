<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPasswordChange
{
    /**
     * Routes that should be accessible even when password change is required.
     */
    protected array $except = [
        'auth/force-password',
        'auth/force-password/update',
        'logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (
            auth()->check()
            && auth()->user()->must_change_password
            && !$this->isExcluded($request)
        ) {
            return redirect()->route('force-password');
        }

        return $next($request);
    }

    private function isExcluded(Request $request): bool
    {
        foreach ($this->except as $path) {
            if ($request->is($path)) {
                return true;
            }
        }
        return false;
    }
}
