<?php

namespace App\Http\Controllers;

use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{
    /**
     * Inline scheduler. Each entry: cron expression => [[artisan command, args], ...].
     *
     * We do NOT use Laravel's schedule:run on Infomaniak: it spawns a PHP CLI
     * subprocess per task via proc_open, which is disabled on Infomaniak's
     * shared hosting. Instead we evaluate cron expressions ourselves and call
     * each due command inline through Artisan::call (no subprocess).
     *
     * Keep this in sync with bootstrap/app.php's withSchedule block.
     */
    private const SCHEDULE = [
        '*/30 * * * *' => [['fleeti:sync-kilometers', []]],
        '*/15 * * * *' => [['logistics:notify-due-engine-maintenance', []]],
        '0 7 * * 1'    => [['logistics:notify-missing-weekly-checklists', []]],
        '15 3 1 * *'   => [['telemetry:compact', []]],
        '30 2 * * *'   => [['places:detect-hubs', []]],
        '45 2 * * *'   => [['logistics:rebuild-trip-segments', ['--days' => 7]]],
        '0 * * * *'    => [['logistics:detect-off-hours-movement', ['--window' => 120]]],
        // Live polling lane — see bootstrap/app.php for documentation.
        '* 6-7 * * *'        => [['fleeti:sync-live-dispatch', ['--cadence' => 1]]],
        '*/2 5,8-22 * * *'   => [['fleeti:sync-live-dispatch', ['--cadence' => 2]]],
        '*/5 0-4,23 * * *'   => [['fleeti:sync-live-dispatch', ['--cadence' => 5]]],
        '*/2 5-22 * * *'     => [['fleeti:sync-fleet-positions', []]],
        '0 23 * * *'         => [['logistics:reconcile-expected-tickets', []]],
    ];

    /**
     * Whitelist of artisan commands that may be triggered via URL.
     * The key is the public slug used in the URL.
     */
    private const JOBS = [
        'fleeti-sync' => 'fleeti:sync-kilometers',
        'fleeti-sync-live' => 'fleeti:sync-live-dispatch',
        'fleeti-sync-fleet-positions' => 'fleeti:sync-fleet-positions',
        'notify-due-engine-maintenance' => 'logistics:notify-due-engine-maintenance',
        'notify-missing-weekly-checklists' => 'logistics:notify-missing-weekly-checklists',
        'telemetry-compact' => 'telemetry:compact',
        'places-detect-hubs' => 'places:detect-hubs',
        'rebuild-trip-segments' => 'logistics:rebuild-trip-segments',
        'detect-off-hours-movement' => 'logistics:detect-off-hours-movement',
        'reconcile-expected-tickets' => 'logistics:reconcile-expected-tickets',
    ];

    /**
     * Inline scheduler entry point. Hit this every minute (or every 15 min
     * on Infomaniak) — it figures out which commands are due and runs them
     * via Artisan::call in this PHP process. No subprocess, no proc_open.
     */
    public function run(Request $request): Response
    {
        $this->ensureAuthorized($request);
        $this->prepareLongRunning();

        $now = now();
        $ran = 0;
        $lines = [];

        foreach (self::SCHEDULE as $expression => $jobs) {
            if (!(new CronExpression($expression))->isDue($now->toDateTimeString())) {
                continue;
            }

            foreach ($jobs as [$command, $params]) {
                try {
                    $exit = Artisan::call($command, $params);
                    $output = trim(Artisan::output());
                    $lines[] = "OK  {$command} (exit={$exit})";
                    Log::info('cron.command', [
                        'command' => $command,
                        'params' => $params,
                        'exit' => $exit,
                        'output' => $output,
                    ]);
                    $ran++;
                } catch (\Throwable $e) {
                    $lines[] = "ERR {$command}: {$e->getMessage()}";
                    Log::error('cron.command_failed', [
                        'command' => $command,
                        'params' => $params,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $summary = "ran {$ran} command(s) at {$now->toIso8601String()}";
        Log::info('cron.run', ['ran' => $ran, 'time' => $now->toIso8601String()]);

        return response("{$summary}\n\n" . implode("\n", $lines), 200)
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
        if ($command === 'fleeti:sync-live-dispatch') {
            $params['--cadence'] = (int) $request->query('cadence', 2);
            $params['--window-hours'] = (int) $request->query('window_hours', 18);
        }

        return $params;
    }
}
