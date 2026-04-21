# API Reference — POO_CloudObjStrg

All endpoints live under `{BASE_URL}/` (configured in `.env`).  
JSON endpoints return `Content-Type: application/json`.

---

## Endpoints

### `GET /index.php`
Upload UI. Returns an HTML page with a PDF upload form.

**No parameters.**

---

### `POST /upload_endpoint.php`

Uploads a PDF and returns a short link.

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

**Response — CSRF failure (`403`)**

```json
{
  "success": false,
  "message": "Token de seguridad inválido. Recarga la página."
}
```

**Response — validation failure (`400`)**

```json
{
  "success": false,
  "message": "Only PDF files are allowed."
}
```

**Response — method not allowed (`405`)**

```json
{
  "success": false,
  "message": "Método no permitido."
}
```

---

### `GET /download.php?id={slug}`

Serves the original PDF file inline.

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

**Response — not found (`404`)**

```
Document not found or has been deactivated.
```

**Response — expired (`410`)**

```
This document link has expired.
```

**Response — invalid slug (`400`)**

```
Invalid document identifier.
```

---

### `GET /edit_pdf.php?id={slug}`

Document viewer with integrated Signature Pad.

**Path parameters**

| Parameter | Format       | Description   |
|-----------|--------------|---------------|
| `id`      | 16 hex chars | Short URL slug |

**Response — success (`200`)**: Full HTML page with embedded PDF iframe and Signature Pad canvas.

**Error responses**: HTML error page with HTTP status code `400`, `404`, or `410`.

---

### `POST /sign_endpoint.php`

Saves a digital signature for a document.

**Request** — `application/json`

```json
{
  "document_id":    42,
  "signature_data": "data:image/png;base64,iVBOR...",
  "_csrf_token":    "a3f8..."
}
```

| Field            | Type    | Required | Constraints                              |
|------------------|---------|----------|------------------------------------------|
| `document_id`    | integer | Yes      | Must be a positive integer               |
| `signature_data` | string  | Yes      | Must start with `data:image/png;base64,` |
| `_csrf_token`    | string  | Yes      | CSRF token from the current session      |

**Response — success (`200`)**

```json
{
  "success":      true,
  "message":      "Firma guardada correctamente.",
  "signature_id": 17
}
```

**Response — CSRF failure (`403`)**

```json
{
  "success": false,
  "message": "Token de seguridad inválido. Recarga la página."
}
```

**Response — validation failure (`400`)**

```json
{
  "success": false,
  "message": "Invalid signature format. Expected a PNG data URL."
}
```

**Response — method not allowed (`405`)**

```json
{
  "success": false,
  "message": "Método no permitido."
}
```

---

## HTTP Status Code Summary

| Code | Meaning                              | Used by                              |
|------|--------------------------------------|--------------------------------------|
| 200  | OK                                   | All successful responses             |
| 400  | Bad Request / Validation Error       | Invalid slug, MIME, size, format     |
| 403  | Forbidden (CSRF mismatch)            | `upload_endpoint`, `sign_endpoint`   |
| 404  | Document not found or inactive       | `download`, `edit_pdf`               |
| 405  | Method Not Allowed                   | All POST endpoints on non-POST       |
| 410  | Gone (link expired)                  | `download`, `edit_pdf`               |

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
