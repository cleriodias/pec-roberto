-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: mysql.paoecafe83.com.br    Database: paoecafe8301
-- ------------------------------------------------------
-- Server version	10.6.24-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cashier_closures`
--

DROP TABLE IF EXISTS `cashier_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cashier_closures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `cash_amount` decimal(12,2) NOT NULL,
  `card_amount` decimal(12,2) NOT NULL,
  `master_cash_amount` decimal(12,2) DEFAULT NULL,
  `master_card_amount` decimal(12,2) DEFAULT NULL,
  `closed_date` date NOT NULL,
  `closed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `master_checked_by` bigint(20) unsigned DEFAULT NULL,
  `master_checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cashier_closures_user_unit_date_unique` (`user_id`,`unit_id`,`closed_date`),
  KEY `cashier_closures_unit_id_foreign` (`unit_id`),
  KEY `cashier_closures_user_id_idx` (`user_id`),
  KEY `cashier_closures_master_checked_by_foreign` (`master_checked_by`),
  CONSTRAINT `cashier_closures_master_checked_by_foreign` FOREIGN KEY (`master_checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cashier_closures_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `cashier_closures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cashier_closures`
--

LOCK TABLES `cashier_closures` WRITE;
/*!40000 ALTER TABLE `cashier_closures` DISABLE KEYS */;
/*!40000 ALTER TABLE `cashier_closures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_supplier_id_foreign` (`supplier_id`),
  KEY `expenses_unit_id_foreign` (`unit_id`),
  KEY `expenses_user_id_foreign` (`user_id`),
  CONSTRAINT `expenses_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2025_11_28_120000_add_employee_fields_to_users_table',1),(5,'2025_11_28_130000_create_tb2_unidades_table',1),(6,'2025_11_28_140000_add_tb2_id_to_users_and_create_pivot_table',1),(7,'2025_11_28_150000_create_tb1_produto_table',1),(8,'2025_11_28_160000_create_tb3_vendas_table',1),(9,'2025_11_28_170000_create_tb4_vendas_pg_table',1),(10,'2025_11_28_180000_add_funcao_original_to_users_table',1),(11,'2025_11_28_190000_create_salary_advances_table',1),(12,'2025_11_29_020500_add_vale_tipo_to_tb3_vendas_table',1),(13,'2025_11_29_030500_flatten_vale_tipo_into_tipo_pago',1),(14,'2025_11_29_060000_add_favorite_to_tb1_produto_table',1),(15,'2025_11_29_070500_create_cashier_closures_table',1),(16,'2025_11_29_080000_create_product_discards_table',1),(17,'2025_11_29_090000_add_vr_credit_to_tb1_produto_table',1),(18,'2025_12_01_120000_add_comanda_fields_to_tb3_vendas_table',1),(19,'2025_12_02_100000_add_cod_acesso_to_users_table',1),(20,'2025_12_03_000000_make_id_user_caixa_nullable_in_tb3_vendas',1),(21,'2025_12_03_010000_update_cashier_closures_unique_index',1),(22,'2025_12_20_230000_create_suppliers_table',1),(23,'2025_12_20_231000_create_expenses_table',1),(24,'2025_12_20_232000_add_unit_id_to_expenses_table',1),(25,'2025_12_20_233000_create_sales_disputes_table',1),(26,'2025_12_20_233500_create_sales_dispute_bids_table',1),(27,'2025_12_20_234000_add_approval_and_invoice_to_sales_dispute_bids_table',1),(28,'2025_12_21_000000_add_unit_id_to_salary_advances_table',1),(29,'2025_12_22_000000_add_unit_id_to_product_discards_table',1),(30,'2025_12_22_130000_create_newsletter_subscriptions_table',1),(31,'2025_12_22_140500_create_newsletter_notices_table',1),(32,'2025_12_23_000000_add_unique_phone_to_newsletter_subscriptions_table',1),(33,'2025_12_23_020000_add_user_id_to_expenses_table',1),(34,'2026_01_18_000000_create_tb_16_boletos_table',1),(35,'2026_04_01_000000_add_unit_price_to_product_discards_table',1),(36,'2026_04_01_010000_create_tb_17_configuracao_descarte_table',1),(37,'2026_04_01_020000_create_tb18_chamados_table',1),(38,'2026_04_02_000000_create_tb19_chamado_interacoes_table',1),(39,'2026_04_02_010000_create_tb20_chamado_anexos_table',1),(40,'2026_04_02_020000_create_tb21_usuarios_online_table',1),(41,'2026_04_02_030000_create_tb22_chat_mensagens_table',1),(42,'2026_04_04_010000_create_tb23_anydesck_codigos_table',1),(43,'2026_04_04_120000_add_master_review_fields_to_cashier_closures_table',1),(44,'2026_04_09_080000_add_tb2_status_to_tb2_unidades_table',1),(45,'2026_04_09_220000_create_tb24_controle_pagamentos_table',1),(46,'2026_04_10_160000_add_user_id_to_tb24_controle_pagamentos_table',1),(47,'2026_04_15_170000_add_tb1_qtd_to_tb1_produto_table',1),(48,'2026_04_15_171000_create_tb25_produto_movimentacoes_table',1),(49,'2026_04_16_190000_sync_short_product_barcodes_with_id',1),(50,'2026_04_16_200000_add_fiscal_fields_to_tb1_produto_table',1),(51,'2026_04_16_201000_create_tb26_configuracoes_fiscais_table',1),(52,'2026_04_16_202000_create_tb27_notas_fiscais_table',1),(53,'2026_04_16_203000_add_certificate_identity_to_tb26_configuracoes_fiscais_table',1),(54,'2026_04_16_204000_add_certificate_validity_to_tb26_configuracoes_fiscais_table',1),(55,'2026_04_17_020000_add_shared_certificate_password_to_tb26_configuracoes_fiscais_table',1),(56,'2026_04_17_030000_add_tb26_geracao_automatica_ativa_to_tb26_configuracoes_fiscais_table',1),(57,'2026_04_19_120000_expand_tipo_pagamento_length_on_tb4_vendas_pg',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_notices`
--

DROP TABLE IF EXISTS `newsletter_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `newsletter_subscription_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `newsletter_notices_newsletter_subscription_id_foreign` (`newsletter_subscription_id`),
  CONSTRAINT `newsletter_notices_newsletter_subscription_id_foreign` FOREIGN KEY (`newsletter_subscription_id`) REFERENCES `newsletter_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_notices`
--

LOCK TABLES `newsletter_notices` WRITE;
/*!40000 ALTER TABLE `newsletter_notices` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscriptions`
--

DROP TABLE IF EXISTS `newsletter_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletter_subscriptions_phone_unique` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscriptions`
--

LOCK TABLES `newsletter_subscriptions` WRITE;
/*!40000 ALTER TABLE `newsletter_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_discards`
--

DROP TABLE IF EXISTS `product_discards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_discards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_discards_user_id_foreign` (`user_id`),
  KEY `product_discards_product_id_foreign` (`product_id`),
  KEY `product_discards_unit_id_foreign` (`unit_id`),
  CONSTRAINT `product_discards_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `tb1_produto` (`tb1_id`) ON DELETE CASCADE,
  CONSTRAINT `product_discards_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `product_discards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_discards`
--

LOCK TABLES `product_discards` WRITE;
/*!40000 ALTER TABLE `product_discards` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_discards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_advances`
--

DROP TABLE IF EXISTS `salary_advances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_advances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `advance_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_advances_user_id_foreign` (`user_id`),
  KEY `salary_advances_unit_id_foreign` (`unit_id`),
  CONSTRAINT `salary_advances_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `salary_advances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_advances`
--

LOCK TABLES `salary_advances` WRITE;
/*!40000 ALTER TABLE `salary_advances` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_advances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_dispute_bids`
--

DROP TABLE IF EXISTS `sales_dispute_bids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_dispute_bids` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sales_dispute_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `invoice_note` text DEFAULT NULL,
  `invoice_file_path` varchar(255) DEFAULT NULL,
  `invoiced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_dispute_bids_sales_dispute_id_supplier_id_unique` (`sales_dispute_id`,`supplier_id`),
  KEY `sales_dispute_bids_supplier_id_foreign` (`supplier_id`),
  KEY `sales_dispute_bids_approved_by_foreign` (`approved_by`),
  CONSTRAINT `sales_dispute_bids_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_dispute_bids_sales_dispute_id_foreign` FOREIGN KEY (`sales_dispute_id`) REFERENCES `sales_disputes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_dispute_bids_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_dispute_bids`
--

LOCK TABLES `sales_dispute_bids` WRITE;
/*!40000 ALTER TABLE `sales_dispute_bids` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_dispute_bids` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_disputes`
--

DROP TABLE IF EXISTS `sales_disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_disputes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_name` varchar(160) NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_disputes_created_by_foreign` (`created_by`),
  CONSTRAINT `sales_disputes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_disputes`
--

LOCK TABLES `sales_disputes` WRITE;
/*!40000 ALTER TABLE `sales_disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('5T1MxfRRR7VuqqSbi9nG5C4K9tXTMoPG7dS5FKDZ',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiZFdnZ1UwVnlsaTBxNnlOYUVFZkk4cUEzYTdIRVpkY2txTVE3R0MwZCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1776754588);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `dispute` tinyint(1) NOT NULL DEFAULT 0,
  `access_code` varchar(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_access_code_unique` (`access_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb18_chamados`
--

DROP TABLE IF EXISTS `tb18_chamados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb18_chamados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `video_original_name` varchar(255) NOT NULL,
  `video_mime_type` varchar(120) NOT NULL,
  `video_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'aberto',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb18_chamados_user_id_foreign` (`user_id`),
  KEY `tb18_chamados_unit_id_foreign` (`unit_id`),
  KEY `tb18_chamados_status_index` (`status`),
  CONSTRAINT `tb18_chamados_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `tb18_chamados_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb18_chamados`
--

LOCK TABLES `tb18_chamados` WRITE;
/*!40000 ALTER TABLE `tb18_chamados` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb18_chamados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb19_chamado_interacoes`
--

DROP TABLE IF EXISTS `tb19_chamado_interacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb19_chamado_interacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(160) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb19_chamado_interacoes_support_ticket_id_foreign` (`support_ticket_id`),
  KEY `tb19_chamado_interacoes_user_id_foreign` (`user_id`),
  CONSTRAINT `tb19_chamado_interacoes_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `tb18_chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tb19_chamado_interacoes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb19_chamado_interacoes`
--

LOCK TABLES `tb19_chamado_interacoes` WRITE;
/*!40000 ALTER TABLE `tb19_chamado_interacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb19_chamado_interacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb1_produto`
--

DROP TABLE IF EXISTS `tb1_produto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb1_produto` (
  `tb1_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tb1_nome` varchar(45) NOT NULL,
  `tb1_vlr_custo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tb1_vlr_venda` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tb1_codbar` varchar(64) NOT NULL,
  `tb1_ncm` varchar(8) DEFAULT NULL,
  `tb1_cest` varchar(7) DEFAULT NULL,
  `tb1_cfop` varchar(4) DEFAULT NULL,
  `tb1_unidade_comercial` varchar(6) NOT NULL DEFAULT 'UN',
  `tb1_unidade_tributavel` varchar(6) NOT NULL DEFAULT 'UN',
  `tb1_origem` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `tb1_csosn` varchar(4) DEFAULT NULL,
  `tb1_cst` varchar(3) DEFAULT NULL,
  `tb1_aliquota_icms` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tb1_tipo` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `tb1_qtd` int(10) unsigned NOT NULL DEFAULT 0,
  `tb1_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `tb1_favorito` tinyint(1) NOT NULL DEFAULT 0,
  `tb1_vr_credit` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb1_id`),
  UNIQUE KEY `tb1_produto_tb1_codbar_unique` (`tb1_codbar`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb1_produto`
--

LOCK TABLES `tb1_produto` WRITE;
/*!40000 ALTER TABLE `tb1_produto` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb1_produto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb20_chamado_anexos`
--

DROP TABLE IF EXISTS `tb20_chamado_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb20_chamado_anexos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_interaction_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb20_chamado_anexos_support_ticket_interaction_id_foreign` (`support_ticket_interaction_id`),
  CONSTRAINT `tb20_chamado_anexos_support_ticket_interaction_id_foreign` FOREIGN KEY (`support_ticket_interaction_id`) REFERENCES `tb19_chamado_interacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb20_chamado_anexos`
--

LOCK TABLES `tb20_chamado_anexos` WRITE;
/*!40000 ALTER TABLE `tb20_chamado_anexos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb20_chamado_anexos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb21_usuarios_online`
--

DROP TABLE IF EXISTS `tb21_usuarios_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb21_usuarios_online` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `active_role` tinyint(3) unsigned NOT NULL,
  `active_unit_id` bigint(20) unsigned DEFAULT NULL,
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tb21_usuarios_online_session_id_unique` (`session_id`),
  KEY `tb21_usuarios_online_active_unit_id_foreign` (`active_unit_id`),
  KEY `tb21_usuarios_online_last_seen_at_index` (`last_seen_at`),
  KEY `tb21_usuarios_online_user_id_last_seen_at_index` (`user_id`,`last_seen_at`),
  CONSTRAINT `tb21_usuarios_online_active_unit_id_foreign` FOREIGN KEY (`active_unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `tb21_usuarios_online_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb21_usuarios_online`
--

LOCK TABLES `tb21_usuarios_online` WRITE;
/*!40000 ALTER TABLE `tb21_usuarios_online` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb21_usuarios_online` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb22_chat_mensagens`
--

DROP TABLE IF EXISTS `tb22_chat_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb22_chat_mensagens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `sender_role` tinyint(3) unsigned NOT NULL,
  `sender_unit_id` bigint(20) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb22_chat_mensagens_sender_unit_id_foreign` (`sender_unit_id`),
  KEY `tb22_chat_mensagens_sender_id_recipient_id_index` (`sender_id`,`recipient_id`),
  KEY `tb22_chat_mensagens_recipient_id_read_at_index` (`recipient_id`,`read_at`),
  CONSTRAINT `tb22_chat_mensagens_recipient_id_foreign` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tb22_chat_mensagens_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tb22_chat_mensagens_sender_unit_id_foreign` FOREIGN KEY (`sender_unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb22_chat_mensagens`
--

LOCK TABLES `tb22_chat_mensagens` WRITE;
/*!40000 ALTER TABLE `tb22_chat_mensagens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb22_chat_mensagens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb23_anydesck_codigos`
--

DROP TABLE IF EXISTS `tb23_anydesck_codigos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb23_anydesck_codigos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `code` varchar(13) NOT NULL,
  `type` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tb23_anydesck_codigos_code_unique` (`code`),
  KEY `tb23_anydesck_codigos_unit_id_type_index` (`unit_id`,`type`),
  CONSTRAINT `tb23_anydesck_codigos_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb23_anydesck_codigos`
--

LOCK TABLES `tb23_anydesck_codigos` WRITE;
/*!40000 ALTER TABLE `tb23_anydesck_codigos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb23_anydesck_codigos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb24_controle_pagamentos`
--

DROP TABLE IF EXISTS `tb24_controle_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb24_controle_pagamentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `frequencia` varchar(20) NOT NULL,
  `dia_semana` tinyint(3) unsigned DEFAULT NULL,
  `dia_mes` tinyint(3) unsigned DEFAULT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `quantidade_parcelas` int(10) unsigned NOT NULL,
  `valor_parcela` decimal(12,2) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb24_controle_pagamentos_user_id_foreign` (`user_id`),
  CONSTRAINT `tb24_controle_pagamentos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb24_controle_pagamentos`
--

LOCK TABLES `tb24_controle_pagamentos` WRITE;
/*!40000 ALTER TABLE `tb24_controle_pagamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb24_controle_pagamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb25_produto_movimentacoes`
--

DROP TABLE IF EXISTS `tb25_produto_movimentacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb25_produto_movimentacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` tinyint(3) unsigned NOT NULL COMMENT '1: entrada, 0: saida',
  `quantity` int(10) unsigned NOT NULL,
  `stock_before` int(10) unsigned NOT NULL,
  `stock_after` int(10) unsigned NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb25_produto_movimentacoes_user_id_foreign` (`user_id`),
  KEY `tb25_prod_mov_prod_created_idx` (`product_id`,`created_at`),
  CONSTRAINT `tb25_produto_movimentacoes_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `tb1_produto` (`tb1_id`) ON DELETE CASCADE,
  CONSTRAINT `tb25_produto_movimentacoes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb25_produto_movimentacoes`
--

LOCK TABLES `tb25_produto_movimentacoes` WRITE;
/*!40000 ALTER TABLE `tb25_produto_movimentacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb25_produto_movimentacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb26_configuracoes_fiscais`
--

DROP TABLE IF EXISTS `tb26_configuracoes_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb26_configuracoes_fiscais` (
  `tb26_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tb2_id` bigint(20) unsigned NOT NULL,
  `tb26_emitir_nfe` tinyint(1) NOT NULL DEFAULT 0,
  `tb26_emitir_nfce` tinyint(1) NOT NULL DEFAULT 0,
  `tb26_geracao_automatica_ativa` tinyint(1) NOT NULL DEFAULT 1,
  `tb26_ambiente` varchar(20) NOT NULL DEFAULT 'homologacao',
  `tb26_serie` varchar(10) NOT NULL DEFAULT '1',
  `tb26_proximo_numero` bigint(20) unsigned NOT NULL DEFAULT 1,
  `tb26_crt` tinyint(3) unsigned DEFAULT NULL,
  `tb26_csc_id` varchar(36) DEFAULT NULL,
  `tb26_csc` varchar(255) DEFAULT NULL,
  `tb26_certificado_tipo` varchar(2) DEFAULT NULL,
  `tb26_certificado_nome` varchar(255) DEFAULT NULL,
  `tb26_certificado_cnpj` varchar(14) DEFAULT NULL,
  `tb26_certificado_valido_ate` timestamp NULL DEFAULT NULL,
  `tb26_certificado_arquivo` varchar(255) DEFAULT NULL,
  `tb26_certificado_senha` text DEFAULT NULL,
  `tb26_certificado_senha_compartilhada` text DEFAULT NULL,
  `tb26_razao_social` varchar(255) DEFAULT NULL,
  `tb26_nome_fantasia` varchar(255) DEFAULT NULL,
  `tb26_ie` varchar(20) DEFAULT NULL,
  `tb26_im` varchar(20) DEFAULT NULL,
  `tb26_cnae` varchar(10) DEFAULT NULL,
  `tb26_logradouro` varchar(255) DEFAULT NULL,
  `tb26_numero` varchar(20) DEFAULT NULL,
  `tb26_complemento` varchar(255) DEFAULT NULL,
  `tb26_bairro` varchar(120) DEFAULT NULL,
  `tb26_codigo_municipio` varchar(7) DEFAULT NULL,
  `tb26_municipio` varchar(120) DEFAULT NULL,
  `tb26_uf` varchar(2) DEFAULT NULL,
  `tb26_cep` varchar(8) DEFAULT NULL,
  `tb26_telefone` varchar(20) DEFAULT NULL,
  `tb26_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb26_id`),
  UNIQUE KEY `tb26_configuracoes_fiscais_tb2_id_unique` (`tb2_id`),
  CONSTRAINT `tb26_configuracoes_fiscais_tb2_id_foreign` FOREIGN KEY (`tb2_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb26_configuracoes_fiscais`
--

LOCK TABLES `tb26_configuracoes_fiscais` WRITE;
/*!40000 ALTER TABLE `tb26_configuracoes_fiscais` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb26_configuracoes_fiscais` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb27_notas_fiscais`
--

DROP TABLE IF EXISTS `tb27_notas_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb27_notas_fiscais` (
  `tb27_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tb4_id` bigint(20) unsigned NOT NULL,
  `tb2_id` bigint(20) unsigned NOT NULL,
  `tb26_id` bigint(20) unsigned DEFAULT NULL,
  `tb27_modelo` varchar(10) NOT NULL,
  `tb27_ambiente` varchar(20) NOT NULL DEFAULT 'homologacao',
  `tb27_serie` varchar(10) DEFAULT NULL,
  `tb27_numero` bigint(20) unsigned DEFAULT NULL,
  `tb27_status` varchar(40) NOT NULL DEFAULT 'pendente_configuracao',
  `tb27_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tb27_payload`)),
  `tb27_erros` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tb27_erros`)),
  `tb27_chave_acesso` varchar(44) DEFAULT NULL,
  `tb27_protocolo` varchar(64) DEFAULT NULL,
  `tb27_recibo` varchar(64) DEFAULT NULL,
  `tb27_xml_envio` longtext DEFAULT NULL,
  `tb27_xml_retorno` longtext DEFAULT NULL,
  `tb27_mensagem` text DEFAULT NULL,
  `tb27_emitida_em` timestamp NULL DEFAULT NULL,
  `tb27_cancelada_em` timestamp NULL DEFAULT NULL,
  `tb27_ultima_tentativa_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb27_id`),
  UNIQUE KEY `tb27_notas_fiscais_tb4_id_unique` (`tb4_id`),
  KEY `tb27_notas_fiscais_tb26_id_foreign` (`tb26_id`),
  KEY `tb27_notas_fiscais_tb2_id_tb27_status_index` (`tb2_id`,`tb27_status`),
  CONSTRAINT `tb27_notas_fiscais_tb26_id_foreign` FOREIGN KEY (`tb26_id`) REFERENCES `tb26_configuracoes_fiscais` (`tb26_id`) ON DELETE SET NULL,
  CONSTRAINT `tb27_notas_fiscais_tb2_id_foreign` FOREIGN KEY (`tb2_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE CASCADE,
  CONSTRAINT `tb27_notas_fiscais_tb4_id_foreign` FOREIGN KEY (`tb4_id`) REFERENCES `tb4_vendas_pg` (`tb4_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb27_notas_fiscais`
--

LOCK TABLES `tb27_notas_fiscais` WRITE;
/*!40000 ALTER TABLE `tb27_notas_fiscais` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb27_notas_fiscais` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb2_unidade_user`
--

DROP TABLE IF EXISTS `tb2_unidade_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb2_unidade_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `tb2_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tb2_unidade_user_user_id_tb2_id_unique` (`user_id`,`tb2_id`),
  KEY `tb2_unidade_user_tb2_id_foreign` (`tb2_id`),
  CONSTRAINT `tb2_unidade_user_tb2_id_foreign` FOREIGN KEY (`tb2_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE CASCADE,
  CONSTRAINT `tb2_unidade_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb2_unidade_user`
--

LOCK TABLES `tb2_unidade_user` WRITE;
/*!40000 ALTER TABLE `tb2_unidade_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb2_unidade_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb2_unidades`
--

DROP TABLE IF EXISTS `tb2_unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb2_unidades` (
  `tb2_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tb2_nome` varchar(255) NOT NULL,
  `tb2_endereco` varchar(255) NOT NULL,
  `tb2_cep` varchar(20) NOT NULL,
  `tb2_fone` varchar(20) NOT NULL,
  `tb2_cnpj` varchar(20) NOT NULL,
  `tb2_localizacao` varchar(512) NOT NULL,
  `tb2_status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb2_unidades`
--

LOCK TABLES `tb2_unidades` WRITE;
/*!40000 ALTER TABLE `tb2_unidades` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb2_unidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb3_vendas`
--

DROP TABLE IF EXISTS `tb3_vendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb3_vendas` (
  `tb3_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tb4_id` bigint(20) unsigned DEFAULT NULL,
  `tb1_id` bigint(20) unsigned NOT NULL,
  `id_comanda` int(10) unsigned DEFAULT NULL,
  `produto_nome` varchar(120) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `quantidade` int(10) unsigned NOT NULL DEFAULT 1,
  `valor_total` decimal(12,2) NOT NULL,
  `data_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user_caixa` bigint(20) unsigned DEFAULT NULL,
  `id_user_vale` bigint(20) unsigned DEFAULT NULL,
  `id_lanc` bigint(20) unsigned DEFAULT NULL,
  `id_unidade` bigint(20) unsigned NOT NULL,
  `tipo_pago` enum('maquina','dinheiro','vale','refeicao','faturar') NOT NULL,
  `status_pago` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb3_id`),
  KEY `tb3_vendas_tb1_id_foreign` (`tb1_id`),
  KEY `tb3_vendas_id_user_vale_foreign` (`id_user_vale`),
  KEY `tb3_vendas_id_unidade_foreign` (`id_unidade`),
  KEY `tb3_vendas_tb4_id_foreign` (`tb4_id`),
  KEY `tb3_vendas_id_comanda_index` (`id_comanda`),
  KEY `tb3_vendas_id_lanc_index` (`id_lanc`),
  KEY `tb3_vendas_status_index` (`status`),
  KEY `tb3_vendas_id_user_caixa_foreign` (`id_user_caixa`),
  CONSTRAINT `tb3_vendas_id_unidade_foreign` FOREIGN KEY (`id_unidade`) REFERENCES `tb2_unidades` (`tb2_id`) ON UPDATE CASCADE,
  CONSTRAINT `tb3_vendas_id_user_caixa_foreign` FOREIGN KEY (`id_user_caixa`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `tb3_vendas_id_user_vale_foreign` FOREIGN KEY (`id_user_vale`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tb3_vendas_tb1_id_foreign` FOREIGN KEY (`tb1_id`) REFERENCES `tb1_produto` (`tb1_id`) ON UPDATE CASCADE,
  CONSTRAINT `tb3_vendas_tb4_id_foreign` FOREIGN KEY (`tb4_id`) REFERENCES `tb4_vendas_pg` (`tb4_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb3_vendas`
--

LOCK TABLES `tb3_vendas` WRITE;
/*!40000 ALTER TABLE `tb3_vendas` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb3_vendas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb4_vendas_pg`
--

DROP TABLE IF EXISTS `tb4_vendas_pg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb4_vendas_pg` (
  `tb4_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `valor_total` decimal(12,2) NOT NULL,
  `tipo_pagamento` varchar(40) NOT NULL,
  `valor_pago` decimal(12,2) DEFAULT NULL,
  `troco` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dois_pgto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tb4_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb4_vendas_pg`
--

LOCK TABLES `tb4_vendas_pg` WRITE;
/*!40000 ALTER TABLE `tb4_vendas_pg` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb4_vendas_pg` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_16_boletos`
--

DROP TABLE IF EXISTS `tb_16_boletos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_16_boletos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date NOT NULL,
  `barcode` varchar(128) NOT NULL,
  `digitable_line` varchar(256) NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tb_16_boletos_unit_id_foreign` (`unit_id`),
  KEY `tb_16_boletos_user_id_foreign` (`user_id`),
  KEY `tb_16_boletos_paid_by_foreign` (`paid_by`),
  CONSTRAINT `tb_16_boletos_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tb_16_boletos_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON DELETE SET NULL,
  CONSTRAINT `tb_16_boletos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_16_boletos`
--

LOCK TABLES `tb_16_boletos` WRITE;
/*!40000 ALTER TABLE `tb_16_boletos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb_16_boletos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_17_configuracao_descarte`
--

DROP TABLE IF EXISTS `tb_17_configuracao_descarte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_17_configuracao_descarte` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `percentual_aceitavel` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_17_configuracao_descarte`
--

LOCK TABLES `tb_17_configuracao_descarte` WRITE;
/*!40000 ALTER TABLE `tb_17_configuracao_descarte` DISABLE KEYS */;
INSERT INTO `tb_17_configuracao_descarte` VALUES (1,0.00,'2026-04-21 06:50:44','2026-04-21 06:50:44');
/*!40000 ALTER TABLE `tb_17_configuracao_descarte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `funcao` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `funcao_original` tinyint(4) DEFAULT NULL,
  `hr_ini` time DEFAULT NULL,
  `hr_fim` time DEFAULT NULL,
  `salario` decimal(10,2) NOT NULL DEFAULT 1518.00,
  `vr_cred` decimal(10,2) NOT NULL DEFAULT 350.00,
  `tb2_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `cod_acesso` varchar(10) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_cod_acesso_unique` (`cod_acesso`),
  KEY `users_tb2_id_foreign` (`tb2_id`),
  CONSTRAINT `users_tb2_id_foreign` FOREIGN KEY (`tb2_id`) REFERENCES `tb2_unidades` (`tb2_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'paoecafe8301'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-21  8:33:22
