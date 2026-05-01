# ADR-001: Shared Schema Multi-Tenancy

**Date:** 2025-01-01  
**Status:** Accepted

## Context

The system needs to support multiple users (document owners) while keeping the signing flow anonymous. Three common multi-tenancy approaches were considered:

1. **Separate database per tenant** — each user gets their own MySQL database.
2. **Separate schema per tenant** — each user gets a distinct schema within MySQL (not natively supported in MySQL the same way as Postgres).
3. **Shared schema with `user_id` FK** — a single schema where every tenant row includes a `user_id` column.

## Decision

We use **shared schema multi-tenancy**: all tenant data lives in the same tables, and every document row carries a nullable `user_id` foreign key that references `usuarios`.

## Rationale

- **Operational simplicity** — One MySQL database, one connection pool. No dynamic database provisioning at registration time.
- **Backward compatibility** — `user_id` is nullable, so anonymous/legacy rows coexist with owned rows without a data migration.
- **Scale target** — Up to ~100,000 users is comfortable in a shared schema with proper indexing (`idx_user_active` on `documentos`).
- **Migratable** — If per-tenant isolation becomes a requirement (compliance, performance), rows can be exported to per-tenant databases with a one-time ETL. The `FileStorageInterface` abstraction makes the file side equally migratable.

## Consequences

- **Positive:** Zero-friction onboarding (no new DB per signup), simpler queries, straightforward backups.
- **Negative:** A bug in the ownership check (`WHERE user_id = :uid`) could expose one tenant's documents to another. Mitigated by always including `user_id` in every owner-facing query and covering this with integration tests.
- **Neutral:** Full-table scans are prevented by the `idx_user_active (user_id, active)` composite index on `documentos`.
