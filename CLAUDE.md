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

**Session grouping:** Sessions are auto-grouped by `project_name` + user identity (`user_id`/`user_email`). No time window — all sessions from the same user+project share a group. `project_name` is required for auto-grouping; sessions without it get no group. Sessions without hook or OTLP project name are labeled `'background'`. Background sessions are only grouped with peers that have been background for >5 minutes (`session_group_window` config), giving the hook time to deliver a real project name before grouping occurs.

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.5
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v13
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`, `vendor/bin/sail artisan tinker --execute "..."`).
- Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `vendor/bin/sail artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `vendor/bin/sail artisan config:show [key]`.
- To inspect routes, run `vendor/bin/sail artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
