-- Sync manual para criar a tabela de codigos AnyDesck
-- Projeto: paoecafepremium

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
