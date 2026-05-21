<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP wrapper around Meta's WhatsApp Business Cloud API.
 *
 * Currently exposes only the one operation we need: sending an approved
 * template message. Mirrors the timeout/retry/throw pattern used by
 * FleetiService.
 */
class WhatsappClient
{
    /**
     * Send an approved template message to a single recipient.
     *
     * @param  string  $toE164         Recipient phone in E.164 digits-only form (no leading "+").
     * @param  string  $template       Meta-approved template name.
     * @param  string  $lang           Template language code (e.g. "fr").
     * @param  array<string,string>  $bodyParams  Positional body parameters in order ({{1}}, {{2}}, ...).
     * @return string                  The wamid (`messages[0].id`) on success.
     *
     * @throws WhatsappSendException   On any non-2xx response or transport error.
     */
    public function sendTemplate(string $toE164, string $template, string $lang, array $bodyParams): string
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion(),
            $this->phoneNumberId(),
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toE164,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $lang],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_values(array_map(
                            fn (string $p) => ['type' => 'text', 'text' => $p],
                            $bodyParams,
                        )),
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(15)
                ->retry(2, 500, throw: false)
                ->withToken($this->accessToken())
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            throw new WhatsappSendException('transport: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $errorCode = (string) data_get($response->json(), 'error.code', $response->status());
            $errorMessage = (string) data_get($response->json(), 'error.message', $response->body());
            throw new WhatsappSendException("meta {$errorCode}: {$errorMessage}");
        }

        $wamid = (string) data_get($response->json(), 'messages.0.id', '');
        if ($wamid === '') {
            Log::warning('WhatsApp send returned 2xx but no message id', ['response' => $response->json()]);
            throw new WhatsappSendException('Meta returned no message id');
        }

        return $wamid;
    }

    /**
     * Build the HMAC-SHA256 signature Meta sends as X-Hub-Signature-256 so
     * the webhook controller can verify it.
     */
    public function signWebhookPayload(string $rawBody): string
    {
        return 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret());
    }

    public function appSecret(): string
    {
        return (string) config('services.whatsapp.app_secret', '');
    }

    public function webhookVerifyToken(): string
    {
        return (string) config('services.whatsapp.webhook_verify_token', '');
    }

    private function apiVersion(): string
    {
        return (string) config('services.whatsapp.api_version', 'v21.0');
    }

    private function phoneNumberId(): string
    {
        $id = (string) config('services.whatsapp.phone_number_id', '');
        if ($id === '') {
            throw new RuntimeException('WHATSAPP_PHONE_NUMBER_ID is not configured.');
        }
        return $id;
    }

    private function accessToken(): string
    {
        $token = (string) config('services.whatsapp.access_token', '');
        if ($token === '') {
            throw new RuntimeException('WHATSAPP_ACCESS_TOKEN is not configured.');
        }
        return $token;
    }
}
