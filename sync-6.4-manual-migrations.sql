-- Mudancas de banco introduzidas apos a branch sync-5.
-- Execute este script manualmente no banco da aplicacao.

START TRANSACTION;

SET @has_unit_price := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'product_discards'
      AND COLUMN_NAME = 'unit_price'
);
SET @sql := IF(
    @has_unit_price = 0,
    'ALTER TABLE product_discards ADD COLUMN unit_price DECIMAL(12,2) NULL AFTER quantity',
    'SELECT ''Coluna product_discards.unit_price ja existe'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tb_17_configuracao_descarte (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    percentual_aceitavel DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tb_17_configuracao_descarte (percentual_aceitavel, created_at, updated_at)
SELECT 0, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM tb_17_configuracao_descarte
);

CREATE TABLE IF NOT EXISTS tb18_chamados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    video_path VARCHAR(255) NOT NULL,
    video_original_name VARCHAR(255) NOT NULL,
    video_mime_type VARCHAR(120) NOT NULL,
    video_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY tb18_chamados_status_index (status),
    KEY tb18_chamados_user_id_foreign (user_id),
    KEY tb18_chamados_unit_id_foreign (unit_id),
    CONSTRAINT tb18_chamados_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT tb18_chamados_unit_id_foreign
        FOREIGN KEY (unit_id) REFERENCES tb2_unidades (tb2_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb19_chamado_interacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    support_ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    author_name VARCHAR(160) NOT NULL,
    message TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY tb19_chamado_interacoes_support_ticket_id_foreign (support_ticket_id),
    KEY tb19_chamado_interacoes_user_id_foreign (user_id),
    CONSTRAINT tb19_chamado_interacoes_support_ticket_id_foreign
        FOREIGN KEY (support_ticket_id) REFERENCES tb18_chamados (id)
        ON DELETE CASCADE,
    CONSTRAINT tb19_chamado_interacoes_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb20_chamado_anexos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    support_ticket_interaction_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY tb20_chamado_anexos_support_ticket_interaction_id_foreign (support_ticket_interaction_id),
    CONSTRAINT tb20_chamado_anexos_support_ticket_interaction_id_foreign
        FOREIGN KEY (support_ticket_interaction_id) REFERENCES tb19_chamado_interacoes (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
