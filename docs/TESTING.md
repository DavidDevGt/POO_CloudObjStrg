# Testing Strategy — POO_CloudObjStrg

## 1. Philosophy

> "Tests exist to give engineers the confidence to change code without fear."

Every change to this codebase should be verifiable by the test suite before it reaches production. The strategy follows the classic test pyramid: many small fast unit tests at the base, fewer integration tests in the middle, and a thin smoke layer at the top.

---

## 2. Test Pyramid

```
        ╔══════════════╗
        ║    Smoke     ║  ← 14 tests — class loading, contracts, file presence
        ╠══════════════╣
        ║ Integration  ║  ← 4 tests  — real DB, full upload → sign flow
        ╠══════════════╣
        ║     Unit     ║  ← 30+ tests — isolated logic, PDO mocked
        ╚══════════════╝
```

---

## 3. Test Suites

### 3.1 Unit Tests (`tests/Unit/`)

Test individual class methods in complete isolation. All database calls are replaced with PHPUnit mock objects (`createMock(PDO::class)`).

| File                                   | Class Tested      | Scenarios Covered                                          |
|----------------------------------------|-------------------|------------------------------------------------------------|
| `Unit/Config/CsrfTest.php`             | `Config\Csrf`     | Token generation, uniqueness, validation, HTML field       |
| `Unit/Models/UploadTest.php`           | `Models\Upload`   | Filename generation, MIME validation, size validation, metadata truncation |
| `Unit/Models/UrlShortenerTest.php`     | `Models\UrlShortener` | Slug format, collision retry, exhaustion throw, URL construction |
| `Unit/Models/DocumentTest.php`         | `Models\Document` | findBySlug (found / not found), isExpired (null / past / future), getFilePath, logAccess |
| `Unit/Models/AutoDeleteTest.php`       | `Models\AutoDelete` | Transaction commit, rollback on error, physical file deletion, missing file tolerance |
| `Unit/Models/SignDocumentTest.php`     | `Models\SignDocument` | Returns insert ID, rejects invalid format, rejects oversized payload, wraps DB errors |

**Run unit tests only:**
```bash
composer test:unit
# or
./vendor/bin/phpunit --testsuite Unit
```

### 3.2 Smoke Tests (`tests/Smoke/`)

Verify the application can be loaded and its components instantiated. These tests catch class-loading failures, namespace typos, and broken constructors with zero infrastructure dependencies.

| Scenario                                         |
|--------------------------------------------------|
| All 7 domain classes are autoloaded successfully |
| Each class can be instantiated with a PDO mock   |
| CSRF token is non-empty and hex-formatted        |
| CSRF `field()` renders valid HTML                |
| `.env.example` file is present                  |
| `uploads/.htaccess` is present                  |
| Both SQL migration files exist                   |
| Slug format matches `[0-9a-f]{16}`               |

**Run smoke tests only:**
```bash
composer test:smoke
# or
./vendor/bin/phpunit --testsuite Smoke
```

### 3.3 Integration Tests (`tests/Integration/`)

Test end-to-end flows against a real MySQL database. These tests are marked `@group integration` and skipped automatically if the test database is unavailable.

| Scenario                                                        |
|-----------------------------------------------------------------|
| Full upload → metadata save → short URL → findBySlug retrieval  |
| `findBySlug` returns `null` for an unknown slug                 |
| Signature is saved and linked to the correct document           |
| `AutoDelete` deactivates documents older than the interval      |

**Database setup (one-time):**
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS pdf_store_test;"
mysql -u root pdf_store_test < migrations/001_create_tables.sql
mysql -u root pdf_store_test < migrations/002_add_indexes_cascade.sql
```

**Run integration tests:**
```bash
composer test:integration
# or
./vendor/bin/phpunit --testsuite Integration
```

---

## 4. Running All Tests

```bash
# All suites
composer test

# With verbose output
./vendor/bin/phpunit --testdox

# With code coverage (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-html coverage/
```

---

## 5. Coverage Targets

| Suite        | Coverage Target | Rationale                                        |
|--------------|-----------------|--------------------------------------------------|
| Unit         | ≥ 85%           | Business logic, validation, error paths          |
| Smoke        | N/A             | Contract tests — no coverage metric needed       |
| Integration  | N/A             | Covers happy path not reachable by unit mocks    |
| **Overall**  | **≥ 75%**       | Minimum bar for merging to `main`                |

---

## 6. Test Design Principles

### Dependency Injection for Testability
All model constructors accept an optional `?PDO $db = null` parameter. When `null`, the production singleton is used. In tests, a PHPUnit mock is injected — no real database required.

```php
// Production
$upload = new Upload();

// Test
$upload = new Upload($this->createMock(PDO::class), sys_get_temp_dir() . '/');
```

### Isolation
- Each test class extends `Tests\TestCase`, which clears `$_SESSION` in `setUp()` and `tearDown()`.
- Temporary directories are created per-test and cleaned up in `tearDown()`.
- No test writes to the real `uploads/` directory.

### Regression Guards
Specific regression tests document previously-fixed bugs:
- `testGenerateStoredNameIs32HexCharsPlusDotPdf` — guards `bin2hex(random_bytes(16))` format.
- `testValidateThrowsOnNonPdfMimeType` — guards against trusting `$_FILES['type']`.
- `testSaveSignatureThrowsOnInvalidFormat` — guards `data:image/png;base64,` requirement.

---

## 7. Naming Convention

```
test{WhatIsBeingTested}{ExpectedBehavior}()
```

Examples:
- `testValidateThrowsOnOversizedFile`
- `testFindBySlugReturnsNullWhenNotFound`
- `testDeleteExpiredDocumentsRollsBackOnDbError`

---

## 8. CI/CD Integration

Add this step to your GitHub Actions / GitLab CI pipeline:

```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]
jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: xdebug
      - run: composer install --no-interaction
      - run: cp .env.example .env
      - run: composer test:unit
      - run: composer test:smoke
      # Integration tests require a DB service — see docs for setup.
```

---

## 9. What Is NOT Tested (and Why)

| Area                          | Reason                                                     |
|-------------------------------|-------------------------------------------------------------|
| `move_uploaded_file()`        | PHP built-in; only works in real HTTP upload context        |
| HTML rendering of views       | Covered by manual testing and future E2E (Playwright/Panther) |
| CDN asset loading             | External service; tested via browser manually               |
| `Database::getConnection()`   | Covered by integration tests; unit tests mock PDO directly  |
| `AutoDelete` cron scheduling  | Infrastructure concern, not application logic               |

---

## 10. Adding New Tests

1. Create a file under the appropriate suite directory (`Unit/`, `Smoke/`, or `Integration/`).
2. Extend `Tests\TestCase` (not PHPUnit's directly).
3. Name the class matching the file name.
4. Use `createMock(PDO::class)` for unit tests.
5. Add `@group integration` annotation for tests requiring a real DB.
6. Run `composer test` before opening a pull request.
