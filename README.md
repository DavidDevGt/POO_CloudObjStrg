# POO_CloudObjStrg

Secure PDF management SaaS built with PHP 8.1+.  
Register, upload PDFs, generate short links, and collect digital signatures — with per-user isolation and production-grade security.

---

## Features

- **User accounts** — registration, login, logout, session-based auth
- **Per-user document isolation** — each user sees only their own documents
- **Dashboard** — list documents, view signature count, copy share link, delete
- **Account management** — update display name, change password
- Secure PDF upload with real MIME validation (`finfo`)
- Atomic upload: file move + DB insert in a single transaction
- Cryptographically random stored filenames (`bin2hex(random_bytes(16))`)
- Short link generation with collision-safe slugs
- PDF viewer + Signature Pad for digital signatures (anonymous — no account needed)
- CSRF protection on all state-changing requests
- Document auto-expiration with physical file cleanup
- Full audit log (`acciones` table)
- `FileStorageInterface` abstraction (LocalAdapter included, S3-ready)
- PHPUnit 10 test suite: Unit · Smoke · Integration (80+ tests)

---

## Requirements

- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Composer

---

## Quick Start

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials and base URL:

```dotenv
DB_HOST=localhost
DB_NAME=pdf_store
DB_USER=root
DB_PASS=your_password

APP_ENV=development
BASE_URL=http://localhost/public

UPLOAD_MAX_SIZE=5000000
AUTO_DELETE_HOURS=12
BCRYPT_COST=12
```

### 3. Create and migrate the database

```bash
# Creates the database if it doesn't exist and applies all three migrations.
php migrations/migrate_data.php
```

Or apply manually:

```bash
mysql -u root -p pdf_store < migrations/001_create_tables.sql
mysql -u root -p pdf_store < migrations/002_add_indexes_cascade.sql
mysql -u root -p pdf_store < migrations/003_add_users_multitenancy.sql
```

### 4. Configure the web server

Point your web server document root to the `public/` directory.

**Apache** — add to your VirtualHost:
```apache
DocumentRoot /path/to/POO_CloudObjStrg/public
<Directory /path/to/POO_CloudObjStrg/public>
    AllowOverride All
</Directory>
```

**PHP built-in server** (development only):
```bash
php -S localhost:8000 -t public/
```

### 5. Ensure `uploads/` is writable

```bash
chmod 755 uploads/
```

The `uploads/.htaccess` already blocks direct HTTP access to all files in that directory.

---

## Usage

### Owner flow
1. Open `http://localhost:8000/register.php` — create an account.
2. Log in at `/login.php`.
3. Select a PDF (max 5 MB) and click **Subir archivo**.
4. Copy the generated short link from the dashboard and share it.
5. View document status, signature count, and manage documents from `/dashboard.php`.
6. Update your name or password at `/account.php`.

### Signer flow (no account needed)
1. Open the shared short link.
2. View the PDF, draw a signature, and click **Guardar firma**.

---

## Running Tests

```bash
# Unit + Smoke (no database needed)
composer test:unit
composer test:smoke

# All suites (integration requires the test DB)
composer test
```

For integration tests, create the test database first:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS pdf_store_test;"
mysql -u root pdf_store_test < migrations/001_create_tables.sql
mysql -u root pdf_store_test < migrations/002_add_indexes_cascade.sql
mysql -u root pdf_store_test < migrations/003_add_users_multitenancy.sql
```

See [docs/TESTING.md](docs/TESTING.md) for the full testing strategy.

CI is configured via GitHub Actions (`.github/workflows/tests.yml`) to run `composer test:unit` and `composer test:smoke` on pull requests and pushes to `main`.


---

## Documentation

| Document                           | Contents                                  |
|------------------------------------|-------------------------------------------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design, component diagram, security model |
| [docs/API.md](docs/API.md)         | All endpoints with request/response examples |
| [docs/TESTING.md](docs/TESTING.md) | Testing strategy, pyramid, coverage targets |
| [docs/RUNBOOK_OPERATIONS.md](docs/RUNBOOK_OPERATIONS.md) | Production operations runbook (deploy, monitoring, backup/restore, rollback) |

---

## Project Structure

```
├── config/              Database singleton, CSRF service, Auth singleton, bootstrap
├── contracts/           Interfaces (FileStorageInterface)
├── docs/                Technical documentation
├── migrations/          SQL migrations + CLI runner
├── models/              Domain model classes (Upload, Document, User, …)
├── public/              Web root — only this directory is web-accessible
│   ├── api/             JSON API endpoints (auth, document, account)
│   └── partials/        Shared HTML partials (error page)
├── storage/             FileStorage adapters (LocalFileStorage)
├── tests/               PHPUnit test suites
│   ├── Unit/            Isolated tests with PDO mocks
│   ├── Smoke/           Class-loading and contract checks
│   └── Integration/     End-to-end tests (require real DB)
└── uploads/             PDF storage — HTTP access blocked via .htaccess
```

---

## Version

`0.3.0` — Phase 3: SaaS — multi-tenancy, auth, user dashboard, FileStorage abstraction.
