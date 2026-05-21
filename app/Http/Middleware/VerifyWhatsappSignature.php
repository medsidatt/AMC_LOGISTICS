<?php

namespace App\Http\Middleware;

use App\Services\Whatsapp\WhatsappClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Meta's HMAC-SHA256 signature on incoming webhook POSTs.
 * Without this, anyone could forge delivery receipts and rewrite our
 * notification_status columns.
 */
class VerifyWhatsappSignature
{
    public function __construct(private WhatsappClient $client)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->client->appSecret() === '') {
            // Misconfigured: refuse rather than silently accept anything.
            return response()->json(['error' => 'misconfigured'], 401);
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = $this->client->signWebhookPayload($request->getContent());

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return $next($request);
    }
}
