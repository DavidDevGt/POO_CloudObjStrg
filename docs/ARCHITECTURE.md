# Architecture — POO_CloudObjStrg

## 1. Overview

POO_CloudObjStrg is a PHP 8.1+ SaaS web application for secure PDF management.  
Users register, log in, upload PDFs, and share short links. Recipients view and digitally sign documents through those links without needing an account. Documents expire automatically after a configurable period.

---

## 2. Technology Stack

| Layer       | Technology                          | Version   |
|-------------|-------------------------------------|-----------|
| Runtime     | PHP                                 | ^8.1      |
| Database    | MySQL / MariaDB                     | 8.0+ / 10.6+ |
| DB Access   | PDO + prepared statements           | native    |
| Config      | vlucas/phpdotenv                    | ^5.6      |
| Frontend    | Bootstrap                           | 5.3       |
| Reactivity  | Alpine.js                           | 3.13      |
| Alerts      | SweetAlert2                         | 11        |
| PDF Viewer  | HTML `<iframe>` + download endpoint | —         |
| Signatures  | Signature Pad                       | 4.1       |
| Testing     | PHPUnit                             | ^10.5     |
| Mocking     | Mockery                             | ^1.6      |
| Autoloading | PSR-4 via Composer                  | —         |

---

## 3. Directory Structure

```
POO_CloudObjStrg/
├── config/
│   ├── bootstrap.php       Entry point: loads autoloader, dotenv, session, Auth
│   ├── Auth.php            Session-based auth singleton
│   ├── Csrf.php            CSRF token generation and validation
│   └── Database.php        PDO singleton factory
│
├── contracts/
│   └── FileStorageInterface.php   store / exists / delete / getAbsolutePath
│
├── docs/                   Technical documentation (this directory)
│
├── migrations/
│   ├── 001_create_tables.sql          Initial schema
│   ├── 002_add_indexes_cascade.sql    Phase 2: indexes + ON DELETE CASCADE + slug
│   ├── 003_add_users_multitenancy.sql Phase 3: usuarios table + user_id FKs
│   └── migrate_data.php    CLI migration runner
│
├── models/
│   ├── AutoDelete.php      Deactivates expired docs + deletes physical files
│   ├── Document.php        Fetches, serves, lists, and audits documents
│   ├── SignDocument.php    Persists digital signatures
│   ├── Upload.php          Validates and stores uploaded PDFs atomically
│   ├── UrlShortener.php    Generates collision-free short URLs
│   └── User.php            User CRUD — create, find, verify, update
│
├── public/                 Web root (only this directory should be web-accessible)
│   ├── api/
│   │   ├── account/
│   │   │   └── update.php     POST: update nombre / change password
│   │   ├── auth/
│   │   │   ├── login.php      POST: authenticate + start session
│   │   │   └── register.php   POST: create account + start session
│   │   └── document/
│   │       └── delete.php     POST: soft-delete a document
│   ├── partials/
│   │   └── error.php          Shared error page partial
│   ├── account.php         Account settings page
│   ├── dashboard.php       User document list
│   ├── download.php        Serves PDF file by slug (anonymous)
│   ├── edit_pdf.php        Document viewer + Signature Pad (anonymous)
│   ├── index.php           Upload UI (requires auth)
│   ├── login.php           Login page
│   ├── logout.php          Destroys session and redirects
│   ├── register.php        Registration page
│   ├── sign_endpoint.php   POST endpoint: saves signature (anonymous)
│   └── upload_endpoint.php POST endpoint: upload + short URL (requires auth)
│
├── storage/
│   └── LocalFileStorage.php   FileStorageInterface: filesystem implementation
│
├── tests/
│   ├── bootstrap.php       Test environment setup (sets $_ENV without .env)
│   ├── TestCase.php        Base class with shared helpers
│   ├── Integration/        Tests requiring a real database
│   ├── Smoke/              Class-loading and contract sanity checks
│   └── Unit/               Isolated unit tests with PDO mocks
│
├── uploads/                PDF storage — NOT web-accessible (see .htaccess)
│   ├── .htaccess           Denies all HTTP access
│   └── .gitkeep
│
├── .env.example            Template for required environment variables
├── .gitignore
├── composer.json
└── phpunit.xml
```

---

## 4. Component Interactions

```
Browser
  │
  ├─► GET  /login.php / register.php ──────────────► Auth forms (CSRF embedded)
  │
  ├─► POST /api/auth/register.php
  │         │ Csrf::validate()
  │         │ User::emailExists()   → 409 if duplicate
  │         │ User::create()        → BCrypt hash
  │         │ Auth::login($id)      → session_regenerate_id()
  │         └─► JSON { success }   → redirect dashboard
  │
  ├─► POST /api/auth/login.php
  │         │ Csrf::validate()
  │         │ User::findByEmail()
  │         │ User::verifyPassword() → password_verify()
  │         │ Auth::login($id)
  │         └─► JSON { success }   → redirect dashboard
  │
  ├─► GET  /index.php              ─── Auth::requireAuthOrRedirect() ──►
  │                                    Renders upload form (CSRF embedded)
  │
  ├─► POST /upload_endpoint.php
  │         │ Auth::isAuthenticated()  → 401 if missing
  │         │ Csrf::validate()
  │         │ Upload::upload()
  │         │   ├─ validate (finfo MIME, size, error code)
  │         │   ├─ beginTransaction
  │         │   ├─ FileStorageInterface::store() → /uploads/{32hex}.pdf
  │         │   ├─ INSERT documentos (with user_id)
  │         │   └─ commit
  │         │ UrlShortener::createShortUrl(documentId)
  │         └─► JSON { success, message, link }
  │
  ├─► GET  /dashboard.php          ─── Auth::requireAuthOrRedirect() ──►
  │         │ Document::listByUser($userId)  ← includes firma_count
  │         └─► HTML table (copy link, delete buttons)
  │
  ├─► POST /api/document/delete.php
  │         │ Auth::isAuthenticated()
  │         │ Csrf::validate()
  │         │ Document::deactivate($docId, $userId)  ← ownership enforced
  │         │ Document::logAccess($docId, 'eliminar')
  │         └─► JSON { success }
  │
  ├─► GET  /edit_pdf.php?id={slug}   (anonymous)
  │         │ Document::findBySlug(slug)
  │         │ Document::isExpired(doc)
  │         └─► HTML (PDF iframe + Signature Pad canvas)
  │
  ├─► GET  /download.php?id={slug}   (anonymous)
  │         │ Document::findBySlug(slug)
  │         │ Document::isExpired(doc)
  │         │ Document::logAccess(id, 'descargar')
  │         └─► readfile(/uploads/{storedName})
  │
  ├─► POST /sign_endpoint.php        (anonymous)
  │         │ Csrf::validate()
  │         │ SignDocument::saveSignature(documentId, signatureData)
  │         │ Document::logAccess(id, 'firmar')
  │         └─► JSON { success, signature_id }
  │
  └─► POST /api/account/update.php
            │ Auth::isAuthenticated()
            │ Csrf::validate()
            │ action=update_nombre → User::updateNombre()
            │ action=change_password → User::verifyPassword() + User::updatePassword()
            └─► JSON { success }
```

---

## 5. Database Schema

```
usuarios
├── id              INT PK AUTO_INCREMENT
├── email           VARCHAR(255) UNIQUE
├── password_hash   VARCHAR(255)   BCrypt
├── nombre          VARCHAR(100) NULL
├── active          BOOLEAN
└── created_at      DATETIME

documentos
├── id              INT PK AUTO_INCREMENT
├── user_id         INT FK → usuarios(id) ON DELETE SET NULL  (NULL = anonymous legacy)
├── nombre          VARCHAR(255)   original filename
├── ruta            VARCHAR(255)   stored filename (32hex.pdf)
├── active          BOOLEAN        FALSE when expired/deleted
├── fecha_subida    DATETIME       upload timestamp
└── fecha_actualizacion DATETIME

enlaces_cortos
├── id              INT PK AUTO_INCREMENT
├── documento_id    INT FK → documentos(id) ON DELETE CASCADE
├── enlace          VARCHAR(255)   full short URL
├── slug            VARCHAR(32)    UNIQUE — 16 hex chars
├── active          BOOLEAN
├── fecha_creacion  DATETIME
└── fecha_expiracion DATETIME NULL

firmas
├── id              INT PK AUTO_INCREMENT
├── documento_id    INT FK → documentos(id) ON DELETE CASCADE
├── firma_data      BLOB           base64 PNG data URL
├── signer_email    VARCHAR(255) NULL
└── fecha_firma     DATETIME

acciones  (audit log)
├── id              INT PK AUTO_INCREMENT
├── documento_id    INT FK → documentos(id) ON DELETE CASCADE
├── accion          ENUM(descargar, eliminar, subir, firmar)
├── user_id         INT FK → usuarios(id) ON DELETE SET NULL  (NULL = anonymous)
└── fecha_accion    DATETIME
```

All foreign keys on child tables use `ON DELETE CASCADE`; `user_id` columns use `ON DELETE SET NULL` to preserve history when a user is removed.

---

## 6. Security Architecture

| Concern              | Implementation                                                  |
|----------------------|-----------------------------------------------------------------|
| Authentication       | BCrypt password hash (cost configurable via `BCRYPT_COST`)      |
| Session fixation     | `session_regenerate_id(true)` on every login                   |
| CSRF                 | `Csrf::getToken()` embeds token in forms; `validate()` on POST  |
| Ownership            | `Document::deactivate()` enforces `user_id = :user_id` in WHERE |
| File type            | `finfo(FILEINFO_MIME_TYPE)` — never trusts `$_FILES['type']`    |
| Stored filename      | `bin2hex(random_bytes(16))` — original name never used on disk  |
| Direct file access   | `uploads/.htaccess` → `Require all denied`                      |
| SQL injection        | All queries use PDO prepared statements with named placeholders  |
| Credentials          | `.env` loaded via phpdotenv; never committed to VCS             |
| DB errors            | `PDO::ERRMODE_EXCEPTION`; `EMULATE_PREPARES=false`              |
| Slug enumeration     | 2^64 collision space; UNIQUE constraint on `slug` column        |
| Error messages       | Internal exceptions logged via `error_log()`; user sees generic |
| Atomicity            | Upload + metadata INSERT wrapped in a single transaction        |
| File storage         | `FileStorageInterface` isolates disk logic; S3 adapter possible |

---

## 7. Environment Variables

| Variable           | Default           | Description                              |
|--------------------|-------------------|------------------------------------------|
| `DB_HOST`          | localhost         | MySQL host                               |
| `DB_NAME`          | pdf_store         | Database name                            |
| `DB_USER`          | root              | Database user                            |
| `DB_PASS`          | *(empty)*         | Database password                        |
| `APP_ENV`          | development       | Environment: development/production/testing |
| `BASE_URL`         | http://localhost/public | Base URL for short link generation |
| `UPLOAD_MAX_SIZE`  | 5000000           | Maximum upload size in bytes             |
| `AUTO_DELETE_HOURS`| 12                | Hours before a document is deactivated   |
| `BCRYPT_COST`      | 12                | BCrypt cost factor (10–14 recommended)   |

---

## 8. FileStorage Abstraction

`FileStorageInterface` decouples `Upload` from the filesystem:

```
Contracts\FileStorageInterface
    └── Storage\LocalFileStorage   (default — wraps move_uploaded_file, unlink)
    └── Storage\S3FileStorage      (Phase 4 — implement the interface for S3/R2)
```

To swap to S3: implement `FileStorageInterface`, inject into `Upload`'s 4th constructor parameter. No other code changes needed.
