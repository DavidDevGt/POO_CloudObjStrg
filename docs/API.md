# API Reference — POO_CloudObjStrg

All endpoints live under `{BASE_URL}/` (configured in `.env`).  
JSON endpoints return `Content-Type: application/json`.

---

## Authentication

Session-based. Obtain a session cookie via `POST /api/auth/login.php` or `POST /api/auth/register.php`.  
All owner endpoints (upload, delete, account) require an active session. Signer endpoints (download, edit_pdf, sign) are anonymous.

---

## Auth Endpoints

### `POST /api/auth/register.php`

Creates a new user account and starts a session.

**Request** — `application/json`

| Field          | Type   | Required | Constraints              |
|----------------|--------|----------|--------------------------|
| `email`        | string | Yes      | Valid email, unique      |
| `password`     | string | Yes      | Min 8 characters         |
| `nombre`       | string | No       | Display name             |
| `_csrf_token`  | string | Yes      | CSRF token from session  |

**Response — success (`200`)**
```json
{ "success": true, "message": "Cuenta creada con éxito." }
```

**Response — duplicate email (`409`)**
```json
{ "success": false, "message": "Este correo ya está registrado." }
```

---

### `POST /api/auth/login.php`

Authenticates a user and starts a session.

**Request** — `application/json`

| Field          | Type   | Required |
|----------------|--------|----------|
| `email`        | string | Yes      |
| `password`     | string | Yes      |
| `_csrf_token`  | string | Yes      |

**Response — success (`200`)**
```json
{ "success": true, "message": "Sesión iniciada." }
```

**Response — wrong credentials (`401`)**
```json
{ "success": false, "message": "Credenciales incorrectas." }
```

---

### `GET /logout.php`

Destroys the session and redirects to `/login.php`. No JSON response.

---

## Document Endpoints

### `GET /index.php`

Upload UI. Requires authentication — redirects to `/login.php` if not logged in.

---

### `POST /upload_endpoint.php`

Uploads a PDF and returns a short link. Requires authentication.

**Request** — `multipart/form-data`

| Field          | Type   | Required | Description                          |
|----------------|--------|----------|--------------------------------------|
| `pdfFile`      | file   | Yes      | PDF file, max `UPLOAD_MAX_SIZE` bytes |
| `_csrf_token`  | string | Yes      | CSRF token from the current session  |

**Response — success (`200`)**

```json
{
  "success": true,
  "message": "Archivo subido con éxito.",
  "link": "http://localhost/public/edit_pdf.php?id=a1b2c3d4e5f60708"
}
```

**Response — not authenticated (`401`)**
```json
{ "success": false, "message": "Autenticación requerida." }
```

**Response — CSRF failure (`403`)**
```json
{ "success": false, "message": "Token de seguridad inválido. Recarga la página." }
```

**Response — validation failure (`400`)**
```json
{ "success": false, "message": "Only PDF files are allowed." }
```

---

### `POST /api/document/delete.php`

Soft-deletes a document owned by the authenticated user.

**Request** — `application/json`

| Field          | Type    | Required |
|----------------|---------|----------|
| `document_id`  | integer | Yes      |
| `_csrf_token`  | string  | Yes      |

**Response — success (`200`)**
```json
{ "success": true, "message": "Documento eliminado." }
```

**Response — not found (`404`)**
```json
{ "success": false, "message": "Documento no encontrado o ya eliminado." }
```

---

### `GET /download.php?id={slug}`

Serves the original PDF file inline. Anonymous — no auth required.

**Path parameters**

| Parameter | Format           | Description               |
|-----------|------------------|---------------------------|
| `id`      | 16 hex chars     | Short URL slug            |

**Response — success (`200`)**

```
Content-Type: application/pdf
Content-Disposition: inline; filename="original_name.pdf"
Content-Length: <bytes>
X-Content-Type-Options: nosniff
Cache-Control: private, no-store

<binary PDF data>
```

**Response — not found (`404`)** · **expired (`410`)** · **invalid slug (`400`)**

---

### `GET /edit_pdf.php?id={slug}`

Document viewer with integrated Signature Pad. Anonymous — no auth required.

**Response — success (`200`)**: Full HTML page with embedded PDF iframe and Signature Pad canvas.

---

### `POST /sign_endpoint.php`

Saves a digital signature for a document. Anonymous — no auth required.

**Request** — `application/json`

```json
{
  "document_id":    42,
  "signature_data": "data:image/png;base64,iVBOR...",
  "_csrf_token":    "a3f8..."
}
```

**Response — success (`200`)**
```json
{ "success": true, "message": "Firma guardada correctamente.", "signature_id": 17 }
```

---

## Account Endpoints

### `GET /dashboard.php`

Lists the authenticated user's documents with signature counts and management actions.

### `GET /account.php`

Account settings page (update name, change password).

### `POST /api/account/update.php`

Updates account settings. Requires authentication.

**Request** — `application/json`

Two actions are supported:

**Update display name:**
```json
{ "action": "update_nombre", "nombre": "Jane Doe", "_csrf_token": "..." }
```

**Change password:**
```json
{
  "action": "change_password",
  "current_password": "old",
  "new_password": "newpass123",
  "_csrf_token": "..."
}
```

**Response — success (`200`)**
```json
{ "success": true, "message": "Nombre actualizado." }
```

**Response — wrong current password (`400`)**
```json
{ "success": false, "message": "La contraseña actual es incorrecta." }
```

---

## HTTP Status Code Summary

| Code | Meaning                              | Used by                                     |
|------|--------------------------------------|---------------------------------------------|
| 200  | OK                                   | All successful responses                    |
| 400  | Bad Request / Validation Error       | Invalid slug, MIME, size, format, password  |
| 401  | Unauthorized                         | Protected endpoints without session         |
| 403  | Forbidden (CSRF mismatch)            | All state-changing endpoints                |
| 404  | Document not found / inactive        | `download`, `edit_pdf`, `delete`            |
| 405  | Method Not Allowed                   | All POST endpoints on non-POST              |
| 409  | Conflict                             | Duplicate email on register                 |
| 410  | Gone (link expired)                  | `download`, `edit_pdf`                      |

---

## Error Response Shape

All JSON error responses share this schema:

```json
{
  "success": false,
  "message": "<human-readable reason>"
}
```

Internal server errors are logged via `error_log()` and return a safe message to the client — stack traces and file paths are never exposed.
