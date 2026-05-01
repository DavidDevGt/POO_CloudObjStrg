# ADR-004: Rate Limiting with APCu / File-Based Fallback

**Date:** 2025-01-01  
**Status:** Accepted

## Context

The system must protect against brute-force login, registration spam, upload abuse, and signature flooding. Production-grade rate limiting typically uses Redis (sorted sets for sliding window) or a dedicated service. The homelab deployment has no Redis.

## Decision

Implement a sliding-window rate limiter in `config/RateLimit.php` that:
1. Uses **APCu** (in-process shared memory) when available and enabled.
2. Falls back to **JSON temp files** (one file per action+key) when APCu is absent.

Both backends maintain a list of timestamps within the current window, pruning expired entries on each check.

## Rationale

- **No extra infrastructure** — APCu is enabled in the PHP-FPM OPcache config already present on the server. No Redis install required.
- **Zero dependencies** — No Composer package needed; pure PHP.
- **Graceful degradation** — File-based fallback ensures rate limiting works even in environments without APCu (e.g., CI, development VMs).
- **Per-endpoint tuning** — Each call site passes its own `$action`, `$key`, `$max`, and `$window`, making limits independently configurable.

## Consequences

- **Positive:** Works on Day 1 with the existing server setup. Limits are enforced across all PHP-FPM workers via APCu shared memory.
- **Negative:** APCu is per-node; on a multi-node deployment the limit is effectively `max × node_count`. Redis would be required for accurate multi-node rate limiting (Phase 4).
- **Negative:** File-based fallback has race conditions under very high concurrency. Acceptable for homelab traffic; unacceptable for high-scale production.
- **Neutral:** The `RateLimit::check()` interface is identical regardless of backend, so replacing with Redis is a backend swap, not an API change.
