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
│       ├── TelemetryServiceTest.php        # Delete, merge, reset, group/ungroup operations
│       └── VersionServiceTest.php          # Version resolution (.version file, git, fallback)
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
- `app/Services/TelemetryService.php` — Session management (delete, merge, reset, group/ungroup)
- `app/Services/VersionService.php` — Version resolution (.version file → git describe → "dev")
- `app/Console/Commands/DashboardShow.php` — Terminal dashboard command

## Critical Patterns

**Event name dual-format:** Claude Code sends event names without prefix (e.g., `tool_result`, `api_request`) but the codebase historically used `claude_code.` prefixed names. `DashboardQueryService::eventQuery()` matches both formats. Metric names DO have the `claude_code.` prefix (e.g., `claude_code.cost.usage`).

**Session attribute extraction:** `session.id` and other session metadata come from dataPoint/logRecord attributes, NOT resource attributes. `OtlpController` merges the first dataPoint's attributes with resource attributes before upserting the session. See `SESSION_META_KEYS` constant.

**Project name auto-detection:** Claude Code doesn't send project name natively via OTLP. A SessionStart hook (`hooks/session-project-name.sh`) auto-detects `project_name` from `basename(cwd)` and `hostname` from `hostname`, sending them to the `/api/sessions/{session}/project` endpoint. The hook fires before the first OTLP export, so `OtlpController::upsertSession()` checks a pending cache (`pending_session_meta:{sessionId}`) for hook data when creating new sessions. OTLP `project.name` from `OTEL_RESOURCE_ATTRIBUTES` takes precedence if set.

**Session grouping:** Sessions are auto-grouped by `project_name` + user identity (`user_id`/`user_email`). No time window — all sessions from the same user+project share a group. `project_name` is required for auto-grouping; sessions without it get no group.

## Database

SQLite (`database/database.sqlite`). Three telemetry tables with cascade deletes:
- `telemetry_sessions` — session metadata (ULID primary key, unique `session_id`, includes `hostname`)
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
- `POST /sessions/{session}/group` — Group sessions together
- `POST /sessions/{session}/ungroup` — Remove session from group
- `POST /api/sessions/{session}/project` — Set project name (hook integration)
- `GET /api/sessions/{session}/activity` — Session activity JSON
- `DELETE /reset` — Reset all data

## Docker Deployment

Pre-built multi-arch images (amd64 + arm64) are published to `ghcr.io/tvup/claude-board` via GitHub Actions on every push to master. Build files live in `.github/docker/` (Dockerfile, entrypoint.sh). The `.version` file is baked into the image with the git SHA at build time.

Production deploy uses `compose.prod.yaml` on a Raspberry Pi with Watchtower for automatic image updates.

**Version display:** `VersionService` resolves version for the footer: `.version` file (Docker) → `git describe` (local dev) → `"dev"` fallback. Full 40-char SHAs are truncated to 7 chars.

## Frontend

Tailwind CSS v4 with custom cyberpunk theme defined in `resources/css/app.css` via `@theme`. Dark theme layout. Auto-refresh uses vanilla JS fetch with `data-field` attribute DOM updates (no full page reload).

**Session grouping from dashboard:** The "gruppér" button uses a two-click JS flow (select source → click target) that POSTs to `/sessions/{session}/group`. State persists across auto-refresh cycles.

## Configuring Claude Code to Send Telemetry

### Quick install (recommended)

Run the install script to configure everything automatically:
```bash
bash hooks/install.sh https://your-claude-board-url
# or via curl (no clone needed):
curl -sSL https://raw.githubusercontent.com/tvup/claude-board/master/hooks/install.sh | bash -s -- https://your-claude-board-url
```

This sets up:
- SessionStart hook for auto project name + hostname detection
- OTEL telemetry export (metrics + events)
- `CLAUDE_BOARD_URL` env var

### Manual setup

Add to `~/.claude/settings.json` (global, applies to all projects):
```json
{
  "env": {
    "CLAUDE_CODE_ENABLE_TELEMETRY": "1",
    "CLAUDE_BOARD_URL": "https://your-claude-board-url",
    "OTEL_METRICS_EXPORTER": "otlp",
    "OTEL_LOGS_EXPORTER": "otlp",
    "OTEL_EXPORTER_OTLP_PROTOCOL": "http/json",
    "OTEL_EXPORTER_OTLP_ENDPOINT": "https://your-claude-board-url"
  },
  "hooks": {
    "SessionStart": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "bash ~/.claude/hooks/session-project-name.sh",
            "timeout": 5
          }
        ]
      }
    ]
  }
}
```

Copy `hooks/session-project-name.sh` to `~/.claude/hooks/` and make it executable.

`OTEL_RESOURCE_ATTRIBUTES=project.name=X` is no longer needed per project — the hook auto-detects it from the working directory.
