# Contributing to Claude Board

Thank you for your interest in contributing to Claude Board! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Run the setup:

```bash
composer setup
```

This installs dependencies, copies `.env.example` to `.env`, generates an app key, runs migrations, and builds frontend assets.

4. Start the development environment:

```bash
composer dev
```

This starts the PHP server on port 8080, the Vite dev server on port 5173, the queue worker, and the log viewer.

## Development Workflow

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes

3. Run tests:
   ```bash
   composer test
   ```

4. Run code formatting:
   ```bash
   ./vendor/bin/pint
   ```

5. Build frontend assets to verify:
   ```bash
   npm run build
   ```

6. Commit your changes and open a pull request

## Pull Request Guidelines

- Keep PRs focused on a single change
- Write a clear description of what the PR does and why
- Include screenshots for UI changes
- Ensure all tests pass
- Follow the existing code style (Laravel Pint handles PHP formatting)

## Code Architecture

Before contributing, familiarize yourself with the architecture:

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
- `app/Http/Controllers/DashboardController.php` — Web dashboard and JSON API
- `app/Services/DashboardQueryService.php` — Shared query logic (web + CLI)
- `app/Console/Commands/DashboardShow.php` — Terminal dashboard command

## Areas Where Help Is Welcome

- Additional language translations (see `lang/` directory)
- Test coverage improvements
- Dashboard visualizations
- Documentation improvements
- Performance optimizations for large datasets
- Docker/container deployment guides

## Translations

Claude Board supports multiple languages. To add a new language:

1. Copy `lang/en/dashboard.php` to `lang/{locale}/dashboard.php`
2. Translate all string values
3. Add the locale to the `APP_LOCALE` comment in `.env.example`
4. Submit a PR

## Reporting Bugs

- Use GitHub Issues for bug reports
- Include steps to reproduce, expected behavior, and actual behavior
- Include your PHP version, Laravel version, and OS
- For security vulnerabilities, see [SECURITY.md](SECURITY.md)

## Code of Conduct

Be respectful and constructive. We are all here to build something useful together.

## License

By contributing to Claude Board, you agree that your contributions will be licensed under the [MIT License](LICENSE).

## Contact

- **Maintainer:** Torben IT ApS
- **Email:** contact@torbenit.dk
- **Issues:** GitHub Issues
