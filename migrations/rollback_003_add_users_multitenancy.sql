-- Rollback: 003_add_users_multitenancy.sql
-- Removes the usuarios table and all user_id columns added in migration 003.
-- WARNING: This is destructive. All user accounts and user_id associations will be lost.
-- Run only after taking a full database backup.

-- 1. Remove FKs from acciones before dropping column
ALTER TABLE acciones
    DROP FOREIGN KEY fk_acciones_usuario,
    DROP COLUMN user_id;

-- 2. Remove signer_email from firmas
ALTER TABLE firmas
    DROP COLUMN signer_email;

-- 3. Remove FK and column from documentos
ALTER TABLE documentos
    DROP FOREIGN KEY fk_documentos_usuario,
    DROP INDEX idx_user_active,
    DROP COLUMN user_id;

-- 4. Drop usuarios table
DROP TABLE IF EXISTS usuarios;

-- 5. Remove migration version record
DELETE FROM schema_versions WHERE version = '003_add_users_multitenancy.sql';
