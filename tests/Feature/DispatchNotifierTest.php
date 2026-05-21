<?php

namespace Tests\Feature;

use App\Jobs\SendDispatchWhatsappJob;
use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Services\Whatsapp\DispatchNotifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchNotifierTest extends TestCase
{
    use DatabaseTransactions;

    public function test_driver_without_phone_is_marked_skipped(): void
    {
        Queue::fake();

        $driver = Driver::create([
            'name' => 'Pas-Tel',
            'phone' => null,
            'whatsapp_opt_in_at' => now(),
            'is_active' => true,
        ]);
        $dispatch = DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::tomorrow()->toDateString(),
            'notification_status' => DailyDispatch::STATUS_PENDING,
        ]);

        app(DispatchNotifier::class)->notifyOne($dispatch->fresh());

        Queue::assertNothingPushed();
        $this->assertSame(DailyDispatch::STATUS_SKIPPED, $dispatch->fresh()->notification_status);
        $this->assertStringContainsString('téléphone', $dispatch->fresh()->notification_error);
    }

    public function test_driver_without_opt_in_is_marked_skipped(): void
    {
        Queue::fake();

        $driver = Driver::create([
            'name' => 'Pas-Consent',
            'phone' => '+22245678901',
            'whatsapp_opt_in_at' => null,
            'is_active' => true,
        ]);
        $dispatch = DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::tomorrow()->toDateString(),
            'notification_status' => DailyDispatch::STATUS_PENDING,
        ]);

        app(DispatchNotifier::class)->notifyOne($dispatch->fresh());

        Queue::assertNothingPushed();
        $this->assertSame(DailyDispatch::STATUS_SKIPPED, $dispatch->fresh()->notification_status);
        $this->assertStringContainsString('consentement', $dispatch->fresh()->notification_error);
    }

    public function test_eligible_driver_queues_the_job(): void
    {
        Queue::fake();

        $driver = Driver::create([
            'name' => 'Eligible',
            'phone' => '+22245678901',
            'whatsapp_opt_in_at' => now()->subDays(2),
            'is_active' => true,
        ]);
        $dispatch = DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::tomorrow()->toDateString(),
            'notification_status' => DailyDispatch::STATUS_PENDING,
        ]);

        app(DispatchNotifier::class)->notifyOne($dispatch->fresh());

        Queue::assertPushed(SendDispatchWhatsappJob::class, function ($job) use ($dispatch) {
            return $job->dispatchId === $dispatch->id;
        });
        $this->assertSame(DailyDispatch::STATUS_PENDING, $dispatch->fresh()->notification_status);
    }

    public function test_past_date_is_marked_skipped(): void
    {
        Queue::fake();

        $driver = Driver::create([
            'name' => 'OK',
            'phone' => '+22245678901',
            'whatsapp_opt_in_at' => now(),
            'is_active' => true,
        ]);
        $dispatch = DailyDispatch::create([
            'driver_id' => $driver->id,
            'dispatch_date' => Carbon::yesterday()->toDateString(),
            'notification_status' => DailyDispatch::STATUS_PENDING,
        ]);

        app(DispatchNotifier::class)->notifyOne($dispatch->fresh());

        Queue::assertNothingPushed();
        $this->assertSame(DailyDispatch::STATUS_SKIPPED, $dispatch->fresh()->notification_status);
        $this->assertStringContainsString('passée', $dispatch->fresh()->notification_error);
    }
}
