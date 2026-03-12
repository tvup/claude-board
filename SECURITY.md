# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| latest  | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in Claude Board, please report it responsibly.

**Do NOT open a public GitHub issue for security vulnerabilities.**

Instead, please send an email to:

**contact@torbenit.dk**

Include the following in your report:

- A description of the vulnerability
- Steps to reproduce the issue
- Potential impact
- Suggested fix (if any)

We will acknowledge receipt within **48 hours** and aim to provide a fix or mitigation plan within **7 days** for critical issues.

## Scope

This policy covers the Claude Board application itself. It does **not** cover:

- The OpenTelemetry protocol or its libraries
- Claude Code or any Anthropic products
- Third-party dependencies (report those to their respective maintainers)

## Security Considerations

Claude Board is designed to run as a **local development tool**. Please be aware of the following:

- **No authentication**: The OTLP endpoints and dashboard have no built-in authentication. Do not expose Claude Board to the public internet without adding your own auth layer (e.g., reverse proxy with basic auth).
- **SQLite database**: Telemetry data is stored locally in an SQLite file. Ensure appropriate file permissions on the database.
- **Telemetry data**: Session data may contain user emails, project names, and usage patterns. Handle the database file accordingly.
- **No TLS**: The default configuration uses plain HTTP. Use a reverse proxy with TLS if running over a network.

## Best Practices

1. Run Claude Board on `localhost` only (the default configuration)
2. Do not commit your `.env` file to version control
3. Keep dependencies up to date (`composer update`, `npm update`)
4. Review the telemetry data your Claude Code instances are sending

## Acknowledgments

We appreciate the security research community's efforts in responsibly disclosing vulnerabilities. Contributors who report valid security issues will be acknowledged here (with permission).
