-- Rollback for migration 003: remove users table and multi-tenancy columns
-- WARNING: this drops all user data permanently. Take a full backup first.

ALTER TABLE firmas
    DROP COLUMN IF EXISTS signer_email;

ALTER TABLE acciones
    DROP FOREIGN KEY IF EXISTS fk_acciones_usuario,
    DROP COLUMN IF EXISTS user_id;

ALTER TABLE documentos
    DROP FOREIGN KEY IF EXISTS fk_documentos_usuario,
    DROP INDEX IF EXISTS idx_user_active,
    DROP COLUMN IF EXISTS user_id;

DROP TABLE IF EXISTS usuarios;
