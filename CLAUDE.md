# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is Claude Board?

An OTLP (OpenTelemetry Protocol) receiver and real-time dashboard for Claude Code telemetry. Ingests metrics and logs from Claude Code sessions and displays them via a web UI (Blade + Tailwind) and a terminal CLI command.

## Commands

```bash
composer setup          # Full setup: install deps, copy .env, generate key, migrate, build assets
composer dev            # Start dev environment (PHP server :8080 + queue + logs + Vite :5173)
sail up                 # Start Sail (includes Vite dev server via node service)
composer test           # Run PHPUnit tests (clears config cache first)
npm run dev             # Vite dev server only
npm run build           # Production asset build
php artisan dashboard:show              # Terminal dashboard
php artisan dashboard:show --watch      # Live-updating terminal dashboard (5s refresh)
php artisan dashboard:show --session=ID # Session detail view
php artisan dashboard:show --delete=ID  # Delete a session
php artisan dashboard:show --merge=SRC:TGT # Merge two sessions
php artisan dashboard:show --reset      # Reset all telemetry data
php artisan dev:simulate              # Simulate fake telemetry (2 sessions, normal speed)
php artisan dev:simulate --sessions=4 --speed=2  # 4 sessions, double speed
sail artisan dev:simulate             # Auto-detects Sail endpoint (http://laravel.test)
```

## Testing

Tests use in-memory SQLite (`:memory:`) so production data is never touched. All database tests use `RefreshDatabase`.

```
tests/
├── Unit/
│   ├── Helpers/FormatTest.php              # Number/currency/date formatting + locale
│   ├── Models/TelemetrySessionTest.php     # ULID, relationships, cascade deletes
│   └── Services/
│       ├── DashboardQueryServiceTest.php   # Query methods, dual-format event names
│       └── TelemetryServiceTest.php        # Delete, merge, reset operations
└── Feature/
    ├── DashboardTest.php                   # All dashboard routes + data integrity
    └── OtlpIngestionTest.php               # OTLP metrics/logs ingestion + session upsert
```

## Architecture

```
Claude Code  --[OTLP http/json]--> POST /v1/metrics, /v1/logs (OtlpController)
                                          |
                                          v
                                       SQLite DB
                                          |
                             +------------+------------+
                             |                         |
                  DashboardController          DashboardShow (Artisan)
                       |                              |
                       +--------- shared ------------>+
                              DashboardQueryService
```

**Key files:**
- `app/Http/Controllers/OtlpController.php` — OTLP protocol parser and ingestion
- `app/Http/Controllers/DashboardController.php` — Web dashboard + JSON API
- `app/Services/DashboardQueryService.php` — Shared query logic (used by both web and CLI)
- `app/Services/TelemetryService.php` — Session management (delete, merge, reset)
- `app/Console/Commands/DashboardShow.php` — Terminal dashboard command

## Critical Patterns

**Event name dual-format:** Claude Code sends event names without prefix (e.g., `tool_result`, `api_request`) but the codebase historically used `claude_code.` prefixed names. `DashboardQueryService::eventQuery()` matches both formats. Metric names DO have the `claude_code.` prefix (e.g., `claude_code.cost.usage`).

**Session attribute extraction:** `session.id` and other session metadata come from dataPoint/logRecord attributes, NOT resource attributes. `OtlpController` merges the first dataPoint's attributes with resource attributes before upserting the session. See `SESSION_META_KEYS` constant.

**Project name injection:** Claude Code doesn't send project name natively. Users set `OTEL_RESOURCE_ATTRIBUTES=project.name=my-project` in their project's `.claude/settings.local.json` to include it.

## Database

SQLite (`database/database.sqlite`). Three telemetry tables with cascade deletes:
- `telemetry_sessions` — session metadata (ULID primary key, unique `session_id`)
- `telemetry_metrics` — raw metric values with JSON attributes (FK → sessions)
- `telemetry_events` — structured log events with JSON attributes (FK → sessions)

Queries use SQLite `json_extract()` for attribute filtering and aggregation.

## Routes

**API** (`routes/api.php`, no prefix via `apiPrefix: ''`):
- `POST /v1/metrics` — OTLP metrics ingestion
- `POST /v1/logs` — OTLP logs ingestion

**Web** (`routes/web.php`):
- `GET /` — Dashboard
- `GET /api/dashboard-data` — JSON polling endpoint (5s auto-refresh)
- `GET /sessions/{session}` — Session detail
- `DELETE /sessions/{session}` — Delete session
- `POST /sessions/{session}/merge` — Merge sessions
- `GET /api/sessions/{session}/activity` — Session activity JSON
- `DELETE /reset` — Reset all data

## Frontend

Tailwind CSS v4 with custom cyberpunk theme defined in `resources/css/app.css` via `@theme`. Dark theme layout. Auto-refresh uses vanilla JS fetch with `data-field` attribute DOM updates (no full page reload).

## Configuring Claude Code to Send Telemetry

In the target project's `.claude/settings.local.json`:
```json
{
  "env": {
    "CLAUDE_CODE_ENABLE_TELEMETRY": "1",
    "OTEL_METRICS_EXPORTER": "otlp",
    "OTEL_LOGS_EXPORTER": "otlp",
    "OTEL_EXPORTER_OTLP_PROTOCOL": "http/json",
    "OTEL_EXPORTER_OTLP_ENDPOINT": "http://localhost:8080",
    "OTEL_METRIC_EXPORT_INTERVAL": "10000",
    "OTEL_RESOURCE_ATTRIBUTES": "project.name=my-project,billing.model=subscription"
  }
}
```
