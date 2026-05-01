-- Rollback: 002_add_indexes_cascade.sql
-- Removes indexes, cascade FKs, and the slug column added in migration 002.

-- 1. Restore original FKs (no CASCADE)
ALTER TABLE enlaces_cortos
    DROP FOREIGN KEY fk_enlaces_documento,
    ADD CONSTRAINT enlaces_cortos_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);

ALTER TABLE firmas
    DROP FOREIGN KEY fk_firmas_documento,
    ADD CONSTRAINT firmas_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);

ALTER TABLE acciones
    DROP FOREIGN KEY fk_acciones_documento,
    ADD CONSTRAINT acciones_ibfk_1
        FOREIGN KEY (documento_id) REFERENCES documentos(id);

-- 2. Remove indexes
ALTER TABLE documentos   DROP INDEX idx_active_fecha;
ALTER TABLE enlaces_cortos
    DROP INDEX idx_active_expiracion,
    DROP KEY   uk_slug,
    DROP COLUMN slug;
ALTER TABLE firmas   DROP INDEX idx_firma_documento;
ALTER TABLE acciones DROP INDEX idx_accion_documento, DROP INDEX idx_accion_tipo;

-- 3. Remove migration version record
DELETE FROM schema_versions WHERE version = '002_add_indexes_cascade.sql';
