-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 28, 2025 at 11:23 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bnvrbjvx_goldaccsrv`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(7, 'مدیر اصلی', 'erfansh8160@gmail.com', '$2y$12$o6rXY6CzhbD8AZ4AI4LNAOkvyb2QCTyl/Aid8bZyu/D8QvbQsZfRW', 'superadmin', '2025-05-03 17:22:04', '2025-05-03 17:22:04');

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restored_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('rayan_tla_cache_activation_challenge_5ad19487b0632110cfe10f201a75b40b', 'a:7:{s:12:\"server_nonce\";s:32:\"60c86f0f1e8c1384242b887a85ec59ab\";s:4:\"salt\";s:64:\"f3df0154a19dd6415b10c42d8d9cc1af9edbd7746401c2bf4a6e1048d90c106e\";s:11:\"hardware_id\";s:64:\"b457cb2e3461725926906a2af3e8f65927cefc3c9984b26cbd6585ce63cbdaee\";s:6:\"domain\";s:16:\"http://localhost\";s:2:\"ip\";s:3:\"::1\";s:6:\"ray_id\";s:32:\"bde8c97f1db1b46b0ff246a291efbac3\";s:10:\"created_at\";i:1748456946;}', 1748457246),
('rayan_tla_cache_api_nonce_009e7667e981f4cc082ce15d778304a9', 'b:1;', 1746823319),
('rayan_tla_cache_api_nonce_4_24c2734b709cea6d743cd8ebbc83c4bf', 'b:1;', 1748354485),
('rayan_tla_cache_api_nonce_4_29a534fed491ba921a940774dc8d6b7a', 'b:1;', 1746916649),
('rayan_tla_cache_api_nonce_4_32c9c04cfe89842213d58ad667ffb1ca', 'b:1;', 1746914384),
('rayan_tla_cache_api_nonce_4_3f02227abbdf82f6c479d347ce463570', 'b:1;', 1748351403),
('rayan_tla_cache_api_nonce_4_401fe2f0ce4b97ffaf3df0ae167f7c14', 'b:1;', 1748352978),
('rayan_tla_cache_api_nonce_4_44cfd41ed5e47542373fa85009c50539', 'b:1;', 1746916615),
('rayan_tla_cache_api_nonce_4_455421ae0641e758e7b65de43dc923e5', 'b:1;', 1748353806),
('rayan_tla_cache_api_nonce_4_53c3b3384a5fed454fb92c6757a660e7', 'b:1;', 1748352787),
('rayan_tla_cache_api_nonce_4_591c9bf357355fa661a172a2ce1dff6e', 'b:1;', 1748354261),
('rayan_tla_cache_api_nonce_4_61c7da073e8ab93ae109fa76c5912bdd', 'b:1;', 1748356208),
('rayan_tla_cache_api_nonce_4_6bc9d0bfb4e95c0d8ca672183a695ba5', 'b:1;', 1748354262),
('rayan_tla_cache_api_nonce_4_7d2c6e40172fcce67d549ed1e895e58c', 'b:1;', 1747720769),
('rayan_tla_cache_api_nonce_4_8379ba8d69c72b18bbdef68fbd8b0441', 'b:1;', 1748351437),
('rayan_tla_cache_api_nonce_4_83ad7fb125ead9b85cd668dbfd2147a3', 'b:1;', 1746915497),
('rayan_tla_cache_api_nonce_4_96901fa0d6ca54d7408cfdcd9235fc01', 'b:1;', 1748353807),
('rayan_tla_cache_api_nonce_4_9a87552d137d266cbac4533e6cc0338e', 'b:1;', 1746916745),
('rayan_tla_cache_api_nonce_4_a179e49e215b81cf7a2daa04e34b5a2d', 'b:1;', 1748352053),
('rayan_tla_cache_api_nonce_4_abeffbb11273b5bed87d212f4fb88ee5', 'b:1;', 1748354057),
('rayan_tla_cache_api_nonce_4_ad87fc4a878a441b39d593a1dff1ac00', 'b:1;', 1748351957),
('rayan_tla_cache_api_nonce_4_aeb57c7241ad8edad68510f241ca3103', 'b:1;', 1748356432),
('rayan_tla_cache_api_nonce_4_c3a17481304e6b50eab09e8ca5934992', 'b:1;', 1748354056),
('rayan_tla_cache_api_nonce_4_c61e63bf169104b72e89c49eb4c7d3dc', 'b:1;', 1748351719),
('rayan_tla_cache_api_nonce_4_ca5660137659cdd1acd2a008a53f75ea', 'b:1;', 1748354246),
('rayan_tla_cache_api_nonce_4_cba9a641e42d99b0d10fc6b98d2b392c', 'b:1;', 1748351578),
('rayan_tla_cache_api_nonce_4_d068307533a4fb1821b29fcb3bbbf851', 'b:1;', 1748353763),
('rayan_tla_cache_api_nonce_4_d0d2426c3267bd102d84c4cfacc09997', 'b:1;', 1748353379),
('rayan_tla_cache_api_nonce_4_d230bf4151d5b63cec01cd8f48433fa1', 'b:1;', 1748353763),
('rayan_tla_cache_api_nonce_4_dd3781633d1b32e02da99f5da5b40228', 'b:1;', 1748353379),
('rayan_tla_cache_api_nonce_4_e3f73c20da7cc9abe6987a40a1471671', 'b:1;', 1748353868),
('rayan_tla_cache_api_nonce_4_f6d6d6a28a093767a644c434c03a208b', 'b:1;', 1748353867),
('rayan_tla_cache_api_nonce_4_f7e60834a2fe396b1d089b2f92cc8ae5', 'b:1;', 1748354245),
('rayan_tla_cache_api_nonce_5d052a4d1a1cc3bfd9f9e3f4681246e8', 'b:1;', 1746819055),
('rayan_tla_cache_api_nonce_5e0fcc757aa01f4ff2203f389bbb67cf', 'b:1;', 1746818053),
('rayan_tla_cache_api_nonce_6_0f32469f3b7089f0132b0ba00b0bfdd5', 'b:1;', 1748407810),
('rayan_tla_cache_api_nonce_6_3dc18ed1310bb37b1d9daa4e8dcfa6ff', 'b:1;', 1748370365),
('rayan_tla_cache_api_nonce_6_4031aa8cb8ba6a48dc8e7c9b2efe9d07', 'b:1;', 1748371928),
('rayan_tla_cache_api_nonce_6_5731842fcc72d59ddd76386ad3aa4d1f', 'b:1;', 1748407817),
('rayan_tla_cache_api_nonce_6_676f2b8081a310e3f96f0a45548512dc', 'b:1;', 1748408316),
('rayan_tla_cache_api_nonce_6_71fc274b939228e414b484a2aca323c7', 'b:1;', 1748408301),
('rayan_tla_cache_api_nonce_6_7f9f52243030ece27c1f988e7fae5919', 'b:1;', 1748373813),
('rayan_tla_cache_api_nonce_6_7fe1b1cf91a955c7bc967311489fb7c6', 'b:1;', 1748370127),
('rayan_tla_cache_api_nonce_6_91f0c42339450896676bdd2192c5b041', 'b:1;', 1748407921),
('rayan_tla_cache_api_nonce_6_977dc420cdb9a3689dc44ec9be696aad', 'b:1;', 1748370530),
('rayan_tla_cache_api_nonce_6_9f8ec77f1fd1fc0f4e1c86901603c865', 'b:1;', 1748370206),
('rayan_tla_cache_api_nonce_6_a8ca3bf9b9da2e8276280f8251cceccc', 'b:1;', 1748369812),
('rayan_tla_cache_api_nonce_6_a930282fc7992d26ea5635de86a7d1ea', 'b:1;', 1748369864),
('rayan_tla_cache_api_nonce_6_bed7dc5b6ec0d5cf0ebe44a0859e2cdd', 'b:1;', 1748370721),
('rayan_tla_cache_api_nonce_6_d6dea9a7ab04b7a17b1b5ea490eb8aca', 'b:1;', 1748373559),
('rayan_tla_cache_api_nonce_6_dca14bff67cf2bbed7e99d481d1a8fe0', 'b:1;', 1748370319),
('rayan_tla_cache_api_nonce_6_e4e6b5f9ddeb6e49a1eec9afc7c60439', 'b:1;', 1748370328),
('rayan_tla_cache_api_nonce_904cb803b5bbde819330b180b939cef1', 'b:1;', 1746821180),
('rayan_tla_cache_api_nonce_a87a93c2cf003a7caa54fdaa98ca110e', 'b:1;', 1746821536),
('rayan_tla_cache_api_nonce_b80cebd4978a2304c63aa60eb3e15883', 'b:1;', 1746820153),
('rayan_tla_cache_api_nonce_ca6f0e3ae963408a39a580232cc59167', 'b:1;', 1746824308),
('rayan_tla_cache_erfansh8160@gmail.com|45.140.227.2', 'i:1;', 1748456199),
('rayan_tla_cache_erfansh8160@gmail.com|45.140.227.2:timer', 'i:1748456199;', 1748456199);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `name`, `email`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(7, NULL, 'Erfan Shahmohamadi', 'erfan.shahmohamadi@gmail.com', '09122147507', NULL, '2025-05-07 12:18:11', '2025-05-07 12:18:11'),
(8, 2, 'کارشناس ارشد توسعه پلتفرم', 'info@taktaco.ir', '02188612845', 'تهران ، ونک ، خیابان خدامی ، خیابان آرارات جنوبی ، بن بست شیرین ، پلاک 3 طبقه 3', '2025-05-27 14:22:25', '2025-05-27 14:22:25'),
(9, 3, 'عبدالله شاه محمدی', 'info@rar-co.ir', '09123458926', NULL, '2025-05-28 05:54:43', '2025-05-28 05:54:43');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_logs`
--

CREATE TABLE `customer_activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED DEFAULT NULL,
  `action_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_keys`
--

CREATE TABLE `encryption_keys` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `key_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `encryption_keys`
--

INSERT INTO `encryption_keys` (`id`, `system_id`, `key_value`, `status`, `created_at`, `updated_at`) VALUES
(6, 6, 'sfuitaCirdGXn6N64IbUd8mWcqJmokA5KgQ4wIffgMgWSfMXiENA7w4i3N8jFmkLKwADDt4PlIFpAzPHim28cJTlbqqhTS3kcYPaYGi8g8QflPblV0nrbqJlj5ah3I9t0Mjj', 'active', '2025-05-27 14:39:04', '2025-05-27 14:39:04');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `hardware_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_nonce` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `server_nonce` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_key_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_key_display` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'بخش نمایشی کلید لایسنس',
  `salt` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activation_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activation_date` timestamp NULL DEFAULT NULL,
  `hardware_id_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `request_code_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `license_activation_logs`
--

CREATE TABLE `license_activation_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `license_id` bigint UNSIGNED DEFAULT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'initiate, request_code, activate',
  `hardware_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ray_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_number` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_05_03_185755_create_core_tables', 1),
(6, '2025_05_05_114552_add_company_fields_to_users_table', 2),
(8, '2025_05_05_133210_create_support_tickets_table', 3),
(10, '2025_05_05_143024_create_ticket_replies_table', 4),
(11, '2025_05_06_043240_add_user_id_to_customers_table', 5);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('2SDV8yd0WSguqocs1fxT2I5k5yy2HbOcCmZ5rXgZ', NULL, '15.237.197.220', 'Mozilla/5.0 (Linux; Android 7.0; SM-G892A Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSTlvWFV2RUs2djE0bzBuMldNUDNBYUdTeWw2bHZFZWdwR2xUSGZhUiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748410276),
('AEcw5WVvtaIqwFhaDQKbE2JHmCGrsFqLiPFAv7um', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRTNZaldOWkRrV2t3NFZpZXJwUU1qRUY2dkJBT2RSa2VESXNlZmE4TCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748448337),
('AIKmbrqGxvtvaZXEKSEhL38axyPlm161vs6Z7aio', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicnNpS1hxZFN1ekhkekZGUkxmUzU2RHdJdkpFbFBVN3lZSlBjcEgzbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748447356),
('anVCJl6yOQuE1NPdqEUxrzZmk5d0wRnOUAn3WQo1', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoickZzY29Dc3JmT1pjdTg4SFBvemhzcEtHeVpXdUVxU3FpWXlvbzBSSyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444840),
('BTG9WeNqiV3Sxi7ddI7fqPX6JLIq3OvDUodd2Glo', NULL, '154.47.19.48', 'Mozilla/5.0 (Linux; Android 7.0; SM-G935T Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/59.0.3071.125 Mobile Safari/537.36[NBC/MOBILE-ANDROID;MARKET/SITEKEY-chi]', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVGNNTFJCTWhDU0drQmlrWkd0Qk1DQWZSTDVJRlVuTUFPUWtRSjVlMCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vZ29sZGFjYy5pciI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748424632),
('C8RvoETbcC0Qs0PDH8CvIFYI7ohigaTwxNvUFro3', NULL, '185.132.36.5', 'Mozilla/5.0 (Linux; Android 6.0.1; SM-N9005 Build/MOB30D) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.89 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVkt1dERnQUt1MlZjUW5kdXJsSlo4VWFUb0xPRkZ1OXY3QVBMNEI2YSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vZ29sZGFjYy5pciI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748406721),
('cKcqntz4Vc4rZ0d3tPSI84HV93MmXP31Hocqr1y8', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTk5lMGx1OFZRaHBvR29UMDFhQWUzSWZSWXRxM2RmbTdIemZxZ3A4MSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748452128),
('d3KEpVfloclQRTtJCt2X6MX1GAy4uVnFT2KZ8GGj', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSjF4azdhMm1KTWMxZ05HVDk3YWxGMHVBSXZDVVBYTWhvejdhaXFBbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748448326),
('D7ZMsCCnxCReDNsMm4tWSxho2zGeap0XpUPsG4WM', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoib2d6ajczUUZTejB6QlpPelF2RWVidTFtaDlIN0pabjYwVWdiT1N5biI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748452103),
('E0KIjTDS08yPUCzFU9kuIzSx5t0MAilpBFH04wGY', NULL, '45.79.135.142', 'Mozilla/5.0 (Linux; Android 4.4.2; SAMSUNG-SM-G870A Build/KOT49H) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.98 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoieGRGZXdRSTU5S1J3VmF6Y2VkNVR1ZFB0NGU2SlZaSFFoTHY1dzJlTyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vZ29sZGFjYy5pciI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748452594),
('ehoNtcdTKkXUZqLJEEmTEPjX3F25ng4fPHwQYpCR', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNDFMdTZOUXhJUDM5cFprRmhENUM5c1ZUWjkwRVY1cEh1WjRvUllqQyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748447333),
('elt25oDPrGgzW1DYFYtFD4KigcXoKDcMXID8rhSJ', NULL, '176.65.134.18', 'python-requests/2.32.3', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSmJEZDFaN21JUzJ1OGczdFdtQUVZMFUzVnZtVndyNktINGJqcXpRbCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTYzOiJodHRwOi8vZ29sZGFjYy5pci8/cT0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnF1ZXJ5PSUyMiUzRSUzQ2gxJTNFbXIweDAxJTNDJTJGaDElM0Umcz0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnNlYXJjaD0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444837),
('ewztOzC5J6GV0qGr3mAHWz4QriIOK15uAchy3YBf', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidXN1UEJ5a1Rjd3JCdFpnTGlPNXZ3RkhZSXNiUFN2SHVlb2laMlBtYSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748452111),
('IcZcGMeZQ5ZHjMHTbH0rL46e09VPwpZr6vMJTTIX', NULL, '176.65.134.18', 'python-requests/2.32.3', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicnE5SEdOdGtFWHFHVWZrRVBWZzV3cGppaWVXcEtIUWNpV3lKS1YwZSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTYzOiJodHRwOi8vZ29sZGFjYy5pci8/cT0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnF1ZXJ5PSUyMiUzRSUzQ2gxJTNFbXIweDAxJTNDJTJGaDElM0Umcz0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnNlYXJjaD0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748448336),
('Jaf24tVfEjOxmof0ut28W9MozCPaXThw5KwUAgRN', 7, '45.140.227.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiVGVadWZpTnFqTjhSc2pSY081TlV4anowVlBtVG9wNFJlU0xBYzlKZiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM0OiJodHRwczovL2dvbGRhY2MuaXIvYWRtaW4vZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MjoibG9naW5fYWRtaW5fNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTo3O30=', 1748459521),
('jObMcmwf5tIzudqPOHt6GIWyCLnMVMZYMXve7l5G', NULL, '176.65.134.18', 'python-requests/2.32.3', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiOXNUbzlvQzNGeHlRcXhVWUFseDBwNHExMjE2Wkc0dTdCcThMek51SiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTYzOiJodHRwOi8vZ29sZGFjYy5pci8/cT0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnF1ZXJ5PSUyMiUzRSUzQ2gxJTNFbXIweDAxJTNDJTJGaDElM0Umcz0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnNlYXJjaD0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748447355),
('jvyen09uy9JXEoSajFpnumBCl0pGn6Zjp3zcIIHt', NULL, '52.167.144.211', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiam1iMHBnbXJBRFhsTVVNM2ZqcURIZGNkek5TN3VpYkE0R2ZqNTdadCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748431251),
('k55Xe8hIeg1Tpp7qMa5TLxF9PgOj8t2JuTCa30IQ', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid0FvNnJpUmdmWnhFbE9JU0M4ejFHc0ZhNnlhTnJCZkZVZWk3Z0xzWiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444798),
('kR1i8qw0oZHOvOrlxUmjfY37F4BFQGzhYmaLeK4L', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNHB4SWd1MWQwMXNnTE5udEltTm9TSW5rUHNWbEVoeURjM1F3MVJ1TiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748447342),
('lcW0i5cS2cWcXQYdLlmdPIlLoGgybPHzSRSKkAXe', NULL, '139.177.179.223', 'Mozilla/5.0 (Linux; Android 4.4.2; LGLS740 Build/KOT49I.LS740ZV6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.89 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY3RNTFRmdFo3QmJWU2lLSm1pN0FoVkJSTUFNMnl1b1FTWHJMNW9XVyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vZ29sZGFjYy5pciI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748445355),
('LFVTXyz8mH9zivhnDNNTCF1RrAL6HOddMN4W2FEC', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSEZXcFFxUElHV1U5SVROZTE5eUpoRENtbXdVWXd3MERpRWYyZVpIQSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444795),
('MwRLGft2GVEJZYH1nh8l09V6qsZqiCuKpkkBr2Sz', 7, '45.140.227.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiU0VxekFWNHlmcFJBNVpIbzg2dEVRNFNiU1R3a3lUclM2R05ZTHNxWiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHBzOi8vZ29sZGFjYy5pci9hZG1pbi9saWNlbnNlcyI7fXM6NTI6ImxvZ2luX2FkbWluXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Nzt9', 1748427644),
('OkNLDdDrMl16nlV7DSBoVHKpCN35rJVVkiWpdBln', 7, '45.140.227.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiUzBZdmROckJrWnVXUDBiTnAxa05reTNEV3FYVE5ndDlCU2JVYzV0WiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMzOiJodHRwczovL2dvbGRhY2MuaXIvYWRtaW4vbGljZW5zZXMiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUyOiJsb2dpbl9hZG1pbl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjc7fQ==', 1748407999),
('QeWrxqGysJuIoVKiKsYVAsCedj8glGCtBEc8qQf7', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWkJ5c1RURUl5blF3cU5QUkdzMDVKME85eWtmY3J0dWx2eG1VTEtXaiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748447329),
('rA4wecM8jGNGCGDhuoazuqezSRb5xmdZztc5oWvd', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTjlIUlRBSnRUcVZYNXdJNGpOZk54M29PWm5GY3dydlNyQ3QwalFVZSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748448317),
('rBxypNLoZLlnoNOpIt3hOX20oCc8wVApQieC1N9w', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVHpQRFVUOEttVFcybVY0Z1hPZ1lpMWVyVWV5RHhXemladEVhcGlQcSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748452090),
('SzN8PEvny2xrdXQvrkh1tR4Kh1agCsfOd5hnwuvE', 7, '45.140.227.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiMDFhaGQybk0zMGxIQ1RCQjI5WWxOemVBZWNxaUc5U0lDNkhqdGlEdCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMzOiJodHRwczovL2dvbGRhY2MuaXIvYWRtaW4vbGljZW5zZXMiO31zOjUyOiJsb2dpbl9hZG1pbl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjc7fQ==', 1748410125),
('uZkG5SralqtFEIBbxOylBMq7DeeoeunDAi6fTKc6', NULL, '176.65.134.18', 'python-requests/2.32.3', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZ29CTWgxTFJhbjg5VjJwWEZ2bWs1SVhvMEp4WHkwN3M1U2ZwQUUxMCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTYzOiJodHRwOi8vZ29sZGFjYy5pci8/cT0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnF1ZXJ5PSUyMiUzRSUzQ2gxJTNFbXIweDAxJTNDJTJGaDElM0Umcz0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFJnNlYXJjaD0lMjIlM0UlM0NoMSUzRW1yMHgwMSUzQyUyRmgxJTNFIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748452126),
('xFlfeL9AHHbFALwm1v5R5dHGGcJH4OZGa69Z55Uf', NULL, '89.38.224.90', 'Mozilla/5.0 (Linux; Android 4.0.4; LG-MS770 Build/IMM76I) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.92 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTWFxZGt3QWZXN1ZQWWhGY3NuNDlUSHNoOExFdWVnQzY2bFp5UkFIUyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTg6Imh0dHBzOi8vZ29sZGFjYy5pciI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748461643),
('XJjR9hP0JCYdgIRgofojXodLXhMcj4PLU9GGu7xE', NULL, '51.68.111.217', 'Mozilla/5.0 (compatible; MJ12bot/v2.0.2; http://mj12bot.com/)', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTndpWVVOV3FiQW9PV3VyNXpTNmxUSkFicDREc290MVNjZnNXZ2luTiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444108),
('XKqKsDUdWeOjb8uM1lvLmmPZGevR6CSChsJV9ZIm', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid1UxNnlramJ3TXBGanlhUDBtS1VSN2F0OGR1UVNFM3ptdU1RdHEySiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748448313),
('zZyAlj5vCsr4hyF0vJxnxz3TAnL9AnAs3I4iD0Oz', NULL, '176.65.134.18', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRzloTnhIWVdYT2NkUEdLVGtkRUpNblB2ekdlS245eUs1QlcyMlJ5NSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MTc6Imh0dHA6Ly9nb2xkYWNjLmlyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748444812);

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `to_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `response` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_settings`
--

CREATE TABLE `sms_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `system_id` bigint UNSIGNED NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `message`, `status`, `priority`, `created_at`, `updated_at`) VALUES
(1, 1, 'درخواست توسعه', 'درخواست ایجاد ویژگی احراز هویت دو عاملی', 'closed', 'medium', '2025-05-05 10:58:24', '2025-05-05 16:24:10'),
(2, 1, 'مشکل در باز شدن هاست', 'با درود . در باز کردن درخواست و انصراف از درخواست خطا وجود دارد', 'open', 'high', '2025-05-06 05:13:37', '2025-05-06 05:13:37'),
(3, 1, 'مشکل در باز شدن هاست', 'لطفا مشکل را بررسی کنید', 'open', 'high', '2025-05-06 05:36:30', '2025-05-06 05:36:30'),
(4, 1, 'تست درخواست', 'تست جهت فراآیندهای درخواست', 'open', 'medium', '2025-05-07 07:42:49', '2025-05-07 07:42:49');

-- --------------------------------------------------------

--
-- Table structure for table `systems`
--

CREATE TABLE `systems` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_secret` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `current_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `handshake_map` text COLLATE utf8mb4_unicode_ci,
  `last_heartbeat_at` timestamp NULL DEFAULT NULL,
  `os_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `php_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `handshake_string` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `systems`
--

INSERT INTO `systems` (`id`, `customer_id`, `name`, `api_key`, `api_secret`, `domain`, `status`, `current_version`, `created_at`, `updated_at`, `handshake_map`, `last_heartbeat_at`, `os_info`, `php_version`, `handshake_string`) VALUES
(6, 8, 'حسابداری طلا', NULL, NULL, 'http://localhost', 'active', '10.250527.002', '2025-05-27 14:23:53', '2025-05-27 14:23:53', NULL, NULL, NULL, NULL, NULL),
(7, 9, 'رایان گلد', NULL, NULL, 'https://gold.taktaco.ir', 'active', '10.250527.002', '2025-05-28 05:55:15', '2025-05-28 05:55:15', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` bigint UNSIGNED NOT NULL,
  `support_ticket_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `admin_id` bigint UNSIGNED DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_replies`
--

INSERT INTO `ticket_replies` (`id`, `support_ticket_id`, `user_id`, `admin_id`, `message`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 7, 'با درود\r\nکاربر گرامی حتما در به روزرسانی آینده این امکان اضافه خواهد شد', '2025-05-05 16:15:12', '2025-05-05 16:15:12'),
(2, 1, NULL, 7, 'بررسی خواهد شد', '2025-05-05 16:24:10', '2025-05-05 16:24:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organizational_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `company_name`, `email`, `organizational_email`, `domain_name`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'علی محمدی', NULL, 'erfansh8160@gmail.com', NULL, NULL, NULL, '$2y$12$nSjibNZLvF9.K9AaIEMIqOkpBfTYrwmG1AIerIqYiTWkSKa/DIBBG', NULL, '2025-05-05 08:52:18', '2025-05-05 08:52:18'),
(2, 'کارشناس ارشد توسعه پلتفرم', NULL, 'info@taktaco.ir', NULL, NULL, NULL, '$2y$12$BID1dY4WlVJ0kGHM1HdT0OS3WGDR.VDZrsrCZt7qDjWrbhStrX6Iu', NULL, '2025-05-27 14:21:28', '2025-05-27 14:21:28'),
(3, 'عبدالله شاه محمدی', NULL, 'info@rar-co.ir', NULL, NULL, NULL, '$2y$12$GR4wc8urvLnxKeuNtc2bX.T9kCpK/MuWIXF.s8VbrRug3FBDyOMSC', NULL, '2025-05-28 05:53:49', '2025-05-28 05:53:49');

-- --------------------------------------------------------

--
-- Table structure for table `versions`
--

CREATE TABLE `versions` (
  `id` bigint UNSIGNED NOT NULL,
  `version_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `release_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admins_email_unique` (`email`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `backups_system_id_foreign` (`system_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customers_email_unique` (`email`),
  ADD KEY `customers_user_id_foreign` (`user_id`);

--
-- Indexes for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_activity_logs_customer_id_foreign` (`customer_id`),
  ADD KEY `customer_activity_logs_system_id_foreign` (`system_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_logs_system_id_foreign` (`system_id`),
  ADD KEY `email_logs_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `encryption_keys_system_id_foreign` (`system_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `licenses_license_key_unique` (`license_key_display`),
  ADD KEY `licenses_system_id_foreign` (`system_id`),
  ADD KEY `idx_license_key_hash` (`license_key_hash`),
  ADD KEY `idx_hardware_id_hash` (`hardware_id_hash`),
  ADD KEY `idx_license_key_display` (`license_key_display`),
  ADD KEY `idx_license_hardware_domain` (`hardware_id`,`domain`),
  ADD KEY `idx_license_request_code` (`request_code`);

--
-- Indexes for table `license_activation_logs`
--
ALTER TABLE `license_activation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_license_log_hardware` (`hardware_id`),
  ADD KEY `idx_license_log_ray` (`ray_id`),
  ADD KEY `idx_license_log_action` (`action_type`,`status`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `logs_system_id_foreign` (`system_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sms_logs_system_id_foreign` (`system_id`),
  ADD KEY `sms_logs_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `sms_settings`
--
ALTER TABLE `sms_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sms_settings_system_id_foreign` (`system_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_tickets_user_id_foreign` (`user_id`);

--
-- Indexes for table `systems`
--
ALTER TABLE `systems`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `systems_domain_unique` (`domain`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD UNIQUE KEY `systems_api_key_unique` (`api_key`),
  ADD KEY `systems_customer_id_foreign` (`customer_id`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_replies_support_ticket_id_foreign` (`support_ticket_id`),
  ADD KEY `ticket_replies_user_id_foreign` (`user_id`),
  ADD KEY `ticket_replies_admin_id_foreign` (`admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_organizational_email_unique` (`organizational_email`);

--
-- Indexes for table `versions`
--
ALTER TABLE `versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `versions_version_code_unique` (`version_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_activation_logs`
--
ALTER TABLE `license_activation_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_settings`
--
ALTER TABLE `sms_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `systems`
--
ALTER TABLE `systems`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `versions`
--
ALTER TABLE `versions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD CONSTRAINT `customer_activity_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_activity_logs_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_logs_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  ADD CONSTRAINT `encryption_keys_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `licenses`
--
ALTER TABLE `licenses`
  ADD CONSTRAINT `licenses_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sms_logs_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_settings`
--
ALTER TABLE `sms_settings`
  ADD CONSTRAINT `sms_settings_system_id_foreign` FOREIGN KEY (`system_id`) REFERENCES `systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `systems`
--
ALTER TABLE `systems`
  ADD CONSTRAINT `systems_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `ticket_replies_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ticket_replies_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
