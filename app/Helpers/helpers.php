<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

if (!function_exists('is_active')) {
    function is_active($url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $route = Route::getRoutes()->match(request()->create($path));
        $routeName = $route->getName();

        if (Str::startsWith(Route::currentRouteName(), $routeName)) {
            return 'active';
        }

        $currentPath = request()->path();
        $targetPath = ltrim($path, '/');
        if (Str::startsWith($currentPath, $targetPath)) {
            return 'active';
        }

        return '';
    }
}

if (!function_exists('is_shown')) {
    function is_shown($url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $route = Route::getRoutes()->match(request()->create($path));
        $routeName = $route->getName();

        return Str::startsWith(Route::currentRouteName(), $routeName) ? 'show' : '';
    }
}

if (!function_exists('format_number')) {
    function format_number($number): string
    {
        return number_format($number, 0, '.', ' ');
    }
}

if (!function_exists('format_date')) {
    function format_date($date): string
    {
        return date('d/m/Y', strtotime($date));
    }
}

if (!function_exists('has_any_permission')) {
    function has_any_permission($permissions): bool
    {
        foreach ($permissions as $permission) {
            if (auth()->user()->can($permission)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('format_date_time')) {
    function format_date_time($date): string
    {
        return date('d/m/Y H:i', strtotime($date));
    }
}

if (!function_exists('convert_time_to_decimal')) {
    function convert_time_to_decimal($time): float
    {
        if (!strpos($time, ':')) {
            if ($time != null) {
                $time = gmdate('H:i:s', $time);
            } else {
                $time = '00:00:00';
            }
        }

        $timeArr = explode(':', $time);
        return $timeArr[0] + $timeArr[1] / 60 + $timeArr[2] / 3600;
    }
}

if (!function_exists('money_format')) {
    function money_format($number): string
    {
        return number_format($number, 0, '', ' ') . ' MRU';
    }
}
