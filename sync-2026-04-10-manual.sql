-- Sync manual consolidado para estruturas pendentes e registro em migrations.
-- Execute este script diretamente no banco correto da aplicacao (ex.: paoecafe8301).
-- Ele foi feito para ser idempotente: cria/ajusta a estrutura que faltar e marca as migrations correspondentes.

CREATE TABLE IF NOT EXISTS `tb_16_boletos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unit_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `due_date` DATE NOT NULL,
    `barcode` VARCHAR(128) NOT NULL,
    `digitable_line` VARCHAR(256) NOT NULL,
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
    `paid_by` BIGINT UNSIGNED NULL,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb_16_boletos_unit_id_foreign` (`unit_id`),
    KEY `tb_16_boletos_user_id_foreign` (`user_id`),
    KEY `tb_16_boletos_paid_by_foreign` (`paid_by`),
    CONSTRAINT `tb_16_boletos_unit_id_foreign`
        FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`)
        ON DELETE SET NULL,
    CONSTRAINT `tb_16_boletos_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb_16_boletos_paid_by_foreign`
        FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'product_discards'
          AND COLUMN_NAME = 'unit_price'
    ),
    'SELECT 1',
    'ALTER TABLE `product_discards` ADD COLUMN `unit_price` DECIMAL(12,2) NULL AFTER `quantity`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `tb_17_configuracao_descarte` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `percentual_aceitavel` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tb_17_configuracao_descarte` (`percentual_aceitavel`, `created_at`, `updated_at`)
SELECT 0, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `tb_17_configuracao_descarte`
);

CREATE TABLE IF NOT EXISTS `tb18_chamados` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `unit_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(160) NOT NULL,
    `description` TEXT NULL,
    `video_path` VARCHAR(255) NOT NULL,
    `video_original_name` VARCHAR(255) NOT NULL,
    `video_mime_type` VARCHAR(120) NOT NULL,
    `video_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'aberto',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb18_chamados_status_index` (`status`),
    KEY `tb18_chamados_user_id_foreign` (`user_id`),
    KEY `tb18_chamados_unit_id_foreign` (`unit_id`),
    CONSTRAINT `tb18_chamados_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb18_chamados_unit_id_foreign`
        FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb19_chamado_interacoes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `support_ticket_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `author_name` VARCHAR(160) NOT NULL,
    `message` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb19_chamado_interacoes_support_ticket_id_foreign` (`support_ticket_id`),
    KEY `tb19_chamado_interacoes_user_id_foreign` (`user_id`),
    CONSTRAINT `tb19_chamado_interacoes_support_ticket_id_foreign`
        FOREIGN KEY (`support_ticket_id`) REFERENCES `tb18_chamados` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb19_chamado_interacoes_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb20_chamado_anexos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `support_ticket_interaction_id` BIGINT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(120) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb20_chamado_anexos_support_ticket_interaction_id_foreign` (`support_ticket_interaction_id`),
    CONSTRAINT `tb20_chamado_anexos_support_ticket_interaction_id_foreign`
        FOREIGN KEY (`support_ticket_interaction_id`) REFERENCES `tb19_chamado_interacoes` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb21_usuarios_online` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_id` VARCHAR(255) NOT NULL,
    `active_role` TINYINT UNSIGNED NOT NULL,
    `active_unit_id` BIGINT UNSIGNED NULL,
    `last_seen_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tb21_usuarios_online_session_id_unique` (`session_id`),
    KEY `tb21_usuarios_online_last_seen_at_index` (`last_seen_at`),
    KEY `tb21_usuarios_online_user_id_last_seen_at_index` (`user_id`, `last_seen_at`),
    KEY `tb21_usuarios_online_active_unit_id_foreign` (`active_unit_id`),
    CONSTRAINT `tb21_usuarios_online_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb21_usuarios_online_active_unit_id_foreign`
        FOREIGN KEY (`active_unit_id`) REFERENCES `tb2_unidades` (`tb2_id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb22_chat_mensagens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `recipient_id` BIGINT UNSIGNED NOT NULL,
    `sender_role` TINYINT UNSIGNED NOT NULL,
    `sender_unit_id` BIGINT UNSIGNED NULL,
    `message` TEXT NOT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb22_chat_mensagens_sender_id_recipient_id_index` (`sender_id`, `recipient_id`),
    KEY `tb22_chat_mensagens_recipient_id_read_at_index` (`recipient_id`, `read_at`),
    KEY `tb22_chat_mensagens_sender_unit_id_foreign` (`sender_unit_id`),
    CONSTRAINT `tb22_chat_mensagens_sender_id_foreign`
        FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb22_chat_mensagens_recipient_id_foreign`
        FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `tb22_chat_mensagens_sender_unit_id_foreign`
        FOREIGN KEY (`sender_unit_id`) REFERENCES `tb2_unidades` (`tb2_id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb23_anydesck_codigos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unit_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(13) NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tb23_anydesck_codigos_code_unique` (`code`),
    KEY `tb23_anydesck_codigos_unit_id_type_index` (`unit_id`, `type`),
    CONSTRAINT `tb23_anydesck_codigos_unit_id_foreign`
        FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cashier_closures'
          AND COLUMN_NAME = 'master_cash_amount'
    ),
    'SELECT 1',
    'ALTER TABLE `cashier_closures` ADD COLUMN `master_cash_amount` DECIMAL(12,2) NULL AFTER `card_amount`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cashier_closures'
          AND COLUMN_NAME = 'master_card_amount'
    ),
    'SELECT 1',
    'ALTER TABLE `cashier_closures` ADD COLUMN `master_card_amount` DECIMAL(12,2) NULL AFTER `master_cash_amount`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cashier_closures'
          AND COLUMN_NAME = 'master_checked_by'
    ),
    'SELECT 1',
    'ALTER TABLE `cashier_closures` ADD COLUMN `master_checked_by` BIGINT UNSIGNED NULL AFTER `closed_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cashier_closures'
          AND COLUMN_NAME = 'master_checked_at'
    ),
    'SELECT 1',
    'ALTER TABLE `cashier_closures` ADD COLUMN `master_checked_at` TIMESTAMP NULL DEFAULT NULL AFTER `master_checked_by`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cashier_closures'
          AND CONSTRAINT_NAME = 'cashier_closures_master_checked_by_foreign'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE `cashier_closures` ADD CONSTRAINT `cashier_closures_master_checked_by_foreign` FOREIGN KEY (`master_checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tb2_unidades'
          AND COLUMN_NAME = 'tb2_status'
    ),
    'SELECT 1',
    'ALTER TABLE `tb2_unidades` ADD COLUMN `tb2_status` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `tb2_localizacao`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `tb24_controle_pagamentos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `frequencia` VARCHAR(20) NOT NULL,
    `dia_semana` TINYINT UNSIGNED NULL,
    `dia_mes` TINYINT UNSIGNED NULL,
    `valor_total` DECIMAL(12,2) NOT NULL,
    `quantidade_parcelas` INT UNSIGNED NOT NULL,
    `valor_parcela` DECIMAL(12,2) NOT NULL,
    `data_inicio` DATE NOT NULL,
    `data_fim` DATE NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tb24_controle_pagamentos_user_id_foreign` (`user_id`),
    CONSTRAINT `tb24_controle_pagamentos_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tb24_controle_pagamentos'
          AND COLUMN_NAME = 'user_id'
    ),
    'SELECT 1',
    'ALTER TABLE `tb24_controle_pagamentos` ADD COLUMN `user_id` BIGINT UNSIGNED NOT NULL AFTER `id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tb24_controle_pagamentos'
          AND INDEX_NAME = 'tb24_controle_pagamentos_user_id_foreign'
    ),
    'SELECT 1',
    'ALTER TABLE `tb24_controle_pagamentos` ADD INDEX `tb24_controle_pagamentos_user_id_foreign` (`user_id`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tb24_controle_pagamentos'
          AND CONSTRAINT_NAME = 'tb24_controle_pagamentos_user_id_foreign'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE `tb24_controle_pagamentos` ADD CONSTRAINT `tb24_controle_pagamentos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @batch := (
    SELECT COALESCE(MAX(batch), 0) + 1
    FROM `migrations`
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_01_18_000000_create_tb_16_boletos_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_01_18_000000_create_tb_16_boletos_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_01_000000_add_unit_price_to_product_discards_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_01_000000_add_unit_price_to_product_discards_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_01_010000_create_tb_17_configuracao_descarte_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_01_010000_create_tb_17_configuracao_descarte_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_01_020000_create_tb18_chamados_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_01_020000_create_tb18_chamados_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_02_000000_create_tb19_chamado_interacoes_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_02_000000_create_tb19_chamado_interacoes_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_02_010000_create_tb20_chamado_anexos_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_02_010000_create_tb20_chamado_anexos_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_02_020000_create_tb21_usuarios_online_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_02_020000_create_tb21_usuarios_online_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_02_030000_create_tb22_chat_mensagens_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_02_030000_create_tb22_chat_mensagens_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_04_010000_create_tb23_anydesck_codigos_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_04_010000_create_tb23_anydesck_codigos_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_04_120000_add_master_review_fields_to_cashier_closures_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_04_120000_add_master_review_fields_to_cashier_closures_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_09_080000_add_tb2_status_to_tb2_unidades_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_09_080000_add_tb2_status_to_tb2_unidades_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_09_220000_create_tb24_controle_pagamentos_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_09_220000_create_tb24_controle_pagamentos_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_10_160000_add_user_id_to_tb24_controle_pagamentos_table', @batch
WHERE NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE `migration` = '2026_04_10_160000_add_user_id_to_tb24_controle_pagamentos_table'
);
