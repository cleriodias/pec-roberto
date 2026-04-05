-- Sync manual para adicionar os campos de conferencia do Master em cashier_closures
-- Projeto: paoecafepremium
-- Execute este script diretamente no banco, sem usar php artisan migrate.

ALTER TABLE `cashier_closures`
    ADD COLUMN `master_cash_amount` DECIMAL(12, 2) NULL AFTER `card_amount`,
    ADD COLUMN `master_card_amount` DECIMAL(12, 2) NULL AFTER `master_cash_amount`,
    ADD COLUMN `master_checked_by` BIGINT UNSIGNED NULL AFTER `closed_at`,
    ADD COLUMN `master_checked_at` TIMESTAMP NULL DEFAULT NULL AFTER `master_checked_by`;

ALTER TABLE `cashier_closures`
    ADD CONSTRAINT `cashier_closures_master_checked_by_foreign`
        FOREIGN KEY (`master_checked_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL;
