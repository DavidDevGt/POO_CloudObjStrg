CREATE TABLE IF NOT EXISTS usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre        VARCHAR(100) NULL,
    active        BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE documentos
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
    ADD CONSTRAINT fk_documentos_usuario
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD INDEX idx_user_active (user_id, active);

ALTER TABLE acciones
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER accion,
    ADD CONSTRAINT fk_acciones_usuario
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE firmas
    ADD COLUMN IF NOT EXISTS signer_email VARCHAR(255) NULL AFTER firma_data;
