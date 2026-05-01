# ADR-005: Structured JSON Logging via error_log()

**Date:** 2025-01-01  
**Status:** Accepted

## Context

The system needs application-level logging for security events (login failures, rate limit hits), upload/sign audit trails, and error diagnostics. Options evaluated:

1. **Monolog** — the PHP de-facto logging library (PSR-3 compliant).
2. **Custom structured logger writing to `error_log()`** — JSON lines emitted through PHP's built-in log channel.
3. **Plain `error_log()` with string messages** — unstructured, not machine-parseable.

## Decision

Use a **custom `Config\Logger` class** that serializes log entries as JSON and emits them via `error_log()`. No third-party logging library is introduced.

## Rationale

- **Zero dependencies** — Monolog is excellent but adds ~30 transitive files. For a single-node homelab system, a 60-line custom class suffices.
- **Machine-parseable from Day 1** — JSON lines can be fed directly into `jq`, Loki, or any log aggregator without a parser configuration step.
- **Leverages existing PHP log routing** — `error_log()` already writes to the path configured in `php.ini` (`error_log` directive). Nginx and systemd-journald capture it automatically.
- **Request context included** — Every log entry carries `method`, `path`, and `ip` from the current request, eliminating the need to correlate across log files.

## Consequences

- **Positive:** No Composer dependency, consistent JSON format across all log levels, zero-config integration with existing PHP-FPM log pipeline.
- **Negative:** Not PSR-3 compliant — cannot be swapped for Monolog without updating call sites. Acceptable trade-off given the scope.
- **Neutral:** To ship logs to a remote sink (Datadog, Loki), configure `error_log` in `php.ini` to write to a file, then use a log shipper (Promtail, Vector) to tail that file. The JSON format is already compatible.
