<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{
    /**
     * Whitelist of artisan commands that may be triggered via URL.
     * The key is the public slug used in the URL.
     */
    private const JOBS = [
        'fleeti-sync' => 'fleeti:sync-kilometers',
        'notify-due-engine-maintenance' => 'logistics:notify-due-engine-maintenance',
        'notify-missing-weekly-checklists' => 'logistics:notify-missing-weekly-checklists',
        'telemetry-compact' => 'telemetry:compact',
        'places-detect-hubs' => 'places:detect-hubs',
        'rebuild-trip-segments' => 'logistics:rebuild-trip-segments',
        'detect-off-hours-movement' => 'logistics:detect-off-hours-movement',
    ];

    /**
     * Run Laravel's scheduler — equivalent to `php artisan schedule:run`.
     * Hit this every minute from Infomaniak.
     */
    public function run(Request $request): Response
    {
        $this->ensureAuthorized($request);
        $this->prepareLongRunning();

        $exit = Artisan::call('schedule:run');
        $output = Artisan::output();

        Log::info('cron.schedule:run', ['exit' => $exit, 'output' => $output]);

        return response("schedule:run exit={$exit}\n\n{$output}", 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Run a single whitelisted job — equivalent to running its artisan command.
     * Use this if you want to schedule each job at its own frequency in Infomaniak.
     */
    public function runJob(Request $request, string $job): Response
    {
        $this->ensureAuthorized($request);

        if (!isset(self::JOBS[$job])) {
            abort(404, "Unknown cron job: {$job}");
        }

        $this->prepareLongRunning();

        $command = self::JOBS[$job];
        $params = $this->parseParams($request, $command);

        $exit = Artisan::call($command, $params);
        $output = Artisan::output();

        Log::info('cron.job', ['job' => $job, 'command' => $command, 'exit' => $exit, 'output' => $output]);

        return response("{$command} exit={$exit}\n\n{$output}", 200)
            ->header('Content-Type', 'text/plain');
    }

    private function ensureAuthorized(Request $request): void
    {
        $expected = (string) config('app.cron_token', env('CRON_TOKEN', ''));
        $provided = (string) ($request->query('token') ?? $request->header('X-Cron-Token', ''));

        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(403, 'Invalid cron token.');
        }
    }

    private function prepareLongRunning(): void
    {
        @set_time_limit(0);
        ignore_user_abort(true);
    }

    /**
     * Map known --flags from the schedule definition (kept in sync with bootstrap/app.php).
     */
    private function parseParams(Request $request, string $command): array
    {
        $params = [];

        if ($command === 'logistics:rebuild-trip-segments') {
            $params['--days'] = (int) $request->query('days', 7);
        }
        if ($command === 'logistics:detect-off-hours-movement') {
            $params['--window'] = (int) $request->query('window', 120);
        }

        return $params;
    }
}
