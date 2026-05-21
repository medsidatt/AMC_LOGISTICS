<?php

namespace Tests\Feature;

use App\Models\DailyDispatch;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WhatsappWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    private string $appSecret = 'whatsapp-test-secret';
    private string $verifyToken = 'verify-test-token';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.app_secret' => $this->appSecret,
            'services.whatsapp.webhook_verify_token' => $this->verifyToken,
        ]);
    }

    public function test_verify_handshake_echoes_challenge_when_token_matches(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=' . urlencode($this->verifyToken) . '&hub_challenge=abc123');

        $response->assertStatus(200);
        $this->assertSame('abc123', $response->getContent());
    }

    public function test_verify_handshake_returns_403_on_token_mismatch(): void
    {
        $response = $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc');

        $response->assertStatus(403);
    }

    public function test_post_without_signature_returns_401(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp', ['entry' => []]);
        $response->assertStatus(401);
    }

    public function test_post_with_invalid_signature_returns_401(): void
    {
        $payload = ['entry' => []];
        $response = $this->postJson('/api/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ]);
        $response->assertStatus(401);
    }

    public function test_delivered_status_updates_matching_dispatch(): void
    {
        $dispatch = $this->createSentDispatch('wamid.testDelivered');

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.testDelivered',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postWithSignature($payload);
        $response->assertOk();

        $this->assertSame(DailyDispatch::STATUS_DELIVERED, $dispatch->fresh()->notification_status);
    }

    public function test_failed_status_marks_dispatch_failed(): void
    {
        $dispatch = $this->createSentDispatch('wamid.testFailed');

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.testFailed',
                            'status' => 'failed',
                            'timestamp' => (string) now()->timestamp,
                            'errors' => [[
                                'code' => 131026,
                                'message' => 'Recipient not in allowed list',
                            ]],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postWithSignature($payload);
        $response->assertOk();

        $fresh = $dispatch->fresh();
        $this->assertSame(DailyDispatch::STATUS_FAILED, $fresh->notification_status);
        $this->assertStringContainsString('131026', $fresh->notification_error);
    }

    public function test_unknown_wamid_is_acked_without_error(): void
    {
        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.unknown',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postWithSignature($payload);
        $response->assertOk();
    }

    private function createSentDispatch(string $wamid): DailyDispatch
    {
        $driver = Driver::create([
            'name' => 'Webhook Test ' . uniqid(),
            'phone' => '+22245678901',
            'whatsapp_opt_in_at' => now(),
            'is_active' => true,
        ]);
        $dispatch = DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::tomorrow()->toDateString(),
        ]);
        $dispatch->markSent($wamid);
        return $dispatch->fresh();
    }

    private function postWithSignature(array $payload)
    {
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        return $this->call(
            'POST',
            '/api/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body,
        );
    }
}
