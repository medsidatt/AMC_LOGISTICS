<?php

namespace App\Support;

use Illuminate\Http\Request;

class SilentSso
{
    /** How long after a silent-SSO failure we skip retrying it (seconds). */
    public const COOLDOWN_SECONDS = 300;

    /** Session key for the unix timestamp of the last silent-SSO failure. */
    public const FAILED_AT_KEY = 'sso_failed_at';

    /** Session key set when the user explicitly logged out. Prevents
     *  silent SSO from immediately re-authenticating them via their
     *  still-active Microsoft browser session. */
    public const LOGGED_OUT_KEY = 'explicit_logout';

    /**
     * Decide where an unauthenticated request should go: into another
     * silent-SSO attempt, or straight to the fallback (typically /login).
     */
    public static function nextRedirectFor(Request $request, string $fallback): string
    {
        if (! self::shouldAttempt($request)) {
            return $fallback;
        }
        return '/auth/microsoft?silent=1';
    }

    public static function shouldAttempt(Request $request): bool
    {
        if ($request->expectsJson()) {
            return false;
        }
        if ($request->session()->get(self::LOGGED_OUT_KEY)) {
            return false;
        }
        $lastFailed = (int) $request->session()->get(self::FAILED_AT_KEY, 0);
        return (time() - $lastFailed) > self::COOLDOWN_SECONDS;
    }

    public static function markFailed(Request $request): void
    {
        $request->session()->put(self::FAILED_AT_KEY, time());
    }

    public static function markLoggedOut(Request $request): void
    {
        $request->session()->put(self::LOGGED_OUT_KEY, true);
    }

    public static function clearCooldown(Request $request): void
    {
        $request->session()->forget([self::FAILED_AT_KEY, self::LOGGED_OUT_KEY]);
    }
}
