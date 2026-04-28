# Análisis técnico del repositorio `POO_CloudObjStrg`

Fecha: 2026-04-28

## 1) Resumen ejecutivo

El proyecto implementa un SaaS de gestión de PDFs con autenticación por sesión, aislamiento por usuario, enlaces cortos y firma digital. La base arquitectónica es sólida para un MVP/Phase 3: hay separación por capas (`config`, `models`, `storage`, `public`), controles de seguridad esenciales (CSRF, validación MIME real, sesiones seguras), y una estrategia de pruebas con suites unitarias/smoke/integración.

## 2) Fortalezas observadas

- **Diseño modular y mantenible**:
  - `FileStorageInterface` desacopla persistencia de archivos y facilita migrar de almacenamiento local a S3.
  - `models/` concentra reglas de negocio y `public/` actúa como capa HTTP.
- **Seguridad bien considerada para contexto PHP clásico**:
  - Validación CSRF en endpoints que mutan estado.
  - Validación de tipo de archivo por `finfo` (no por `$_FILES['type']`).
  - Nombres de archivo aleatorios con `random_bytes`.
  - Restricción de acceso directo a `uploads/` por `.htaccess`.
- **Flujo de subida robusto**:
  - Operación atómica (archivo + metadata DB) mediante transacción y rollback.
- **Cobertura funcional amplia para el dominio**:
  - Registro/login/logout, dashboard, borrado lógico, firma anónima, vencimiento automático y log de acciones.
- **Documentación estructurada**:
  - README, arquitectura, API y estrategia de testing son consistentes entre sí.

## 3) Riesgos y hallazgos

### 3.1 Calidad de documentación

- `TODO.md` no contiene un backlog técnico; actualmente incluye un script de PowerShell para listar árbol de archivos. Esto puede confundir al equipo y a futuros contribuidores.

### 3.2 Operación/infra

- El proyecto depende de tareas programadas para auto-borrado (`bin/autodelete.php`, unit/timer en `deploy/`), pero no existe (en este repo) una guía operativa detallada de monitoreo/alertado del job.

### 3.3 Endurecimiento de seguridad (oportunidades)

- Sesiones con `SameSite=Lax` y `secure` dependiente de HTTPS están bien para base; en producción sería recomendable reforzar políticas de cabeceras HTTP globales (CSP, HSTS, X-Frame-Options según necesidad).
- La firma se almacena como base64 en DB (`firmas.firma_data`); para crecimiento fuerte convendría evaluar costos de almacenamiento y posibles estrategias (compresión o blob store externo).

### 3.4 Escalabilidad y evolución

- La arquitectura está bien para un monolito PHP pequeño/mediano, pero faltan capas explícitas de servicio/DTO para dominios más complejos.
- Aún no se ve pipeline CI versionado en el repo (en docs sí hay ejemplo). Materializarlo en `.github/workflows/` mejoraría gobernanza.

## 4) Recomendaciones priorizadas

### Prioridad alta (1-2 sprints)

1. **Corregir `TODO.md`** con backlog real priorizado (bugs, deuda técnica, seguridad, observabilidad).
2. **Agregar CI real** que ejecute `composer test:unit` y `composer test:smoke` en cada PR.
3. **Publicar runbook operativo** (autodelete, backups, restore, rotación de logs).

### Prioridad media

4. **Incorporar análisis estático** (PHPStan/Psalm) y style checks (PHP-CS-Fixer).
5. **Agregar pruebas E2E ligeras** para flujos críticos (registro, subida, firma, eliminación).
6. **Revisar headers de seguridad** en servidor web (`deploy/nginx.conf` + Apache equivalente).

### Prioridad baja

7. **Definir métricas de producto y técnica** (uploads/día, latencia endpoint, ratio errores 4xx/5xx).
8. **Planificar estrategia de almacenamiento de firmas** si volumen crece.

## 5) Conclusión

Repositorio bien encaminado para un SaaS académico/prototipo avanzado con buenas decisiones de seguridad y pruebas para su tamaño. El mayor salto de madurez vendrá de reforzar operación (CI, runbooks, observabilidad) y de pulir la documentación operativa.
