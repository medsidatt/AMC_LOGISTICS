<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendDispatchWhatsappJob;
use App\Models\DailyDispatch;
use Carbon\Carbon;

/**
 * Entry point any controller/command calls to notify drivers about their
 * dispatch. Keeps the "should we send / will we skip" decision in one place
 * so Phase 2+ (calendar UI, multi-day trips) plug into the same seam.
 */
class DispatchNotifier
{
    /**
     * Resolve each id, decide skip vs queue, dispatch the job for sendable rows.
     *
     * @param  array<int>  $dispatchIds
     */
    public function notifyForDispatchIds(array $dispatchIds): void
    {
        if (empty($dispatchIds)) {
            return;
        }

        $dispatches = DailyDispatch::with('driver')
            ->whereIn('id', $dispatchIds)
            ->get();

        foreach ($dispatches as $dispatch) {
            $this->notifyOne($dispatch);
        }
    }

    public function notifyOne(DailyDispatch $dispatch): void
    {
        // Past-date guard — never notify about dispatches that are already
        // in the past (avoids surprise after re-saves on historical rows).
        if (Carbon::parse($dispatch->dispatch_date)->lt(Carbon::today())) {
            $dispatch->markSkipped('date passée');
            return;
        }

        $driver = $dispatch->driver;
        if (! $driver || ! $driver->is_active) {
            $dispatch->markSkipped('chauffeur inactif');
            return;
        }

        if (empty($driver->phone)) {
            $dispatch->markSkipped('pas de téléphone');
            return;
        }

        if (! $driver->whatsapp_opt_in_at) {
            $dispatch->markSkipped('sans consentement');
            return;
        }

        $dispatch->markPending();
        SendDispatchWhatsappJob::dispatch($dispatch->id);
    }
}
