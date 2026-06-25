# Deployment & Production Configuration

Operational runbook for the AMC Fleet Platform (Laravel 12 + Inertia/React, built
with Vite). Pair this with `.env.example` — that file is a safe template; the
values below are what a **production** server must override.

---

## 1. Production environment (`.env`)

`.env` is gitignored and lives only on the server. After copying `.env.example`,
set these for production:

| Key | Local | Production | Why |
|-----|-------|-----------|-----|
| `APP_ENV` | `local` | `production` | Enables prod optimisations, disables dev affordances |
| `APP_DEBUG` | `true` | **`false`** | `true` leaks stack traces, env vars and secrets to visitors |
| `APP_URL` | `http://localhost` | `https://<your-domain>` | Correct absolute URLs, OAuth redirects, mail links |
| `LOG_LEVEL` | `debug` | `warning` | `debug` fills the disk and logs sensitive payloads |
| `LOG_CHANNEL` | `stack` | `daily` | Daily rotation prevents an unbounded log file |
| `SESSION_SECURE_COOKIE` | `false` | **`true`** | Session cookie sent only over HTTPS |
| `SESSION_ENCRYPT` | `false` | `true` | Encrypts session payload at rest |
| `AJAX_TOKEN` | — | long random string | Guards AJAX-only endpoints; never ship the default |
| `QUEUE_CONNECTION` | `sync` | `sync` (today) | See §4 before switching to `database` |

Generate the app key once per environment: `php artisan key:generate`.

### Secret rotation (do this now)

A real Office365 mail password was historically committed in `.env.example`.
It has been scrubbed from the template, but **git history still contains it**
(history is intentionally not being rewritten). Treat it as compromised:

1. Rotate the Office365 / `MAIL_PASSWORD` (use an app password).
2. Set a strong unique `AJAX_TOKEN` (its fallback default is publicly known).
3. Rotate any other secret that was ever committed (Pusher, Fleeti, WhatsApp,
   SharePoint) if it was real.

---

## 2. Build & release (fresh clone → running app)

Compiled assets are **not** committed (`/public/build` is gitignored). The
deploy pipeline must build them:

```bash
git clone <repo> && cd amc-logistics
composer install --no-dev --optimize-autoloader   # PHP deps
npm ci                                             # JS deps (lockfile-exact)
npm run type-check                                 # tsc --noEmit, must be 0 errors
npm run build                                      # Vite -> public/build
php artisan key:generate                           # first install only
php artisan migrate --force                        # apply schema
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

A redeploy of code-only changes still needs `npm run build` whenever
`resources/js` or `resources/css` changed, and `php artisan migrate --force`
whenever a migration was added. Run `php artisan optimize:clear` after a deploy
if cached config/routes went stale.

---

## 3. Scheduler (already wired — just add the cron)

All scheduled commands are defined in `bootstrap/app.php` (`->withSchedule()`):
Fleeti telemetry sync, live dispatch polling, maintenance/checklist alerts,
hub detection, trip-segment rebuild, off-hours movement, ticket reconciliation,
telemetry compaction. They run **only** if the Laravel scheduler is invoked
once a minute. Add a single cron entry on the server:

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Verify with `php artisan schedule:list`.

---

## 4. Queue migration — Phase 1 infrastructure task

**Current state (Phase 0): `QUEUE_CONNECTION=sync`.** Jobs run inline inside the
HTTP request. This is correct until a persistent worker exists — do **not**
switch to `database` without one, or queued work (driver WhatsApp dispatch,
mail) would be enqueued and never processed.

### What is queued

- `App\Jobs\SendDispatchWhatsappJob` (`ShouldQueue`) — driver dispatch
  notifications. `$tries = 3`, `$backoff = [10, 60, 300]` seconds.
- Mail (`InvitationMail`, etc.) currently sends synchronously.

### Driver-compatibility check (done)

The `jobs`, `job_batches` and `failed_jobs` tables already exist
(`2026_05_20_160200_create_jobs_tables`, `2019_..._create_failed_jobs_table`),
so the `database` driver is ready schema-wise. `SendDispatchWhatsappJob`
serialises only a scalar `dispatchId` and re-loads the model in `handle()`, so
it is safe to queue.

### Switch-over procedure (only after a worker is deployed)

1. Provision a persistent worker (see below) and confirm it is running.
2. Set `QUEUE_CONNECTION=database` in the server `.env`.
3. `php artisan config:cache`.
4. Restart the worker so it picks up the new connection.

### Worker process

Run under a supervisor so it auto-restarts on crash/deploy.

**Supervisor** (`/etc/supervisor/conf.d/amc-queue.conf`):

```ini
[program:amc-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=3600
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start amc-queue:*
```

If the host has no Supervisor/systemd (e.g. restricted shared hosting), keep
`sync`, or use a hosted queue (Redis/SQS) with a managed worker.

### Operate the queue

- **Deploys:** run `php artisan queue:restart` after each deploy so workers
  reload new code (a long-lived worker holds the old code in memory otherwise).
- **Monitor failures:** `php artisan queue:failed`. Retry with
  `php artisan queue:retry all`; purge with `php artisan queue:flush`.
- **Retry/timeout:** per-job `$tries`/`$backoff` as above; pass `--max-time`
  to recycle the worker periodically.

---

## 5. Caching

- `CACHE_DRIVER=file` is fine for a single server. For multi-server or heavy
  load, move cache + sessions to Redis (already configured in
  `config/cache.php`; set `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`).
- Always run `config:cache`, `route:cache`, `view:cache` on deploy; clear them
  with `php artisan optimize:clear` if behaviour looks stale.
