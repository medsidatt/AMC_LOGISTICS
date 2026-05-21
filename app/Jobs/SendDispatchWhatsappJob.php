<?php

namespace App\Jobs;

use App\Models\DailyDispatch;
use App\Services\Whatsapp\WhatsappClient;
use App\Services\Whatsapp\WhatsappSendException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sends one WhatsApp dispatch-notification template message and writes the
 * result back to the daily_dispatches row.
 *
 * Idempotent: the job re-checks status on entry and refuses to send if the
 * row is already in a terminal sent/delivered/read state (handles double
 * dispatch from save-spam).
 */
class SendDispatchWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public int $dispatchId)
    {
    }

    public function handle(WhatsappClient $client): void
    {
        $dispatch = DailyDispatch::with('driver')->find($this->dispatchId);
        if (! $dispatch) {
            return;
        }

        if (in_array($dispatch->notification_status, [
            DailyDispatch::STATUS_SENT,
            DailyDispatch::STATUS_DELIVERED,
            DailyDispatch::STATUS_READ,
        ], true)) {
            return;
        }

        $driver = $dispatch->driver;
        if (! $driver || ! $driver->is_active) {
            $dispatch->markSkipped('chauffeur inactif');
            return;
        }

        $toE164 = $driver->whatsapp_e164;
        if (! $toE164) {
            $dispatch->markSkipped('numéro de téléphone invalide');
            return;
        }

        if (! $driver->whatsapp_opt_in_at) {
            $dispatch->markSkipped('sans consentement');
            return;
        }

        $bodyParams = [
            $driver->name ?? '—',
            Carbon::parse($dispatch->dispatch_date)->locale('fr')->isoFormat('dddd D MMMM Y'),
        ];

        try {
            $wamid = $client->sendTemplate(
                $toE164,
                (string) config('services.whatsapp.template_dispatch', 'amc_dispatch_v1'),
                (string) config('services.whatsapp.template_lang', 'fr'),
                $bodyParams,
            );
        } catch (WhatsappSendException $e) {
            // Let Laravel's retry mechanism handle transient failures; only
            // write the failure on the final attempt.
            if ($this->attempts() >= $this->tries) {
                $dispatch->markFailed($e->getMessage());
            }
            throw $e;
        }

        $dispatch->markSent($wamid);
    }

    public function failed(Throwable $e): void
    {
        $dispatch = DailyDispatch::find($this->dispatchId);
        if ($dispatch && $dispatch->notification_status === DailyDispatch::STATUS_PENDING) {
            $dispatch->markFailed($e->getMessage());
        }
    }

}
