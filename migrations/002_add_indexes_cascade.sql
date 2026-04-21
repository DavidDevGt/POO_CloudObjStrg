-- Phase 2: indexes, ON DELETE CASCADE, slug column
-- Run AFTER 001_create_tables.sql

-- 1. Add slug column for efficient O(1) lookup (no LIKE scan)
ALTER TABLE enlaces_cortos
    ADD COLUMN slug VARCHAR(32) NULL AFTER enlace,
    ADD UNIQUE KEY uk_slug (slug);

-- 2. Indexes for hot query paths
ALTER TABLE documentos
    ADD INDEX idx_active_fecha (active, fecha_subida);

ALTER TABLE enlaces_cortos
    ADD INDEX idx_active_expiracion (active, fecha_expiracion);

ALTER TABLE firmas
    ADD INDEX idx_firma_documento (documento_id);

ALTER TABLE acciones
    ADD INDEX idx_accion_documento (documento_id),
    ADD INDEX idx_accion_tipo     (accion);

-- 3. ON DELETE CASCADE so orphan rows are cleaned up automatically
ALTER TABLE enlaces_cortos
    DROP FOREIGN KEY enlaces_cortos_ibfk_1,
    ADD CONSTRAINT fk_enlaces_documento
        FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE;

ALTER TABLE firmas
    DROP FOREIGN KEY firmas_ibfk_1,
    ADD CONSTRAINT fk_firmas_documento
        FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE;

ALTER TABLE acciones
    DROP FOREIGN KEY acciones_ibfk_1,
    ADD CONSTRAINT fk_acciones_documento
        FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE;
