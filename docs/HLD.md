# High-Level Design — POO_CloudObjStrg

## 1. System Purpose

A multi-tenant SaaS that lets registered users upload PDF documents, generate tamper-proof short links, and collect anonymous digital signatures from recipients — without requiring recipients to have an account.

---

## 2. Context Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                            Internet                                      │
│                                                                          │
│   ┌─────────────┐      HTTPS       ┌─────────────────────────────────┐  │
│   │  Document   │ ──────────────►  │         Ubuntu Server           │  │
│   │   Owner     │                  │   (Homelab / VPS)               │  │
│   │ (browser)   │ ◄──────────────  │                                 │  │
│   └─────────────┘                  │  ┌─────────┐  ┌─────────────┐  │  │
│                                    │  │  Nginx  │  │  PHP-FPM    │  │  │
│   ┌─────────────┐      HTTPS       │  │ (proxy) │  │ (workers)   │  │  │
│   │   Signer    │ ──────────────►  │  └────┬────┘  └──────┬──────┘  │  │
│   │  (browser)  │                  │       │               │         │  │
│   │ (anonymous) │ ◄──────────────  │       └───────┬───────┘         │  │
│   └─────────────┘                  │               │                 │  │
│                                    │       ┌───────▼───────┐         │  │
│                                    │       │    MySQL 8    │         │  │
│                                    │       └───────────────┘         │  │
│                                    │                                 │  │
│                                    │       /uploads/  (local disk)   │  │
│                                    └─────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Component Diagram

```
┌────────────────────────────────────────────────────────┐
│  public/  (Web Root)                                   │
│                                                        │
│  Pages: index · login · register · dashboard · account │
│  API:   upload_endpoint · sign_endpoint · download     │
│  API:   /api/auth/{login,register}                     │
│  API:   /api/account/update                            │
│  API:   /api/document/delete                           │
│  Util:  health.php                                     │
└────────────────┬───────────────────────────────────────┘
                 │ require
┌────────────────▼───────────────────────────────────────┐
│  config/  (Services & Cross-cutting)                   │
│                                                        │
│  bootstrap.php  — env, errors, session, auth init      │
│  Database.php   — PDO singleton, utf8mb4               │
│  Auth.php       — session auth facade                  │
│  Csrf.php       — token generate/validate              │
│  Logger.php     — structured JSON logs via error_log   │
│  RateLimit.php  — sliding window, APCu or file-based   │
└────────────────┬───────────────────────────────────────┘
                 │ instantiate
┌────────────────▼───────────────────────────────────────┐
│  models/  (Domain Logic)                               │
│                                                        │
│  User.php         — CRUD, bcrypt, email normalize      │
│  Document.php     — find, list, deactivate, serve      │
│  Upload.php       — validate, transaction, FileStorage │
│  UrlShortener.php — slug generation, short URL         │
│  SignDocument.php — validate & persist signatures      │
│  AutoDelete.php   — scheduled cleanup, transaction     │
└────────────────┬───────────────────────────────────────┘
                 │ implements / uses
┌───────────────┬▼──────────────────────────────────────┐
│ contracts/    │ storage/                               │
│               │                                        │
│ FileStorage   │ LocalFileStorage (move_uploaded_file)  │
│ Interface     │ [S3FileStorage — Phase 4, plug in]     │
└───────────────┴────────────────────────────────────────┘
```

---

## 4. Request Flows

### 4.1 Owner: Register → Upload → Share

```
Browser ──POST /api/auth/register──► RateLimit(5/5min)
                                     Csrf::validate()
                                     User::emailExists()
                                     User::create()      ──► DB: INSERT usuarios
                                     Auth::login()       ──► session_regenerate_id()
                                  ◄── { success }

Browser ──POST /upload_endpoint──►   Auth::isAuthenticated()  (401 if not)
                                     RateLimit(20/hr per user)
                                     Csrf::validate()
                                     Upload::validate()  (finfo MIME, size)
                                     DB: beginTransaction
                                     LocalFileStorage::store()  ──► /uploads/
                                     DB: INSERT documentos (user_id)
                                     DB: commit
                                     UrlShortener::createShortUrl()  ──► DB: INSERT enlaces_cortos
                                  ◄── { link: "https://…/edit_pdf.php?id=<slug>" }

Browser ──GET /dashboard──►          Auth::requireAuthOrRedirect()
                                     Document::listByUser()  ──► DB: SELECT + firma_count subquery
                                  ◄── HTML table
```

### 4.2 Signer: Open Link → Sign

```
Browser ──GET /edit_pdf.php?id=<slug>──►  preg_match /^[0-9a-f]{16}$/
                                           Document::findBySlug()  ──► DB: JOIN lookup
                                           Document::isExpired()
                                        ◄── HTML (PDF iframe + Signature Pad)

Browser ──GET /download.php?id=<slug>──►  Document::findBySlug()
                                           Document::logAccess('descargar')
                                           readfile(/uploads/)
                                        ◄── application/pdf stream

Browser ──POST /sign_endpoint──►           RateLimit(30/hr per IP)
                                           Csrf::validate()
                                           SignDocument::saveSignature()  ──► DB: INSERT firmas
                                           Document::logAccess('firmar')
                                        ◄── { signature_id }
```

### 4.3 Background: AutoDelete (hourly)

```
systemd timer ──► bin/autodelete.php
                  AutoDelete::deleteExpiredDocuments()
                    DB: beginTransaction
                    SELECT ruta WHERE fecha_subida < NOW() - INTERVAL X HOUR
                    UPDATE documentos SET active = FALSE  WHERE …
                    UPDATE enlaces_cortos SET active = FALSE WHERE expiracion < NOW()
                    DB: commit
                    unlink() physical files
                  Logger::info() ──► /var/log/pdfmanager-autodelete.log
```

---

## 5. Data Model

```
usuarios ──────────────────────────────────────
  id PK | email UNIQUE | password_hash | nombre
  active | created_at

documentos ────────────────────────────────────
  id PK | user_id FK→usuarios (SET NULL) | nombre
  ruta (stored filename) | active | fecha_subida

enlaces_cortos ─────────────────────────────────
  id PK | documento_id FK→documentos (CASCADE)
  enlace | slug UNIQUE | active | fecha_expiracion

firmas ─────────────────────────────────────────
  id PK | documento_id FK→documentos (CASCADE)
  firma_data BLOB | signer_email | fecha_firma

acciones (audit log) ────────────────────────────
  id PK | documento_id FK→documentos (CASCADE)
  accion ENUM | user_id FK→usuarios (SET NULL)
  fecha_accion

schema_versions (migration tracker) ────────────
  version PK | applied_at | description
```

---

## 6. Security Controls Summary

| Threat                  | Control                                              |
|-------------------------|------------------------------------------------------|
| CSRF                    | `Csrf` class — 32-byte random token, hash_equals()   |
| SQL Injection           | PDO prepared statements everywhere                   |
| XSS                     | `htmlspecialchars()` on all output                   |
| Brute-force login       | `RateLimit` — 10 attempts / 60s per IP               |
| Registration spam       | `RateLimit` — 5 registrations / 5min per IP          |
| Upload abuse            | `RateLimit` — 20 uploads / hour per user             |
| MIME spoofing           | `finfo(FILEINFO_MIME_TYPE)` — not $_FILES['type']    |
| Path traversal          | `basename()` on stored filenames                     |
| Session fixation        | `session_regenerate_id(true)` on every login         |
| Password leak           | BCrypt cost-12, never logged                         |
| Exception info leak     | Internal errors logged, generic message to client    |
| Unauthorized file read  | `.htaccess` denies /uploads/, PHP serves via slug    |
| Unauthorized delete     | `deactivate()` verifies user_id ownership in WHERE   |
| Credential commit       | `.env` in `.gitignore`, `.env.example` committed     |

---

## 7. Observability

| Signal   | Implementation                        | Location                             |
|----------|---------------------------------------|--------------------------------------|
| Logs     | JSON via `Logger` → `error_log()`    | PHP error_log (configured in php.ini) |
| Access   | Nginx `access_log`                   | /var/log/nginx/pdfmanager.access.log |
| Errors   | Nginx `error_log`                    | /var/log/nginx/pdfmanager.error.log  |
| Audit    | `acciones` table in MySQL            | Every download, sign, upload, delete |
| Health   | `GET /health.php` (JSON)             | DB check, disk check, PHP version    |
| Cleanup  | autodelete.service output            | /var/log/pdfmanager-autodelete.log   |

**Missing (Phase 4):** Metrics (Prometheus), distributed tracing, Slack/email alerts on error spikes.

---

## 8. Deployment Topology (Homelab)

```
Ubuntu Server (single node)
│
├── Nginx 1.24+
│     - Serves static assets
│     - Reverse-proxies *.php to PHP-FPM
│     - HTTPS termination (self-signed or Let's Encrypt)
│
├── PHP 8.3-FPM
│     - OPcache enabled (4000 files, no revalidation)
│     - 128 MB OPcache memory
│     - upload_max_filesize=10M
│
├── MySQL 8.0
│     - Database: pdf_store
│     - User: app-specific (not root in production)
│
├── /var/www/pdfmanager/
│     - uploads/        (writable by www-data)
│     - .env            (chmod 640, owned root:www-data)
│
└── systemd
      - pdfmanager-autodelete.timer (hourly)
      - Manages AutoDelete background job
```

---

## 9. Future Evolution (Phase 4+)

| Feature              | Pre-requisite                    | Effort |
|----------------------|----------------------------------|--------|
| S3/R2 storage        | `FileStorageInterface` ready     | M      |
| Redis session store  | Install predis/client            | S      |
| Email notifications  | Any PSR mailer (Symfony Mailer)  | M      |
| Stripe billing       | User + Org models in place       | L      |
| Multi-org / teams    | `organizations` table + FK       | L      |
| Prometheus metrics   | Expose /metrics endpoint         | M      |
| API keys / JWT       | Auth refactor                    | L      |
| CDN for uploads      | S3 storage first                 | S      |
