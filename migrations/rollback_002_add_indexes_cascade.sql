-- Rollback for migration 002: remove indexes, cascade FKs, and slug column

ALTER TABLE acciones
    DROP FOREIGN KEY IF EXISTS fk_acciones_documento,
    DROP INDEX IF EXISTS idx_accion_tipo,
    DROP INDEX IF EXISTS idx_accion_documento;

ALTER TABLE firmas
    DROP FOREIGN KEY IF EXISTS fk_firmas_documento,
    DROP INDEX IF EXISTS idx_firma_documento;

ALTER TABLE enlaces_cortos
    DROP FOREIGN KEY IF EXISTS fk_enlaces_documento,
    DROP INDEX IF EXISTS idx_active_expiracion,
    DROP INDEX IF EXISTS uk_slug,
    DROP COLUMN IF EXISTS slug;

ALTER TABLE documentos
    DROP INDEX IF EXISTS idx_active_fecha;

-- Re-add plain (non-cascade) foreign keys as created by migration 001
ALTER TABLE enlaces_cortos
    ADD CONSTRAINT enlaces_cortos_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);

ALTER TABLE firmas
    ADD CONSTRAINT firmas_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);

ALTER TABLE acciones
    ADD CONSTRAINT acciones_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);
