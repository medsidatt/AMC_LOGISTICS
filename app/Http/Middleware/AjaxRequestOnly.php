<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxRequestOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $isAjax = $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';
        $hasCustomHeader = $request->header('X-App-Request') === config('app.ajax_token');

        if (!$isAjax || !$hasCustomHeader) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
