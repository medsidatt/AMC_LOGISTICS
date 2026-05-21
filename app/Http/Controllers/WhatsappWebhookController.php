<?php

namespace App\Http\Controllers;

use App\Models\DailyDispatch;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints Meta calls to verify the webhook URL and to push delivery /
 * read / failure events. Routes are unauthenticated (Meta can't auth);
 * POSTs are signature-checked via VerifyWhatsappSignature middleware.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(private WhatsappClient $client)
    {
    }

    /**
     * GET handshake — Meta calls this once when the webhook URL is registered
     * and expects hub.challenge back when the token matches.
     */
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');
        $expected = $this->client->webhookVerifyToken();

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200);
        }

        return response('forbidden', 403);
    }

    /**
     * POST status updates. Payload shape:
     *   { entry: [{ changes: [{ value: { statuses: [{ id, status, timestamp, errors: [...] }] } }] }] }
     * We only care about the `statuses` array.
     */
    public function receive(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $statuses = [];
        foreach ((array) data_get($payload, 'entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $statuses[] = $status;
                }
            }
        }

        foreach ($statuses as $status) {
            $wamid = (string) data_get($status, 'id', '');
            if ($wamid === '') {
                continue;
            }

            $dispatch = DailyDispatch::where('whatsapp_message_id', $wamid)->first();
            if (! $dispatch) {
                // Unknown wamid — ack 200 (Meta retries on non-2xx).
                continue;
            }

            $metaStatus = (string) data_get($status, 'status', '');
            switch ($metaStatus) {
                case 'sent':
                    if ($dispatch->notification_status === DailyDispatch::STATUS_PENDING) {
                        $dispatch->forceFill([
                            'notification_status' => DailyDispatch::STATUS_SENT,
                            'notified_at' => $this->timestamp($status) ?? now(),
                        ])->save();
                    }
                    break;

                case 'delivered':
                    if (in_array($dispatch->notification_status, [
                        DailyDispatch::STATUS_PENDING,
                        DailyDispatch::STATUS_SENT,
                    ], true)) {
                        $dispatch->forceFill([
                            'notification_status' => DailyDispatch::STATUS_DELIVERED,
                        ])->save();
                    }
                    break;

                case 'read':
                    $dispatch->forceFill([
                        'notification_status' => DailyDispatch::STATUS_READ,
                    ])->save();
                    break;

                case 'failed':
                    $err = (string) data_get($status, 'errors.0.message', 'échec de livraison');
                    $code = data_get($status, 'errors.0.code');
                    $dispatch->markFailed($code ? "meta {$code}: {$err}" : $err);
                    break;

                default:
                    Log::info('WhatsApp webhook: unhandled status', ['status' => $metaStatus, 'wamid' => $wamid]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function timestamp(array $status): ?Carbon
    {
        $ts = data_get($status, 'timestamp');
        if (! is_numeric($ts)) {
            return null;
        }
        return Carbon::createFromTimestamp((int) $ts);
    }
}
