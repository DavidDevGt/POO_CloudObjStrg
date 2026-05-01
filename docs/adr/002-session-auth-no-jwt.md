# ADR-002: Session-Based Authentication (No JWT)

**Date:** 2025-01-01  
**Status:** Accepted

## Context

The system needs to authenticate document owners for upload, dashboard, and account management. Two primary approaches were evaluated:

1. **JWT / API tokens** — stateless tokens signed with a secret, stored client-side.
2. **Server-side sessions** — PHP `$_SESSION` backed by file or DB storage, `session_id` in a cookie.

## Decision

We use **PHP server-side sessions** with `session_regenerate_id(true)` on every login.

## Rationale

- **Zero new dependencies** — PHP sessions are built-in. JWT would require a signing library and token refresh logic.
- **Instant revocation** — Destroying the session file immediately invalidates the user's session. JWTs remain valid until expiry unless a blocklist is maintained.
- **CSRF synergy** — The existing `Csrf` class already stores its token in `$_SESSION`. Auth and CSRF share the same session lifecycle with no added complexity.
- **SameSite=Lax + HttpOnly cookies** — The session cookie is never accessible to JavaScript and is not sent on cross-origin requests, making CSRF and XSS session-theft infeasible without additional token overhead.

## Consequences

- **Positive:** Simple implementation, immediate logout, no token refresh flow, compatible with existing CSRF infrastructure.
- **Negative:** Not stateless — the server must store session files (or switch to a session store). Horizontal scaling requires sticky sessions or a shared session store (Redis, Phase 4).
- **Neutral:** `session_regenerate_id(true)` on login prevents session fixation. `session_destroy()` on logout clears all state.
