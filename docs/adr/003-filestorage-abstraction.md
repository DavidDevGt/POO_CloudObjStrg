# ADR-003: FileStorageInterface Abstraction for File Operations

**Date:** 2025-01-01  
**Status:** Accepted

## Context

Document uploads are stored on local disk (`/uploads/`). The system is a single-node homelab deployment today, but S3-compatible storage (AWS S3, Cloudflare R2) is a planned Phase 4 feature. The `Upload` model originally called `move_uploaded_file()` directly, coupling business logic to the filesystem.

## Decision

Introduce a `FileStorageInterface` (in `contracts/`) with four methods: `store()`, `exists()`, `delete()`, `getAbsolutePath()`. The concrete `LocalFileStorage` implementation (in `storage/`) wraps the local filesystem. `Upload` accepts an optional `FileStorageInterface` via its constructor.

## Rationale

- **Swap without model changes** — To add S3 support, implement `S3FileStorage` and inject it. The `Upload`, `AutoDelete`, and `Document` models are unchanged.
- **Testability** — Unit tests can inject a mock `FileStorageInterface` instead of touching the real filesystem.
- **Separation of concerns** — File I/O logic (path resolution, permissions, error handling) lives in the storage adapter, not in the domain model.

## Consequences

- **Positive:** Phase 4 S3 migration is a drop-in adapter change. Unit tests run without filesystem access. Storage backends are independently testable.
- **Negative:** Minor indirection added for what is currently a single concrete implementation. Constructor arity of `Upload` increases.
- **Neutral:** `LocalFileStorage` resolves paths relative to `dirname(__DIR__) . '/uploads/'`, matching the previous hardcoded path exactly.
