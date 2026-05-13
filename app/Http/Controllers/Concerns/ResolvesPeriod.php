<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolvePeriod(Request $request): array
    {
        $preset = $request->string('preset')->toString() ?: 'month';
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay(), 'custom'];
        }

        return match ($preset) {
            'day' => [now()->startOfDay(), now()->endOfDay(), 'day'],
            'week' => [now()->startOfWeek(Carbon::MONDAY), now()->endOfDay(), 'week'],
            'year' => [now()->startOfYear(), now()->endOfDay(), 'year'],
            default => [now()->startOfMonth(), now()->endOfDay(), 'month'],
        };
    }
}
