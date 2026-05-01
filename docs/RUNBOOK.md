# Operations Runbook — POO_CloudObjStrg

## 1. First-Time Deployment

```bash
# Clone and run the install script (requires root or sudo)
git clone https://github.com/daviddevgt/poo_cloudobjstrg /var/www/pdfmanager
cd /var/www/pdfmanager
bash deploy/deploy.sh install
bash deploy/deploy.sh post-install
```

The deploy script:
- Installs Nginx, PHP 8.3-FPM, MySQL 8.0, APCu
- Creates the `pdf_store` database and `pdfapp` user
- Copies `nginx.conf` and enables the site
- Enables the `pdfmanager-autodelete` systemd timer

After the script completes:
```bash
cp .env.example .env
# Edit .env — set DB_PASS, APP_ENV=production, BCRYPT_COST=12, TRUST_PROXY=true
nano .env
php migrations/migrate_data.php
```

---

## 2. Routine Update Deployment

```bash
cd /var/www/pdfmanager
bash deploy/deploy.sh update
```

The update script: `git pull` → `composer install --no-dev` → `php migrations/migrate_data.php` → `systemctl reload php8.3-fpm`.

Manual equivalent:
```bash
git pull origin main
composer install --no-interaction --prefer-dist --no-dev
php migrations/migrate_data.php
systemctl reload php8.3-fpm
```

---

## 3. Health Check

```bash
curl -s https://yourdomain.com/health.php | jq .
```

Expected healthy response:
```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "uploads_writable": "ok",
    "disk_free_mb": 12345,
    "php_version": "8.3.x"
  }
}
```

HTTP 503 means at least one check failed — inspect the `checks` object to identify the failing component.

---

## 4. Logs

| Log | Location | View command |
|-----|----------|-------------|
| PHP application | `/var/log/php8.3-fpm.log` (or path in `php.ini`) | `journalctl -u php8.3-fpm -f` |
| Nginx access | `/var/log/nginx/pdfmanager.access.log` | `tail -f /var/log/nginx/pdfmanager.access.log` |
| Nginx error | `/var/log/nginx/pdfmanager.error.log` | `tail -f /var/log/nginx/pdfmanager.error.log` |
| AutoDelete | `/var/log/pdfmanager-autodelete.log` | `tail -f /var/log/pdfmanager-autodelete.log` |

Filter structured JSON application logs with `jq`:
```bash
# Last 100 error-level entries
journalctl -u php8.3-fpm -n 200 | grep '"level":"error"' | tail -20 | jq .

# Failed login attempts
journalctl -u php8.3-fpm | grep '"channel":"auth"' | grep '"level":"warning"' | jq .

# All rate-limit hits
journalctl -u php8.3-fpm | grep '"channel":"ratelimit"' | jq .
```

---

## 5. Database Backup and Restore

### Backup
```bash
mysqldump -u pdfapp -p pdf_store \
  --single-transaction --quick --routines --triggers \
  | gzip > /var/backups/pdfmanager_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Restore
```bash
gunzip < /var/backups/pdfmanager_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -u pdfapp -p pdf_store
```

### Upload files backup
```bash
tar -czf /var/backups/pdfmanager_uploads_$(date +%Y%m%d).tar.gz \
  /var/www/pdfmanager/uploads/
```

---

## 6. Migration Rollback Procedure

> **Always take a full DB backup before running rollbacks.**

### Roll back migration 003 (users + multitenancy)
```bash
# DESTRUCTIVE: drops usuarios table and user_id columns
mysql -u pdfapp -p pdf_store < migrations/rollback_003_add_users_multitenancy.sql
```

### Roll back migration 002 (indexes + cascade FKs)
```bash
mysql -u pdfapp -p pdf_store < migrations/rollback_002_add_indexes_cascade.sql
```

### Verify current schema state
```sql
SELECT version, applied_at, description
FROM schema_versions
ORDER BY applied_at;
```

### Re-apply from scratch (development only)
```bash
# Drop and recreate the database, then re-run migrations
mysql -u root -e "DROP DATABASE IF EXISTS pdf_store; CREATE DATABASE pdf_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php migrations/migrate_data.php
```

---

## 7. AutoDelete Job

The `pdfmanager-autodelete.timer` runs hourly via systemd.

```bash
# Check timer status
systemctl status pdfmanager-autodelete.timer

# Run manually (for testing or forced cleanup)
systemctl start pdfmanager-autodelete.service

# View recent autodelete runs
journalctl -u pdfmanager-autodelete -n 50

# Check last run output in log file
tail -20 /var/log/pdfmanager-autodelete.log
```

---

## 8. Certificate Renewal (Let's Encrypt)

```bash
certbot renew --dry-run        # test renewal
certbot renew                  # actual renewal
systemctl reload nginx         # apply new cert
```

Certbot installs a cron/timer automatically on most distros. Verify:
```bash
systemctl list-timers | grep certbot
```

---

## 9. PHP-FPM and Nginx Restart

```bash
# Reload without dropping connections (preferred for config changes)
systemctl reload nginx
systemctl reload php8.3-fpm

# Full restart (only if reload fails)
systemctl restart nginx
systemctl restart php8.3-fpm

# Check config before reload
nginx -t
php-fpm8.3 -t
```

---

## 10. Disk Space Management

Check free space:
```bash
df -h /var/www/pdfmanager/uploads
```

Find large uploads:
```bash
find /var/www/pdfmanager/uploads -name "*.pdf" -size +5M \
  | xargs ls -lh | sort -k5 -rh | head -20
```

Force autodelete to run immediately (removes documents past their expiry):
```bash
systemctl start pdfmanager-autodelete.service
```

Remove orphaned files (files on disk with no DB record — rare, for manual audit):
```bash
# List DB-tracked filenames
mysql -u pdfapp -p pdf_store -e \
  "SELECT ruta FROM documentos WHERE active = 1" -s -N > /tmp/db_files.txt

# Compare with actual files
ls /var/www/pdfmanager/uploads/ > /tmp/disk_files.txt

# Show disk files not in DB
comm -23 <(sort /tmp/disk_files.txt) <(sort /tmp/db_files.txt)
```

---

## 11. Incident Response Quick Reference

| Symptom | First check | Fix |
|---------|------------|-----|
| 502 Bad Gateway | `systemctl status php8.3-fpm` | `systemctl restart php8.3-fpm` |
| 504 Gateway Timeout | PHP slow log, DB slow query log | Check `SHOW PROCESSLIST` in MySQL |
| Login loop / session loss | Session file permissions | `chown -R www-data:www-data /var/lib/php/sessions` |
| Upload fails (500) | PHP error log | Check `upload_max_filesize` in php.ini (must be ≥10M) |
| Health check 503 | `curl /health.php | jq .checks` | Fix failing component per section above |
| Rate limit false positives | APCu not enabled | `php -m | grep apcu` — install php8.3-apcu if missing |
| DB connection refused | `systemctl status mysql` | `systemctl start mysql` |
| High disk usage | `df -h /uploads` | Run autodelete, archive old backups |

---

## 12. Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | `production` suppresses stack traces |
| `DB_HOST` | `127.0.0.1` | MySQL host |
| `DB_NAME` | `pdf_store` | MySQL database name |
| `DB_USER` | `pdfapp` | MySQL username |
| `DB_PASS` | _(required)_ | MySQL password — never commit this |
| `BCRYPT_COST` | `12` | BCrypt work factor (10–14 range) |
| `TRUST_PROXY` | `false` | Set `true` when behind Nginx reverse proxy for HTTPS detection |
| `AUTO_DELETE_HOURS` | `24` | Hours before uploaded documents expire |
