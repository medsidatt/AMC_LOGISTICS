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

## 2. Build & release — compiled assets are COMMITTED

> **The Infomaniak deployment target runs only `git pull` — it has no Node.js
> and never runs `npm run build`.** Therefore the Vite output **`public/build/`
> (including `manifest.json`) is intentionally version-controlled.** This is the
> project's established strategy; removing/ignoring it breaks production with
> `ViteManifestNotFoundException: public/build/manifest.json`. **Do not
> re-ignore `public/build` during future cleanups.**

### Whoever has Node builds locally, then commits the result

After any change to `resources/js` or `resources/css`, the **developer** (who
has Node) rebuilds and commits the assets so production receives them on the
next `git pull`:

```bash
npm ci                 # JS deps (lockfile-exact)
npm run type-check     # tsc --noEmit, must be 0 errors
npm run build          # Vite -> public/build (manifest.json + assets/)
git add public/build   # commit the compiled assets with the code change
```

Keep code and built assets in the **same commit** so they never go out of sync
(no stale manifest in production).

### On the Infomaniak server (deploy)

```bash
git pull                         # brings PHP code AND public/build together
composer install --no-dev --optimize-autoloader
php artisan migrate --force      # when a migration was added
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan optimize:clear       # if cached config/routes went stale
```

No `npm`/Node step runs on the server — the manifest is already in the repo.

> If deployment later moves to a host with Node.js or CI/CD, the strategy can be
> revisited then (gitignore `public/build` and add `npm ci && npm run build` to
> the pipeline) — but only once that build step actually exists.

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

## 4. Background queue (database driver + cron worker)

**`QUEUE_CONNECTION=database`.** Long-running work runs as jobs drained by a
**cron-driven worker** — no Supervisor/Redis/Horizon (the Infomaniak target has
no persistent process control). The `jobs`, `job_batches` and `failed_jobs`
tables already exist.

### The worker is the existing cron

No extra process to run. `bootstrap/app.php` (`->withSchedule`) schedules, every
minute:

```
queue:work database --stop-when-empty --max-time=55 --tries=3 --sleep=1   (withoutOverlapping)
```

So the **single** production cron entry already documented in §3
(`* * * * * php artisan schedule:run`) both runs scheduled commands **and**
drains the queue — a short-lived worker that processes pending jobs and exits
within the minute. `withoutOverlapping` prevents two workers; the explicit
`database` connection means a `sync` rollback leaves it a harmless no-op.

### What is queued (Phase 1, incremental)

- ✅ `App\Jobs\SendDispatchWhatsappJob` — driver dispatch notifications.
  `$tries=3`, `$backoff=[10,60,300]`, `$timeout=60` (< the 90s `retry_after`,
  so a hung Meta call is killed before re-reservation → no double-send).
  Idempotent: refuses to send if the row is already sent/delivered/read.
- ⏳ Excel import — next.
- ⏳ SharePoint upload — after Excel.
- Mail + notifications stay **synchronous** this phase.
- OpenAI analysis stays synchronous (deferred to a later async-AI phase).

### Deploy order (must not invert)

1. Deploy the code (the scheduled worker ships in `bootstrap/app.php`).
2. Confirm the cron (`schedule:run`) is live; `php artisan schedule:list` shows
   the `queue:work` entry.
3. Set `QUEUE_CONNECTION=database` in the server `.env`, then `config:cache`.
   *(Setting `database` before the worker/cron exists would enqueue jobs that
   never run — flip it last.)*

### Operate / monitor

- **Failures:** `php artisan queue:failed`; retry `php artisan queue:retry all`;
  purge `php artisan queue:flush`. Job outcomes also log to `storage/logs`.
- **After each deploy:** `php artisan queue:restart` (and `config:cache`).

### Rollback (instant, code-free)

Set `QUEUE_CONNECTION=sync` + `php artisan config:cache`. Every `ShouldQueue`
job then runs inline exactly as before — no code revert needed.

---

## 5. Caching

- `CACHE_DRIVER=file` is fine for a single server. For multi-server or heavy
  load, move cache + sessions to Redis (already configured in
  `config/cache.php`; set `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`).
- Always run `config:cache`, `route:cache`, `view:cache` on deploy; clear them
  with `php artisan optimize:clear` if behaviour looks stale.
