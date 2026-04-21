# Architecture — POO_CloudObjStrg

## 1. Overview

POO_CloudObjStrg is a PHP 8.1+ web application for secure PDF management.  
Users upload PDFs, receive a short link, and recipients can view and digitally sign the document through that link. Documents expire automatically after a configurable period.

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
│   ├── bootstrap.php       Entry point: loads autoloader, dotenv, session
│   ├── Csrf.php            CSRF token generation and validation
│   └── Database.php        PDO singleton factory
│
├── docs/                   Technical documentation (this directory)
│
├── migrations/
│   ├── 001_create_tables.sql   Initial schema
│   ├── 002_add_indexes_cascade.sql  Phase 2: indexes + ON DELETE CASCADE + slug column
│   └── migrate_data.php    CLI migration runner
│
├── models/
│   ├── AutoDelete.php      Deactivates expired docs + deletes physical files
│   ├── Document.php        Fetches, serves, and audits documents by slug
│   ├── SignDocument.php    Persists digital signatures
│   ├── Upload.php          Validates and stores uploaded PDFs atomically
│   └── UrlShortener.php    Generates collision-free short URLs
│
├── public/                 Web root (only this directory should be web-accessible)
│   ├── partials/
│   │   └── error.php       Shared error page partial
│   ├── download.php        Serves PDF file by slug with access logging
│   ├── edit_pdf.php        Document viewer + Signature Pad UI
│   ├── index.php           Upload UI
│   ├── sign_endpoint.php   POST endpoint: saves signature (JSON API)
│   └── upload_endpoint.php POST endpoint: upload + short URL (JSON API)
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
  ├─► GET  /index.php          ─────────────────────► Renders upload form
  │                                                   (CSRF token embedded)
  │
  ├─► POST /upload_endpoint.php
  │         │ validates CSRF
  │         │ Upload::upload()
  │         │   ├─ validate (MIME via finfo, size, error code)
  │         │   ├─ beginTransaction
  │         │   ├─ move_uploaded_file → /uploads/{32hex}.pdf
  │         │   ├─ INSERT documentos
  │         │   └─ commit  ──────────────── returns documentId
  │         │ UrlShortener::createShortUrl(documentId)
  │         │   ├─ generateUniqueSlug  (bin2hex(random_bytes(8)))
  │         │   └─ INSERT enlaces_cortos (slug, enlace)
  │         └─► JSON { success, message, link }
  │
  ├─► GET  /edit_pdf.php?id={slug}
  │         │ Document::findBySlug(slug)   ← exact slug lookup (indexed)
  │         │ Document::isExpired(doc)
  │         └─► HTML (PDF iframe + Signature Pad canvas)
  │
  ├─► GET  /download.php?id={slug}
  │         │ Document::findBySlug(slug)
  │         │ Document::isExpired(doc)
  │         │ Document::logAccess(id, 'descargar')
  │         └─► readfile(/uploads/{storedName})  [Content-Type: application/pdf]
  │
  └─► POST /sign_endpoint.php
            │ validates CSRF
            │ SignDocument::saveSignature(documentId, signatureData)
            │   ├─ validateSignatureData (format + size)
            │   └─ INSERT firmas
            │ Document::logAccess(id, 'firmar')
            └─► JSON { success, message, signature_id }
```

---

## 5. Database Schema

```
documentos
├── id              INT PK AUTO_INCREMENT
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
└── fecha_firma     DATETIME

acciones  (audit log)
├── id              INT PK AUTO_INCREMENT
├── documento_id    INT FK → documentos(id) ON DELETE CASCADE
├── accion          ENUM(descargar, eliminar, subir, firmar)
└── fecha_accion    DATETIME
```

All foreign keys use `ON DELETE CASCADE` — deleting a document removes its links, signatures, and audit entries automatically.

---

## 6. Security Architecture

| Concern              | Implementation                                                  |
|----------------------|-----------------------------------------------------------------|
| CSRF                 | `Csrf::getToken()` embeds token in forms; `validate()` on POST  |
| File type            | `finfo(FILEINFO_MIME_TYPE)` — never trusts `$_FILES['type']`    |
| Stored filename      | `bin2hex(random_bytes(16))` — original name never used on disk  |
| Direct file access   | `uploads/.htaccess` → `Require all denied`                      |
| SQL injection        | All queries use PDO prepared statements with named placeholders  |
| Credentials          | `.env` loaded via phpdotenv; never committed to VCS             |
| DB errors            | `PDO::ERRMODE_EXCEPTION`; `EMULATE_PREPARES=false`              |
| Slug enumeration     | 2^64 collision space; UNIQUE constraint on `slug` column        |
| Error messages       | Internal exceptions logged via `error_log()`; user sees generic |
| Atomicity            | Upload + metadata INSERT wrapped in a single transaction        |

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

---

## 8. Data Flow: Upload → Short Link → Sign

```
[User]                 [index.php]         [upload_endpoint]       [DB]         [/uploads]
  │                        │                       │                 │               │
  ├── chooses PDF ─────────►│                       │                 │               │
  │                        ├── validates client ───►│                 │               │
  │                        │   CSRF + file          │                 │               │
  │                        │                        ├── validates ────►               │
  │                        │                        │   MIME+size     │               │
  │                        │                        ├── beginTx ──────►               │
  │                        │                        ├── move_file ────────────────────►
  │                        │                        ├── INSERT doc ───►               │
  │                        │                        ├── commit ───────►               │
  │                        │                        ├── generate slug                  │
  │                        │                        ├── INSERT link ──►               │
  │                        │◄── { link } ───────────┤                 │               │
  │◄── shows link + copy ──┤                        │                 │               │
  │                        │                        │                 │               │
  ├── opens link ──────────────────────────────────────────────────────               │
  │                   [edit_pdf.php]                │                 │               │
  │                        ├── findBySlug ──────────────────────────►│               │
  │                        ├── isExpired check       │                │               │
  │◄── PDF viewer + sig ───┤                         │                │               │
  │   pad rendered         │                         │                │               │
  │                        │                         │                │               │
  ├── draws signature ─────►[sign_endpoint]           │               │               │
  │                        ├── CSRF check             │               │               │
  │                        ├── validate sig data      │               │               │
  │                        ├── INSERT firma ──────────────────────────►               │
  │                        ├── INSERT accion ─────────────────────────►               │
  │◄── { success } ────────┤                          │               │               │
```
