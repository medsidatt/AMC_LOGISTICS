# AMC Fleet Platform — Roadmap

> Single source of truth for project status. **Update this file before every
> commit** that completes a feature, refactor, cleanup, or infra change:
> mark phases done, record the completing commit hash, add/remove technical
> debt, and refresh *Current Focus* / *Next Phase*. Keep it concise.

**Last updated:** 2026-06-25

---

## Current Focus
Production Phase 1 — infrastructure hardening (queues, test DB + CI, secret rotation, scheduler cron).

## Next Phase
Track B · Queues — migrate `sync → database` and stand up a persistent worker (`DEPLOYMENT.md §4`).

---

## Track A — Operations Platform
`Planning → Dispatch → Réalisation → Réconciliation → Analytics → Optimization`

| Area | Status | Milestone (commit) |
|---|---|---|
| Planning · Operations Calendar | ✅ | `f643a929` |
| Planning · Per-truck capacity model | ✅ | `e2d9f064` |
| Planning · Availability windows + engine | ✅ | `65177c7c` |
| Planning · Flat workflow nav (Planification→Répartition→Réalisation→Réconciliation) | ✅ | `bcfc99d1` |
| Planning · Full objective hierarchy on overview (no winner-takes-all) | ✅ | `d792d57a` |
| Planning · Confirm-before-overwrite + archived-objective reactivation | ✅ | `d057b3c8`, `effa1df7` |
| Réalisation · Redesign (operational briefing, per-truck realization) | ✅ | `bcfc99d1` |
| Réalisation · Hierarchical-mean reference estimation | ✅ | `04857584` |
| Réconciliation · Missing-ticket worklist + nightly reconcile | ✅ | (existing) |
| Analytics · Real `suspiciousDrivers` metric (de-fabricated) | ✅ | `39730420` |
| Planning · PeriodSwitcher on the overview (historical periods) | 🟡 backlog | — |
| Optimization · (rotation/route optimization) | ⚪ future | — |

## Track B — Infrastructure
`Production Deployment · Queues · Scheduler · Caching · Monitoring · Performance · Security`

| Area | Status | Milestone (commit) |
|---|---|---|
| Deployment · Committed build artifacts (Infomaniak, git-pull only) | ✅ | `a316fc29`, `af98ee45` |
| Build · `type-check` script + tsconfig fix | ✅ | `51672fb0` |
| Security · User suspension enforced server-side | ✅ | `98e6f30b` |
| Security · Phantom truck/driver guard (data integrity) | ✅ | `7b4d54d7` |
| Config · Safe `.env.example` + `DEPLOYMENT.md` | ✅ | `2ad1a259` |
| Queues · `sync → database` + worker | 🟠 pending | — |
| Scheduler · cron entry on server (`schedule:run`) | 🟠 pending (server) | — |
| Test infra · dedicated test DB + CI pipeline | 🟠 pending | — |
| Security · rotate leaked mail password + `AJAX_TOKEN`; drop hardcoded fallback | 🟠 pending (server) | — |
| Production `.env` · `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true` | 🟠 pending (server) | — |
| Caching · Redis for cache + sessions | ⚪ future | — |
| Monitoring · error/uptime/observability | ⚪ future | — |
| Performance · JS code-splitting (>500 KB chunk) | ⚪ future | — |

## Track C — Platform Architecture
`Shared Services · Authentication · Notifications · Documents · Email · SMS · WhatsApp · SharePoint · MCP · API Gateway`

| Area | Status | Notes |
|---|---|---|
| Authentication · Spatie permissions + Microsoft OAuth/SSO + suspension | ✅ mature | `98e6f30b` |
| Shared Services · Capacity / Achievement / Workspace / FleetIdentifier | ✅ mature | — |
| Notifications · in-app + WhatsApp dispatch job | ✅ | `SendDispatchWhatsappJob` |
| Documents · SharePoint storage + DomPDF | ✅ | — |
| Email · Office365 SMTP (InvitationMail) | ✅ sync | queue when Track B·Queues lands |
| WhatsApp · Business Cloud API (Meta) | 🟡 gated | needs Meta template/sender approval |
| SharePoint · Graph file access | ✅ | — |
| SMS | ⚪ not present | — |
| MCP / API Gateway · Sanctum + Swagger REST | ✅ basic | MCP not present |

---

## Technical Debt
- `config/app.php` ships a hardcoded `AJAX_TOKEN` fallback (`MySecretToken123`) — remove once prod sets a real token.
- Tests run against the **live dev DB** via `DatabaseTransactions` (no separate test DB).
- `operationsBadges` runs a count query per request for dispatch users — add caching.
- `redistributeOpenObjectives()` iterates objectives without an `active()` filter (touches archived rows) — review.
- Untracked `docs/audit/*` and `docs/backlog/*` — decide commit vs discard.

## Resolved Debt
- Build artifacts tracked then mis-ignored → settled on committed-artifacts strategy (`af98ee45`).
- Fabricated dashboard metric (`suspiciousDrivers: 0`) → real calculation (`39730420`).
- Stale planning tests vs flat nav → updated (`d1236463`).
- Objective overwrite silently replacing manual planning → confirm-before-overwrite (`d057b3c8`).
