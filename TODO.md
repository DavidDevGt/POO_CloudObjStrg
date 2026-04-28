# TODO — Backlog técnico priorizado

Fecha de actualización: 2026-04-28

## Prioridad alta (1-2 sprints)

- [ ] **CI obligatorio en pull requests y pushes a `main`**
  - Ejecutar `composer install`.
  - Ejecutar `composer test:unit` y `composer test:smoke`.
  - Publicar resultado de pruebas como requisito de merge.
- [ ] **Runbook operativo en producción**
  - Procedimiento de despliegue seguro y rollback.
  - Verificación y monitoreo de `pdfmanager-autodelete.timer`.
  - Política de backup/restore de base de datos y uploads.
  - Troubleshooting para fallas comunes (migraciones, permisos, logs).
- [ ] **Gobernanza mínima de cambios**
  - Definir CODEOWNERS (o equivalente) para `config/`, `models/`, `deploy/`.
  - Proteger rama principal con checks obligatorios.

## Prioridad media

- [ ] **Análisis estático**
  - Integrar PHPStan o Psalm en CI.
  - Incluir baseline inicial y plan de reducción de hallazgos.
- [ ] **Calidad de código**
  - Integrar PHP-CS-Fixer y reglas de estilo consistentes.
- [ ] **Pruebas E2E de flujos críticos**
  - Registro/login.
  - Subida de PDF.
  - Firma y consulta desde enlace corto.
  - Eliminación desde dashboard.
- [ ] **Seguridad HTTP en servidor**
  - Revisar cabeceras: CSP, HSTS, X-Content-Type-Options, Referrer-Policy.
  - Verificar configuración equivalente para Nginx y Apache.

## Prioridad baja

- [ ] **Observabilidad**
  - Definir métricas clave (uploads/día, 4xx/5xx, latencias por endpoint).
  - Estandarizar formato de logs para facilitar ingestión.
- [ ] **Estrategia de almacenamiento de firmas**
  - Evaluar mover blobs grandes a almacenamiento externo.
  - Estimar impacto de crecimiento en costos y performance.
- [ ] **Roadmap de escalabilidad**
  - Evaluar capa de servicios/DTO para dominios más complejos.

## Definición de terminado (DoD)

Una tarea de este backlog se considera terminada si:

1. Tiene PR aprobado y mergeado.
2. Incluye pruebas/documentación actualizadas.
3. No rompe `composer test:unit` ni `composer test:smoke`.
4. Incluye nota operativa cuando impacta despliegue o monitoreo.
