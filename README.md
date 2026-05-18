# Claude Board

[![Tests](https://github.com/tvup/claude-board/actions/workflows/tests.yml/badge.svg)](https://github.com/tvup/claude-board/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/endpoint?url=https://tvup.github.io/claude-board/badge.json)](https://tvup.github.io/claude-board/)

Real-time telemetry dashboard for [Claude Code](https://docs.anthropic.com/en/docs/claude-code). Receives OpenTelemetry (OTLP) metrics and logs, stores them in SQLite, and displays session activity, token usage, cost estimates, tool performance, and more — via a web UI and a terminal CLI.

Built with Laravel 12, Tailwind CSS v4, and vanilla JavaScript. No frontend framework required.

<table>
<tr>
<td width="50%">
<strong>Web Dashboard</strong><br>
<img src="docs/screenshots/dashboard.png" alt="Dashboard overview" width="100%">
</td>
<td width="50%">
<strong>Session Detail</strong><br>
<img src="docs/screenshots/session-detail.png" alt="Session detail view" width="100%">
</td>
</tr>
<tr>
<td width="50%">
<strong>Terminal CLI</strong><br>
<img src="docs/screenshots/cli-dashboard.png" alt="CLI dashboard" width="100%">
</td>
<td width="50%"></td>
</tr>
</table>

## Features

- **OTLP receiver** — Ingests metrics and logs from Claude Code via standard OpenTelemetry HTTP/JSON protocol
- **Live web dashboard** — Auto-refreshing (5s) dark-themed UI with session overview, cost breakdown, token analysis, tool usage, and event stream
- **Session detail view** — Per-session metrics, events timeline, activity status with real-time polling, and session merging
- **Terminal CLI** — Full dashboard in your terminal with `php artisan dashboard:show` (supports `--watch` for live updates)
- **Billing awareness** — Distinguishes subscription (flat-rate) vs. API (pay-per-use) billing, configurable globally or per-project via OTLP resource attributes
- **Claude Usage panel** — Optional panel showing live rate limit consumption (5h, 7d, 7d Sonnet) and account balance, fetched from an external usage API. Each card shows: current utilisation %, time remaining in the period, and a pace marker indicating whether you are consuming tokens faster or slower than expected given how much of the window has elapsed
- **Connectivity error log** — Automatically records every failed dashboard poll (504s, network errors) with timestamp and HTTP status. View history at `/connectivity-errors` to diagnose recurring outages
- **Multi-language** — English and Danish (easily extensible)
- **Locale-aware formatting** — Numbers, currency, dates, and relative times adapt to the configured locale

## Architecture

```
Claude Code  ──[OTLP http/json]──▶  POST /v1/metrics, /v1/logs (OtlpController)
                                              │
                                              ▼
                                          SQLite DB
                                              │
                                 ┌────────────┼────────────┐
                                 │                         │
                      DashboardController          DashboardShow (CLI)
                                 │                         │
                                 └──── shared query ───────┘
                                    DashboardQueryService
```

## Quick Start

### Requirements

- PHP 8.4+
- Composer
- Node.js 18+ and npm

### Installation

```bash
git clone https://github.com/tvup/claude-board.git
cd claude-board
composer setup
```

This runs `composer install`, copies `.env.example` to `.env`, generates an app key, runs migrations, and builds frontend assets.

### Start the Development Server

```bash
composer dev
```

Starts the PHP server on `:8080`, queue worker, log viewer, and Vite dev server on `:5173`.

#### With Laravel Sail

```bash
sail up
```

Sail automatically starts the Vite dev server via a dedicated `node` service — HMR works out of the box on `:5173`.

Or start components individually:

```bash
php artisan serve --port=8080    # Web server
npm run dev                       # Vite (HMR for CSS/JS)
```

### Configure Claude Code to Send Telemetry

In the project where you use Claude Code, create or edit `.claude/settings.local.json`:

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

| Attribute | Description |
|-----------|-------------|
| `project.name` | Label shown in the dashboard for this project |
| `billing.model` | `subscription` (default) or `api` — controls how cost figures are labeled |

Open [http://localhost:8080](http://localhost:8080) and start a Claude Code session — data will appear within seconds.

## CLI Dashboard

```bash
php artisan dashboard:show                        # Summary dashboard
php artisan dashboard:show --watch                # Live mode (5s refresh)
php artisan dashboard:show --session=<ID>         # Session detail
php artisan dashboard:show --delete=<ID>          # Delete a session
php artisan dashboard:show --merge=<SOURCE>:<TARGET>  # Merge sessions
php artisan dashboard:show --reset                # Reset all data
```

## Development Simulator

When developing locally without real Claude Code telemetry, use the built-in simulator to generate realistic fake data:

```bash
php artisan dev:simulate                          # 2 sessions, normal speed
php artisan dev:simulate --sessions=4 --speed=2   # 4 sessions, double speed
php artisan dev:simulate --duration=0             # Run indefinitely
php artisan dev:simulate --projects=my-app        # Specific project name
```

With Laravel Sail, the endpoint is auto-detected:

```bash
sail artisan dev:simulate --sessions=4 --speed=2
```

The simulator sends standard OTLP payloads to the local server, exercising all dashboard features:

- **Sessions** with status indicators (active/idle)
- **Cost by model** — weighted distribution across Sonnet (70%), Opus (20%), Haiku (10%)
- **Token breakdown** — input, output, cache read, cache creation
- **Tool usage** — 8 tools with realistic success rates and durations
- **Recent events** — user prompts, API requests, tool decisions/results, errors (3-5%)
- **Session grouping** — 50% of completed sessions continue as new grouped sessions
- **Lines of code** — added/removed counts on Write/Edit tool results
- **Commits & PRs** — occasional commit (5%) and PR (2%) events

Start the server first with `composer dev` (or `sail up`), then run the simulator in a separate terminal.

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_LOCALE` | `en` | UI language: `en` or `da` |
| `CLAUDE_BILLING_MODEL` | `subscription` | Global billing mode: `subscription` or `api` |
| `APP_PORT` | `8080` | PHP server port |
| `VITE_PORT` | `5173` | Vite dev server port |
| `CLAUDE_USAGE_API_URL` | *(empty)* | URL of an external JSON API that provides Claude usage stats (rate limits, balance). Leave empty to hide the usage panel |
| `CLAUDE_USAGE_CACHE_TTL` | `20` | Seconds to cache the response from `CLAUDE_USAGE_API_URL`. Prevents blocking workers on every 5s poll |
| `CLAUDE_DASHBOARD_CACHE_TTL` | `5` | Seconds to cache the full dashboard data response. Increase to `30`–`60` on low-powered hardware (e.g. Raspberry Pi) to reduce query load |
| `CACHE_STORE` | `file` | Laravel cache driver. Use `file` (default) or `redis` for best performance. Avoid `database` — it competes with OTLP ingestion writes on the same SQLite file |
| `REDIS_CLIENT` | *(Laravel default)* | Set to `predis` to use the bundled `predis/predis` package (no PHP extension required) |
| `REDIS_HOST` | `127.0.0.1` | Redis hostname. Use the Docker service/container name when running in Docker Compose |

### Claude Usage Panel

Set `CLAUDE_USAGE_API_URL` to enable the usage panel. The URL must return a JSON object with the following shape (all fields optional):

```json
{
  "five_hour_usage_pct":            42.5,
  "five_hour_resets_at":            "2026-04-18T09:30:00Z",
  "seven_day_usage_pct":            18.0,
  "seven_day_resets_at":            "2026-04-22T00:00:00Z",
  "seven_day_sonnet_usage_pct":     55.3,
  "seven_day_sonnet_resets_at":     "2026-04-22T00:00:00Z",
  "balance_usd":                    12.50,
  "extra_usage_pct":                0.0
}
```

Each rate-limit card displays:
- **Utilisation bar** — colour-coded green → amber → red as the limit fills
- **Time-remaining bar** — grey bar showing how much of the current window has elapsed
- **Pace marker** — a `|--x--|` indicator showing whether usage is ahead of or behind the expected pace. The marker sits at centre (50 %) when usage exactly matches elapsed time; right of centre means over pace (amber/red), left means under pace (green)

### Per-Project Billing Model

The billing model can be set per project via the `billing.model` OTLP resource attribute. Per-session values override the global `CLAUDE_BILLING_MODEL` setting. This allows mixing subscription and API projects in the same dashboard.

## API Endpoints

### OTLP Ingestion

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/v1/metrics` | OTLP metrics ingestion |
| `POST` | `/v1/logs` | OTLP logs ingestion |

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/` | Web dashboard |
| `GET` | `/api/dashboard-data` | JSON polling endpoint |
| `GET` | `/sessions/{id}` | Session detail |
| `GET` | `/api/sessions/{id}/activity` | Session activity JSON |
| `DELETE` | `/sessions/{id}` | Delete session |
| `POST` | `/sessions/{id}/merge` | Merge sessions |
| `POST` | `/sessions/{id}/group` | Group sessions together |
| `POST` | `/sessions/{id}/ungroup` | Remove session from group |
| `DELETE` | `/reset` | Reset all data |
| `GET` | `/connectivity-errors` | Connectivity error log |
| `DELETE` | `/connectivity-errors` | Clear connectivity error log |
| `POST` | `/api/connectivity-error` | Log a connectivity error (called by dashboard JS) |

## Docker

Pre-built images are published to GitHub Container Registry on every push to `master`.

```bash
docker pull ghcr.io/tvup/claude-board:latest
```

Run with persistent SQLite storage:

```bash
docker run -d \
  --name claude-board \
  -p 8080:8080 \
  -v claude-board-data:/data \
  ghcr.io/tvup/claude-board:latest
```

Or with Docker Compose:

```yaml
services:
  claude-board:
    image: ghcr.io/tvup/claude-board:latest
    ports:
      - "8080:8080"
    volumes:
      - claude-board-data:/data
    environment:
      APP_LOCALE: en

volumes:
  claude-board-data:
```

Point Claude Code's `OTEL_EXPORTER_OTLP_ENDPOINT` at the host running the container.

## Tech Stack

- **Backend:** PHP 8.4+, Laravel 12
- **Database:** SQLite
- **Frontend:** Tailwind CSS v4, Vite 7, vanilla JavaScript
- **Protocol:** OpenTelemetry HTTP/JSON (OTLP)

## Adding a Language

1. Copy `lang/en/dashboard.php` to `lang/{locale}/dashboard.php`
2. Translate all values
3. Set `APP_LOCALE={locale}` in `.env`

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

Claude Board is designed as a **local development tool** with no built-in authentication. Do not expose it to the public internet without adding your own auth layer. See [SECURITY.md](SECURITY.md) for details and vulnerability reporting.

## Disclaimer

This project is not affiliated with, endorsed by, or officially connected to [Anthropic](https://www.anthropic.com/). "Claude" and "Claude Code" are trademarks of Anthropic, PBC. [OpenTelemetry](https://opentelemetry.io/) is a [CNCF](https://www.cncf.io/) project. This application simply implements the standard OTLP receiver protocol to display telemetry data that Claude Code can optionally emit.

## Built With

This project was designed and built collaboratively by [Torben](mailto:contact@torbenit.dk) and [Claude Code](https://docs.anthropic.com/en/docs/claude-code) (Anthropic's AI coding agent). The vast majority of the code — architecture, OTLP parsing, dashboard UI, CLI, security hardening, tests, and documentation — was authored by Claude Code under Torben's direction and review.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE](LICENSE) for the full text.

Copyright (c) 2026 [Torben IT ApS](mailto:contact@torbenit.dk) (CVR 39630605)
