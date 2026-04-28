# Runbook operativo — POO_CloudObjStrg

Fecha: 2026-04-28
Ámbito: entorno Linux con Nginx + PHP-FPM + MySQL + systemd.

## 1) Objetivo

Definir procedimientos de operación para despliegue, verificación, monitoreo, backup/restore y recuperación rápida del servicio.

## 2) Datos base del servicio

- Ruta de app (por defecto): `/var/www/pdfmanager`
- Job de expiración automática:
  - Service: `pdfmanager-autodelete.service`
  - Timer: `pdfmanager-autodelete.timer`
  - Script ejecutado: `bin/autodelete.php`
- Log de autodelete (según unidad): `/var/log/pdfmanager-autodelete.log`

## 3) Checklist post-deploy

Ejecutar inmediatamente después de instalar/actualizar:

```bash
sudo systemctl status nginx --no-pager
sudo systemctl status php8.3-fpm --no-pager
sudo systemctl status pdfmanager-autodelete.timer --no-pager
sudo systemctl list-timers --all | grep pdfmanager-autodelete
sudo php /var/www/pdfmanager/bin/autodelete.php
```

Criterios de éxito:

- Nginx y PHP-FPM en estado `active (running)`.
- Timer `pdfmanager-autodelete.timer` habilitado y con próxima ejecución programada.
- Ejecución manual de `autodelete.php` retorna código `0`.

## 4) Monitoreo y alertado mínimo recomendado

### 4.1 Monitoreo periódico (cada 5-15 min)

- Estado de procesos:
  - `nginx`
  - `php-fpm`
  - `mysql`
  - `pdfmanager-autodelete.timer`
- Verificación de crecimiento de logs:
  - `/var/log/nginx/error.log`
  - `/var/log/php*-fpm.log`
  - `/var/log/pdfmanager-autodelete.log`

### 4.2 Alertas sugeridas

- Servicio web caído (`nginx` o `php-fpm` no activos).
- 5xx sostenidos en Nginx por más de 5 minutos.
- Timer de autodelete deshabilitado o sin última ejecución exitosa en > 2 horas.
- Error en `autodelete.php` (`exit code != 0`).

## 5) Procedimiento de actualización (standard)

```bash
cd /var/www/pdfmanager
sudo bash deploy/deploy.sh update
```

Verificación posterior:

```bash
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
sudo systemctl status pdfmanager-autodelete.timer --no-pager
```

Si hay rollback necesario, ver sección 8.

## 6) Backups y restore

## 6.1 Backup (diario recomendado)

### Base de datos

```bash
mysqldump -u <user> -p --single-transaction --quick pdf_store > /backups/pdf_store_$(date +%F).sql
```

### Archivos subidos

```bash
tar -czf /backups/uploads_$(date +%F).tar.gz /var/www/pdfmanager/uploads
```

### Retención sugerida

- Diario: 7 días
- Semanal: 4 semanas
- Mensual: 3 meses

## 6.2 Restore

### Base de datos

```bash
mysql -u <user> -p pdf_store < /backups/pdf_store_YYYY-MM-DD.sql
```

### Uploads

```bash
tar -xzf /backups/uploads_YYYY-MM-DD.tar.gz -C /
```

Tras restore:

```bash
sudo chown -R www-data:www-data /var/www/pdfmanager
sudo chmod 755 /var/www/pdfmanager/uploads
```

## 7) Troubleshooting rápido

### Problema: `composer install` falla en servidor

- Verificar conectividad a packagist/github.
- Revisar proxy corporativo/firewall.
- Reintentar con `--prefer-dist`.

### Problema: falla de migraciones

- Revisar `.env` (credenciales DB).
- Ejecutar manualmente:

```bash
php /var/www/pdfmanager/migrations/migrate_data.php
```

- Validar permisos de usuario MySQL.

### Problema: errores de permisos al subir archivos

- Asegurar ownership y permisos:

```bash
sudo chown -R www-data:www-data /var/www/pdfmanager
sudo chmod 755 /var/www/pdfmanager/uploads
```

### Problema: autodelete no corre

```bash
sudo systemctl status pdfmanager-autodelete.timer --no-pager
sudo systemctl status pdfmanager-autodelete.service --no-pager
sudo journalctl -u pdfmanager-autodelete.service -n 100 --no-pager
sudo tail -n 100 /var/log/pdfmanager-autodelete.log
```

## 8) Rollback

Estrategia mínima:

1. Mantener snapshot/tag de release previa.
2. Revertir código a commit estable.
3. Reinstalar dependencias de release previa.
4. Restaurar DB/uploads si hubo cambios incompatibles.
5. Reiniciar servicios y ejecutar checklist post-deploy.

Comandos orientativos:

```bash
cd /var/www/pdfmanager
git fetch --all
git checkout <commit_estable>
composer install --no-dev --optimize-autoloader
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

## 9) Responsables

- Operación: equipo DevOps/SRE.
- Aprobación de cambios productivos: mantenedor principal del repositorio.
- Escalamiento de incidentes críticos: on-call definido por organización.
