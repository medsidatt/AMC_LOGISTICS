<?php

namespace Tests\Feature;

use App\Jobs\SendDispatchWhatsappJob;
use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Services\Whatsapp\WhatsappClient;
use App\Services\Whatsapp\WhatsappSendException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendDispatchWhatsappJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_version' => 'v21.0',
            'services.whatsapp.phone_number_id' => '111222333',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.template_dispatch' => 'amc_dispatch_v1',
            'services.whatsapp.template_lang' => 'fr',
        ]);
    }

    private function createDispatchForEligibleDriver(): DailyDispatch
    {
        $driver = Driver::create([
            'name' => 'Mohamed',
            'phone' => '+22245678901',
            'whatsapp_opt_in_at' => now()->subDay(),
            'is_active' => true,
        ]);
        return DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::tomorrow()->toDateString(),
            'notification_status' => DailyDispatch::STATUS_PENDING,
        ]);
    }

    public function test_successful_send_marks_dispatch_as_sent_with_wamid(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HBgL...test123']],
            ], 200),
        ]);

        $dispatch = $this->createDispatchForEligibleDriver();
        (new SendDispatchWhatsappJob($dispatch->id))->handle(app(WhatsappClient::class));

        $fresh = $dispatch->fresh();
        $this->assertSame(DailyDispatch::STATUS_SENT, $fresh->notification_status);
        $this->assertSame('wamid.HBgL...test123', $fresh->whatsapp_message_id);
        $this->assertNotNull($fresh->notified_at);
        $this->assertNull($fresh->notification_error);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            return $request->url() === 'https://graph.facebook.com/v21.0/111222333/messages'
                && $body['to'] === '22245678901'
                && $body['type'] === 'template'
                && $body['template']['name'] === 'amc_dispatch_v1'
                && count($body['template']['components'][0]['parameters']) === 2;
        });
    }

    public function test_meta_4xx_throws_and_final_attempt_marks_failed(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['code' => 131026, 'message' => 'Recipient not opted in'],
            ], 400),
        ]);

        $dispatch = $this->createDispatchForEligibleDriver();
        $job = new SendDispatchWhatsappJob($dispatch->id);

        // Simulate final attempt by reflection on the queue InteractsWithQueue
        // — easier: bump $tries down to 1 so we hit the "final attempt" path
        // immediately.
        $job->tries = 1;
        $jobMock = $this->getMockBuilder(SendDispatchWhatsappJob::class)
            ->setConstructorArgs([$dispatch->id])
            ->onlyMethods(['attempts'])
            ->getMock();
        $jobMock->tries = 1;
        $jobMock->method('attempts')->willReturn(1);

        try {
            $jobMock->handle(app(WhatsappClient::class));
            $this->fail('Expected WhatsappSendException');
        } catch (WhatsappSendException $e) {
            $this->assertStringContainsString('131026', $e->getMessage());
        }

        $fresh = $dispatch->fresh();
        $this->assertSame(DailyDispatch::STATUS_FAILED, $fresh->notification_status);
        $this->assertStringContainsString('131026', $fresh->notification_error);
    }

    public function test_already_sent_dispatch_is_skipped_on_re_run(): void
    {
        Http::fake();

        $dispatch = $this->createDispatchForEligibleDriver();
        $dispatch->markSent('wamid.previous');

        (new SendDispatchWhatsappJob($dispatch->id))->handle(app(WhatsappClient::class));

        Http::assertNothingSent();
        $this->assertSame('wamid.previous', $dispatch->fresh()->whatsapp_message_id);
    }
}
