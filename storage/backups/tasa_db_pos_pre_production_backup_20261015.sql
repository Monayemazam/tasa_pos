-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: tasa-db-pos
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `accessory_consumptions`
--

DROP TABLE IF EXISTS `accessory_consumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accessory_consumptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `accessory_item_id` bigint(20) unsigned NOT NULL,
  `consumption_date` date NOT NULL,
  `qty` decimal(12,3) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accessory_consumptions_created_by_foreign` (`created_by`),
  KEY `accessory_consumptions_updated_by_foreign` (`updated_by`),
  KEY `accessory_consumptions_accessory_item_id_consumption_date_index` (`accessory_item_id`,`consumption_date`),
  CONSTRAINT `accessory_consumptions_accessory_item_id_foreign` FOREIGN KEY (`accessory_item_id`) REFERENCES `accessory_items` (`id`),
  CONSTRAINT `accessory_consumptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accessory_consumptions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accessory_consumptions_qty_check` CHECK (`qty` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessory_consumptions`
--

LOCK TABLES `accessory_consumptions` WRITE;
/*!40000 ALTER TABLE `accessory_consumptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `accessory_consumptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accessory_items`
--

DROP TABLE IF EXISTS `accessory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accessory_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `min_stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accessory_items_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessory_items`
--

LOCK TABLES `accessory_items` WRITE;
/*!40000 ALTER TABLE `accessory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `accessory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accessory_purchases`
--

DROP TABLE IF EXISTS `accessory_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accessory_purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `accessory_item_id` bigint(20) unsigned NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL,
  `rate` decimal(12,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accessory_purchases_created_by_foreign` (`created_by`),
  KEY `accessory_purchases_updated_by_foreign` (`updated_by`),
  KEY `accessory_purchases_accessory_item_id_purchase_date_index` (`accessory_item_id`,`purchase_date`),
  CONSTRAINT `accessory_purchases_accessory_item_id_foreign` FOREIGN KEY (`accessory_item_id`) REFERENCES `accessory_items` (`id`),
  CONSTRAINT `accessory_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accessory_purchases_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accessory_purchases_values_check` CHECK (`qty` > 0 and `rate` >= 0 and `paid_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessory_purchases`
--

LOCK TABLES `accessory_purchases` WRITE;
/*!40000 ALTER TABLE `accessory_purchases` DISABLE KEYS */;
/*!40000 ALTER TABLE `accessory_purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(40) NOT NULL,
  `auditable_type` varchar(120) NOT NULL,
  `auditable_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 02:26:58\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 20:26:58'),(2,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 02:26:59\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 20:26:59'),(3,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 02:28:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 20:28:00'),(4,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 02:28:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 20:28:00'),(5,1,'pathao_settings_updated','App\\Models\\Setting',NULL,'{\"pathao.store_id\":\"\",\"pathao.sender_name\":\"\",\"pathao.sender_phone\":\"\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":0.5,\"pathao.show_success_rate\":1}','{\"pathao.store_id\":\"1\",\"pathao.sender_name\":\"Tasa Leather Goods\",\"pathao.sender_phone\":\"\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":\"0.5\",\"pathao.show_success_rate\":\"1\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:24:39'),(6,1,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":1,\"invoice_no\":\"TASA-2610-0001\",\"grand_total\":\"1000.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01845700011\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:25:34'),(7,1,'pathao_settings_updated','App\\Models\\Setting',NULL,'{\"pathao.store_id\":1,\"pathao.sender_name\":\"Tasa Leather Goods\",\"pathao.sender_phone\":\"\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":0.5,\"pathao.show_success_rate\":1}','{\"pathao.store_id\":\"67938\",\"pathao.sender_name\":\"Tasa\",\"pathao.sender_phone\":\"01332679646\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":\"0.5\",\"pathao.show_success_rate\":\"1\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:32:24'),(8,1,'pathao_settings_updated','App\\Models\\Setting',NULL,'{\"pathao.store_id\":67938,\"pathao.sender_name\":\"Tasa\",\"pathao.sender_phone\":\"01332679646\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":0.5,\"pathao.show_success_rate\":1}','{\"pathao.store_id\":\"67938\",\"pathao.sender_name\":\"Tasa\",\"pathao.sender_phone\":\"01332679646\",\"pathao.default_delivery_type\":48,\"pathao.default_item_type\":2,\"pathao.default_weight\":\"0.5\",\"pathao.show_success_rate\":\"1\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:32:26'),(9,1,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":2,\"invoice_no\":\"TASA-2610-0002\",\"grand_total\":\"10000.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01845700011\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:33:19'),(10,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-14 03:33:57\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:33:57'),(11,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-14 03:33:57\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:33:57'),(12,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 03:34:24\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:34:24'),(13,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 03:34:24\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:34:24'),(14,1,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":3,\"invoice_no\":\"TASA-2610-0003\",\"grand_total\":\"1223.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01845700011\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:39:59'),(15,1,'role_updated','App\\Models\\Role',2,'{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"products.view\",\"products.create\",\"products.update\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"expenses.view\",\"expenses.create\",\"reports.sales\"]}','{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"products.view\",\"products.create\",\"products.update\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"expenses.view\",\"expenses.create\",\"reports.sales\",\"sales.return\",\"sales.cancel\",\"courier.settle_cod\",\"products.import\",\"inventory.view_costs\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-13 21:44:19'),(16,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 23:29:04\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:29:04'),(17,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-14 23:29:04\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:29:04'),(18,1,'created','App\\Models\\Product',2,NULL,'{\"product_category_id\":2,\"name\":\"Eyewear Case Series 1 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000001\",\"cost_price\":\"0.00\",\"selling_price\":\"850.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":2,\"variants\":[{\"id\":3,\"product_id\":2,\"sku\":\"EWCS1 SE-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"850.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(19,1,'created','App\\Models\\Product',3,NULL,'{\"product_category_id\":2,\"name\":\"Eyewear Case Series 3\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000002\",\"cost_price\":\"0.00\",\"selling_price\":\"850.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":3,\"variants\":[{\"id\":4,\"product_id\":3,\"sku\":\"EWCS3-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"850.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(20,1,'created','App\\Models\\Product',4,NULL,'{\"product_category_id\":2,\"name\":\"Eyewear Case Series 3 Raw\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000003\",\"cost_price\":\"0.00\",\"selling_price\":\"950.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":4,\"variants\":[{\"id\":5,\"product_id\":4,\"sku\":\"EWCS3-OILBROWN\",\"color_name\":\"Oil Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Oil Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"950.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(21,1,'created','App\\Models\\Product',5,NULL,'{\"product_category_id\":2,\"name\":\"Smart Organizer Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000004\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":5,\"variants\":[{\"id\":6,\"product_id\":5,\"sku\":\"SOS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(22,1,'created','App\\Models\\Product',6,NULL,'{\"product_category_id\":2,\"name\":\"Smart Organizer Series 1 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000005\",\"cost_price\":\"0.00\",\"selling_price\":\"1580.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":6,\"variants\":[{\"id\":7,\"product_id\":6,\"sku\":\"SOS1 SE-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"1580.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(23,1,'created','App\\Models\\Product',7,NULL,'{\"product_category_id\":3,\"name\":\"Long Wallet Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000006\",\"cost_price\":\"0.00\",\"selling_price\":\"1199.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":7,\"variants\":[{\"id\":8,\"product_id\":7,\"sku\":\"LWS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1199.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":9,\"product_id\":7,\"sku\":\"LWS1-CHOCOLATE\",\"color_name\":\"Chocolate\",\"attribute_name\":\"Color\",\"attribute_value\":\"Chocolate\",\"cost_price\":\"0.00\",\"selling_price\":\"1199.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(24,1,'created','App\\Models\\Product',8,NULL,'{\"product_category_id\":3,\"name\":\"Long Wallet Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000007\",\"cost_price\":\"0.00\",\"selling_price\":\"2280.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":8,\"variants\":[{\"id\":10,\"product_id\":8,\"sku\":\"LWS2-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"2280.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(25,1,'created','App\\Models\\Product',9,NULL,'{\"product_category_id\":3,\"name\":\"Long Wallet Series 5\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000008\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":9,\"variants\":[{\"id\":11,\"product_id\":9,\"sku\":\"LWS5-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":12,\"product_id\":9,\"sku\":\"LWS5-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(26,1,'created','App\\Models\\Product',10,NULL,'{\"product_category_id\":3,\"name\":\"Long Wallet Series 6\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000009\",\"cost_price\":\"0.00\",\"selling_price\":\"1799.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":10,\"variants\":[{\"id\":13,\"product_id\":10,\"sku\":\"LWS6-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1799.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":14,\"product_id\":10,\"sku\":\"LWS6-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"1799.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(27,1,'created','App\\Models\\Product',11,NULL,'{\"product_category_id\":3,\"name\":\"Long Wallet Series 7\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000010\",\"cost_price\":\"0.00\",\"selling_price\":\"1599.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":11,\"variants\":[{\"id\":15,\"product_id\":11,\"sku\":\"LWS7-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1599.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":16,\"product_id\":11,\"sku\":\"LWS7-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"1599.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(28,1,'created','App\\Models\\Product',12,NULL,'{\"product_category_id\":4,\"name\":\"Minimalist Wallet Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000011\",\"cost_price\":\"0.00\",\"selling_price\":\"790.00\",\"stock_qty\":3,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":12,\"variants\":[{\"id\":17,\"product_id\":12,\"sku\":\"MWS2-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"790.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":19,\"product_id\":12,\"sku\":\"MWS2-DARK ASH\",\"color_name\":\"Dark Ash\",\"attribute_name\":\"Color\",\"attribute_value\":\"Dark Ash\",\"cost_price\":\"0.00\",\"selling_price\":\"790.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":18,\"product_id\":12,\"sku\":\"MWS2-OILB ROWN\",\"color_name\":\"Oil Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Oil Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"790.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(29,1,'created','App\\Models\\Product',13,NULL,'{\"product_category_id\":4,\"name\":\"Minimalist Wallet Series 3\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000012\",\"cost_price\":\"0.00\",\"selling_price\":\"490.00\",\"stock_qty\":3,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":13,\"variants\":[{\"id\":20,\"product_id\":13,\"sku\":\"MWS3-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"490.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":22,\"product_id\":13,\"sku\":\"MWS2-REDWINE\",\"color_name\":\"Red Wine\",\"attribute_name\":\"Color\",\"attribute_value\":\"Red Wine\",\"cost_price\":\"0.00\",\"selling_price\":\"490.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":21,\"product_id\":13,\"sku\":\"MWS3-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"490.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(30,1,'created','App\\Models\\Product',14,NULL,'{\"product_category_id\":4,\"name\":\"Minimalist Wallet Series 3 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000013\",\"cost_price\":\"0.00\",\"selling_price\":\"590.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":14,\"variants\":[{\"id\":23,\"product_id\":14,\"sku\":\"MWS3 SE-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"590.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(31,1,'created','App\\Models\\Product',15,NULL,'{\"product_category_id\":2,\"name\":\"Passport Holder Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000014\",\"cost_price\":\"0.00\",\"selling_price\":\"999.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":15,\"variants\":[{\"id\":25,\"product_id\":15,\"sku\":\"PHS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"999.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":24,\"product_id\":15,\"sku\":\"PHS1-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"999.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(32,1,'created','App\\Models\\Product',16,NULL,'{\"product_category_id\":2,\"name\":\"Passport Holder Series 1 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000015\",\"cost_price\":\"0.00\",\"selling_price\":\"999.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":16,\"variants\":[{\"id\":26,\"product_id\":16,\"sku\":\"PHSSE1-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"999.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(33,1,'created','App\\Models\\Product',17,NULL,'{\"product_category_id\":2,\"name\":\"Passport Holder Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000016\",\"cost_price\":\"0.00\",\"selling_price\":\"950.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":17,\"variants\":[{\"id\":28,\"product_id\":17,\"sku\":\"PHS1-DARK ASH\",\"color_name\":\"Dark Ash\",\"attribute_name\":\"Color\",\"attribute_value\":\"Dark Ash\",\"cost_price\":\"0.00\",\"selling_price\":\"950.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":27,\"product_id\":17,\"sku\":\"PHS1-OIL BROWN\",\"color_name\":\"Oil Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Oil Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"950.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(34,1,'created','App\\Models\\Product',18,NULL,'{\"product_category_id\":4,\"name\":\"Mulberry Bifold Wallet Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000017\",\"cost_price\":\"0.00\",\"selling_price\":\"1299.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":18,\"variants\":[{\"id\":29,\"product_id\":18,\"sku\":\"MBWS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"1299.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":30,\"product_id\":18,\"sku\":\"MNWS1-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(35,1,'created','App\\Models\\Product',19,NULL,'{\"product_category_id\":4,\"name\":\"Mulberry Bifold Wallet Series 1 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000018\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":19,\"variants\":[{\"id\":31,\"product_id\":19,\"sku\":\"MNWS1SE-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"1390.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(36,1,'created','App\\Models\\Product',20,NULL,'{\"product_category_id\":5,\"name\":\"A4 File Bag Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000019\",\"cost_price\":\"0.00\",\"selling_price\":\"3399.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":20,\"variants\":[{\"id\":32,\"product_id\":20,\"sku\":\"A4FBS2-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"3399.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":33,\"product_id\":20,\"sku\":\"A4FBS2-CHOCOLATE\",\"color_name\":\"Chocolate\",\"attribute_name\":\"Color\",\"attribute_value\":\"Chocolate\",\"cost_price\":\"0.00\",\"selling_price\":\"3399.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(37,1,'created','App\\Models\\Product',21,NULL,'{\"product_category_id\":5,\"name\":\"A4 File Bag Series 3\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000020\",\"cost_price\":\"0.00\",\"selling_price\":\"2999.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":21,\"variants\":[{\"id\":34,\"product_id\":21,\"sku\":\"A4FBS3-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"2999.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":35,\"product_id\":21,\"sku\":\"A4FBS3-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"2950.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(38,1,'created','App\\Models\\Product',22,NULL,'{\"product_category_id\":5,\"name\":\"Shoulder Bag Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000021\",\"cost_price\":\"0.00\",\"selling_price\":\"2699.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":22,\"variants\":[{\"id\":36,\"product_id\":22,\"sku\":\"SBS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"2699.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":37,\"product_id\":22,\"sku\":\"SBS1-BLUE\",\"color_name\":\"Blue\",\"attribute_name\":\"Color\",\"attribute_value\":\"Blue\",\"cost_price\":\"0.00\",\"selling_price\":\"2699.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(39,1,'created','App\\Models\\Product',23,NULL,'{\"product_category_id\":5,\"name\":\"Shoulder Bag Series 1 SE\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000022\",\"cost_price\":\"0.00\",\"selling_price\":\"2699.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":23,\"variants\":[{\"id\":38,\"product_id\":23,\"sku\":\"SBS1 SE-GREEN\",\"color_name\":\"Green\",\"attribute_name\":\"Color\",\"attribute_value\":\"Green\",\"cost_price\":\"0.00\",\"selling_price\":\"2699.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(40,1,'created','App\\Models\\Product',24,NULL,'{\"product_category_id\":5,\"name\":\"Messenger Bag Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000023\",\"cost_price\":\"0.00\",\"selling_price\":\"3099.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":24,\"variants\":[{\"id\":39,\"product_id\":24,\"sku\":\"MBS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"3099.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":40,\"product_id\":24,\"sku\":\"MBS1-OIL BROWN\",\"color_name\":\"Oil Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Oil Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"3099.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(41,1,'created','App\\Models\\Product',25,NULL,'{\"product_category_id\":2,\"name\":\"Mouse Pad Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000024\",\"cost_price\":\"0.00\",\"selling_price\":\"699.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":25,\"variants\":[{\"id\":41,\"product_id\":25,\"sku\":\"MPS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"699.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":42,\"product_id\":25,\"sku\":\"MPS1-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"699.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(42,1,'created','App\\Models\\Product',26,NULL,'{\"product_category_id\":2,\"name\":\"Mouse Pad Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000025\",\"cost_price\":\"0.00\",\"selling_price\":\"899.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":26,\"variants\":[{\"id\":43,\"product_id\":26,\"sku\":\"MPS2-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"899.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":44,\"product_id\":26,\"sku\":\"MPS2-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"899.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(43,1,'created','App\\Models\\Product',27,NULL,'{\"product_category_id\":2,\"name\":\"Mouse Pad Series 3\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000026\",\"cost_price\":\"0.00\",\"selling_price\":\"420.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":27,\"variants\":[{\"id\":45,\"product_id\":27,\"sku\":\"MPS3-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"420.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(44,1,'created','App\\Models\\Product',28,NULL,'{\"product_category_id\":5,\"name\":\"Vintage Satchel Bag Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000027\",\"cost_price\":\"0.00\",\"selling_price\":\"7999.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":28,\"variants\":[{\"id\":46,\"product_id\":28,\"sku\":\"VSS1-CHOCOLATE\",\"color_name\":\"Chocolate\",\"attribute_name\":\"Color\",\"attribute_value\":\"Chocolate\",\"cost_price\":\"0.00\",\"selling_price\":\"7999.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(45,1,'created','App\\Models\\Product',29,NULL,'{\"product_category_id\":5,\"name\":\"Vintage Satchel Bag Series 3\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000028\",\"cost_price\":\"0.00\",\"selling_price\":\"9900.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":29,\"variants\":[{\"id\":47,\"product_id\":29,\"sku\":\"VSS3-OIL BROWN\",\"color_name\":\"Oil Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Oil Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"9900.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(46,1,'created','App\\Models\\Product',30,NULL,'{\"product_category_id\":5,\"name\":\"Backpack Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000029\",\"cost_price\":\"0.00\",\"selling_price\":\"7800.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":30,\"variants\":[{\"id\":48,\"product_id\":30,\"sku\":\"BPS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"7800.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":49,\"product_id\":30,\"sku\":\"BPS1-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"7800.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(47,1,'created','App\\Models\\Product',31,NULL,'{\"product_category_id\":5,\"name\":\"Briefcase Series 2\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000030\",\"cost_price\":\"0.00\",\"selling_price\":\"18000.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":31,\"variants\":[{\"id\":50,\"product_id\":31,\"sku\":\"BSS2-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"18000.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(48,1,'created','App\\Models\\Product',32,NULL,'{\"product_category_id\":5,\"name\":\"Folio Laptop Bag Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000031\",\"cost_price\":\"0.00\",\"selling_price\":\"7199.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":32,\"variants\":[{\"id\":51,\"product_id\":32,\"sku\":\"FLS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"7199.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":52,\"product_id\":32,\"sku\":\"FLS1-TAN\",\"color_name\":\"Tan\",\"attribute_name\":\"Color\",\"attribute_value\":\"Tan\",\"cost_price\":\"0.00\",\"selling_price\":\"7199.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(49,1,'created','App\\Models\\Product',33,NULL,'{\"product_category_id\":6,\"name\":\"Folk Waist Belt Series 1\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000032\",\"cost_price\":\"0.00\",\"selling_price\":\"2250.00\",\"stock_qty\":2,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":33,\"variants\":[{\"id\":54,\"product_id\":33,\"sku\":\"FWBS1-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"2250.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null},{\"id\":53,\"product_id\":33,\"sku\":\"FWBS1-BROWN\",\"color_name\":\"Brown\",\"attribute_name\":\"Color\",\"attribute_value\":\"Brown\",\"cost_price\":\"0.00\",\"selling_price\":\"2250.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(50,1,'created','App\\Models\\Product',34,NULL,'{\"product_category_id\":5,\"name\":\"Briefcase Series 5\",\"has_variants\":true,\"variation_type\":\"Color\",\"sku\":\"P-000033\",\"cost_price\":\"0.00\",\"selling_price\":\"10500.00\",\"stock_qty\":1,\"min_stock\":1,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"id\":34,\"variants\":[{\"id\":55,\"product_id\":34,\"sku\":\"BSS5-BLACK\",\"color_name\":\"Black\",\"attribute_name\":\"Color\",\"attribute_value\":\"Black\",\"cost_price\":\"0.00\",\"selling_price\":\"10500.00\",\"stock_qty\":1,\"min_stock\":1,\"barcode\":null,\"is_active\":true,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-14T17:32:30.000000Z\",\"updated_at\":\"2026-10-14T17:32:30.000000Z\",\"deleted_at\":null}]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(51,1,'imported','App\\Models\\Product',NULL,NULL,'{\"import_log_id\":1,\"filename\":\"tasa_product_import_template (1).xlsx\",\"total_rows\":55,\"success_rows\":53,\"failed_rows\":2}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:32:30'),(52,1,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":4,\"invoice_no\":\"TASA-2610-0004\",\"grand_total\":\"3399.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01845700011\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:34:11'),(53,1,'sale_cancelled','App\\Models\\Sale',NULL,'{\"status\":\"active\",\"cancelled_at\":null,\"cancel_reason\":null}','{\"id\":4,\"invoice_no\":\"TASA-2610-0004\",\"status\":\"cancelled\",\"cancel_reason\":\"aaaaa\",\"cancelled_by\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 17:47:36'),(54,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 00:08:10\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:08:10'),(55,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 00:08:10\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:08:10'),(56,1,'employee.created','App\\Models\\Employee',1,NULL,'{\"name\":\"Mizanur Rahman\",\"phone\":\"01711223344\",\"designation\":\"Senior Leather Craftsman\",\"monthly_salary\":\"35000.00\",\"joined_at\":null,\"left_at\":null,\"is_active\":true,\"updated_at\":\"2026-10-14T18:27:59.000000Z\",\"created_at\":\"2026-10-14T18:27:59.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:27:59'),(57,1,'salary_month.created','App\\Models\\SalaryMonth',1,NULL,'{\"employee_id\":1,\"period\":\"2026-09-30T18:00:00.000000Z\",\"base_salary\":\"35000.00\",\"bonus\":\"0.00\",\"deduction\":\"0.00\",\"note\":null,\"created_by\":1,\"updated_at\":\"2026-10-14T18:27:59.000000Z\",\"created_at\":\"2026-10-14T18:27:59.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:27:59'),(58,1,'worker.created','App\\Models\\Worker',1,NULL,'{\"name\":\"Kashem Mia\",\"phone\":\"01811223344\",\"role\":\"Master Pattern Cutter\",\"wage_basis\":\"per_piece\",\"rate\":\"150.00\",\"is_active\":true,\"updated_at\":\"2026-10-14T18:30:59.000000Z\",\"created_at\":\"2026-10-14T18:30:59.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:30:59'),(59,1,'worker_earning.created','App\\Models\\WorkerEarning',1,NULL,'{\"worker_id\":1,\"work_date\":\"2026-10-13T18:00:00.000000Z\",\"period_start\":null,\"qty\":\"10.01\",\"rate\":\"150.00\",\"amount\":\"1501.50\",\"note\":\"10 pieces finished\",\"created_by\":1,\"updated_at\":\"2026-10-14T18:34:39.000000Z\",\"created_at\":\"2026-10-14T18:34:39.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:34:39'),(60,1,'worker_payment.created','App\\Models\\WorkerPayment',1,NULL,'{\"worker_id\":1,\"paid_on\":\"2026-10-13T18:00:00.000000Z\",\"amount\":\"501.50\",\"method\":\"cash\",\"note\":\"Partial payout\",\"created_by\":1,\"updated_at\":\"2026-10-14T18:36:58.000000Z\",\"created_at\":\"2026-10-14T18:36:58.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:36:58'),(61,1,'salary_payment.created','App\\Models\\SalaryPayment',1,NULL,'{\"employee_id\":1,\"period\":\"2026-09-30T18:00:00.000000Z\",\"paid_on\":\"2026-10-13T18:00:00.000000Z\",\"amount\":\"15000.00\",\"type\":\"salary\",\"method\":\"cash\",\"note\":\"Mid-month salary\",\"created_by\":1,\"updated_at\":\"2026-10-14T18:37:48.000000Z\",\"created_at\":\"2026-10-14T18:37:48.000000Z\",\"id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:37:48'),(62,1,'invoice_settings_updated','App\\Models\\Setting',NULL,'{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"\",\"invoice.terms\":\"\",\"invoice.custom_template\":null,\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"\",\"general.phone\":\"\",\"invoice.selectable_fields\":null,\"general.logo\":\"\"}','{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"Thank you for shopping with Tasa Leather Goods!\",\"invoice.terms\":\"Exchange within 7 days with invoice. No cash refunds.\",\"invoice.custom_template\":null,\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"invoice.selectable_fields\":{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"},\"general.logo\":\"\\/storage\\/branding\\/PbnMGnQgXyTgvvZeLrfFldSoucadhVvjGRas2jfw.png\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:49:17'),(63,1,'invoice_settings_updated','App\\Models\\Setting',NULL,'{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"Thank you for shopping with Tasa Leather Goods!\",\"invoice.terms\":\"Exchange within 7 days with invoice. No cash refunds.\",\"invoice.custom_template\":\"\",\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"invoice.selectable_fields\":{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"},\"general.logo\":\"\\/storage\\/branding\\/PbnMGnQgXyTgvvZeLrfFldSoucadhVvjGRas2jfw.png\"}','{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"Thank you for shopping with Tasa Leather Goods!\",\"invoice.terms\":\"Exchange within 7 days with invoice. No cash refunds.\",\"invoice.custom_template\":null,\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"invoice.selectable_fields\":{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"},\"general.logo\":\"\\/storage\\/branding\\/Han3F00yqZyeYS1vUxTXRBIFuB4bHnaImw5Rz4wT.png\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:49:47'),(64,1,'invoice_settings_updated','App\\Models\\Setting',NULL,'{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"Thank you for shopping with Tasa Leather Goods!\",\"invoice.terms\":\"Exchange within 7 days with invoice. No cash refunds.\",\"invoice.custom_template\":\"\",\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"invoice.selectable_fields\":{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"},\"general.logo\":\"\\/storage\\/branding\\/tasa-logo.png\"}','{\"invoice.prefix\":\"TASA\",\"invoice.paper_size\":\"A4\",\"invoice.footer_note\":\"Thank you for shopping with Tasa Leather Goods!\",\"invoice.terms\":\"Exchange within 7 days with invoice. No cash refunds.\",\"invoice.custom_template\":null,\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"invoice.selectable_fields\":{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"},\"general.logo\":\"\\/storage\\/branding\\/o9V5zJNNDpf80BVERPdTM4F1HcHLZ7UchGXfeJ6G.png\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:58:48'),(65,1,'general_settings_updated','App\\Models\\Setting',NULL,'{\"general.shop_name\":\"Tasa Leather Goods\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1700-000000\",\"general.currency_symbol\":\"\\u09f3\",\"inventory.allow_negative_stock\":0,\"payroll.cost_price_includes_labour\":0,\"sales.allow_price_edit\":null,\"pathao.show_success_rate\":1}','{\"general.shop_name\":\"Tasa Bangladesh\",\"general.address\":\"Dhaka, Bangladesh\",\"general.phone\":\"+880 1332-679646\",\"general.currency_symbol\":\"\\u09f3\",\"inventory.allow_negative_stock\":\"0\",\"payroll.cost_price_includes_labour\":\"0\",\"sales.allow_price_edit\":\"1\",\"pathao.show_success_rate\":\"1\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 18:59:40'),(66,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:00:46\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:00:46'),(67,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:00:47\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:00:47'),(68,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:01:44\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:01:44'),(69,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:01:44\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:01:44'),(70,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:50:38\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:50:38'),(71,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:50:38\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:50:38'),(72,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:51:31\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:51:31'),(73,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:51:32\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:51:32'),(74,2,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":5,\"invoice_no\":\"TASA-2610-0005\",\"grand_total\":\"3499.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01845700011\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:51:41'),(75,1,'role_updated','App\\Models\\Role',2,'{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.cancel\",\"sales.return\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"courier.settle_cod\",\"products.view\",\"products.create\",\"products.update\",\"products.import\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"inventory.view_costs\",\"expenses.view\",\"expenses.create\",\"reports.sales\"]}','{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.cancel\",\"sales.return\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"courier.settle_cod\",\"products.view\",\"products.create\",\"products.update\",\"products.import\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"inventory.view_costs\",\"expenses.view\",\"expenses.create\",\"reports.sales\",\"payroll.view\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:53:17'),(76,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:54:13\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:54:13'),(77,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:54:13\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:54:13'),(78,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:55:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:55:00'),(79,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 01:55:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:55:00'),(80,1,'role_updated','App\\Models\\Role',2,'{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.cancel\",\"sales.return\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"courier.settle_cod\",\"products.view\",\"products.create\",\"products.update\",\"products.import\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"inventory.view_costs\",\"expenses.view\",\"expenses.create\",\"payroll.view\",\"reports.sales\"]}','{\"name\":\"Staff\",\"description\":\"Operational access to POS, invoices, inventory, and expense logging.\",\"permissions\":[\"dashboard.view\",\"sales.view\",\"sales.create\",\"sales.edit_price\",\"sales.cancel\",\"sales.return\",\"sales.add_payment\",\"customers.view\",\"customers.update\",\"courier.view\",\"courier.retry\",\"courier.settle_cod\",\"products.view\",\"products.create\",\"products.update\",\"products.import\",\"leather.view\",\"leather.create\",\"leather.update\",\"accessories.view\",\"accessories.create\",\"accessories.update\",\"adjustments.view\",\"adjustments.create\",\"inventory.view_costs\",\"expenses.view\",\"expenses.create\",\"reports.sales\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:56:46'),(81,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:57:39\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:57:39'),(82,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 01:57:40\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 19:57:40'),(83,1,'expense_reconciled','App\\Models\\Expense',1,'{\"id\":1,\"expense_category_id\":4,\"expense_date\":\"2026-10-13T18:00:00.000000Z\",\"amount\":\"60.00\",\"payment_method\":\"other\",\"vendor\":\"Pathao\",\"note\":\"Pathao courier delivery fee for TASA-2610-0003 (Consignment: DT141026B3AEXT)\",\"attachment_path\":null,\"source\":\"pathao_charge\",\"sale_id\":3,\"courier_order_id\":3,\"sale_return_id\":null,\"recurring_expense_id\":null,\"fee_deducted_from_cod\":true,\"created_by\":1,\"updated_by\":null,\"created_at\":\"2026-10-14T17:53:09.000000Z\",\"updated_at\":\"2026-10-14T17:53:09.000000Z\",\"deleted_at\":null,\"courier_order\":{\"id\":3,\"sale_id\":3,\"merchant_order_id\":\"TASA-2610-0003\",\"consignment_id\":\"DT141026B3AEXT\",\"store_id\":67938,\"delivery_type\":48,\"item_type\":2,\"item_quantity\":1,\"item_weight\":\"0.50\",\"item_description\":\"asd\",\"special_instruction\":null,\"recipient_name\":\"Alter Reality\",\"recipient_phone\":\"01845700011\",\"recipient_address\":\"1 no panirkol, halishahar, chittagong\",\"recipient_city\":2,\"recipient_zone\":75,\"recipient_area\":982,\"cod_amount\":\"1223.00\",\"courier_charge\":\"60.00\",\"status\":\"cancelled\",\"raw_status\":\"Pickup Cancel\",\"attempts\":1,\"last_error\":null,\"last_synced_at\":\"2026-10-14T17:56:56.000000Z\",\"delivered_at\":null,\"cod_settled_at\":null,\"cod_settled_amount\":null,\"cod_settled_by\":null,\"created_at\":\"2026-10-13T21:39:59.000000Z\",\"updated_at\":\"2026-10-14T17:56:56.000000Z\"},\"sale\":{\"id\":3,\"invoice_no\":\"TASA-2610-0003\",\"idempotency_token\":\"5e5d0fb1-50e3-4e25-a023-59246078bfe5\",\"customer_id\":1,\"customer_name\":\"Alter Reality\",\"customer_phone\":\"01845700011\",\"customer_address\":\"1 no panirkol, halishahar, chittagong\",\"city_id\":2,\"zone_id\":202,\"area_id\":20202,\"city_name\":\"Chittagong\",\"zone_name\":\"GEC Circle\",\"area_name\":\"Sector \\/ Area 2\",\"sale_date\":\"2026-10-13T18:00:00.000000Z\",\"sold_at\":\"2026-10-13T21:39:59.000000Z\",\"subtotal\":\"1223.00\",\"discount_type\":\"flat\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"delivery_charge\":\"0.00\",\"grand_total\":\"1223.00\",\"paid_total\":\"0.00\",\"due_amount\":1223,\"payment_status\":\"unpaid\",\"status\":\"active\",\"courier_mode\":\"pathao\",\"notes\":null,\"cancel_reason\":null,\"cancelled_at\":null,\"cancelled_by\":null,\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-10-13T21:39:59.000000Z\",\"updated_at\":\"2026-10-13T21:39:59.000000Z\"}}','{\"status\":\"reversed_cancelled_order\",\"reason\":\"Soft-deleted courier expense #1 because courier order #TASA-2610-0003 was cancelled.\"}','127.0.0.1','Migration/2026_10_15_000002_reconcile_historical_courier_expenses','2026-10-14 20:07:46'),(84,2,'expense_reconciled','App\\Models\\Expense',2,'{\"id\":2,\"expense_category_id\":4,\"expense_date\":\"2026-10-14T18:00:00.000000Z\",\"amount\":\"60.00\",\"payment_method\":\"other\",\"vendor\":\"Pathao\",\"note\":\"Pathao courier delivery fee for TASA-2610-0005 (Consignment: DT141026E9DGYE)\",\"attachment_path\":null,\"source\":\"pathao_charge\",\"sale_id\":5,\"courier_order_id\":5,\"sale_return_id\":null,\"recurring_expense_id\":null,\"fee_deducted_from_cod\":true,\"created_by\":2,\"updated_by\":null,\"created_at\":\"2026-10-14T19:51:45.000000Z\",\"updated_at\":\"2026-10-14T19:51:45.000000Z\",\"deleted_at\":null,\"courier_order\":{\"id\":5,\"sale_id\":5,\"merchant_order_id\":\"TASA-2610-0005\",\"consignment_id\":\"DT141026E9DGYE\",\"store_id\":67938,\"delivery_type\":48,\"item_type\":2,\"item_quantity\":1,\"item_weight\":\"0.50\",\"item_description\":\"A4 File Bag Series 2 \\u2014 Black\",\"special_instruction\":null,\"recipient_name\":\"Alter Reality\",\"recipient_phone\":\"01845700011\",\"recipient_address\":\"1 no panirkol, halishahar, chittagong\",\"recipient_city\":2,\"recipient_zone\":365,\"recipient_area\":19809,\"cod_amount\":\"3499.00\",\"courier_charge\":\"60.00\",\"status\":\"cancelled\",\"raw_status\":\"Pickup Cancel\",\"attempts\":1,\"last_error\":null,\"last_synced_at\":\"2026-10-14T20:04:44.000000Z\",\"delivered_at\":null,\"cod_settled_at\":null,\"cod_settled_amount\":null,\"cod_settled_by\":null,\"created_at\":\"2026-10-14T19:51:41.000000Z\",\"updated_at\":\"2026-10-14T20:04:44.000000Z\"},\"sale\":{\"id\":5,\"invoice_no\":\"TASA-2610-0005\",\"idempotency_token\":\"765f7f7e-72d7-4c09-aef2-eaa65685896a\",\"customer_id\":1,\"customer_name\":\"Alter Reality\",\"customer_phone\":\"01845700011\",\"customer_address\":\"1 no panirkol, halishahar, chittagong\",\"city_id\":2,\"zone_id\":365,\"area_id\":19809,\"city_name\":\"Chittagong\",\"zone_name\":\"Sadarghat (Chattogram)\",\"area_name\":\"Kadam Tali\",\"sale_date\":\"2026-10-14T18:00:00.000000Z\",\"sold_at\":\"2026-10-14T19:51:41.000000Z\",\"subtotal\":\"3399.00\",\"discount_type\":\"flat\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"delivery_charge\":\"100.00\",\"grand_total\":\"3499.00\",\"paid_total\":\"0.00\",\"due_amount\":3499,\"payment_status\":\"unpaid\",\"status\":\"active\",\"courier_mode\":\"pathao\",\"notes\":null,\"cancel_reason\":null,\"cancelled_at\":null,\"cancelled_by\":null,\"created_by\":2,\"updated_by\":2,\"created_at\":\"2026-10-14T19:51:41.000000Z\",\"updated_at\":\"2026-10-14T19:51:41.000000Z\"}}','{\"status\":\"reversed_cancelled_order\",\"reason\":\"Soft-deleted courier expense #2 because courier order #TASA-2610-0005 was cancelled.\"}','127.0.0.1','Migration/2026_10_15_000002_reconcile_historical_courier_expenses','2026-10-14 20:07:46'),(85,2,'sale_created','App\\Models\\Sale',NULL,NULL,'{\"id\":6,\"invoice_no\":\"TASA-2610-0006\",\"grand_total\":\"3399.00\",\"paid_total\":\"0.00\",\"customer_phone\":\"01712345678\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:25:37'),(86,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:30:05\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:30:05'),(87,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:30:05\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:30:05'),(88,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 02:35:32\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:35:32'),(89,2,'login','App\\Models\\User',2,NULL,'{\"email\":\"staff@tasa.com\",\"last_login_at\":\"2026-10-15 02:35:32\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:35:32'),(90,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:40:41\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:40:41'),(91,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:40:42\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:40:42'),(92,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:44:44\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:44:44'),(93,1,'login','App\\Models\\User',1,NULL,'{\"email\":\"owner@tasa.com\",\"last_login_at\":\"2026-10-15 02:44:44\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-14 20:44:44');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

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
INSERT INTO `cache` VALUES ('laravel-cache-2e5b5e0b70f6ec86a35f9a1e639e12ed','i:1;',1792010192),('laravel-cache-2e5b5e0b70f6ec86a35f9a1e639e12ed:timer','i:1792010192;',1792010192),('laravel-cache-4179c5395bdcb8f7b97783c117ca43ab','i:1;',1792010744),('laravel-cache-4179c5395bdcb8f7b97783c117ca43ab:timer','i:1792010744;',1792010744),('laravel-cache-pathao_areas_156','a:156:{i:0;a:2:{s:7:\"area_id\";i:13193;s:9:\"area_name\";s:13:\"Adarsh school\";}i:1;a:2:{s:7:\"area_id\";i:13180;s:9:\"area_name\";s:12:\"Alia madrasa\";}i:2;a:2:{s:7:\"area_id\";i:13186;s:9:\"area_name\";s:12:\"Al islah mor\";}i:3;a:2:{s:7:\"area_id\";i:13184;s:9:\"area_name\";s:9:\"Amla para\";}i:4;a:2:{s:7:\"area_id\";i:18628;s:9:\"area_name\";s:11:\"Arjun Bahar\";}i:5;a:2:{s:7:\"area_id\";i:3335;s:9:\"area_name\";s:13:\"Asrom ar math\";}i:6;a:2:{s:7:\"area_id\";i:3334;s:9:\"area_name\";s:10:\"Badam tola\";}i:7;a:2:{s:7:\"area_id\";i:18629;s:9:\"area_name\";s:12:\"Badekarapara\";}i:8;a:2:{s:7:\"area_id\";i:18630;s:9:\"area_name\";s:21:\"Bagerhat Collectorate\";}i:9;a:2:{s:7:\"area_id\";i:13194;s:9:\"area_name\";s:21:\"Bagerhat  model thana\";}i:10;a:2:{s:7:\"area_id\";i:2927;s:9:\"area_name\";s:14:\"Bagerhat sadar\";}i:11;a:2:{s:7:\"area_id\";i:13198;s:9:\"area_name\";s:22:\"Bagerhat Sadar Stadium\";}i:12;a:2:{s:7:\"area_id\";i:3337;s:9:\"area_name\";s:22:\"Bagerhat sader upazila\";}i:13;a:2:{s:7:\"area_id\";i:18631;s:9:\"area_name\";s:7:\"Baitpur\";}i:14;a:2:{s:7:\"area_id\";i:13230;s:9:\"area_name\";s:17:\"Bajondar bari mor\";}i:15;a:2:{s:7:\"area_id\";i:13201;s:9:\"area_name\";s:8:\"Basabati\";}i:16;a:2:{s:7:\"area_id\";i:13211;s:9:\"area_name\";s:20:\"Basabati kali mondir\";}i:17;a:2:{s:7:\"area_id\";i:13205;s:9:\"area_name\";s:15:\"Basabati mandir\";}i:18;a:2:{s:7:\"area_id\";i:13212;s:9:\"area_name\";s:23:\"Basabati police quarter\";}i:19;a:2:{s:7:\"area_id\";i:13213;s:9:\"area_name\";s:23:\"Basabati pongu hospital\";}i:20;a:2:{s:7:\"area_id\";i:13217;s:9:\"area_name\";s:8:\"Beribadh\";}i:21;a:2:{s:7:\"area_id\";i:18632;s:9:\"area_name\";s:8:\"Binetala\";}i:22;a:2:{s:7:\"area_id\";i:13209;s:9:\"area_name\";s:5:\"Bisic\";}i:23;a:2:{s:7:\"area_id\";i:4365;s:9:\"area_name\";s:12:\"Botpur bazer\";}i:24;a:2:{s:7:\"area_id\";i:3352;s:9:\"area_name\";s:12:\"Boys\' school\";}i:25;a:2:{s:7:\"area_id\";i:13206;s:9:\"area_name\";s:25:\"Bus stand  Bagerhat Sadar\";}i:26;a:2:{s:7:\"area_id\";i:18633;s:9:\"area_name\";s:15:\"Chakshree Bazar\";}i:27;a:2:{s:7:\"area_id\";i:18634;s:9:\"area_name\";s:18:\"Chalk Kumaria Jola\";}i:28;a:2:{s:7:\"area_id\";i:13179;s:9:\"area_name\";s:11:\"Chilta tola\";}i:29;a:2:{s:7:\"area_id\";i:13287;s:9:\"area_name\";s:13:\"Chondro Mohol\";}i:30;a:2:{s:7:\"area_id\";i:18801;s:9:\"area_name\";s:8:\"Chulkati\";}i:31;a:2:{s:7:\"area_id\";i:18635;s:9:\"area_name\";s:8:\"Churulia\";}i:32;a:2:{s:7:\"area_id\";i:13248;s:9:\"area_name\";s:11:\"C.M.B bazar\";}i:33;a:2:{s:7:\"area_id\";i:3345;s:9:\"area_name\";s:12:\"College gate\";}i:34;a:2:{s:7:\"area_id\";i:13223;s:9:\"area_name\";s:12:\"Court masjid\";}i:35;a:2:{s:7:\"area_id\";i:13226;s:9:\"area_name\";s:15:\"Dak banglow mor\";}i:36;a:2:{s:7:\"area_id\";i:18636;s:9:\"area_name\";s:7:\"Dashani\";}i:37;a:2:{s:7:\"area_id\";i:13196;s:9:\"area_name\";s:8:\"Das para\";}i:38;a:2:{s:7:\"area_id\";i:13197;s:9:\"area_name\";s:8:\"Das para\";}i:39;a:2:{s:7:\"area_id\";i:13189;s:9:\"area_name\";s:12:\"D.C  bangalo\";}i:40;a:2:{s:7:\"area_id\";i:13172;s:9:\"area_name\";s:10:\"D.C office\";}i:41;a:2:{s:7:\"area_id\";i:18637;s:9:\"area_name\";s:6:\"Debraj\";}i:42;a:2:{s:7:\"area_id\";i:18638;s:9:\"area_name\";s:8:\"Denatala\";}i:43;a:2:{s:7:\"area_id\";i:13167;s:9:\"area_name\";s:15:\"Dhakhil madrasa\";}i:44;a:2:{s:7:\"area_id\";i:13227;s:9:\"area_name\";s:20:\"Digital X-ray center\";}i:45;a:2:{s:7:\"area_id\";i:13210;s:9:\"area_name\";s:15:\"Doratana bridge\";}i:46;a:2:{s:7:\"area_id\";i:4364;s:9:\"area_name\";s:11:\"Dori talluk\";}i:47;a:2:{s:7:\"area_id\";i:3338;s:9:\"area_name\";s:18:\"Dosh gombuz morsid\";}i:48;a:2:{s:7:\"area_id\";i:18639;s:9:\"area_name\";s:24:\"Fatehpur- Bagerhat Sadar\";}i:49;a:2:{s:7:\"area_id\";i:13216;s:9:\"area_name\";s:9:\"Fol potti\";}i:50;a:2:{s:7:\"area_id\";i:18640;s:9:\"area_name\";s:7:\"Fulhata\";}i:51;a:2:{s:7:\"area_id\";i:3329;s:9:\"area_name\";s:8:\"Ful tola\";}i:52;a:2:{s:7:\"area_id\";i:18641;s:9:\"area_name\";s:22:\"Gajalia-Bagerhat Sadar\";}i:53;a:2:{s:7:\"area_id\";i:18642;s:9:\"area_name\";s:9:\"Ghoperhat\";}i:54;a:2:{s:7:\"area_id\";i:18643;s:9:\"area_name\";s:12:\"Ghoshiakhali\";}i:55;a:2:{s:7:\"area_id\";i:18644;s:9:\"area_name\";s:8:\"Gilatala\";}i:56;a:2:{s:7:\"area_id\";i:13192;s:9:\"area_name\";s:12:\"Girls school\";}i:57;a:2:{s:7:\"area_id\";i:18645;s:9:\"area_name\";s:8:\"Gotapara\";}i:58;a:2:{s:7:\"area_id\";i:18785;s:9:\"area_name\";s:8:\"Hakimpur\";}i:59;a:2:{s:7:\"area_id\";i:18646;s:9:\"area_name\";s:12:\"Haolar Danga\";}i:60;a:2:{s:7:\"area_id\";i:13166;s:9:\"area_name\";s:10:\"Hari khali\";}i:61;a:2:{s:7:\"area_id\";i:13169;s:9:\"area_name\";s:25:\"Hari khali primary school\";}i:62;a:2:{s:7:\"area_id\";i:18647;s:9:\"area_name\";s:10:\"Hedayetpur\";}i:63;a:2:{s:7:\"area_id\";i:18648;s:9:\"area_name\";s:8:\"Hermahat\";}i:64;a:2:{s:7:\"area_id\";i:3343;s:9:\"area_name\";s:11:\"Horin khana\";}i:65;a:2:{s:7:\"area_id\";i:18649;s:9:\"area_name\";s:9:\"Islamganj\";}i:66;a:2:{s:7:\"area_id\";i:3347;s:9:\"area_name\";s:8:\"Jadunath\";}i:67;a:2:{s:7:\"area_id\";i:18650;s:9:\"area_name\";s:9:\"Kafurpura\";}i:68;a:2:{s:7:\"area_id\";i:18651;s:9:\"area_name\";s:16:\"Kamarhati Depara\";}i:69;a:2:{s:7:\"area_id\";i:18652;s:9:\"area_name\";s:24:\"Kandapara-Bagerhat Sadar\";}i:70;a:2:{s:7:\"area_id\";i:3328;s:9:\"area_name\";s:9:\"Kara para\";}i:71;a:2:{s:7:\"area_id\";i:3330;s:9:\"area_name\";s:21:\"Kara para bank ar mor\";}i:72;a:2:{s:7:\"area_id\";i:3336;s:9:\"area_name\";s:24:\"Kara para union parishad\";}i:73;a:2:{s:7:\"area_id\";i:18653;s:9:\"area_name\";s:9:\"Kartikdia\";}i:74;a:2:{s:7:\"area_id\";i:18654;s:9:\"area_name\";s:15:\"Kashimpur Bazar\";}i:75;a:2:{s:7:\"area_id\";i:3341;s:9:\"area_name\";s:10:\"Katal tola\";}i:76;a:2:{s:7:\"area_id\";i:13170;s:9:\"area_name\";s:22:\"Khan jahan ali collage\";}i:77;a:2:{s:7:\"area_id\";i:13161;s:9:\"area_name\";s:13:\"Khanka shorif\";}i:78;a:2:{s:7:\"area_id\";i:18655;s:9:\"area_name\";s:22:\"Khanpur-Bagerhat Sadar\";}i:79;a:2:{s:7:\"area_id\";i:18656;s:9:\"area_name\";s:5:\"Kodla\";}i:80;a:2:{s:7:\"area_id\";i:18657;s:9:\"area_name\";s:8:\"Koramara\";}i:81;a:2:{s:7:\"area_id\";i:13219;s:9:\"area_name\";s:11:\"Krishi bank\";}i:82;a:2:{s:7:\"area_id\";i:4363;s:9:\"area_name\";s:19:\"Kulia Dar badamtola\";}i:83;a:2:{s:7:\"area_id\";i:13224;s:9:\"area_name\";s:11:\"Launch ghat\";}i:84;a:2:{s:7:\"area_id\";i:3324;s:9:\"area_name\";s:11:\"L.G.E.D mor\";}i:85;a:2:{s:7:\"area_id\";i:3333;s:9:\"area_name\";s:10:\"Magni tola\";}i:86;a:2:{s:7:\"area_id\";i:18658;s:9:\"area_name\";s:9:\"Magra hat\";}i:87;a:2:{s:7:\"area_id\";i:3340;s:9:\"area_name\";s:9:\"Majar mor\";}i:88;a:2:{s:7:\"area_id\";i:18659;s:9:\"area_name\";s:12:\"Malliker ber\";}i:89;a:2:{s:7:\"area_id\";i:3350;s:9:\"area_name\";s:11:\"Mats ar mor\";}i:90;a:2:{s:7:\"area_id\";i:13182;s:9:\"area_name\";s:15:\"Mitha pukur par\";}i:91;a:2:{s:7:\"area_id\";i:3332;s:9:\"area_name\";s:13:\"Mohila School\";}i:92;a:2:{s:7:\"area_id\";i:13177;s:9:\"area_name\";s:14:\"Mollick ar mor\";}i:93;a:2:{s:7:\"area_id\";i:13200;s:9:\"area_name\";s:11:\"Monica hall\";}i:94;a:2:{s:7:\"area_id\";i:13165;s:9:\"area_name\";s:15:\"Munigonj bridge\";}i:95;a:2:{s:7:\"area_id\";i:13162;s:9:\"area_name\";s:18:\"Munigonj khayaghat\";}i:96;a:2:{s:7:\"area_id\";i:13164;s:9:\"area_name\";s:23:\"Munigonj primary school\";}i:97;a:2:{s:7:\"area_id\";i:13215;s:9:\"area_name\";s:11:\"Nagar bajar\";}i:98;a:2:{s:7:\"area_id\";i:18660;s:9:\"area_name\";s:11:\"Nager Bazar\";}i:99;a:2:{s:7:\"area_id\";i:13160;s:9:\"area_name\";s:15:\"New police line\";}i:100;a:2:{s:7:\"area_id\";i:18661;s:9:\"area_name\";s:10:\"Nightkhali\";}i:101;a:2:{s:7:\"area_id\";i:13173;s:9:\"area_name\";s:16:\"Nirbachan office\";}i:102;a:2:{s:7:\"area_id\";i:13171;s:9:\"area_name\";s:11:\"Notun court\";}i:103;a:2:{s:7:\"area_id\";i:3351;s:9:\"area_name\";s:14:\"Nur masjid mor\";}i:104;a:2:{s:7:\"area_id\";i:13159;s:9:\"area_name\";s:15:\"Old police line\";}i:105;a:2:{s:7:\"area_id\";i:3344;s:9:\"area_name\";s:15:\"Panch rasta mor\";}i:106;a:2:{s:7:\"area_id\";i:13202;s:9:\"area_name\";s:18:\"Pani unnayan board\";}i:107;a:2:{s:7:\"area_id\";i:13176;s:9:\"area_name\";s:31:\"Passport office  Bagerhat Sadar\";}i:108;a:2:{s:7:\"area_id\";i:18662;s:9:\"area_name\";s:11:\"Patilakhali\";}i:109;a:2:{s:7:\"area_id\";i:3346;s:9:\"area_name\";s:11:\"P.C college\";}i:110;a:2:{s:7:\"area_id\";i:3331;s:9:\"area_name\";s:16:\"Pocha dighir par\";}i:111;a:2:{s:7:\"area_id\";i:13229;s:9:\"area_name\";s:19:\"Polli bidyut office\";}i:112;a:2:{s:7:\"area_id\";i:13214;s:9:\"area_name\";s:10:\"Pouro park\";}i:113;a:2:{s:7:\"area_id\";i:13191;s:9:\"area_name\";s:10:\"Press ciub\";}i:114;a:2:{s:7:\"area_id\";i:13225;s:9:\"area_name\";s:9:\"P.T.I mor\";}i:115;a:2:{s:7:\"area_id\";i:13185;s:9:\"area_name\";s:29:\"Puraton bazar  Bagerhat Sadar\";}i:116;a:2:{s:7:\"area_id\";i:13222;s:9:\"area_name\";s:13:\"Puraton Court\";}i:117;a:2:{s:7:\"area_id\";i:18664;s:9:\"area_name\";s:27:\"Raghunathpur-Bagerhat Sadar\";}i:118;a:2:{s:7:\"area_id\";i:13220;s:9:\"area_name\";s:12:\"Rahat ar mor\";}i:119;a:2:{s:7:\"area_id\";i:13195;s:9:\"area_name\";s:9:\"Rail road\";}i:120;a:2:{s:7:\"area_id\";i:18665;s:9:\"area_name\";s:11:\"Rakhalgachi\";}i:121;a:2:{s:7:\"area_id\";i:18663;s:9:\"area_name\";s:7:\"Rangdia\";}i:122;a:2:{s:7:\"area_id\";i:3339;s:9:\"area_name\";s:13:\"Ronobejoy pur\";}i:123;a:2:{s:7:\"area_id\";i:4362;s:9:\"area_name\";s:17:\"Ronobejoy pur mor\";}i:124;a:2:{s:7:\"area_id\";i:18666;s:9:\"area_name\";s:20:\"Sabukhali Bakhergonj\";}i:125;a:2:{s:7:\"area_id\";i:13163;s:9:\"area_name\";s:14:\"Sadar hospital\";}i:126;a:2:{s:7:\"area_id\";i:18668;s:9:\"area_name\";s:5:\"Saira\";}i:127;a:2:{s:7:\"area_id\";i:18667;s:9:\"area_name\";s:13:\"Samaddarkhali\";}i:128;a:2:{s:7:\"area_id\";i:13199;s:9:\"area_name\";s:17:\"S.A paribahan mor\";}i:129;a:2:{s:7:\"area_id\";i:13188;s:9:\"area_name\";s:12:\"Shadonar mor\";}i:130;a:2:{s:7:\"area_id\";i:13207;s:9:\"area_name\";s:10:\"Shaha para\";}i:131;a:2:{s:7:\"area_id\";i:13208;s:9:\"area_name\";s:16:\"Shahapara school\";}i:132;a:2:{s:7:\"area_id\";i:18669;s:9:\"area_name\";s:13:\"Shah Auliabag\";}i:133;a:2:{s:7:\"area_id\";i:13183;s:9:\"area_name\";s:9:\"Shal tola\";}i:134;a:2:{s:7:\"area_id\";i:18795;s:9:\"area_name\";s:8:\"Shattala\";}i:135;a:2:{s:7:\"area_id\";i:13181;s:9:\"area_name\";s:15:\"Shishu hospital\";}i:136;a:2:{s:7:\"area_id\";i:3348;s:9:\"area_name\";s:17:\"Shommellon ar mor\";}i:137;a:2:{s:7:\"area_id\";i:13218;s:9:\"area_name\";s:12:\"Shonali bank\";}i:138;a:2:{s:7:\"area_id\";i:3326;s:9:\"area_name\";s:9:\"Shonatola\";}i:139;a:2:{s:7:\"area_id\";i:3327;s:9:\"area_name\";s:25:\"Shonatola praimari school\";}i:140;a:2:{s:7:\"area_id\";i:3342;s:9:\"area_name\";s:13:\"Shundor ghona\";}i:141;a:2:{s:7:\"area_id\";i:13228;s:9:\"area_name\";s:20:\"Sixty gombuj station\";}i:142;a:2:{s:7:\"area_id\";i:13203;s:9:\"area_name\";s:20:\"Sorui boro kabristan\";}i:143;a:2:{s:7:\"area_id\";i:13204;s:9:\"area_name\";s:20:\"Sorui soto kabristan\";}i:144;a:2:{s:7:\"area_id\";i:13190;s:9:\"area_name\";s:11:\"S.P bangalo\";}i:145;a:2:{s:7:\"area_id\";i:13174;s:9:\"area_name\";s:10:\"S.P office\";}i:146;a:2:{s:7:\"area_id\";i:13249;s:9:\"area_name\";s:8:\"Sri ghat\";}i:147;a:2:{s:7:\"area_id\";i:3349;s:9:\"area_name\";s:13:\"Staff quarter\";}i:148;a:2:{s:7:\"area_id\";i:13187;s:9:\"area_name\";s:29:\"Sundarban courier service mor\";}i:149;a:2:{s:7:\"area_id\";i:18670;s:9:\"area_name\";s:11:\"Sundarghona\";}i:150;a:2:{s:7:\"area_id\";i:13168;s:9:\"area_name\";s:10:\"Tatul tola\";}i:151;a:2:{s:7:\"area_id\";i:3325;s:9:\"area_name\";s:11:\"Traffic mor\";}i:152;a:2:{s:7:\"area_id\";i:18671;s:9:\"area_name\";s:6:\"Uthkul\";}i:153;a:2:{s:7:\"area_id\";i:13231;s:9:\"area_name\";s:21:\"Vangor partex company\";}i:154;a:2:{s:7:\"area_id\";i:13175;s:9:\"area_name\";s:9:\"V.I.P mor\";}i:155;a:2:{s:7:\"area_id\";i:13178;s:9:\"area_name\";s:10:\"V.I.P road\";}}',1792015761),('laravel-cache-pathao_areas_202','a:3:{i:0;a:2:{s:7:\"area_id\";i:20201;s:9:\"area_name\";s:11:\"Central Hub\";}i:1;a:2:{s:7:\"area_id\";i:20202;s:9:\"area_name\";s:8:\"Sector 1\";}i:2;a:2:{s:7:\"area_id\";i:20203;s:9:\"area_name\";s:8:\"Sector 2\";}}',1792086725),('laravel-cache-pathao_areas_2896','a:3:{i:0;a:2:{s:7:\"area_id\";i:38621;s:9:\"area_name\";s:19:\"Airport Inside Area\";}i:1;a:2:{s:7:\"area_id\";i:38629;s:9:\"area_name\";s:12:\"Chinese Ghat\";}i:2;a:2:{s:7:\"area_id\";i:38618;s:9:\"area_name\";s:13:\"Naval Academy\";}}',1792085621),('laravel-cache-pathao_areas_298','a:1:{i:0;a:2:{s:7:\"area_id\";i:3069;s:9:\"area_name\";s:12:\"60 feet road\";}}',1792015492),('laravel-cache-pathao_areas_3013','a:91:{i:0;a:2:{s:7:\"area_id\";i:40835;s:9:\"area_name\";s:11:\"Abeder bari\";}i:1;a:2:{s:7:\"area_id\";i:40843;s:9:\"area_name\";s:19:\"Abjal talukder bari\";}i:2;a:2:{s:7:\"area_id\";i:40804;s:9:\"area_name\";s:18:\"alam companyr bari\";}i:3;a:2:{s:7:\"area_id\";i:5320;s:9:\"area_name\";s:9:\"amanbazar\";}i:4;a:2:{s:7:\"area_id\";i:4300;s:9:\"area_name\";s:9:\"Amanbazar\";}i:5;a:2:{s:7:\"area_id\";i:40800;s:9:\"area_name\";s:31:\"Aman Bazar Jugirhat, Nur Market\";}i:6;a:2:{s:7:\"area_id\";i:40801;s:9:\"area_name\";s:22:\"Aman bazar koshal shah\";}i:7;a:2:{s:7:\"area_id\";i:40828;s:9:\"area_name\";s:10:\"Amin villa\";}i:8;a:2:{s:7:\"area_id\";i:40849;s:9:\"area_name\";s:12:\"azizia mazar\";}i:9;a:2:{s:7:\"area_id\";i:40853;s:9:\"area_name\";s:15:\"bainna building\";}i:10;a:2:{s:7:\"area_id\";i:5318;s:9:\"area_name\";s:9:\"Baluchara\";}i:11;a:2:{s:7:\"area_id\";i:40807;s:9:\"area_name\";s:16:\"Baluchara abasik\";}i:12;a:2:{s:7:\"area_id\";i:40814;s:9:\"area_name\";s:20:\"Baluchara Cantonment\";}i:13;a:2:{s:7:\"area_id\";i:40809;s:9:\"area_name\";s:15:\"Baluchara  More\";}i:14;a:2:{s:7:\"area_id\";i:40810;s:9:\"area_name\";s:24:\"baluchara primary school\";}i:15;a:2:{s:7:\"area_id\";i:40813;s:9:\"area_name\";s:20:\"Baluchara Tiger road\";}i:16;a:2:{s:7:\"area_id\";i:40808;s:9:\"area_name\";s:34:\"Baluchara Tufani road kashem vaban\";}i:17;a:2:{s:7:\"area_id\";i:4298;s:9:\"area_name\";s:9:\"Baluchora\";}i:18;a:2:{s:7:\"area_id\";i:40830;s:9:\"area_name\";s:6:\"Bathua\";}i:19;a:2:{s:7:\"area_id\";i:40842;s:9:\"area_name\";s:29:\"Bhara Pukur setu bandhan club\";}i:20;a:2:{s:7:\"area_id\";i:4303;s:9:\"area_name\";s:12:\"Bordigir par\";}i:21;a:2:{s:7:\"area_id\";i:5322;s:9:\"area_name\";s:12:\"Bordigir par\";}i:22;a:2:{s:7:\"area_id\";i:40792;s:9:\"area_name\";s:28:\"Boro Dighir Par kakoner para\";}i:23;a:2:{s:7:\"area_id\";i:40787;s:9:\"area_name\";s:28:\"Boro Dighir Par Khilla Para \";}i:24;a:2:{s:7:\"area_id\";i:40784;s:9:\"area_name\";s:20:\"Boro Dighir Par more\";}i:25;a:2:{s:7:\"area_id\";i:40794;s:9:\"area_name\";s:25:\"Boro Dighir Par uttar par\";}i:26;a:2:{s:7:\"area_id\";i:4302;s:9:\"area_name\";s:4:\"BRTA\";}i:27;a:2:{s:7:\"area_id\";i:5321;s:9:\"area_name\";s:4:\"BRTA\";}i:28;a:2:{s:7:\"area_id\";i:40832;s:9:\"area_name\";s:13:\"buijjer dokan\";}i:29;a:2:{s:7:\"area_id\";i:40812;s:9:\"area_name\";s:9:\"Buliapara\";}i:30;a:2:{s:7:\"area_id\";i:40838;s:9:\"area_name\";s:16:\"burishchar bazar\";}i:31;a:2:{s:7:\"area_id\";i:5323;s:9:\"area_name\";s:14:\"chowdhoury hat\";}i:32;a:2:{s:7:\"area_id\";i:40777;s:9:\"area_name\";s:13:\"Chowdhury Hat\";}i:33;a:2:{s:7:\"area_id\";i:40781;s:9:\"area_name\";s:26:\"Chowdhury Hat College Gate\";}i:34;a:2:{s:7:\"area_id\";i:40780;s:9:\"area_name\";s:21:\"Chowdhury hat Station\";}i:35;a:2:{s:7:\"area_id\";i:40856;s:9:\"area_name\";s:17:\"della baper ghata\";}i:36;a:2:{s:7:\"area_id\";i:40848;s:9:\"area_name\";s:24:\"dighirpar chowdhury bari\";}i:37;a:2:{s:7:\"area_id\";i:40851;s:9:\"area_name\";s:30:\"dokkin Burischar Khalifar para\";}i:38;a:2:{s:7:\"area_id\";i:40840;s:9:\"area_name\";s:13:\"Dolamiya road\";}i:39;a:2:{s:7:\"area_id\";i:40852;s:9:\"area_name\";s:17:\"duppul kola bagan\";}i:40;a:2:{s:7:\"area_id\";i:40854;s:9:\"area_name\";s:11:\"duppul more\";}i:41;a:2:{s:7:\"area_id\";i:40823;s:9:\"area_name\";s:17:\"ekkullo pukur par\";}i:42;a:2:{s:7:\"area_id\";i:40855;s:9:\"area_name\";s:11:\"faisal club\";}i:43;a:2:{s:7:\"area_id\";i:40776;s:9:\"area_name\";s:8:\"Fateabad\";}i:44;a:2:{s:7:\"area_id\";i:1057;s:9:\"area_name\";s:9:\"Fatehabad\";}i:45;a:2:{s:7:\"area_id\";i:40778;s:9:\"area_name\";s:17:\"Fatehabad Bottali\";}i:46;a:2:{s:7:\"area_id\";i:40841;s:9:\"area_name\";s:17:\"hamid sharif road\";}i:47;a:2:{s:7:\"area_id\";i:40860;s:9:\"area_name\";s:15:\"jahangir abasik\";}i:48;a:2:{s:7:\"area_id\";i:40803;s:9:\"area_name\";s:10:\"Kabarshtan\";}i:49;a:2:{s:7:\"area_id\";i:40834;s:9:\"area_name\";s:10:\"Kaker bari\";}i:50;a:2:{s:7:\"area_id\";i:40805;s:9:\"area_name\";s:22:\"Kathal Bagan Baluchara\";}i:51;a:2:{s:7:\"area_id\";i:40844;s:9:\"area_name\";s:7:\"Kb park\";}i:52;a:2:{s:7:\"area_id\";i:40811;s:9:\"area_name\";s:14:\"khandakiya hat\";}i:53;a:2:{s:7:\"area_id\";i:40790;s:9:\"area_name\";s:19:\"Killapara 3no Bazar\";}i:54;a:2:{s:7:\"area_id\";i:1062;s:9:\"area_name\";s:7:\"Kulgaon\";}i:55;a:2:{s:7:\"area_id\";i:1054;s:9:\"area_name\";s:7:\"Kulgaon\";}i:56;a:2:{s:7:\"area_id\";i:40819;s:9:\"area_name\";s:14:\"Kulgaon abasik\";}i:57;a:2:{s:7:\"area_id\";i:19211;s:9:\"area_name\";s:18:\"Kulgaon-Cantonment\";}i:58;a:2:{s:7:\"area_id\";i:40799;s:9:\"area_name\";s:10:\"Laliar Hat\";}i:59;a:2:{s:7:\"area_id\";i:40816;s:9:\"area_name\";s:10:\"Majar Gate\";}i:60;a:2:{s:7:\"area_id\";i:40837;s:9:\"area_name\";s:16:\"middle burischar\";}i:61;a:2:{s:7:\"area_id\";i:40839;s:9:\"area_name\";s:24:\"Najumia hat shekh market\";}i:62;a:2:{s:7:\"area_id\";i:915;s:9:\"area_name\";s:12:\"Najumiar Hut\";}i:63;a:2:{s:7:\"area_id\";i:40829;s:9:\"area_name\";s:28:\"Niamot ali Otiti Bekary more\";}i:64;a:2:{s:7:\"area_id\";i:40827;s:9:\"area_name\";s:11:\"Niyamot Ali\";}i:65;a:2:{s:7:\"area_id\";i:40824;s:9:\"area_name\";s:23:\"niyamot ali chinar pool\";}i:66;a:2:{s:7:\"area_id\";i:40822;s:9:\"area_name\";s:16:\"nojumiya borbari\";}i:67;a:2:{s:7:\"area_id\";i:40820;s:9:\"area_name\";s:25:\"nojumiya showdagorer bari\";}i:68;a:2:{s:7:\"area_id\";i:5319;s:9:\"area_name\";s:9:\"notunpara\";}i:69;a:2:{s:7:\"area_id\";i:4299;s:9:\"area_name\";s:9:\"Notunpara\";}i:70;a:2:{s:7:\"area_id\";i:40802;s:9:\"area_name\";s:18:\"Notunpara BHD Gate\";}i:71;a:2:{s:7:\"area_id\";i:40859;s:9:\"area_name\";s:15:\"pashchim quaish\";}i:72;a:2:{s:7:\"area_id\";i:40861;s:9:\"area_name\";s:14:\"probel academy\";}i:73;a:2:{s:7:\"area_id\";i:40821;s:9:\"area_name\";s:32:\"purbo sikharpur niyamot ali road\";}i:74;a:2:{s:7:\"area_id\";i:19209;s:9:\"area_name\";s:6:\"Quaish\";}i:75;a:2:{s:7:\"area_id\";i:20224;s:9:\"area_name\";s:14:\"Quaish college\";}i:76;a:2:{s:7:\"area_id\";i:40845;s:9:\"area_name\";s:25:\"quaish college badam toll\";}i:77;a:2:{s:7:\"area_id\";i:40847;s:9:\"area_name\";s:19:\"Quaish college more\";}i:78;a:2:{s:7:\"area_id\";i:40858;s:9:\"area_name\";s:20:\"Quaish gopaler ghata\";}i:79;a:2:{s:7:\"area_id\";i:40857;s:9:\"area_name\";s:25:\"Quaish notun rastar matha\";}i:80;a:2:{s:7:\"area_id\";i:40833;s:9:\"area_name\";s:18:\"safamot ali school\";}i:81;a:2:{s:7:\"area_id\";i:40779;s:9:\"area_name\";s:13:\"Sandwip coloy\";}i:82;a:2:{s:7:\"area_id\";i:40817;s:9:\"area_name\";s:16:\"Sherebangla road\";}i:83;a:2:{s:7:\"area_id\";i:40826;s:9:\"area_name\";s:20:\"solaiman hajir ghata\";}i:84;a:2:{s:7:\"area_id\";i:40806;s:9:\"area_name\";s:22:\"Subhan Housing society\";}i:85;a:2:{s:7:\"area_id\";i:40846;s:9:\"area_name\";s:9:\"syed bari\";}i:86;a:2:{s:7:\"area_id\";i:40818;s:9:\"area_name\";s:18:\"Talukder bari road\";}i:87;a:2:{s:7:\"area_id\";i:40831;s:9:\"area_name\";s:10:\"tetul tola\";}i:88;a:2:{s:7:\"area_id\";i:40825;s:9:\"area_name\";s:31:\"uttar burischar tendol er ghata\";}i:89;a:2:{s:7:\"area_id\";i:40836;s:9:\"area_name\";s:28:\"uttar burishar gomaster bari\";}i:90;a:2:{s:7:\"area_id\";i:40815;s:9:\"area_name\";s:12:\"Walton Golli\";}}',1792086732),('laravel-cache-pathao_areas_365','a:14:{i:0;a:2:{s:7:\"area_id\";i:3475;s:9:\"area_name\";s:14:\"East Madarbari\";}i:1;a:2:{s:7:\"area_id\";i:15877;s:9:\"area_name\";s:15:\"east motherbari\";}i:2;a:2:{s:7:\"area_id\";i:928;s:9:\"area_name\";s:16:\"Ice Factory Road\";}i:3;a:2:{s:7:\"area_id\";i:19809;s:9:\"area_name\";s:10:\"Kadam Tali\";}i:4;a:2:{s:7:\"area_id\";i:885;s:9:\"area_name\";s:16:\"kobi nazrul road\";}i:5;a:2:{s:7:\"area_id\";i:932;s:9:\"area_name\";s:9:\"Kodomtoli\";}i:6;a:2:{s:7:\"area_id\";i:3353;s:9:\"area_name\";s:14:\"North nalapara\";}i:7;a:2:{s:7:\"area_id\";i:5315;s:9:\"area_name\";s:9:\"Sadargath\";}i:8;a:2:{s:7:\"area_id\";i:4209;s:9:\"area_name\";s:9:\"Sadarghat\";}i:9;a:2:{s:7:\"area_id\";i:3469;s:9:\"area_name\";s:10:\"Sadar Ghat\";}i:10;a:2:{s:7:\"area_id\";i:886;s:9:\"area_name\";s:14:\"South nalapara\";}i:11;a:2:{s:7:\"area_id\";i:4211;s:9:\"area_name\";s:10:\"stand road\";}i:12;a:2:{s:7:\"area_id\";i:4285;s:9:\"area_name\";s:10:\"stand road\";}i:13;a:2:{s:7:\"area_id\";i:1141;s:9:\"area_name\";s:14:\"West madarbari\";}}',1792093890),('laravel-cache-pathao_areas_434','a:8:{i:0;a:2:{s:7:\"area_id\";i:5301;s:9:\"area_name\";s:16:\"Anawara - Center\";}i:1;a:2:{s:7:\"area_id\";i:4193;s:9:\"area_name\";s:18:\"Anawara - Crossing\";}i:2;a:2:{s:7:\"area_id\";i:5299;s:9:\"area_name\";s:18:\"Chaturi Chowmuhani\";}i:3;a:2:{s:7:\"area_id\";i:5302;s:9:\"area_name\";s:19:\"Cufl Housing colony\";}i:4;a:2:{s:7:\"area_id\";i:5298;s:9:\"area_name\";s:9:\"Fakir hat\";}i:5;a:2:{s:7:\"area_id\";i:5300;s:9:\"area_name\";s:20:\"Kafco Housing Colony\";}i:6;a:2:{s:7:\"area_id\";i:33648;s:9:\"area_name\";s:10:\"Korean EPZ\";}i:7;a:2:{s:7:\"area_id\";i:3496;s:9:\"area_name\";s:10:\"Moijjartek\";}}',1792086755),('laravel-cache-pathao_areas_459','a:11:{i:0;a:2:{s:7:\"area_id\";i:964;s:9:\"area_name\";s:8:\"Banigram\";}i:1;a:2:{s:7:\"area_id\";i:971;s:9:\"area_name\";s:7:\"Chambol\";}i:2;a:2:{s:7:\"area_id\";i:963;s:9:\"area_name\";s:8:\"Fukuriya\";}i:3;a:2:{s:7:\"area_id\";i:19909;s:9:\"area_name\";s:8:\"Gunagari\";}i:4;a:2:{s:7:\"area_id\";i:966;s:9:\"area_name\";s:8:\"Gunaguri\";}i:5;a:2:{s:7:\"area_id\";i:19910;s:9:\"area_name\";s:5:\"Jaldi\";}i:6;a:2:{s:7:\"area_id\";i:969;s:9:\"area_name\";s:16:\"Joldi pourashaba\";}i:7;a:2:{s:7:\"area_id\";i:967;s:9:\"area_name\";s:7:\"Kalipur\";}i:8;a:2:{s:7:\"area_id\";i:968;s:9:\"area_name\";s:11:\"Meyar bazar\";}i:9;a:2:{s:7:\"area_id\";i:965;s:9:\"area_name\";s:9:\"Shadanpur\";}i:10;a:2:{s:7:\"area_id\";i:970;s:9:\"area_name\";s:7:\"Shilkop\";}}',1792086513),('laravel-cache-pathao_areas_681','a:12:{i:0;a:2:{s:7:\"area_id\";i:19290;s:9:\"area_name\";s:20:\"Boalkhali -Boalkhali\";}i:1;a:2:{s:7:\"area_id\";i:973;s:9:\"area_name\";s:10:\"C A office\";}i:2;a:2:{s:7:\"area_id\";i:19291;s:9:\"area_name\";s:10:\"Charandwip\";}i:3;a:2:{s:7:\"area_id\";i:5303;s:9:\"area_name\";s:13:\"Chowdhury hat\";}i:4;a:2:{s:7:\"area_id\";i:974;s:9:\"area_name\";s:16:\"Gumdondi Fultola\";}i:5;a:2:{s:7:\"area_id\";i:19289;s:9:\"area_name\";s:10:\"Iqbal Park\";}i:6;a:2:{s:7:\"area_id\";i:3523;s:9:\"area_name\";s:12:\"Kanongo para\";}i:7;a:2:{s:7:\"area_id\";i:3478;s:9:\"area_name\";s:9:\"Kodurkhil\";}i:8;a:2:{s:7:\"area_id\";i:3443;s:9:\"area_name\";s:10:\"Koruldanga\";}i:9;a:2:{s:7:\"area_id\";i:3109;s:9:\"area_name\";s:19:\"KY factory Badamtol\";}i:10;a:2:{s:7:\"area_id\";i:3502;s:9:\"area_name\";s:13:\"Millitary pol\";}i:11;a:2:{s:7:\"area_id\";i:5077;s:9:\"area_name\";s:7:\"Sakpura\";}}',1792086514),('laravel-cache-pathao_areas_75','a:52:{i:0;a:2:{s:7:\"area_id\";i:5249;s:9:\"area_name\";s:9:\"Abbaspara\";}i:1;a:2:{s:7:\"area_id\";i:953;s:9:\"area_name\";s:7:\"A Block\";}i:2;a:2:{s:7:\"area_id\";i:984;s:9:\"area_name\";s:7:\"A Block\";}i:3;a:2:{s:7:\"area_id\";i:979;s:9:\"area_name\";s:17:\"Abul biri factory\";}i:4;a:2:{s:7:\"area_id\";i:5261;s:9:\"area_name\";s:7:\"Alankar\";}i:5;a:2:{s:7:\"area_id\";i:3508;s:9:\"area_name\";s:12:\"Alankar more\";}i:6;a:2:{s:7:\"area_id\";i:980;s:9:\"area_name\";s:6:\"Amtala\";}i:7;a:2:{s:7:\"area_id\";i:5247;s:9:\"area_name\";s:11:\"Anandabazar\";}i:8;a:2:{s:7:\"area_id\";i:5257;s:9:\"area_name\";s:14:\"basundhara R/A\";}i:9;a:2:{s:7:\"area_id\";i:954;s:9:\"area_name\";s:7:\"B block\";}i:10;a:2:{s:7:\"area_id\";i:985;s:9:\"area_name\";s:7:\"B block\";}i:11;a:2:{s:7:\"area_id\";i:5260;s:9:\"area_name\";s:8:\"BDR Hall\";}i:12;a:2:{s:7:\"area_id\";i:5262;s:9:\"area_name\";s:5:\"BITAK\";}i:13;a:2:{s:7:\"area_id\";i:5240;s:9:\"area_name\";s:11:\"Block A ctg\";}i:14;a:2:{s:7:\"area_id\";i:5239;s:9:\"area_name\";s:7:\"Block B\";}i:15;a:2:{s:7:\"area_id\";i:5245;s:9:\"area_name\";s:7:\"Block G\";}i:16;a:2:{s:7:\"area_id\";i:5246;s:9:\"area_name\";s:7:\"Block H\";}i:17;a:2:{s:7:\"area_id\";i:5241;s:9:\"area_name\";s:7:\"Block I\";}i:18;a:2:{s:7:\"area_id\";i:5242;s:9:\"area_name\";s:11:\"Block J ctg\";}i:19;a:2:{s:7:\"area_id\";i:5243;s:9:\"area_name\";s:7:\"Block K\";}i:20;a:2:{s:7:\"area_id\";i:5244;s:9:\"area_name\";s:7:\"Block L\";}i:21;a:2:{s:7:\"area_id\";i:4195;s:9:\"area_name\";s:13:\"Borapukur par\";}i:22;a:2:{s:7:\"area_id\";i:4301;s:9:\"area_name\";s:7:\"Boropol\";}i:23;a:2:{s:7:\"area_id\";i:1040;s:9:\"area_name\";s:8:\"Bowbazar\";}i:24;a:2:{s:7:\"area_id\";i:5266;s:9:\"area_name\";s:12:\"CDA 7 market\";}i:25;a:2:{s:7:\"area_id\";i:5263;s:9:\"area_name\";s:10:\"CDA market\";}i:26;a:2:{s:7:\"area_id\";i:1021;s:9:\"area_name\";s:10:\"CDA Market\";}i:27;a:2:{s:7:\"area_id\";i:5251;s:9:\"area_name\";s:9:\"Chowchala\";}i:28;a:2:{s:7:\"area_id\";i:5250;s:9:\"area_name\";s:15:\"Chowdhoury para\";}i:29;a:2:{s:7:\"area_id\";i:987;s:9:\"area_name\";s:17:\"Chunafactory More\";}i:30;a:2:{s:7:\"area_id\";i:956;s:9:\"area_name\";s:17:\"Chunafactory More\";}i:31;a:2:{s:7:\"area_id\";i:5254;s:9:\"area_name\";s:12:\"Cuna factory\";}i:32;a:2:{s:7:\"area_id\";i:3481;s:9:\"area_name\";s:7:\"DT Road\";}i:33;a:2:{s:7:\"area_id\";i:5255;s:9:\"area_name\";s:16:\"Foillatali bazar\";}i:34;a:2:{s:7:\"area_id\";i:986;s:9:\"area_name\";s:23:\"Green view abashik area\";}i:35;a:2:{s:7:\"area_id\";i:972;s:9:\"area_name\";s:21:\"Halishahar Cantonment\";}i:36;a:2:{s:7:\"area_id\";i:988;s:9:\"area_name\";s:9:\"Loharpool\";}i:37;a:2:{s:7:\"area_id\";i:983;s:9:\"area_name\";s:16:\"Moushumi abashik\";}i:38;a:2:{s:7:\"area_id\";i:5248;s:9:\"area_name\";s:13:\"Newmoring R/A\";}i:39;a:2:{s:7:\"area_id\";i:5252;s:9:\"area_name\";s:6:\"Nimtol\";}i:40;a:2:{s:7:\"area_id\";i:5259;s:9:\"area_name\";s:16:\"North halishahar\";}i:41;a:2:{s:7:\"area_id\";i:5265;s:9:\"area_name\";s:13:\"North Kattali\";}i:42;a:2:{s:7:\"area_id\";i:950;s:9:\"area_name\";s:9:\"Noyabazar\";}i:43;a:2:{s:7:\"area_id\";i:5253;s:9:\"area_name\";s:21:\"Pahartoli abul kheyer\";}i:44;a:2:{s:7:\"area_id\";i:5264;s:9:\"area_name\";s:15:\"Pahartoli Bazar\";}i:45;a:2:{s:7:\"area_id\";i:982;s:9:\"area_name\";s:8:\"Panirkol\";}i:46;a:2:{s:7:\"area_id\";i:4231;s:9:\"area_name\";s:11:\"Port Colony\";}i:47;a:2:{s:7:\"area_id\";i:4296;s:9:\"area_name\";s:8:\"Sagorika\";}i:48;a:2:{s:7:\"area_id\";i:4304;s:9:\"area_name\";s:9:\"Saraipara\";}i:49;a:2:{s:7:\"area_id\";i:989;s:9:\"area_name\";s:10:\"Sharaipara\";}i:50;a:2:{s:7:\"area_id\";i:5258;s:9:\"area_name\";s:12:\"Shaymoli R/A\";}i:51;a:2:{s:7:\"area_id\";i:5256;s:9:\"area_name\";s:13:\"Shobujbag R/A\";}}',1792086514),('laravel-cache-pathao_areas_86','a:81:{i:0;a:2:{s:7:\"area_id\";i:3512;s:9:\"area_name\";s:13:\"3 No fokirhat\";}i:1;a:2:{s:7:\"area_id\";i:4172;s:9:\"area_name\";s:21:\"Agrabaad Asscess Road\";}i:2;a:2:{s:7:\"area_id\";i:1145;s:9:\"area_name\";s:11:\"Agrabad C/A\";}i:3;a:2:{s:7:\"area_id\";i:3486;s:9:\"area_name\";s:23:\"Agrabad commercial zone\";}i:4;a:2:{s:7:\"area_id\";i:30812;s:9:\"area_name\";s:15:\"Agrabad Housing\";}i:5;a:2:{s:7:\"area_id\";i:18941;s:9:\"area_name\";s:9:\"Askarabad\";}i:6;a:2:{s:7:\"area_id\";i:3533;s:9:\"area_name\";s:9:\"Badamtoli\";}i:7;a:2:{s:7:\"area_id\";i:1148;s:9:\"area_name\";s:6:\"Banani\";}i:8;a:2:{s:7:\"area_id\";i:4190;s:9:\"area_name\";s:10:\"Banani R/A\";}i:9;a:2:{s:7:\"area_id\";i:3452;s:9:\"area_name\";s:11:\"Bank colony\";}i:10;a:2:{s:7:\"area_id\";i:15875;s:9:\"area_name\";s:26:\"Bank colony Double mooring\";}i:11;a:2:{s:7:\"area_id\";i:1147;s:9:\"area_name\";s:13:\"Barikbuilding\";}i:12;a:2:{s:7:\"area_id\";i:4173;s:9:\"area_name\";s:14:\"Barik building\";}i:13;a:2:{s:7:\"area_id\";i:18935;s:9:\"area_name\";s:10:\"Barua Para\";}i:14;a:2:{s:7:\"area_id\";i:3446;s:9:\"area_name\";s:15:\"Bohutola colony\";}i:15;a:2:{s:7:\"area_id\";i:3141;s:9:\"area_name\";s:6:\"CDA 10\";}i:16;a:2:{s:7:\"area_id\";i:3538;s:9:\"area_name\";s:6:\"CDA 11\";}i:17;a:2:{s:7:\"area_id\";i:3463;s:9:\"area_name\";s:6:\"CDA 12\";}i:18;a:2:{s:7:\"area_id\";i:3456;s:9:\"area_name\";s:6:\"CDA 13\";}i:19;a:2:{s:7:\"area_id\";i:3482;s:9:\"area_name\";s:6:\"CDA 14\";}i:20;a:2:{s:7:\"area_id\";i:3514;s:9:\"area_name\";s:6:\"CDA 15\";}i:21;a:2:{s:7:\"area_id\";i:3480;s:9:\"area_name\";s:6:\"CDA 16\";}i:22;a:2:{s:7:\"area_id\";i:3537;s:9:\"area_name\";s:6:\"CDA 17\";}i:23;a:2:{s:7:\"area_id\";i:3528;s:9:\"area_name\";s:6:\"CDA 18\";}i:24;a:2:{s:7:\"area_id\";i:4177;s:9:\"area_name\";s:6:\"CDA 19\";}i:25;a:2:{s:7:\"area_id\";i:3440;s:9:\"area_name\";s:13:\"CDA 1 no road\";}i:26;a:2:{s:7:\"area_id\";i:4178;s:9:\"area_name\";s:6:\"CDA 20\";}i:27;a:2:{s:7:\"area_id\";i:4179;s:9:\"area_name\";s:6:\"CDA 21\";}i:28;a:2:{s:7:\"area_id\";i:4180;s:9:\"area_name\";s:6:\"CDA 22\";}i:29;a:2:{s:7:\"area_id\";i:4181;s:9:\"area_name\";s:6:\"CDA 23\";}i:30;a:2:{s:7:\"area_id\";i:4182;s:9:\"area_name\";s:6:\"CDA 24\";}i:31;a:2:{s:7:\"area_id\";i:4183;s:9:\"area_name\";s:6:\"CDA 25\";}i:32;a:2:{s:7:\"area_id\";i:4184;s:9:\"area_name\";s:6:\"CDA 26\";}i:33;a:2:{s:7:\"area_id\";i:3497;s:9:\"area_name\";s:6:\"CDA 27\";}i:34;a:2:{s:7:\"area_id\";i:4185;s:9:\"area_name\";s:6:\"CDA 28\";}i:35;a:2:{s:7:\"area_id\";i:4186;s:9:\"area_name\";s:6:\"CDA 29\";}i:36;a:2:{s:7:\"area_id\";i:3477;s:9:\"area_name\";s:13:\"CDA 2 no road\";}i:37;a:2:{s:7:\"area_id\";i:4187;s:9:\"area_name\";s:6:\"CDA 30\";}i:38;a:2:{s:7:\"area_id\";i:16312;s:9:\"area_name\";s:13:\"CDA 3 no road\";}i:39;a:2:{s:7:\"area_id\";i:3517;s:9:\"area_name\";s:13:\"CDA 4 No road\";}i:40;a:2:{s:7:\"area_id\";i:3485;s:9:\"area_name\";s:13:\"CDA 5 No Road\";}i:41;a:2:{s:7:\"area_id\";i:3466;s:9:\"area_name\";s:13:\"CDA 6 No Road\";}i:42;a:2:{s:7:\"area_id\";i:3441;s:9:\"area_name\";s:13:\"CDA 7 No road\";}i:43;a:2:{s:7:\"area_id\";i:3506;s:9:\"area_name\";s:13:\"CDA 8 No road\";}i:44;a:2:{s:7:\"area_id\";i:3453;s:9:\"area_name\";s:5:\"CDA 9\";}i:45;a:2:{s:7:\"area_id\";i:3518;s:9:\"area_name\";s:10:\"CGS Colony\";}i:46;a:2:{s:7:\"area_id\";i:3460;s:9:\"area_name\";s:8:\"Chotopol\";}i:47;a:2:{s:7:\"area_id\";i:3491;s:9:\"area_name\";s:10:\"Chowmohuni\";}i:48;a:2:{s:7:\"area_id\";i:1015;s:9:\"area_name\";s:16:\"Commerce college\";}i:49;a:2:{s:7:\"area_id\";i:3492;s:9:\"area_name\";s:14:\"Damua Pokurpar\";}i:50;a:2:{s:7:\"area_id\";i:3439;s:9:\"area_name\";s:8:\"Debarpar\";}i:51;a:2:{s:7:\"area_id\";i:1133;s:9:\"area_name\";s:8:\"Dewanhat\";}i:52;a:2:{s:7:\"area_id\";i:4175;s:9:\"area_name\";s:8:\"Dewanhat\";}i:53;a:2:{s:7:\"area_id\";i:1134;s:9:\"area_name\";s:14:\"Dhaniwala para\";}i:54;a:2:{s:7:\"area_id\";i:3500;s:9:\"area_name\";s:13:\"Dhoniala para\";}i:55;a:2:{s:7:\"area_id\";i:4192;s:9:\"area_name\";s:7:\"Eidhgah\";}i:56;a:2:{s:7:\"area_id\";i:3505;s:9:\"area_name\";s:11:\"Gosaildanga\";}i:57;a:2:{s:7:\"area_id\";i:5335;s:9:\"area_name\";s:8:\"hazipara\";}i:58;a:2:{s:7:\"area_id\";i:4188;s:9:\"area_name\";s:18:\"islamiya Breakfild\";}i:59;a:2:{s:7:\"area_id\";i:3479;s:9:\"area_name\";s:15:\"Katabotgach mor\";}i:60;a:2:{s:7:\"area_id\";i:1136;s:9:\"area_name\";s:9:\"Kodomtali\";}i:61;a:2:{s:7:\"area_id\";i:990;s:9:\"area_name\";s:9:\"Kolka CNG\";}i:62;a:2:{s:7:\"area_id\";i:18937;s:9:\"area_name\";s:9:\"Majirghat\";}i:63;a:2:{s:7:\"area_id\";i:3513;s:9:\"area_name\";s:10:\"Mistripara\";}i:64;a:2:{s:7:\"area_id\";i:1142;s:9:\"area_name\";s:9:\"mogoltuli\";}i:65;a:2:{s:7:\"area_id\";i:4174;s:9:\"area_name\";s:9:\"Mogoltuly\";}i:66;a:2:{s:7:\"area_id\";i:3386;s:9:\"area_name\";s:10:\"Mohuripara\";}i:67;a:2:{s:7:\"area_id\";i:3136;s:9:\"area_name\";s:9:\"Mollapara\";}i:68;a:2:{s:7:\"area_id\";i:4176;s:9:\"area_name\";s:10:\"Monsurabad\";}i:69;a:2:{s:7:\"area_id\";i:1138;s:9:\"area_name\";s:10:\"Pathantuli\";}i:70;a:2:{s:7:\"area_id\";i:3501;s:9:\"area_name\";s:10:\"Pathantuly\";}i:71;a:2:{s:7:\"area_id\";i:3468;s:9:\"area_name\";s:15:\"Rashid building\";}i:72;a:2:{s:7:\"area_id\";i:16313;s:9:\"area_name\";s:9:\"Rongipara\";}i:73;a:2:{s:7:\"area_id\";i:4189;s:9:\"area_name\";s:22:\"Shantibag pollice line\";}i:74;a:2:{s:7:\"area_id\";i:1073;s:9:\"area_name\";s:13:\"shantibag R/A\";}i:75;a:2:{s:7:\"area_id\";i:16314;s:9:\"area_name\";s:12:\"Shaymoli R/A\";}i:76;a:2:{s:7:\"area_id\";i:3535;s:9:\"area_name\";s:16:\"Sheik mojib road\";}i:77;a:2:{s:7:\"area_id\";i:18936;s:9:\"area_name\";s:13:\"Sk Mujib Road\";}i:78;a:2:{s:7:\"area_id\";i:3476;s:9:\"area_name\";s:11:\"Strand Road\";}i:79;a:2:{s:7:\"area_id\";i:3389;s:9:\"area_name\";s:15:\"Supari walapara\";}i:80;a:2:{s:7:\"area_id\";i:3489;s:9:\"area_name\";s:15:\"West motherbari\";}}',1792086529),('laravel-cache-pathao_areas_986','a:6:{i:0;a:2:{s:7:\"area_id\";i:18940;s:9:\"area_name\";s:7:\"Ambagan\";}i:1;a:2:{s:7:\"area_id\";i:19285;s:9:\"area_name\";s:15:\"Basic-Pahartali\";}i:2;a:2:{s:7:\"area_id\";i:18939;s:9:\"area_name\";s:18:\"Dakkshin Pahartoli\";}i:3;a:2:{s:7:\"area_id\";i:18938;s:9:\"area_name\";s:9:\"Pahartali\";}i:4;a:2:{s:7:\"area_id\";i:18934;s:9:\"area_name\";s:11:\"Rampura TSO\";}i:5;a:2:{s:7:\"area_id\";i:19284;s:9:\"area_name\";s:8:\"Sakarika\";}}',1792086468),('laravel-cache-pathao_cities','a:64:{i:0;a:2:{s:7:\"city_id\";i:52;s:9:\"city_name\";s:8:\"Bagerhat\";}i:1;a:2:{s:7:\"city_id\";i:62;s:9:\"city_name\";s:10:\"Bandarban \";}i:2;a:2:{s:7:\"city_id\";i:34;s:9:\"city_name\";s:8:\"Barguna \";}i:3;a:2:{s:7:\"city_id\";i:17;s:9:\"city_name\";s:7:\"Barisal\";}i:4;a:2:{s:7:\"city_id\";i:32;s:9:\"city_name\";s:8:\"B. Baria\";}i:5;a:2:{s:7:\"city_id\";i:53;s:9:\"city_name\";s:5:\"Bhola\";}i:6;a:2:{s:7:\"city_id\";i:9;s:9:\"city_name\";s:5:\"Bogra\";}i:7;a:2:{s:7:\"city_id\";i:8;s:9:\"city_name\";s:8:\"Chandpur\";}i:8;a:2:{s:7:\"city_id\";i:15;s:9:\"city_name\";s:15:\"Chapainawabganj\";}i:9;a:2:{s:7:\"city_id\";i:2;s:9:\"city_name\";s:10:\"Chittagong\";}i:10;a:2:{s:7:\"city_id\";i:61;s:9:\"city_name\";s:9:\"Chuadanga\";}i:11;a:2:{s:7:\"city_id\";i:11;s:9:\"city_name\";s:11:\"Cox\'s Bazar\";}i:12;a:2:{s:7:\"city_id\";i:5;s:9:\"city_name\";s:7:\"Cumilla\";}i:13;a:2:{s:7:\"city_id\";i:1;s:9:\"city_name\";s:5:\"Dhaka\";}i:14;a:2:{s:7:\"city_id\";i:35;s:9:\"city_name\";s:8:\"Dinajpur\";}i:15;a:2:{s:7:\"city_id\";i:18;s:9:\"city_name\";s:8:\"Faridpur\";}i:16;a:2:{s:7:\"city_id\";i:6;s:9:\"city_name\";s:4:\"Feni\";}i:17;a:2:{s:7:\"city_id\";i:38;s:9:\"city_name\";s:9:\"Gaibandha\";}i:18;a:2:{s:7:\"city_id\";i:22;s:9:\"city_name\";s:7:\"Gazipur\";}i:19;a:2:{s:7:\"city_id\";i:56;s:9:\"city_name\";s:10:\"Gopalgonj \";}i:20;a:2:{s:7:\"city_id\";i:30;s:9:\"city_name\";s:8:\"Habiganj\";}i:21;a:2:{s:7:\"city_id\";i:41;s:9:\"city_name\";s:8:\"Jamalpur\";}i:22;a:2:{s:7:\"city_id\";i:19;s:9:\"city_name\";s:7:\"Jashore\";}i:23;a:2:{s:7:\"city_id\";i:27;s:9:\"city_name\";s:10:\"Jhalokathi\";}i:24;a:2:{s:7:\"city_id\";i:49;s:9:\"city_name\";s:8:\"Jhenidah\";}i:25;a:2:{s:7:\"city_id\";i:48;s:9:\"city_name\";s:9:\"Joypurhat\";}i:26;a:2:{s:7:\"city_id\";i:63;s:9:\"city_name\";s:11:\"Khagrachari\";}i:27;a:2:{s:7:\"city_id\";i:20;s:9:\"city_name\";s:6:\"Khulna\";}i:28;a:2:{s:7:\"city_id\";i:42;s:9:\"city_name\";s:11:\"Kishoreganj\";}i:29;a:2:{s:7:\"city_id\";i:55;s:9:\"city_name\";s:9:\"Kurigram \";}i:30;a:2:{s:7:\"city_id\";i:28;s:9:\"city_name\";s:7:\"Kushtia\";}i:31;a:2:{s:7:\"city_id\";i:40;s:9:\"city_name\";s:10:\"Lakshmipur\";}i:32;a:2:{s:7:\"city_id\";i:57;s:9:\"city_name\";s:12:\"Lalmonirhat \";}i:33;a:2:{s:7:\"city_id\";i:43;s:9:\"city_name\";s:9:\"Madaripur\";}i:34;a:2:{s:7:\"city_id\";i:60;s:9:\"city_name\";s:7:\"Magura \";}i:35;a:2:{s:7:\"city_id\";i:16;s:9:\"city_name\";s:9:\"Manikganj\";}i:36;a:2:{s:7:\"city_id\";i:50;s:9:\"city_name\";s:8:\"Meherpur\";}i:37;a:2:{s:7:\"city_id\";i:12;s:9:\"city_name\";s:11:\"Moulvibazar\";}i:38;a:2:{s:7:\"city_id\";i:23;s:9:\"city_name\";s:9:\"Munsiganj\";}i:39;a:2:{s:7:\"city_id\";i:26;s:9:\"city_name\";s:10:\"Mymensingh\";}i:40;a:2:{s:7:\"city_id\";i:46;s:9:\"city_name\";s:7:\"Naogaon\";}i:41;a:2:{s:7:\"city_id\";i:54;s:9:\"city_name\";s:7:\"Narail \";}i:42;a:2:{s:7:\"city_id\";i:21;s:9:\"city_name\";s:11:\"Narayanganj\";}i:43;a:2:{s:7:\"city_id\";i:47;s:9:\"city_name\";s:10:\"Narshingdi\";}i:44;a:2:{s:7:\"city_id\";i:14;s:9:\"city_name\";s:6:\"Natore\";}i:45;a:2:{s:7:\"city_id\";i:44;s:9:\"city_name\";s:9:\"Netrakona\";}i:46;a:2:{s:7:\"city_id\";i:39;s:9:\"city_name\";s:10:\"Nilphamari\";}i:47;a:2:{s:7:\"city_id\";i:7;s:9:\"city_name\";s:8:\"Noakhali\";}i:48;a:2:{s:7:\"city_id\";i:24;s:9:\"city_name\";s:5:\"Pabna\";}i:49;a:2:{s:7:\"city_id\";i:37;s:9:\"city_name\";s:10:\"Panchagarh\";}i:50;a:2:{s:7:\"city_id\";i:29;s:9:\"city_name\";s:10:\"Patuakhali\";}i:51;a:2:{s:7:\"city_id\";i:31;s:9:\"city_name\";s:8:\"Pirojpur\";}i:52;a:2:{s:7:\"city_id\";i:58;s:9:\"city_name\";s:8:\"Rajbari \";}i:53;a:2:{s:7:\"city_id\";i:4;s:9:\"city_name\";s:8:\"Rajshahi\";}i:54;a:2:{s:7:\"city_id\";i:59;s:9:\"city_name\";s:10:\"Rangamati \";}i:55;a:2:{s:7:\"city_id\";i:25;s:9:\"city_name\";s:7:\"Rangpur\";}i:56;a:2:{s:7:\"city_id\";i:51;s:9:\"city_name\";s:8:\"Satkhira\";}i:57;a:2:{s:7:\"city_id\";i:64;s:9:\"city_name\";s:11:\"Shariatpur \";}i:58;a:2:{s:7:\"city_id\";i:33;s:9:\"city_name\";s:7:\"Sherpur\";}i:59;a:2:{s:7:\"city_id\";i:10;s:9:\"city_name\";s:9:\"Sirajganj\";}i:60;a:2:{s:7:\"city_id\";i:45;s:9:\"city_name\";s:9:\"Sunamganj\";}i:61;a:2:{s:7:\"city_id\";i:3;s:9:\"city_name\";s:6:\"Sylhet\";}i:62;a:2:{s:7:\"city_id\";i:13;s:9:\"city_name\";s:7:\"Tangail\";}i:63;a:2:{s:7:\"city_id\";i:36;s:9:\"city_name\";s:11:\"Thakurgaon \";}}',1792086522),('laravel-cache-pathao_merchant_stores','a:1:{i:0;a:3:{s:8:\"store_id\";i:67938;s:10:\"store_name\";s:4:\"Tasa\";s:13:\"store_address\";s:103:\"Our Design Studio : Tasa villa, Pahartali DT road, beside bheluar dighi school, Pahartali, Chattogram.\n\";}}',1792011104),('laravel-cache-pathao_zones_2','a:68:{i:0;a:2:{s:7:\"zone_id\";i:2896;s:9:\"zone_name\";s:18:\"Airport Chittagong\";}i:1;a:2:{s:7:\"zone_id\";i:78;s:9:\"zone_name\";s:8:\"Akbarsha\";}i:2;a:2:{s:7:\"zone_id\";i:3013;s:9:\"zone_name\";s:10:\"Aman Bazar\";}i:3;a:2:{s:7:\"zone_id\";i:434;s:9:\"zone_name\";s:7:\"Anawara\";}i:4;a:2:{s:7:\"zone_id\";i:69;s:9:\"zone_name\";s:10:\"Andarkilla\";}i:5;a:2:{s:7:\"zone_id\";i:453;s:9:\"zone_name\";s:7:\"Anowara\";}i:6;a:2:{s:7:\"zone_id\";i:2966;s:9:\"zone_name\";s:11:\"Bahaddarhat\";}i:7;a:2:{s:7:\"zone_id\";i:83;s:9:\"zone_name\";s:14:\"Bakoliya thana\";}i:8;a:2:{s:7:\"zone_id\";i:2999;s:9:\"zone_name\";s:11:\"Baraiyarhat\";}i:9;a:2:{s:7:\"zone_id\";i:459;s:9:\"zone_name\";s:9:\"Bashkhali\";}i:10;a:2:{s:7:\"zone_id\";i:80;s:9:\"zone_name\";s:7:\"Bayazid\";}i:11;a:2:{s:7:\"zone_id\";i:3012;s:9:\"zone_name\";s:7:\"Bhujpur\";}i:12;a:2:{s:7:\"zone_id\";i:394;s:9:\"zone_name\";s:22:\"Bibir Hat - Fatikchori\";}i:13;a:2:{s:7:\"zone_id\";i:681;s:9:\"zone_name\";s:9:\"Boalkhali\";}i:14;a:2:{s:7:\"zone_id\";i:1027;s:9:\"zone_name\";s:6:\"Bondor\";}i:15;a:2:{s:7:\"zone_id\";i:73;s:9:\"zone_name\";s:4:\"CEPZ\";}i:16;a:2:{s:7:\"zone_id\";i:1015;s:9:\"zone_name\";s:11:\"Chandanaish\";}i:17;a:2:{s:7:\"zone_id\";i:375;s:9:\"zone_name\";s:9:\"Chandgaon\";}i:18;a:2:{s:7:\"zone_id\";i:79;s:9:\"zone_name\";s:23:\"Chawkbazar (Chattogram)\";}i:19;a:2:{s:7:\"zone_id\";i:88;s:9:\"zone_name\";s:21:\"Chawkbazar North Side\";}i:20;a:2:{s:7:\"zone_id\";i:460;s:9:\"zone_name\";s:14:\"CTG - Lohagara\";}i:21;a:2:{s:7:\"zone_id\";i:1032;s:9:\"zone_name\";s:8:\"Dohajari\";}i:22;a:2:{s:7:\"zone_id\";i:86;s:9:\"zone_name\";s:14:\"Double mooring\";}i:23;a:2:{s:7:\"zone_id\";i:400;s:9:\"zone_name\";s:10:\"East Joara\";}i:24;a:2:{s:7:\"zone_id\";i:405;s:9:\"zone_name\";s:12:\"Enayet bazar\";}i:25;a:2:{s:7:\"zone_id\";i:2892;s:9:\"zone_name\";s:23:\"Estrin refinery housing\";}i:26;a:2:{s:7:\"zone_id\";i:417;s:9:\"zone_name\";s:11:\"Fatikchhari\";}i:27;a:2:{s:7:\"zone_id\";i:75;s:9:\"zone_name\";s:10:\"Halishahar\";}i:28;a:2:{s:7:\"zone_id\";i:2912;s:9:\"zone_name\";s:20:\"haque shaheb er Goli\";}i:29;a:2:{s:7:\"zone_id\";i:81;s:9:\"zone_name\";s:9:\"Hathazari\";}i:30;a:2:{s:7:\"zone_id\";i:2906;s:9:\"zone_name\";s:14:\"Housing Colony\";}i:31;a:2:{s:7:\"zone_id\";i:3000;s:9:\"zone_name\";s:22:\"Jorarganj Rastar matha\";}i:32;a:2:{s:7:\"zone_id\";i:3001;s:9:\"zone_name\";s:9:\"Jorargonj\";}i:33;a:2:{s:7:\"zone_id\";i:993;s:9:\"zone_name\";s:10:\"Karnophuli\";}i:34;a:2:{s:7:\"zone_id\";i:2714;s:9:\"zone_name\";s:24:\"Karolia-Takia Fatikchori\";}i:35;a:2:{s:7:\"zone_id\";i:2891;s:9:\"zone_name\";s:7:\"Katghor\";}i:36;a:2:{s:7:\"zone_id\";i:2721;s:9:\"zone_name\";s:28:\"Kazirhat Borobill-Fatikchori\";}i:37;a:2:{s:7:\"zone_id\";i:716;s:9:\"zone_name\";s:9:\"Keranihat\";}i:38;a:2:{s:7:\"zone_id\";i:2908;s:9:\"zone_name\";s:18:\"Khalpar mazar goli\";}i:39;a:2:{s:7:\"zone_id\";i:84;s:9:\"zone_name\";s:7:\"Khulshi\";}i:40;a:2:{s:7:\"zone_id\";i:2468;s:9:\"zone_name\";s:20:\"Korean EPZ - Anwara \";}i:41;a:2:{s:7:\"zone_id\";i:364;s:9:\"zone_name\";s:19:\"Kotowali Chittagong\";}i:42;a:2:{s:7:\"zone_id\";i:987;s:9:\"zone_name\";s:20:\"Kotwali (Chattogram)\";}i:43;a:2:{s:7:\"zone_id\";i:70;s:9:\"zone_name\";s:13:\"Lalkhan Bazar\";}i:44;a:2:{s:7:\"zone_id\";i:87;s:9:\"zone_name\";s:9:\"Mirsharai\";}i:45;a:2:{s:7:\"zone_id\";i:2476;s:9:\"zone_name\";s:19:\"Muradpur - Bibirhat\";}i:46;a:2:{s:7:\"zone_id\";i:685;s:9:\"zone_name\";s:9:\"Nasirabad\";}i:47;a:2:{s:7:\"zone_id\";i:703;s:9:\"zone_name\";s:8:\"Nazirhat\";}i:48;a:2:{s:7:\"zone_id\";i:72;s:9:\"zone_name\";s:14:\"New Market CTG\";}i:49;a:2:{s:7:\"zone_id\";i:366;s:9:\"zone_name\";s:11:\"New Mooring\";}i:50;a:2:{s:7:\"zone_id\";i:458;s:9:\"zone_name\";s:11:\"Notunbridge\";}i:51;a:2:{s:7:\"zone_id\";i:2713;s:9:\"zone_name\";s:20:\"On-Demand-Chattogram\";}i:52;a:2:{s:7:\"zone_id\";i:986;s:9:\"zone_name\";s:20:\"Pahartoli-Halishahar\";}i:53;a:2:{s:7:\"zone_id\";i:2658;s:9:\"zone_name\";s:16:\"Pahartoli-Raozan\";}i:54;a:2:{s:7:\"zone_id\";i:333;s:9:\"zone_name\";s:14:\"Panchlaish ctg\";}i:55;a:2:{s:7:\"zone_id\";i:457;s:9:\"zone_name\";s:5:\"Patia\";}i:56;a:2:{s:7:\"zone_id\";i:370;s:9:\"zone_name\";s:7:\"Potenga\";}i:57;a:2:{s:7:\"zone_id\";i:378;s:9:\"zone_name\";s:8:\"Rangunia\";}i:58;a:2:{s:7:\"zone_id\";i:2475;s:9:\"zone_name\";s:19:\"Rangunia - Ranirhat\";}i:59;a:2:{s:7:\"zone_id\";i:68;s:9:\"zone_name\";s:6:\"Raozan\";}i:60;a:2:{s:7:\"zone_id\";i:365;s:9:\"zone_name\";s:22:\"Sadarghat (Chattogram)\";}i:61;a:2:{s:7:\"zone_id\";i:705;s:9:\"zone_name\";s:7:\"Sandwip\";}i:62;a:2:{s:7:\"zone_id\";i:371;s:9:\"zone_name\";s:9:\"Satkaniya\";}i:63;a:2:{s:7:\"zone_id\";i:382;s:9:\"zone_name\";s:11:\"Sholakbahar\";}i:64;a:2:{s:7:\"zone_id\";i:436;s:9:\"zone_name\";s:10:\"Shulokhbor\";}i:65;a:2:{s:7:\"zone_id\";i:420;s:9:\"zone_name\";s:9:\"Sitakunda\";}i:66;a:2:{s:7:\"zone_id\";i:1026;s:9:\"zone_name\";s:7:\"Vatiary\";}i:67;a:2:{s:7:\"zone_id\";i:730;s:9:\"zone_name\";s:9:\"Zorargonj\";}}',1792086468),('laravel-cache-pathao_zones_32','a:28:{i:0;a:2:{s:7:\"zone_id\";i:2678;s:9:\"zone_name\";s:7:\"Ajampur\";}i:1;a:2:{s:7:\"zone_id\";i:546;s:9:\"zone_name\";s:7:\"Akhaura\";}i:2;a:2:{s:7:\"zone_id\";i:2677;s:9:\"zone_name\";s:17:\"Akhaura Checkpost\";}i:3;a:2:{s:7:\"zone_id\";i:728;s:9:\"zone_name\";s:14:\"Ashuganj Sadar\";}i:4;a:2:{s:7:\"zone_id\";i:2661;s:9:\"zone_name\";s:18:\"Austagram Ashuganj\";}i:5;a:2:{s:7:\"zone_id\";i:133;s:9:\"zone_name\";s:12:\"Bancharampur\";}i:6;a:2:{s:7:\"zone_id\";i:748;s:9:\"zone_name\";s:10:\"Bijoynagar\";}i:7;a:2:{s:7:\"zone_id\";i:547;s:9:\"zone_name\";s:19:\"Brahamanbaria Sadar\";}i:8;a:2:{s:7:\"zone_id\";i:2660;s:9:\"zone_name\";s:13:\"Char Chartola\";}i:9;a:2:{s:7:\"zone_id\";i:2672;s:9:\"zone_name\";s:9:\"Chargachh\";}i:10;a:2:{s:7:\"zone_id\";i:548;s:9:\"zone_name\";s:5:\"Kasba\";}i:11;a:2:{s:7:\"zone_id\";i:2666;s:9:\"zone_name\";s:11:\"Kasba Sadar\";}i:12;a:2:{s:7:\"zone_id\";i:2667;s:9:\"zone_name\";s:10:\"Kasba West\";}i:13;a:2:{s:7:\"zone_id\";i:2671;s:9:\"zone_name\";s:7:\"Kharera\";}i:14;a:2:{s:7:\"zone_id\";i:2676;s:9:\"zone_name\";s:9:\"Khorompur\";}i:15;a:2:{s:7:\"zone_id\";i:2669;s:9:\"zone_name\";s:4:\"Kuti\";}i:16;a:2:{s:7:\"zone_id\";i:2663;s:9:\"zone_name\";s:15:\"Lalpur Ashuganj\";}i:17;a:2:{s:7:\"zone_id\";i:2674;s:9:\"zone_name\";s:11:\"Mogra Bazar\";}i:18;a:2:{s:7:\"zone_id\";i:2670;s:9:\"zone_name\";s:16:\"Mojlishpur-kasba\";}i:19;a:2:{s:7:\"zone_id\";i:2673;s:9:\"zone_name\";s:9:\"Moniondha\";}i:20;a:2:{s:7:\"zone_id\";i:549;s:9:\"zone_name\";s:9:\"Nabinagar\";}i:21;a:2:{s:7:\"zone_id\";i:551;s:9:\"zone_name\";s:10:\"Nasirnagar\";}i:22;a:2:{s:7:\"zone_id\";i:2668;s:9:\"zone_name\";s:8:\"Noyanpur\";}i:23;a:2:{s:7:\"zone_id\";i:2665;s:9:\"zone_name\";s:16:\"Panishwar-Sarail\";}i:24;a:2:{s:7:\"zone_id\";i:550;s:9:\"zone_name\";s:6:\"Sarail\";}i:25;a:2:{s:7:\"zone_id\";i:2662;s:9:\"zone_name\";s:8:\"Sohagpur\";}i:26;a:2:{s:7:\"zone_id\";i:2664;s:9:\"zone_name\";s:9:\"Talshahar\";}i:27;a:2:{s:7:\"zone_id\";i:2675;s:9:\"zone_name\";s:12:\"Tontor Bazar\";}}',1792085977),('laravel-cache-pathao_zones_52','a:12:{i:0;a:2:{s:7:\"zone_id\";i:156;s:9:\"zone_name\";s:14:\"Bagerhat Sadar\";}i:1;a:2:{s:7:\"zone_id\";i:989;s:9:\"zone_name\";s:5:\"Bajua\";}i:2;a:2:{s:7:\"zone_id\";i:594;s:9:\"zone_name\";s:10:\"Chitalmari\";}i:3;a:2:{s:7:\"zone_id\";i:595;s:9:\"zone_name\";s:8:\"Fakirhat\";}i:4;a:2:{s:7:\"zone_id\";i:596;s:9:\"zone_name\";s:6:\"Kachua\";}i:5;a:2:{s:7:\"zone_id\";i:593;s:9:\"zone_name\";s:8:\"Mollahat\";}i:6;a:2:{s:7:\"zone_id\";i:868;s:9:\"zone_name\";s:6:\"Mongla\";}i:7;a:2:{s:7:\"zone_id\";i:2129;s:9:\"zone_name\";s:12:\"Mongla Sadar\";}i:8;a:2:{s:7:\"zone_id\";i:597;s:9:\"zone_name\";s:9:\"Morelganj\";}i:9;a:2:{s:7:\"zone_id\";i:598;s:9:\"zone_name\";s:6:\"Rampal\";}i:10;a:2:{s:7:\"zone_id\";i:599;s:9:\"zone_name\";s:7:\"Rayenda\";}i:11;a:2:{s:7:\"zone_id\";i:984;s:9:\"zone_name\";s:10:\"Sarankhola\";}}',1792015760),('laravel-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:6:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";s:1:\"j\";s:9:\"is_locked\";s:1:\"k\";s:11:\"description\";}s:11:\"permissions\";a:44:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:14:\"dashboard.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:25:\"dashboard.view_financials\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:10:\"sales.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:12:\"sales.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:16:\"sales.edit_price\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:12:\"sales.cancel\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:12:\"sales.return\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:17:\"sales.add_payment\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:8;a:4:{s:1:\"a\";i:9;s:1:\"b\";s:14:\"customers.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:9;a:4:{s:1:\"a\";i:10;s:1:\"b\";s:16:\"customers.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:10;a:4:{s:1:\"a\";i:11;s:1:\"b\";s:12:\"courier.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:11;a:4:{s:1:\"a\";i:12;s:1:\"b\";s:13:\"courier.retry\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:12;a:4:{s:1:\"a\";i:13;s:1:\"b\";s:18:\"courier.settle_cod\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:13;a:4:{s:1:\"a\";i:14;s:1:\"b\";s:13:\"products.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:14;a:4:{s:1:\"a\";i:15;s:1:\"b\";s:15:\"products.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:15;a:4:{s:1:\"a\";i:16;s:1:\"b\";s:15:\"products.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:16;a:4:{s:1:\"a\";i:17;s:1:\"b\";s:15:\"products.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:17;a:4:{s:1:\"a\";i:18;s:1:\"b\";s:15:\"products.import\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:18;a:4:{s:1:\"a\";i:19;s:1:\"b\";s:12:\"leather.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:19;a:4:{s:1:\"a\";i:20;s:1:\"b\";s:14:\"leather.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:20;a:4:{s:1:\"a\";i:21;s:1:\"b\";s:14:\"leather.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:21;a:4:{s:1:\"a\";i:22;s:1:\"b\";s:14:\"leather.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:22;a:4:{s:1:\"a\";i:23;s:1:\"b\";s:16:\"accessories.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:23;a:4:{s:1:\"a\";i:24;s:1:\"b\";s:18:\"accessories.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:24;a:4:{s:1:\"a\";i:25;s:1:\"b\";s:18:\"accessories.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:25;a:4:{s:1:\"a\";i:26;s:1:\"b\";s:18:\"accessories.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:26;a:4:{s:1:\"a\";i:27;s:1:\"b\";s:16:\"adjustments.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:27;a:4:{s:1:\"a\";i:28;s:1:\"b\";s:18:\"adjustments.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:28;a:4:{s:1:\"a\";i:29;s:1:\"b\";s:20:\"inventory.view_costs\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:29;a:4:{s:1:\"a\";i:30;s:1:\"b\";s:13:\"expenses.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:30;a:4:{s:1:\"a\";i:31;s:1:\"b\";s:15:\"expenses.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:31;a:4:{s:1:\"a\";i:32;s:1:\"b\";s:15:\"expenses.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:32;a:4:{s:1:\"a\";i:33;s:1:\"b\";s:15:\"expenses.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:33;a:4:{s:1:\"a\";i:34;s:1:\"b\";s:12:\"payroll.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:34;a:4:{s:1:\"a\";i:35;s:1:\"b\";s:14:\"payroll.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:35;a:4:{s:1:\"a\";i:36;s:1:\"b\";s:14:\"payroll.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:36;a:4:{s:1:\"a\";i:37;s:1:\"b\";s:14:\"payroll.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:37;a:4:{s:1:\"a\";i:38;s:1:\"b\";s:13:\"reports.sales\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:38;a:4:{s:1:\"a\";i:39;s:1:\"b\";s:20:\"reports.sales_profit\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:39;a:4:{s:1:\"a\";i:40;s:1:\"b\";s:19:\"reports.profit_loss\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:40;a:4:{s:1:\"a\";i:41;s:1:\"b\";s:15:\"settings.manage\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:41;a:4:{s:1:\"a\";i:42;s:1:\"b\";s:12:\"users.manage\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:42;a:4:{s:1:\"a\";i:43;s:1:\"b\";s:12:\"roles.manage\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:43;a:4:{s:1:\"a\";i:44;s:1:\"b\";s:19:\"inventory.cost.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}}s:5:\"roles\";a:2:{i:0;a:5:{s:1:\"a\";i:1;s:1:\"b\";s:5:\"Owner\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;s:1:\"k\";s:60:\"Full access to all modules, financial metrics, and settings.\";}i:1;a:5:{s:1:\"a\";i:2;s:1:\"b\";s:5:\"Staff\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:0;s:1:\"k\";s:68:\"Operational access to POS, invoices, inventory, and expense logging.\";}}}',1792094206);
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
-- Table structure for table `courier_orders`
--

DROP TABLE IF EXISTS `courier_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courier_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `merchant_order_id` varchar(30) NOT NULL,
  `consignment_id` varchar(50) DEFAULT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `delivery_type` tinyint(3) unsigned NOT NULL DEFAULT 48,
  `item_type` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `item_quantity` int(10) unsigned NOT NULL,
  `item_weight` decimal(6,2) NOT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `special_instruction` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(120) NOT NULL,
  `recipient_phone` char(11) NOT NULL,
  `recipient_address` varchar(500) NOT NULL,
  `recipient_city` int(10) unsigned NOT NULL,
  `recipient_zone` int(10) unsigned NOT NULL,
  `recipient_area` int(10) unsigned NOT NULL,
  `cod_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `courier_charge` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `raw_status` varchar(60) DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cod_settled_at` datetime DEFAULT NULL,
  `cod_settled_amount` decimal(12,2) DEFAULT NULL,
  `cod_settled_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courier_orders_sale_id_unique` (`sale_id`),
  UNIQUE KEY `courier_orders_consignment_id_unique` (`consignment_id`),
  KEY `courier_orders_cod_settled_by_foreign` (`cod_settled_by`),
  KEY `courier_orders_status_index` (`status`),
  KEY `courier_orders_status_last_synced_at_index` (`status`,`last_synced_at`),
  KEY `courier_orders_cod_amount_delivered_at_cod_settled_at_index` (`cod_amount`,`delivered_at`,`cod_settled_at`),
  CONSTRAINT `courier_orders_cod_settled_by_foreign` FOREIGN KEY (`cod_settled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courier_orders_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courier_orders`
--

LOCK TABLES `courier_orders` WRITE;
/*!40000 ALTER TABLE `courier_orders` DISABLE KEYS */;
INSERT INTO `courier_orders` VALUES (1,1,'TASA-2610-0001',NULL,67938,48,2,1,0.50,'abc',NULL,'Alter Reality','01845700011','CHITTAGONG',2,86,1145,1000.00,NULL,'awaiting_retry',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-13 21:25:34','2026-10-14 17:49:13'),(2,2,'TASA-2610-0002',NULL,67938,48,2,1,0.50,'safds',NULL,'Alter Reality','01845700011','halishahar, chittagong',2,75,5240,10000.00,NULL,'awaiting_retry',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-13 21:33:19','2026-10-14 17:49:13'),(3,3,'TASA-2610-0003','DT141026B3AEXT',67938,48,2,1,0.50,'asd',NULL,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,75,982,1223.00,60.00,'cancelled','Pickup Cancel',1,NULL,'2026-10-14 23:56:56',NULL,NULL,NULL,NULL,'2026-10-13 21:39:59','2026-10-14 17:56:56'),(4,4,'TASA-2610-0004',NULL,67938,48,2,1,0.50,'A4 File Bag Series 2 — Black',NULL,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,202,20202,3399.00,NULL,'cancelled',NULL,3,'Exceeded maximum retry attempts (3). Please check address details.',NULL,NULL,NULL,NULL,NULL,'2026-10-14 17:34:11','2026-10-14 17:47:36'),(5,5,'TASA-2610-0005','DT141026E9DGYE',67938,48,2,1,0.50,'A4 File Bag Series 2 — Black',NULL,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,365,19809,3499.00,60.00,'cancelled','Pickup Cancel',1,NULL,'2026-10-15 02:37:51',NULL,NULL,NULL,NULL,'2026-10-14 19:51:41','2026-10-14 20:37:51'),(6,6,'TASA-2610-0006','DT141026YUKA5D',67938,48,2,1,0.50,'A4 File Bag Series 2 — Chocolate',NULL,'Test Customer','01712345678','123 Test Street, Test Area, Dhaka',52,156,13193,3399.00,120.00,'cancelled','Pickup Cancel',1,NULL,'2026-10-15 02:37:52',NULL,NULL,NULL,NULL,'2026-10-14 20:25:37','2026-10-14 20:37:52');
/*!40000 ALTER TABLE `courier_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courier_status_logs`
--

DROP TABLE IF EXISTS `courier_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courier_status_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `courier_order_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL,
  `raw_status` varchar(60) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `logged_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `courier_status_logs_courier_order_id_logged_at_index` (`courier_order_id`,`logged_at`),
  CONSTRAINT `courier_status_logs_courier_order_id_foreign` FOREIGN KEY (`courier_order_id`) REFERENCES `courier_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courier_status_logs`
--

LOCK TABLES `courier_status_logs` WRITE;
/*!40000 ALTER TABLE `courier_status_logs` DISABLE KEYS */;
INSERT INTO `courier_status_logs` VALUES (1,3,'created','Order created','{\"consignment_id\":\"DT141026B3AEXT\",\"delivery_fee\":60,\"merchant_order_id\":\"TASA-2610-0003\",\"attempt\":1}','2026-10-14 23:53:09'),(2,3,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-14T23:56:53+06:00\"}','2026-10-14 23:56:53'),(3,3,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-14T23:56:56+06:00\"}','2026-10-14 23:56:56'),(4,5,'created','Order created','{\"consignment_id\":\"DT141026E9DGYE\",\"delivery_fee\":60,\"merchant_order_id\":\"TASA-2610-0005\",\"attempt\":1}','2026-10-15 01:51:45'),(5,5,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-15T02:04:44+06:00\"}','2026-10-15 02:04:44'),(6,6,'created','Order created','{\"consignment_id\":\"DT141026YUKA5D\",\"delivery_fee\":120,\"merchant_order_id\":\"TASA-2610-0006\",\"attempt\":1}','2026-10-15 02:25:40'),(7,6,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-15T02:37:18+06:00\"}','2026-10-15 02:37:18'),(8,6,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-15T02:37:19+06:00\"}','2026-10-15 02:37:19'),(9,5,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-15T02:37:51+06:00\"}','2026-10-15 02:37:51'),(10,6,'cancelled','Pickup Cancel','{\"synced_at\":\"2026-10-15T02:37:52+06:00\"}','2026-10-15 02:37:52');
/*!40000 ALTER TABLE `courier_status_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` char(11) NOT NULL,
  `alt_phone` char(11) DEFAULT NULL,
  `address` varchar(500) NOT NULL,
  `pathao_city_id` int(10) unsigned DEFAULT NULL,
  `pathao_zone_id` int(10) unsigned DEFAULT NULL,
  `pathao_area_id` int(10) unsigned DEFAULT NULL,
  `city_name` varchar(100) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `area_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_phone_unique` (`phone`),
  KEY `customers_name_index` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Alter Reality','01845700011',NULL,'1 no panirkol, halishahar, chittagong',2,365,19809,'Chittagong','Sadarghat (Chattogram)','Kadam Tali',NULL,'2026-10-13 21:25:34','2026-10-14 19:51:41',NULL),(2,'Test Customer','01712345678',NULL,'123 Test Street, Test Area, Dhaka',52,156,13193,'Bagerhat','Bagerhat Sadar','Adarsh school',NULL,'2026-10-14 20:25:37','2026-10-14 20:25:37',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `monthly_salary` decimal(12,2) NOT NULL,
  `joined_at` date DEFAULT NULL,
  `left_at` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'Mizanur Rahman','01711223344','Senior Leather Craftsman',35000.00,NULL,NULL,1,'2026-10-14 18:27:59','2026-10-14 18:27:59',NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `pl_group` varchar(30) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'Facebook Ads','marketing_facebook',1,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(2,'Other Marketing','marketing_other',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(3,'Packaging','packaging',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(4,'Courier Charges','courier',1,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(5,'Rent','rent_utilities',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(6,'Electricity','rent_utilities',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(7,'Internet','rent_utilities',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(8,'Gas / Water','rent_utilities',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(9,'Transport','other',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(10,'Repair & Maintenance','other',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(11,'Tools & Equipment','other',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43'),(12,'Miscellaneous','other',0,1,'2026-10-10 21:16:43','2026-10-10 21:16:43');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_category_id` bigint(20) unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(20) DEFAULT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'manual',
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `courier_order_id` bigint(20) unsigned DEFAULT NULL,
  `sale_return_id` bigint(20) unsigned DEFAULT NULL,
  `recurring_expense_id` bigint(20) unsigned DEFAULT NULL,
  `fee_deducted_from_cod` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_courier_order_id_source_unique` (`courier_order_id`,`source`),
  KEY `expenses_sale_id_foreign` (`sale_id`),
  KEY `expenses_sale_return_id_foreign` (`sale_return_id`),
  KEY `expenses_recurring_expense_id_foreign` (`recurring_expense_id`),
  KEY `expenses_created_by_foreign` (`created_by`),
  KEY `expenses_updated_by_foreign` (`updated_by`),
  KEY `expenses_expense_date_index` (`expense_date`),
  KEY `expenses_expense_category_id_expense_date_index` (`expense_category_id`,`expense_date`),
  CONSTRAINT `expenses_courier_order_id_foreign` FOREIGN KEY (`courier_order_id`) REFERENCES `courier_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `expenses_recurring_expense_id_foreign` FOREIGN KEY (`recurring_expense_id`) REFERENCES `recurring_expenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_sale_return_id_foreign` FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_amount_check` CHECK (`amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,4,'2026-10-14',60.00,'other','Pathao','Pathao courier delivery fee for TASA-2610-0003 (Consignment: DT141026B3AEXT)',NULL,'pathao_charge',3,3,NULL,NULL,1,1,NULL,'2026-10-14 17:53:09','2026-10-14 20:07:46','2026-10-14 20:07:46'),(2,4,'2026-10-15',60.00,'other','Pathao','Pathao courier delivery fee for TASA-2610-0005 (Consignment: DT141026E9DGYE)',NULL,'pathao_charge',5,5,NULL,NULL,1,2,NULL,'2026-10-14 19:51:45','2026-10-14 20:07:46','2026-10-14 20:07:46'),(3,4,'2026-10-15',120.00,'other','Pathao','Business-borne courier delivery fee for TASA-2610-0006 (Consignment: DT141026YUKA5D, Courier Fee: ৳120, Customer Paid: ৳0)',NULL,'pathao_charge',6,6,NULL,NULL,1,2,NULL,'2026-10-14 20:25:40','2026-10-14 20:37:19','2026-10-14 20:37:19');
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
-- Table structure for table `import_logs`
--

DROP TABLE IF EXISTS `import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `success_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`errors`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `import_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `import_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_logs`
--

LOCK TABLES `import_logs` WRITE;
/*!40000 ALTER TABLE `import_logs` DISABLE KEYS */;
INSERT INTO `import_logs` VALUES (1,1,'products','tasa_product_import_template (1).xlsx',55,53,2,'[{\"row\":2,\"name\":\"Eyewear Case Series 1 (Black)\",\"error\":\"Variant \'Black\' already exists on product.\"},{\"row\":3,\"name\":\"Eyewear Case Series 1 (Brown)\",\"error\":\"Variant \'Brown\' already exists on product.\"}]','2026-10-14 17:32:30');
/*!40000 ALTER TABLE `import_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_sequences`
--

DROP TABLE IF EXISTS `invoice_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_sequences` (
  `period` char(4) NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_sequences`
--

LOCK TABLES `invoice_sequences` WRITE;
/*!40000 ALTER TABLE `invoice_sequences` DISABLE KEYS */;
INSERT INTO `invoice_sequences` VALUES ('2610',6);
/*!40000 ALTER TABLE `invoice_sequences` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leather_consumptions`
--

DROP TABLE IF EXISTS `leather_consumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leather_consumptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leather_item_id` bigint(20) unsigned NOT NULL,
  `consumption_date` date NOT NULL,
  `qty_sqft` decimal(12,3) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leather_consumptions_created_by_foreign` (`created_by`),
  KEY `leather_consumptions_updated_by_foreign` (`updated_by`),
  KEY `leather_consumptions_leather_item_id_consumption_date_index` (`leather_item_id`,`consumption_date`),
  CONSTRAINT `leather_consumptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leather_consumptions_leather_item_id_foreign` FOREIGN KEY (`leather_item_id`) REFERENCES `leather_items` (`id`),
  CONSTRAINT `leather_consumptions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leather_consumptions_qty_check` CHECK (`qty_sqft` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leather_consumptions`
--

LOCK TABLES `leather_consumptions` WRITE;
/*!40000 ALTER TABLE `leather_consumptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `leather_consumptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leather_items`
--

DROP TABLE IF EXISTS `leather_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leather_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `colour_grade` varchar(100) DEFAULT NULL,
  `min_stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leather_items_name_colour_grade_unique` (`name`,`colour_grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leather_items`
--

LOCK TABLES `leather_items` WRITE;
/*!40000 ALTER TABLE `leather_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `leather_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leather_purchases`
--

DROP TABLE IF EXISTS `leather_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leather_purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leather_item_id` bigint(20) unsigned NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `qty_sqft` decimal(12,3) NOT NULL,
  `rate_per_sqft` decimal(12,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leather_purchases_created_by_foreign` (`created_by`),
  KEY `leather_purchases_updated_by_foreign` (`updated_by`),
  KEY `leather_purchases_leather_item_id_purchase_date_index` (`leather_item_id`,`purchase_date`),
  KEY `leather_purchases_purchase_date_index` (`purchase_date`),
  CONSTRAINT `leather_purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leather_purchases_leather_item_id_foreign` FOREIGN KEY (`leather_item_id`) REFERENCES `leather_items` (`id`),
  CONSTRAINT `leather_purchases_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leather_purchases_values_check` CHECK (`qty_sqft` > 0 and `rate_per_sqft` >= 0 and `paid_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leather_purchases`
--

LOCK TABLES `leather_purchases` WRITE;
/*!40000 ALTER TABLE `leather_purchases` DISABLE KEYS */;
/*!40000 ALTER TABLE `leather_purchases` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_10_11_000001_create_users_table',1),(2,'2026_10_11_000002_create_permission_tables',1),(3,'2026_10_11_000003_create_system_tables',1),(4,'2026_10_11_000004_create_customers_table',1),(5,'2026_10_11_000005_create_product_tables',1),(6,'2026_10_11_000006_create_leather_tables',1),(7,'2026_10_11_000007_create_accessory_tables',1),(8,'2026_10_11_000008_create_stock_adjustments_table',1),(9,'2026_10_11_000009_create_expense_category_tables',1),(10,'2026_10_11_000010_create_sales_tables',1),(11,'2026_10_11_000011_create_courier_tables',1),(12,'2026_10_11_000012_create_expenses_table',1),(13,'2026_10_11_000013_create_payroll_tables',1),(14,'2026_10_11_000014_create_framework_tables',1),(15,'2026_10_11_000015_create_pathao_courier_tokens_table',1),(16,'2026_10_11_000016_create_database_views',1),(18,'2026_10_14_000001_create_product_variants_tables',2),(19,'2026_10_15_000001_fix_model_has_roles_model_type',3),(20,'2026_10_15_000002_reconcile_historical_courier_expenses',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',2);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(190) NOT NULL,
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
-- Table structure for table `pathao_courier_tokens`
--

DROP TABLE IF EXISTS `pathao_courier_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pathao_courier_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_in` int(10) unsigned DEFAULT NULL,
  `token_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pathao_courier_tokens`
--

LOCK TABLES `pathao_courier_tokens` WRITE;
/*!40000 ALTER TABLE `pathao_courier_tokens` DISABLE KEYS */;
INSERT INTO `pathao_courier_tokens` VALUES (1,'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI4NzcyMCIsImF1ZCI6WyIxNjQ4Il0sImV4cCI6MTc5OTcwMjIxNiwibmJmIjoxNzkxOTI2MjE2LCJpYXQiOjE3OTE5MjYyMTYsImp0aSI6ImYxYzRjM2VkODQ5Y2Y0NjUwYTMwZDU2OTM2MjE0ZDY3MGZmMmRmYzI3NzQzNjk5MWJlOTYxNDAzYzk0YmM0MGIiLCJtZXJjaGFudF9pZCI6Im9wZW5MUTViN0EiLCJzY29wZXMiOltdfQ.ngw-0DdA9gOFuvwx3jGq238XcstVkM0NeZIB81CPbWL0lrlGZI9f35lT7K5GT4zABc2OL5DSY8UGpQic6Bo_gN5FZ2WMJsOFzcBp5i2ZL_9419jKE74dxTJruG6HBmJ2ZlzwB_KfuVLrz8_RY8dtQ0asYnYEbq00xqUonWpiufcAhVCKAaFkIfombqfhmNzmz6YnkvJJXArxq7kjSG5YGToqitOpi0Ia_WB5EYC12bCtCEDzm9JhTOPxFu6yH9BZKqPwddEMUrKYBx7FFrRLCIBuFrPNNuwOUQsrtUzj2MUy0fUfrDs_zeyJHGuiDMqmyDhcnFiYyn8hZ96C3KzbnM5FjdSxtB7nO4dVz4IREKX8cXtN8a2IDRPNzFL89nn6bs-PZxHGyAifdHdqXp5-_-zFb2NYB71ExV8q2QKaLRlOIHHaKD97Gc-s453rc_rEpMiU2E0RXPTRD5ZJQDar6N3h0ZTfq8ZRnVw3--_He3cDHqshLf_riUAHZRMZbCye82N5PXN21uvju7O537tP3N7dmmXduJLhIxG1W2RHRZJ0j5diVZAHH7gVA4BLFUX2U3sGceLv9WEx6zNtd_L3QwZOBW6UEpYxVQp8w2DSNKZpTc70p1yZbPHQM-vABWJPcd-yoi29pSsDjLwSfPt5KcQBXLWZpW4nsVQQmS8p0uA','def5020044d0e17b59190263c7a8744f0e39dd5ef0392f036ba361cd1bb502de18b36b3e7f8a7a382fde57b8925d00cf6adafcc96887ef1ed03413406e237f5e4acfe756a7e8688161d38b098e19c24a9276cfa20712f14051c6aa1b686bdc2680e2f413823b822f1cb578734c028e90eb3464e613bdcd2a1d8d979af7b219102397f264efa1396cb5ddb202289278dfaee861053f5ccbf723c50736b95de8aac54974f45d6b4ba0534097488b23405c049ab38de3a7de75c8877a723c6290959eaa10efc480462e6c33f0a4fbe5a6a378400be0be2758ad225094fdd24cfd5ee208e461b9de7316532b57f7b29ddac6aa686d53761ab8c8698dea565468e2ac6b5680053f140627e951883ad3b5f57f16d63b757e0b446aa1e4baf258ca078afdbfb6e0f0305653426da8f7a8b61c3e82ce39de46602670746de37ca4ad15dd3a5ea710dacc05eab466575e7880e713983d53142db3d63c4b1208',7776000,'Bearer','2026-10-13 21:16:56','2026-10-13 21:16:56');
/*!40000 ALTER TABLE `pathao_courier_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(125) NOT NULL,
  `guard_name` varchar(125) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(2,'dashboard.view_financials','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(3,'sales.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(4,'sales.create','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(5,'sales.edit_price','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(6,'sales.cancel','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(7,'sales.return','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(8,'sales.add_payment','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(9,'customers.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(10,'customers.update','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(11,'courier.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(12,'courier.retry','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(13,'courier.settle_cod','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(14,'products.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(15,'products.create','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(16,'products.update','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(17,'products.delete','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(18,'products.import','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(19,'leather.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(20,'leather.create','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(21,'leather.update','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(22,'leather.delete','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(23,'accessories.view','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(24,'accessories.create','web','2026-10-10 21:16:42','2026-10-10 21:16:42'),(25,'accessories.update','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(26,'accessories.delete','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(27,'adjustments.view','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(28,'adjustments.create','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(29,'inventory.view_costs','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(30,'expenses.view','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(31,'expenses.create','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(32,'expenses.update','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(33,'expenses.delete','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(34,'payroll.view','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(35,'payroll.create','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(36,'payroll.update','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(37,'payroll.delete','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(38,'reports.sales','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(39,'reports.sales_profit','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(40,'reports.profit_loss','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(41,'settings.manage','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(42,'users.manage','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(43,'roles.manage','web','2026-10-10 21:16:43','2026-10-10 21:16:43'),(44,'inventory.cost.view','web','2026-10-11 17:16:28','2026-10-11 17:16:28');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,'Eyewear Cases','2026-10-13 21:55:54','2026-10-13 21:55:54'),(2,'Accessories','2026-10-14 17:32:30','2026-10-14 17:32:30'),(3,'Long Wallet','2026-10-14 17:32:30','2026-10-14 17:32:30'),(4,'Wallet','2026-10-14 17:32:30','2026-10-14 17:32:30'),(5,'Bag','2026-10-14 17:32:30','2026-10-14 17:32:30'),(6,'Belts','2026-10-14 17:32:30','2026-10-14 17:32:30');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_stock_movements`
--

DROP TABLE IF EXISTS `product_stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` varchar(60) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `movement_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_stock_movements_created_by_foreign` (`created_by`),
  KEY `product_stock_movements_product_id_movement_date_index` (`product_id`,`movement_date`),
  KEY `product_stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `product_stock_movements_product_variant_id_foreign` (`product_variant_id`),
  CONSTRAINT `product_stock_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_stock_movements_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_stock_movements_qty_check` CHECK (`qty` <> 0)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_stock_movements`
--

LOCK TABLES `product_stock_movements` WRITE;
/*!40000 ALTER TABLE `product_stock_movements` DISABLE KEYS */;
INSERT INTO `product_stock_movements` VALUES (1,1,1,'opening',12,12,NULL,NULL,'2026-10-14','Opening stock for Brown variant',1,'2026-10-13 21:55:54'),(2,1,2,'opening',8,8,NULL,NULL,'2026-10-14','Opening stock for Black variant',1,'2026-10-13 21:55:54'),(3,2,3,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(4,3,4,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(5,4,5,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Oil Brown)',1,'2026-10-14 17:32:30'),(6,5,6,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(7,6,7,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(8,7,8,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(9,7,9,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Chocolate)',1,'2026-10-14 17:32:30'),(10,8,10,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(11,9,11,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(12,9,12,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(13,10,13,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(14,10,14,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(15,11,15,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(16,11,16,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(17,12,17,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(18,12,18,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Oil Brown)',1,'2026-10-14 17:32:30'),(19,12,19,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Dark Ash)',1,'2026-10-14 17:32:30'),(20,13,20,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(21,13,21,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(22,13,22,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Red Wine)',1,'2026-10-14 17:32:30'),(23,14,23,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(24,15,24,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(25,15,25,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(26,16,26,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(27,17,27,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Oil Brown)',1,'2026-10-14 17:32:30'),(28,17,28,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Dark Ash)',1,'2026-10-14 17:32:30'),(29,18,29,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(30,18,30,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(31,19,31,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(32,20,32,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(33,20,33,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Chocolate)',1,'2026-10-14 17:32:30'),(34,21,34,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(35,21,35,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(36,22,36,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(37,22,37,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Blue)',1,'2026-10-14 17:32:30'),(38,23,38,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Green)',1,'2026-10-14 17:32:30'),(39,24,39,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(40,24,40,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Oil Brown)',1,'2026-10-14 17:32:30'),(41,25,41,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(42,25,42,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(43,26,43,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(44,26,44,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(45,27,45,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(46,28,46,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Chocolate)',1,'2026-10-14 17:32:30'),(47,29,47,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Oil Brown)',1,'2026-10-14 17:32:30'),(48,30,48,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(49,30,49,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(50,31,50,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(51,32,51,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(52,32,52,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Tan)',1,'2026-10-14 17:32:30'),(53,33,53,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Brown)',1,'2026-10-14 17:32:30'),(54,33,54,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(55,34,55,'opening',1,1,NULL,NULL,'2026-10-14','Opening stock (Black)',1,'2026-10-14 17:32:30'),(56,20,32,'sale',-1,0,'sales',4,'2026-10-14','Sale TASA-2610-0004 (Black)',1,'2026-10-14 17:34:11'),(57,20,32,'sale_cancel',1,1,'sales',4,'2026-10-14','Sale cancel (TASA-2610-0004): aaaaa',1,'2026-10-14 17:47:36'),(58,20,32,'sale',-1,0,'sales',5,'2026-10-15','Sale TASA-2610-0005 (Black)',2,'2026-10-14 19:51:41'),(59,20,33,'sale',-1,0,'sales',6,'2026-10-15','Sale TASA-2610-0006 (Chocolate)',2,'2026-10-14 20:25:37');
/*!40000 ALTER TABLE `product_stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(50) NOT NULL,
  `color_name` varchar(100) DEFAULT NULL,
  `attribute_name` varchar(50) NOT NULL DEFAULT 'Color',
  `attribute_value` varchar(100) NOT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `barcode` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  UNIQUE KEY `product_attribute_unique` (`product_id`,`attribute_value`,`deleted_at`),
  KEY `product_variants_created_by_foreign` (`created_by`),
  KEY `product_variants_updated_by_foreign` (`updated_by`),
  KEY `product_variants_product_id_is_active_index` (`product_id`,`is_active`),
  KEY `product_variants_sku_index` (`sku`),
  KEY `product_variants_color_name_index` (`color_name`),
  KEY `product_variants_attribute_value_index` (`attribute_value`),
  CONSTRAINT `product_variants_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_variants_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_variants_prices_check` CHECK (`cost_price` >= 0 and `selling_price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'EWC-S1-BRN','Brown','Color','Brown',450.00,850.00,12,3,NULL,1,1,1,'2026-10-13 21:55:54','2026-10-13 21:55:54',NULL),(2,1,'EWC-S1-BLK','Black','Color','Black',450.00,850.00,8,3,NULL,1,1,1,'2026-10-13 21:55:54','2026-10-13 21:55:54',NULL),(3,2,'EWCS1 SE-GREEN','Green','Color','Green',0.00,850.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(4,3,'EWCS3-BLACK','Black','Color','Black',0.00,850.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(5,4,'EWCS3-OILBROWN','Oil Brown','Color','Oil Brown',0.00,950.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(6,5,'SOS1-BLACK','Black','Color','Black',0.00,1390.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(7,6,'SOS1 SE-GREEN','Green','Color','Green',0.00,1580.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(8,7,'LWS1-BLACK','Black','Color','Black',0.00,1199.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(9,7,'LWS1-CHOCOLATE','Chocolate','Color','Chocolate',0.00,1199.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(10,8,'LWS2-BLACK','Black','Color','Black',0.00,2280.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(11,9,'LWS5-BLACK','Black','Color','Black',0.00,1390.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(12,9,'LWS5-BROWN','Brown','Color','Brown',0.00,1390.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(13,10,'LWS6-BLACK','Black','Color','Black',0.00,1799.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(14,10,'LWS6-TAN','Tan','Color','Tan',0.00,1799.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(15,11,'LWS7-BLACK','Black','Color','Black',0.00,1599.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(16,11,'LWS7-TAN','Tan','Color','Tan',0.00,1599.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(17,12,'MWS2-BLACK','Black','Color','Black',0.00,790.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(18,12,'MWS2-OILB ROWN','Oil Brown','Color','Oil Brown',0.00,790.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(19,12,'MWS2-DARK ASH','Dark Ash','Color','Dark Ash',0.00,790.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(20,13,'MWS3-BLACK','Black','Color','Black',0.00,490.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(21,13,'MWS3-TAN','Tan','Color','Tan',0.00,490.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(22,13,'MWS2-REDWINE','Red Wine','Color','Red Wine',0.00,490.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(23,14,'MWS3 SE-GREEN','Green','Color','Green',0.00,590.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(24,15,'PHS1-BROWN','Brown','Color','Brown',0.00,999.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(25,15,'PHS1-BLACK','Black','Color','Black',0.00,999.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(26,16,'PHSSE1-GREEN','Green','Color','Green',0.00,999.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(27,17,'PHS1-OIL BROWN','Oil Brown','Color','Oil Brown',0.00,950.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(28,17,'PHS1-DARK ASH','Dark Ash','Color','Dark Ash',0.00,950.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(29,18,'MBWS1-BLACK','Black','Color','Black',0.00,1299.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(30,18,'MNWS1-BROWN','Brown','Color','Brown',0.00,1390.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(31,19,'MNWS1SE-GREEN','Green','Color','Green',0.00,1390.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(32,20,'A4FBS2-BLACK','Black','Color','Black',0.00,3399.00,0,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 19:51:41',NULL),(33,20,'A4FBS2-CHOCOLATE','Chocolate','Color','Chocolate',0.00,3399.00,0,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 20:25:37',NULL),(34,21,'A4FBS3-BLACK','Black','Color','Black',0.00,2999.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(35,21,'A4FBS3-BROWN','Brown','Color','Brown',0.00,2950.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(36,22,'SBS1-BLACK','Black','Color','Black',0.00,2699.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(37,22,'SBS1-BLUE','Blue','Color','Blue',0.00,2699.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(38,23,'SBS1 SE-GREEN','Green','Color','Green',0.00,2699.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(39,24,'MBS1-BLACK','Black','Color','Black',0.00,3099.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(40,24,'MBS1-OIL BROWN','Oil Brown','Color','Oil Brown',0.00,3099.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(41,25,'MPS1-BLACK','Black','Color','Black',0.00,699.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(42,25,'MPS1-TAN','Tan','Color','Tan',0.00,699.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(43,26,'MPS2-BLACK','Black','Color','Black',0.00,899.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(44,26,'MPS2-TAN','Tan','Color','Tan',0.00,899.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(45,27,'MPS3-BLACK','Black','Color','Black',0.00,420.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(46,28,'VSS1-CHOCOLATE','Chocolate','Color','Chocolate',0.00,7999.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(47,29,'VSS3-OIL BROWN','Oil Brown','Color','Oil Brown',0.00,9900.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(48,30,'BPS1-BLACK','Black','Color','Black',0.00,7800.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(49,30,'BPS1-BROWN','Brown','Color','Brown',0.00,7800.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(50,31,'BSS2-BLACK','Black','Color','Black',0.00,18000.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(51,32,'FLS1-BLACK','Black','Color','Black',0.00,7199.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(52,32,'FLS1-TAN','Tan','Color','Tan',0.00,7199.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(53,33,'FWBS1-BROWN','Brown','Color','Brown',0.00,2250.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(54,33,'FWBS1-BLACK','Black','Color','Black',0.00,2250.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(55,34,'BSS5-BLACK','Black','Color','Black',0.00,10500.00,1,1,NULL,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL);
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `has_variants` tinyint(1) NOT NULL DEFAULT 0,
  `variation_type` varchar(50) DEFAULT 'Color',
  `sku` varchar(50) NOT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_created_by_foreign` (`created_by`),
  KEY `products_updated_by_foreign` (`updated_by`),
  KEY `products_name_index` (`name`),
  KEY `products_product_category_id_index` (`product_category_id`),
  KEY `products_is_active_index` (`is_active`),
  KEY `products_has_variants_index` (`has_variants`),
  CONSTRAINT `products_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_prices_check` CHECK (`cost_price` >= 0 and `selling_price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'Eyewear Case Series 1',1,'Color','EWC-S1',450.00,850.00,20,5,1,1,1,'2026-10-13 21:55:54','2026-10-13 21:55:54',NULL),(2,2,'Eyewear Case Series 1 SE',1,'Color','P-000001',0.00,850.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(3,2,'Eyewear Case Series 3',1,'Color','P-000002',0.00,850.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(4,2,'Eyewear Case Series 3 Raw',1,'Color','P-000003',0.00,950.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(5,2,'Smart Organizer Series 1',1,'Color','P-000004',0.00,1390.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(6,2,'Smart Organizer Series 1 SE',1,'Color','P-000005',0.00,1580.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(7,3,'Long Wallet Series 1',1,'Color','P-000006',0.00,1199.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(8,3,'Long Wallet Series 2',1,'Color','P-000007',0.00,2280.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(9,3,'Long Wallet Series 5',1,'Color','P-000008',0.00,1390.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(10,3,'Long Wallet Series 6',1,'Color','P-000009',0.00,1799.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(11,3,'Long Wallet Series 7',1,'Color','P-000010',0.00,1599.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(12,4,'Minimalist Wallet Series 2',1,'Color','P-000011',0.00,790.00,3,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(13,4,'Minimalist Wallet Series 3',1,'Color','P-000012',0.00,490.00,3,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(14,4,'Minimalist Wallet Series 3 SE',1,'Color','P-000013',0.00,590.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(15,2,'Passport Holder Series 1',1,'Color','P-000014',0.00,999.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(16,2,'Passport Holder Series 1 SE',1,'Color','P-000015',0.00,999.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(17,2,'Passport Holder Series 2',1,'Color','P-000016',0.00,950.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(18,4,'Mulberry Bifold Wallet Series 1',1,'Color','P-000017',0.00,1299.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(19,4,'Mulberry Bifold Wallet Series 1 SE',1,'Color','P-000018',0.00,1390.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(20,5,'A4 File Bag Series 2',1,'Color','P-000019',0.00,3399.00,0,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 20:25:37',NULL),(21,5,'A4 File Bag Series 3',1,'Color','P-000020',0.00,2999.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(22,5,'Shoulder Bag Series 1',1,'Color','P-000021',0.00,2699.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(23,5,'Shoulder Bag Series 1 SE',1,'Color','P-000022',0.00,2699.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(24,5,'Messenger Bag Series 1',1,'Color','P-000023',0.00,3099.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(25,2,'Mouse Pad Series 1',1,'Color','P-000024',0.00,699.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(26,2,'Mouse Pad Series 2',1,'Color','P-000025',0.00,899.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(27,2,'Mouse Pad Series 3',1,'Color','P-000026',0.00,420.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(28,5,'Vintage Satchel Bag Series 1',1,'Color','P-000027',0.00,7999.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(29,5,'Vintage Satchel Bag Series 3',1,'Color','P-000028',0.00,9900.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(30,5,'Backpack Series 1',1,'Color','P-000029',0.00,7800.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(31,5,'Briefcase Series 2',1,'Color','P-000030',0.00,18000.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(32,5,'Folio Laptop Bag Series 1',1,'Color','P-000031',0.00,7199.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(33,6,'Folk Waist Belt Series 1',1,'Color','P-000032',0.00,2250.00,2,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL),(34,5,'Briefcase Series 5',1,'Color','P-000033',0.00,10500.00,1,1,1,1,1,'2026-10-14 17:32:30','2026-10-14 17:32:30',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurring_expenses`
--

DROP TABLE IF EXISTS `recurring_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_category_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT NULL,
  `day_of_month` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_confirmed_period` char(7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_expenses_expense_category_id_foreign` (`expense_category_id`),
  CONSTRAINT `recurring_expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurring_expenses`
--

LOCK TABLES `recurring_expenses` WRITE;
/*!40000 ALTER TABLE `recurring_expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurring_expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(2,1),(3,1),(3,2),(4,1),(4,2),(5,1),(5,2),(6,1),(6,2),(7,1),(7,2),(8,1),(8,2),(9,1),(9,2),(10,1),(10,2),(11,1),(11,2),(12,1),(12,2),(13,1),(13,2),(14,1),(14,2),(15,1),(15,2),(16,1),(16,2),(17,1),(18,1),(18,2),(19,1),(19,2),(20,1),(20,2),(21,1),(21,2),(22,1),(23,1),(23,2),(24,1),(24,2),(25,1),(25,2),(26,1),(27,1),(27,2),(28,1),(28,2),(29,1),(29,2),(30,1),(30,2),(31,1),(31,2),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(38,2),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(125) NOT NULL,
  `guard_name` varchar(125) NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Owner','web',1,'Full access to all modules, financial metrics, and settings.','2026-10-10 21:16:43','2026-10-10 21:16:43'),(2,'Staff','web',0,'Operational access to POS, invoices, inventory, and expense logging.','2026-10-10 21:16:43','2026-10-10 21:16:43');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_months`
--

DROP TABLE IF EXISTS `salary_months`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_months` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `period` date NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `bonus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payable` decimal(12,2) GENERATED ALWAYS AS (`base_salary` + `bonus` - `deduction`) STORED,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_months_employee_id_period_unique` (`employee_id`,`period`),
  KEY `salary_months_created_by_foreign` (`created_by`),
  KEY `salary_months_updated_by_foreign` (`updated_by`),
  KEY `salary_months_period_index` (`period`),
  CONSTRAINT `salary_months_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_months_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `salary_months_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_months`
--

LOCK TABLES `salary_months` WRITE;
/*!40000 ALTER TABLE `salary_months` DISABLE KEYS */;
INSERT INTO `salary_months` VALUES (1,1,'2026-10-01',35000.00,0.00,0.00,35000.00,NULL,1,NULL,'2026-10-14 18:27:59','2026-10-14 18:27:59');
/*!40000 ALTER TABLE `salary_months` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_payments`
--

DROP TABLE IF EXISTS `salary_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `period` date NOT NULL,
  `paid_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'salary',
  `method` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_payments_created_by_foreign` (`created_by`),
  KEY `salary_payments_employee_id_period_index` (`employee_id`,`period`),
  KEY `salary_payments_paid_on_index` (`paid_on`),
  CONSTRAINT `salary_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `salary_payments_amount_check` CHECK (`amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_payments`
--

LOCK TABLES `salary_payments` WRITE;
/*!40000 ALTER TABLE `salary_payments` DISABLE KEYS */;
INSERT INTO `salary_payments` VALUES (1,1,'2026-10-01','2026-10-14',15000.00,'salary','cash','Mid-month salary',1,'2026-10-14 18:37:48','2026-10-14 18:37:48');
/*!40000 ALTER TABLE `salary_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(190) NOT NULL,
  `variant_name` varchar(100) DEFAULT NULL,
  `variant_sku` varchar(50) DEFAULT NULL,
  `qty` int(10) unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL,
  `returned_qty` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_id_index` (`sale_id`),
  KEY `sale_items_product_id_index` (`product_id`),
  KEY `sale_items_product_variant_id_index` (`product_variant_id`),
  CONSTRAINT `sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_qty_check` CHECK (`qty` > 0 and `returned_qty` <= `qty`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,NULL,NULL,1,'abc',NULL,NULL,1,1000.00,0.00,1000.00,0),(2,2,NULL,NULL,1,'safds',NULL,NULL,1,10000.00,0.00,10000.00,0),(3,3,NULL,NULL,1,'asd',NULL,NULL,1,1223.00,0.00,1223.00,0),(4,4,20,32,0,'A4 File Bag Series 2 — Black','Black','A4FBS2-BLACK',1,3399.00,0.00,3399.00,0),(5,5,20,32,0,'A4 File Bag Series 2 — Black','Black','A4FBS2-BLACK',1,3399.00,0.00,3399.00,0),(6,6,20,33,0,'A4 File Bag Series 2 — Chocolate','Chocolate','A4FBS2-CHOCOLATE',1,3399.00,0.00,3399.00,0);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_payments`
--

DROP TABLE IF EXISTS `sale_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `paid_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `courier_order_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_payments_created_by_foreign` (`created_by`),
  KEY `sale_payments_sale_id_index` (`sale_id`),
  KEY `sale_payments_paid_on_index` (`paid_on`),
  KEY `sale_payments_courier_order_id_foreign` (`courier_order_id`),
  CONSTRAINT `sale_payments_courier_order_id_foreign` FOREIGN KEY (`courier_order_id`) REFERENCES `courier_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_payments_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `sale_payments_amount_check` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_payments`
--

LOCK TABLES `sale_payments` WRITE;
/*!40000 ALTER TABLE `sale_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_return_items`
--

DROP TABLE IF EXISTS `sale_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_return_id` bigint(20) unsigned NOT NULL,
  `sale_item_id` bigint(20) unsigned NOT NULL,
  `qty` int(10) unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_return_items_sale_return_id_foreign` (`sale_return_id`),
  KEY `sale_return_items_sale_item_id_foreign` (`sale_item_id`),
  CONSTRAINT `sale_return_items_sale_item_id_foreign` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`),
  CONSTRAINT `sale_return_items_sale_return_id_foreign` FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_return_items`
--

LOCK TABLES `sale_return_items` WRITE;
/*!40000 ALTER TABLE `sale_return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_returns`
--

DROP TABLE IF EXISTS `sale_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `returned_on` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `return_value` decimal(12,2) NOT NULL,
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `restock` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_returns_created_by_foreign` (`created_by`),
  KEY `sale_returns_sale_id_index` (`sale_id`),
  KEY `sale_returns_returned_on_index` (`returned_on`),
  CONSTRAINT `sale_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_returns_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_returns`
--

LOCK TABLES `sale_returns` WRITE;
/*!40000 ALTER TABLE `sale_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(20) NOT NULL,
  `idempotency_token` char(36) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `customer_phone` char(11) NOT NULL,
  `customer_address` varchar(500) NOT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  `zone_id` int(10) unsigned DEFAULT NULL,
  `area_id` int(10) unsigned DEFAULT NULL,
  `city_name` varchar(100) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `area_name` varchar(100) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `sold_at` datetime NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `discount_type` varchar(10) NOT NULL DEFAULT 'flat',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL,
  `paid_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) GENERATED ALWAYS AS (`grand_total` - `paid_total`) STORED,
  `payment_status` varchar(10) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `courier_mode` varchar(10) NOT NULL DEFAULT 'pathao',
  `notes` varchar(500) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoice_no_unique` (`invoice_no`),
  UNIQUE KEY `sales_idempotency_token_unique` (`idempotency_token`),
  KEY `sales_cancelled_by_foreign` (`cancelled_by`),
  KEY `sales_created_by_foreign` (`created_by`),
  KEY `sales_updated_by_foreign` (`updated_by`),
  KEY `sales_sale_date_index` (`sale_date`),
  KEY `sales_customer_id_index` (`customer_id`),
  KEY `sales_status_sale_date_index` (`status`,`sale_date`),
  KEY `sales_payment_status_index` (`payment_status`),
  KEY `sales_customer_phone_index` (`customer_phone`),
  CONSTRAINT `sales_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `sales_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_totals_check` CHECK (`grand_total` >= 0 and `paid_total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'TASA-2610-0001','7df0f47d-750b-4cfe-aad4-4d447a92b08e',1,'Alter Reality','01845700011','CHITTAGONG',2,201,20103,'Chittagong','Agrabad','Main Town Area','2026-10-14','2026-10-14 03:25:34',1000.00,'flat',0.00,0.00,0.00,1000.00,0.00,1000.00,'unpaid','active','pathao',NULL,NULL,NULL,NULL,1,1,'2026-10-13 21:25:34','2026-10-13 21:25:34'),(2,'TASA-2610-0002','97d5c374-f7ab-4b08-b40e-e092cfa74195',1,'Alter Reality','01845700011','CHITTAGONG',2,202,20202,'Chittagong','GEC Circle','Sector / Area 2','2026-10-14','2026-10-14 03:33:19',10000.00,'flat',0.00,0.00,0.00,10000.00,0.00,10000.00,'unpaid','active','pathao',NULL,NULL,NULL,NULL,1,1,'2026-10-13 21:33:19','2026-10-13 21:33:19'),(3,'TASA-2610-0003','5e5d0fb1-50e3-4e25-a023-59246078bfe5',1,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,202,20202,'Chittagong','GEC Circle','Sector / Area 2','2026-10-14','2026-10-14 03:39:59',1223.00,'flat',0.00,0.00,0.00,1223.00,0.00,1223.00,'unpaid','active','pathao',NULL,NULL,NULL,NULL,1,1,'2026-10-13 21:39:59','2026-10-13 21:39:59'),(4,'TASA-2610-0004','b176d3ee-cacf-49b9-ad18-cb9fc1460015',1,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,202,20202,'Chittagong','GEC Circle','Sector / Area 2','2026-10-14','2026-10-14 23:34:11',3399.00,'flat',0.00,0.00,0.00,3399.00,0.00,3399.00,'unpaid','cancelled','pathao',NULL,'aaaaa','2026-10-14 23:47:36',1,1,1,'2026-10-14 17:34:11','2026-10-14 17:47:36'),(5,'TASA-2610-0005','765f7f7e-72d7-4c09-aef2-eaa65685896a',1,'Alter Reality','01845700011','1 no panirkol, halishahar, chittagong',2,365,19809,'Chittagong','Sadarghat (Chattogram)','Kadam Tali','2026-10-15','2026-10-15 01:51:41',3399.00,'flat',0.00,0.00,100.00,3499.00,0.00,3499.00,'unpaid','active','pathao',NULL,NULL,NULL,NULL,2,2,'2026-10-14 19:51:41','2026-10-14 19:51:41'),(6,'TASA-2610-0006','7a0d96b9-0d1b-4978-bde0-fa04cb04a12c',2,'Test Customer','01712345678','123 Test Street, Test Area, Dhaka',52,156,13193,'Bagerhat','Bagerhat Sadar','Adarsh school','2026-10-15','2026-10-15 02:25:37',3399.00,'flat',0.00,0.00,0.00,3399.00,0.00,3399.00,'unpaid','active','pathao',NULL,NULL,NULL,NULL,2,2,'2026-10-14 20:25:37','2026-10-14 20:25:37');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
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
INSERT INTO `sessions` VALUES ('kU0VRRV6GxUkrSUDfIDoBiMjWT1NDUihR3MBhrpC',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJsTWZjVmhHdzB3MXJkSVJUZ21JY0ZaeGZQaTJoNVdkU2ZoN3FGNGxzIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJ1cmwiOltdLCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2Rhc2hib2FyZCIsInJvdXRlIjoiZGFzaGJvYXJkIn0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxLCJwYXNzd29yZF9oYXNoX3dlYiI6IjcyYTFmODQyNmU3NzMzNjE1OWY5YTBiOTUzYTA2ZWM0ZGQxYTVlZmU0NmU1YTQyYjQ2MTU5Yjg2MjJjY2FjNzQifQ==',1792010715),('vsl92zd3Yq8RMEUQ1Ja9ynW5AEn9MzbnnlZPZryk',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJkcU1ETmNOTm5sdTg3TnFBeklCcUZUamR3WkxLa0VuWm9QQzdCSmFwIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MiwicGFzc3dvcmRfaGFzaF93ZWIiOiI0MTEyMWRhMmEwNWQ5ZGUwMjA0MjZmZjU2MmY5NzU0OWYxN2M1MDFiZjI5ODdmNWNjODk2OWIzZDg2NjZlYmUyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9kYXNoYm9hcmQiLCJyb3V0ZSI6ImRhc2hib2FyZCJ9fQ==',1792010347);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(50) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_group_key_unique` (`group`,`key`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'general','shop_name','Tasa Bangladesh','2026-10-10 21:16:43','2026-10-14 18:59:40'),(2,'general','address','Dhaka, Bangladesh','2026-10-10 21:16:43','2026-10-14 18:49:17'),(3,'general','phone','+880 1332-679646','2026-10-10 21:16:43','2026-10-14 18:59:40'),(4,'general','logo','/storage/branding/o9V5zJNNDpf80BVERPdTM4F1HcHLZ7UchGXfeJ6G.png','2026-10-10 21:16:43','2026-10-14 18:58:48'),(5,'general','currency_symbol','৳','2026-10-10 21:16:43','2026-10-10 21:16:43'),(6,'invoice','prefix','TASA','2026-10-10 21:16:43','2026-10-10 21:16:43'),(7,'invoice','paper_size','A4','2026-10-10 21:16:43','2026-10-10 21:16:43'),(8,'invoice','footer_note','Thank you for shopping with Tasa Leather Goods!','2026-10-10 21:16:43','2026-10-14 18:49:17'),(9,'invoice','terms','Exchange within 7 days with invoice. No cash refunds.','2026-10-10 21:16:43','2026-10-14 18:49:17'),(10,'invoice','custom_template','','2026-10-10 21:16:43','2026-10-14 18:49:17'),(11,'inventory','allow_negative_stock','0','2026-10-10 21:16:43','2026-10-10 21:16:43'),(12,'payroll','cost_price_includes_labour','0','2026-10-10 21:16:43','2026-10-10 21:16:43'),(13,'pathao','store_id','67938','2026-10-10 21:16:43','2026-10-13 21:32:24'),(14,'pathao','sender_name','Tasa','2026-10-10 21:16:43','2026-10-13 21:32:24'),(15,'pathao','sender_phone','01332679646','2026-10-10 21:16:43','2026-10-13 21:32:24'),(16,'pathao','default_delivery_type','48','2026-10-10 21:16:43','2026-10-10 21:16:43'),(17,'pathao','default_item_type','2','2026-10-10 21:16:43','2026-10-10 21:16:43'),(18,'pathao','default_weight','0.5','2026-10-10 21:16:43','2026-10-10 21:16:43'),(19,'pathao','show_success_rate','1','2026-10-10 21:16:43','2026-10-10 21:16:43'),(20,'invoice','selectable_fields','{\"show_logo\":\"1\",\"show_shop_address\":\"1\",\"show_shop_phone\":\"1\",\"show_discount\":\"1\",\"show_delivery_charge\":\"1\",\"show_consignment_id\":\"1\",\"show_payment_methods\":\"1\",\"show_footer_note\":\"1\",\"show_terms\":\"1\"}','2026-10-14 18:49:17','2026-10-14 18:49:17'),(21,'sales','allow_price_edit','1','2026-10-14 18:59:40','2026-10-14 18:59:40');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_type` varchar(20) NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL,
  `reason` varchar(30) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `adjusted_on` date NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `stock_adjustments_created_by_foreign` (`created_by`),
  KEY `stock_adjustments_item_type_item_id_adjusted_on_index` (`item_type`,`item_id`,`adjusted_on`),
  KEY `stock_adjustments_product_variant_id_index` (`product_variant_id`),
  CONSTRAINT `stock_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_adjustments_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Tasa Owner','owner@tasa.com','01700000000','2026-10-10 21:16:43','$2y$12$zBnCwGEeE.cNFvyA24SDZeXXruuLi9sVW9x/Zpb3YdxlEz18wicXS',NULL,NULL,NULL,1,'2026-10-15 02:44:44',NULL,'2026-10-10 21:16:43','2026-10-14 20:44:44',NULL),(2,'Tasa Staff','staff@tasa.com','01711111111','2026-10-13 20:15:14','$2y$12$iczJC2JqKMZTczJZTMVtYurXRGoZ966fdU3okFhQVRj.lXXtJuwbG',NULL,NULL,NULL,1,'2026-10-15 02:35:32',NULL,'2026-10-13 20:15:14','2026-10-14 20:35:32',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_accessory_stock`
--

DROP TABLE IF EXISTS `v_accessory_stock`;
/*!50001 DROP VIEW IF EXISTS `v_accessory_stock`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_accessory_stock` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `unit`,
  1 AS `min_stock`,
  1 AS `purchased`,
  1 AS `consumed`,
  1 AS `adjusted`,
  1 AS `current_stock`,
  1 AS `avg_cost`,
  1 AS `stock_value` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_leather_stock`
--

DROP TABLE IF EXISTS `v_leather_stock`;
/*!50001 DROP VIEW IF EXISTS `v_leather_stock`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_leather_stock` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `colour_grade`,
  1 AS `min_stock`,
  1 AS `purchased`,
  1 AS `consumed`,
  1 AS `adjusted`,
  1 AS `current_stock`,
  1 AS `avg_cost`,
  1 AS `stock_value` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `worker_earnings`
--

DROP TABLE IF EXISTS `worker_earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_earnings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `worker_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `period_start` date DEFAULT NULL,
  `qty` decimal(8,2) NOT NULL DEFAULT 1.00,
  `rate` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `worker_earnings_created_by_foreign` (`created_by`),
  KEY `worker_earnings_updated_by_foreign` (`updated_by`),
  KEY `worker_earnings_worker_id_work_date_index` (`worker_id`,`work_date`),
  KEY `worker_earnings_work_date_index` (`work_date`),
  CONSTRAINT `worker_earnings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worker_earnings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worker_earnings_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`),
  CONSTRAINT `worker_earnings_values_check` CHECK (`qty` > 0 and `amount` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_earnings`
--

LOCK TABLES `worker_earnings` WRITE;
/*!40000 ALTER TABLE `worker_earnings` DISABLE KEYS */;
INSERT INTO `worker_earnings` VALUES (1,1,'2026-10-14',NULL,10.01,150.00,1501.50,'10 pieces finished',1,NULL,'2026-10-14 18:34:39','2026-10-14 18:34:39');
/*!40000 ALTER TABLE `worker_earnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_payments`
--

DROP TABLE IF EXISTS `worker_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `worker_id` bigint(20) unsigned NOT NULL,
  `paid_on` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `worker_payments_created_by_foreign` (`created_by`),
  KEY `worker_payments_worker_id_paid_on_index` (`worker_id`,`paid_on`),
  CONSTRAINT `worker_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worker_payments_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`),
  CONSTRAINT `worker_payments_amount_check` CHECK (`amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_payments`
--

LOCK TABLES `worker_payments` WRITE;
/*!40000 ALTER TABLE `worker_payments` DISABLE KEYS */;
INSERT INTO `worker_payments` VALUES (1,1,'2026-10-14',501.50,'cash','Partial payout',1,'2026-10-14 18:36:58','2026-10-14 18:36:58');
/*!40000 ALTER TABLE `worker_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workers`
--

DROP TABLE IF EXISTS `workers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `wage_basis` varchar(15) NOT NULL,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workers`
--

LOCK TABLES `workers` WRITE;
/*!40000 ALTER TABLE `workers` DISABLE KEYS */;
INSERT INTO `workers` VALUES (1,'Kashem Mia','01811223344','Master Pattern Cutter','per_piece',150.00,1,'2026-10-14 18:30:59','2026-10-14 18:30:59',NULL);
/*!40000 ALTER TABLE `workers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `v_accessory_stock`
--

/*!50001 DROP VIEW IF EXISTS `v_accessory_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_accessory_stock` AS select `ai`.`id` AS `id`,`ai`.`name` AS `name`,`ai`.`unit` AS `unit`,`ai`.`min_stock` AS `min_stock`,coalesce(`p`.`qty`,0) AS `purchased`,coalesce(`c`.`qty`,0) AS `consumed`,coalesce(`a`.`qty`,0) AS `adjusted`,coalesce(`p`.`qty`,0) - coalesce(`c`.`qty`,0) + coalesce(`a`.`qty`,0) AS `current_stock`,case when coalesce(`p`.`qty`,0) = 0 then 0 else coalesce(`p`.`cost`,0) / `p`.`qty` end AS `avg_cost`,(coalesce(`p`.`qty`,0) - coalesce(`c`.`qty`,0) + coalesce(`a`.`qty`,0)) * case when coalesce(`p`.`qty`,0) = 0 then 0 else `p`.`cost` / `p`.`qty` end AS `stock_value` from (((`accessory_items` `ai` left join (select `accessory_purchases`.`accessory_item_id` AS `accessory_item_id`,sum(`accessory_purchases`.`qty`) AS `qty`,sum(`accessory_purchases`.`total_cost`) AS `cost` from `accessory_purchases` where `accessory_purchases`.`deleted_at` is null group by `accessory_purchases`.`accessory_item_id`) `p` on(`p`.`accessory_item_id` = `ai`.`id`)) left join (select `accessory_consumptions`.`accessory_item_id` AS `accessory_item_id`,sum(`accessory_consumptions`.`qty`) AS `qty` from `accessory_consumptions` where `accessory_consumptions`.`deleted_at` is null group by `accessory_consumptions`.`accessory_item_id`) `c` on(`c`.`accessory_item_id` = `ai`.`id`)) left join (select `stock_adjustments`.`item_id` AS `item_id`,sum(`stock_adjustments`.`qty`) AS `qty` from `stock_adjustments` where `stock_adjustments`.`item_type` = 'accessory' group by `stock_adjustments`.`item_id`) `a` on(`a`.`item_id` = `ai`.`id`)) where `ai`.`deleted_at` is null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_leather_stock`
--

/*!50001 DROP VIEW IF EXISTS `v_leather_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_leather_stock` AS select `li`.`id` AS `id`,`li`.`name` AS `name`,`li`.`colour_grade` AS `colour_grade`,`li`.`min_stock` AS `min_stock`,coalesce(`p`.`qty`,0) AS `purchased`,coalesce(`c`.`qty`,0) AS `consumed`,coalesce(`a`.`qty`,0) AS `adjusted`,coalesce(`p`.`qty`,0) - coalesce(`c`.`qty`,0) + coalesce(`a`.`qty`,0) AS `current_stock`,case when coalesce(`p`.`qty`,0) = 0 then 0 else coalesce(`p`.`cost`,0) / `p`.`qty` end AS `avg_cost`,(coalesce(`p`.`qty`,0) - coalesce(`c`.`qty`,0) + coalesce(`a`.`qty`,0)) * case when coalesce(`p`.`qty`,0) = 0 then 0 else `p`.`cost` / `p`.`qty` end AS `stock_value` from (((`leather_items` `li` left join (select `leather_purchases`.`leather_item_id` AS `leather_item_id`,sum(`leather_purchases`.`qty_sqft`) AS `qty`,sum(`leather_purchases`.`total_cost`) AS `cost` from `leather_purchases` where `leather_purchases`.`deleted_at` is null group by `leather_purchases`.`leather_item_id`) `p` on(`p`.`leather_item_id` = `li`.`id`)) left join (select `leather_consumptions`.`leather_item_id` AS `leather_item_id`,sum(`leather_consumptions`.`qty_sqft`) AS `qty` from `leather_consumptions` where `leather_consumptions`.`deleted_at` is null group by `leather_consumptions`.`leather_item_id`) `c` on(`c`.`leather_item_id` = `li`.`id`)) left join (select `stock_adjustments`.`item_id` AS `item_id`,sum(`stock_adjustments`.`qty`) AS `qty` from `stock_adjustments` where `stock_adjustments`.`item_type` = 'leather' group by `stock_adjustments`.`item_id`) `a` on(`a`.`item_id` = `li`.`id`)) where `li`.`deleted_at` is null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-15  2:47:54
