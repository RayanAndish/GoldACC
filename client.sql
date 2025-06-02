-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2025 at 08:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mjxbtrrr_gold_acc`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `ray_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allowed_ips`
--

CREATE TABLE `allowed_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assay_offices`
--

CREATE TABLE `assay_offices` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'نام مرکز ری گیری',
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='اطلاعات مراکز ری گیری (انگ)';

--
-- Dumping data for table `assay_offices`
--

INSERT INTO `assay_offices` (`id`, `name`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(157, 'ری گیری احمدی', '88994466', 'تهران بازار', '2025-05-12 17:34:10', '2025-05-12 17:34:10'),
(158, 'ریگیری حسین', '02188665544', NULL, '2025-06-15 06:27:04', '2025-06-15 06:27:04');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_name` varchar(150) NOT NULL COMMENT 'نام دلخواه برای حساب (مثال: ملی اصلی، پاسارگاد کاری)',
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'نام بانک (اختیاری)',
  `account_number` varchar(50) DEFAULT NULL COMMENT 'شماره حساب یا کارت (اختیاری)',
  `initial_balance` decimal(25,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی اولیه هنگام ثبت حساب',
  `current_balance` decimal(25,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی فعلی (با احتساب تراکنش ها)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='اطلاعات حساب های بانکی کاربر';

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_name`, `bank_name`, `account_number`, `initial_balance`, `current_balance`, `created_at`, `updated_at`) VALUES
(5, 'شعبه پاسارگاد شهید دنیامالی', 'پاسارگاد', '908560562.55525.105.10.1', 0.00, 0.00, '2025-06-01 09:00:41', '2025-06-01 09:00:41');

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_account_id` int(10) UNSIGNED NOT NULL COMMENT 'لینک به حساب بانکی مربوطه',
  `transaction_date` datetime NOT NULL COMMENT 'تاریخ و زمان تراکنش بانکی',
  `amount` decimal(25,2) NOT NULL COMMENT 'مبلغ تراکنش (مثبت برای واریز، منفی برای برداشت)',
  `type` enum('deposit','withdrawal','transfer_in','transfer_out','fee','interest','initial','other') NOT NULL DEFAULT 'other' COMMENT 'نوع تراکنش',
  `description` text DEFAULT NULL COMMENT 'توضیحات تراکنش (مثال: واریز از X، برداشت برای Y، کارمزد)',
  `related_payment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'لینک به رکورد پرداخت مرتبط در جدول payments (اختیاری)',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='تراکنش های واریز و برداشت حساب های بانکی';

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `block_until` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `capital_accounts`
--

CREATE TABLE `capital_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_name` varchar(191) NOT NULL COMMENT 'نام حساب سرمایه/حقوق صاحبان سهام',
  `account_code` varchar(50) DEFAULT NULL COMMENT 'کد حساب (اختیاری)',
  `account_type` varchar(50) NOT NULL COMMENT 'نوع حساب: initial_capital, owner_contribution, drawings, retained_earnings',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='انواع حساب‌های سرمایه و حقوق صاحبان سهام';

-- --------------------------------------------------------

--
-- Table structure for table `capital_ledger`
--

CREATE TABLE `capital_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `capital_account_id` int(10) UNSIGNED NOT NULL COMMENT 'شناسه حساب سرمایه',
  `transaction_date` datetime NOT NULL COMMENT 'تاریخ تراکنش سرمایه',
  `description` varchar(255) NOT NULL COMMENT 'شرح تراکنش',
  `amount` decimal(25,2) NOT NULL COMMENT 'مبلغ (مثبت برای افزایش، منفی برای کاهش سرمایه)',
  `related_payment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه پرداخت مرتبط (مثلاً برداشت نقدی مالک)',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='دفتر تراکنش‌های مربوط به حساب‌های سرمایه';

-- --------------------------------------------------------

--
-- Table structure for table `coin_inventory`
--

CREATE TABLE `coin_inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `coin_type` enum('coin_bahar_azadi_new','coin_bahar_azadi_old','coin_emami','coin_half','coin_quarter','coin_gerami','other_coin') NOT NULL COMMENT 'نوع سکه (از gold_product_type)',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد موجودی',
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='موجودی انواع سکه';

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('debtor','creditor_account','counterparty','mixed','other') NOT NULL COMMENT 'debtor=بدهکار شما, creditor_account=حساب مقصد معرفی شده, counterparty=طرف حساب اصلی, mixed=ترکیبی, other=سایر',
  `details` text DEFAULT NULL COMMENT 'شماره حساب، تلفن، توضیحات',
  `credit_limit` decimal(25,2) DEFAULT NULL COMMENT 'سقف اعتبار معامله با این مخاطب (ریال)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `name`, `type`, `details`, `credit_limit`, `created_at`, `updated_at`) VALUES
(7, 'علی احمدی', 'counterparty', 'بازار پله - 88994455', 10000000000.00, '2025-05-12 17:33:31', '2025-05-12 17:33:31');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `carat` int(11) NOT NULL COMMENT 'عیار طلا',
  `total_weight_grams` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total_value_rials` decimal(25,2) NOT NULL DEFAULT 0.00,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_calculations`
--

CREATE TABLE `inventory_calculations` (
  `id` int(10) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `calculation_date` date NOT NULL,
  `calculation_type` enum('initial_balance','daily_balance','monthly_balance') NOT NULL,
  `quantity_before` int(11) DEFAULT NULL,
  `weight_before` decimal(10,3) DEFAULT NULL,
  `quantity_after` int(11) DEFAULT NULL,
  `weight_after` decimal(10,3) DEFAULT NULL,
  `average_purchase_price` decimal(15,2) DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT NULL,
  `target_capital` decimal(15,2) DEFAULT NULL,
  `balance_percentage` decimal(5,2) DEFAULT NULL,
  `balance_status` enum('shortage','normal','excess') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_ledger`
--

CREATE TABLE `inventory_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL COMMENT 'شناسه محصول',
  `transaction_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه معامله مرتبط',
  `movement_date` datetime NOT NULL COMMENT 'تاریخ و زمان دقیق تغییر در موجودی',
  `movement_type` varchar(50) NOT NULL COMMENT 'نوع تغییر: initial_balance, purchase, sale, sale_return, purchase_return, adjustment_in, adjustment_out',
  `related_transaction_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه معامله مرتبط (خرید/فروش)',
  `related_initial_balance_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه موجودی اولیه مرتبط',
  `related_payment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه پرداخت مرتبط (برای هزینه های مستقیم خرید و ...)',
  `description` varchar(255) DEFAULT NULL COMMENT 'شرح حرکت در کاردکس',
  `quantity_in` decimal(15,4) DEFAULT NULL COMMENT 'تعداد/مقدار وارده',
  `quantity_out` decimal(15,4) DEFAULT NULL COMMENT 'تعداد/مقدار صادره',
  `balance_quantity_after_movement` decimal(15,4) NOT NULL COMMENT 'موجودی تعدادی پس از این تغییر',
  `weight_grams_in` decimal(15,4) DEFAULT NULL COMMENT 'وزن وارده به گرم',
  `weight_grams_out` decimal(15,4) DEFAULT NULL COMMENT 'وزن صادره به گرم',
  `balance_weight_grams_after_movement` decimal(15,4) DEFAULT NULL COMMENT 'موجودی وزنی پس از این تغییر',
  `carat` int(11) DEFAULT NULL COMMENT 'عیار کالای جابجا شده (برای محاسبه طلای خالص)',
  `price_per_unit_at_movement` decimal(25,2) NOT NULL COMMENT 'بهای واحد در زمان این تغییر (خرید/فروش/میانگین)',
  `total_value_in` decimal(25,2) DEFAULT NULL COMMENT 'ارزش کل وارده',
  `total_value_out` decimal(25,2) DEFAULT NULL COMMENT 'ارزش کل صادره',
  `balance_total_value_after_movement` decimal(25,2) NOT NULL COMMENT 'ارزش کل موجودی پس از این تغییر (بهای تمام شده)',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه کاربر ثبت کننده',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `transaction_item_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه قلم معامله مرتبط (Null برای دستی)',
  `change_quantity` int(11) DEFAULT NULL COMMENT 'تغییر تعدادی (+/-)',
  `change_weight_grams` decimal(15,4) DEFAULT NULL COMMENT 'تغییر وزنی (گرم) (+/-)',
  `quantity_after` int(11) DEFAULT NULL COMMENT 'موجودی تعدادی بعد از تغییر',
  `weight_grams_after` decimal(15,4) DEFAULT NULL COMMENT 'موجودی وزنی بعد از تغییر',
  `event_type` varchar(50) NOT NULL COMMENT 'نوع رویداد (INITIAL_CAPITAL, BUY_COMPLETED, SELL_COMPLETED, MANUAL_ADJUST)',
  `event_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'زمان وقوع رویداد',
  `notes` text DEFAULT NULL COMMENT 'یادداشت'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='کاردکس یا دفتر موجودی کالا و تغییرات آن';

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` int(11) NOT NULL,
  `license_key` varchar(255) NOT NULL,
  `license_type` enum('trial','standard','premium','enterprise') NOT NULL DEFAULT 'standard',
  `domain` varchar(255) NOT NULL,
  `request_code` varchar(64) DEFAULT NULL,
  `client_nonce` varchar(32) DEFAULT NULL,
  `server_nonce` varchar(32) DEFAULT NULL,
  `salt` varchar(64) DEFAULT NULL,
  `activation_ip` varchar(45) DEFAULT NULL,
  `activation_date` timestamp NULL DEFAULT NULL,
  `activation_challenge` varchar(128) DEFAULT NULL COMMENT 'Challenge string for activation process',
  `activation_challenge_expires` datetime DEFAULT NULL COMMENT 'Challenge expiration timestamp',
  `activation_nonce` varchar(64) DEFAULT NULL COMMENT 'Server nonce for activation',
  `activation_salt` varchar(64) DEFAULT NULL COMMENT 'One-time salt for activation',
  `activation_status` enum('pending','completed','expired','failed') DEFAULT NULL COMMENT 'Current status of activation process',
  `activation_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of activation attempts',
  `last_activation_attempt` datetime DEFAULT NULL COMMENT 'Timestamp of last activation attempt',
  `ip_whitelist` text DEFAULT NULL COMMENT 'Comma-separated list of allowed IPs',
  `max_activation_attempts` int(10) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Maximum allowed activation attempts',
  `ip_address` varchar(45) NOT NULL,
  `ray_id` varchar(64) DEFAULT NULL,
  `hardware_id` varchar(255) DEFAULT NULL,
  `activation_count` int(11) DEFAULT 0,
  `max_activations` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `activated_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','suspended','expired','revoked') NOT NULL DEFAULT 'active',
  `validated` tinyint(1) DEFAULT 0,
  `last_validated` datetime DEFAULT NULL,
  `last_check` datetime DEFAULT NULL,
  `check_interval` int(11) DEFAULT 10,
  `expires_at` datetime DEFAULT NULL,
  `max_users` int(11) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_activations`
--

CREATE TABLE `license_activations` (
  `id` int(11) NOT NULL,
  `license_id` int(11) NOT NULL,
  `hardware_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `activation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_check` datetime DEFAULT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_activation_logs`
--

CREATE TABLE `license_activation_logs` (
  `id` int(11) NOT NULL,
  `license_id` int(11) NOT NULL COMMENT 'Reference to licenses table',
  `step` enum('initiation','challenge','completion','failure') NOT NULL COMMENT 'Activation step',
  `status` enum('success','failed','expired','invalid') NOT NULL COMMENT 'Step status',
  `client_nonce` varchar(64) DEFAULT NULL COMMENT 'Client nonce if applicable',
  `server_nonce` varchar(64) DEFAULT NULL COMMENT 'Server nonce if applicable',
  `challenge` varchar(128) DEFAULT NULL COMMENT 'Challenge string if applicable',
  `error_message` text DEFAULT NULL COMMENT 'Error message if status is failed',
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON encoded request data' CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON encoded response data' CHECK (json_valid(`response_data`)),
  `ip_address` varchar(45) NOT NULL COMMENT 'IP address of the request',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User agent of the request',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs for license activation process';

-- --------------------------------------------------------

--
-- Table structure for table `license_checks`
--

CREATE TABLE `license_checks` (
  `id` int(11) NOT NULL,
  `license_id` int(11) NOT NULL,
  `check_type` enum('online','offline','hardware','usage') NOT NULL,
  `check_result` tinyint(1) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_logs`
--

CREATE TABLE `license_logs` (
  `id` int(11) NOT NULL,
  `type` enum('info','error') NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `license_requests`
--

CREATE TABLE `license_requests` (
  `id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `ray_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `request_code` varchar(255) NOT NULL,
  `client_nonce` varchar(64) DEFAULT NULL COMMENT 'Client nonce for activation',
  `server_nonce` varchar(64) DEFAULT NULL COMMENT 'Server nonce for activation',
  `challenge` varchar(128) DEFAULT NULL COMMENT 'Challenge string',
  `challenge_expires` datetime DEFAULT NULL COMMENT 'Challenge expiration timestamp',
  `activation_step` enum('initiated','completed') DEFAULT NULL COMMENT 'Current step in activation process',
  `request_ip` varchar(45) DEFAULT NULL COMMENT 'IP address of the request',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User agent of the request',
  `request_headers` text DEFAULT NULL COMMENT 'JSON encoded request headers',
  `hardware_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `activation_count` int(11) DEFAULT 0,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_date` datetime NOT NULL,
  `amount_rials` decimal(25,2) NOT NULL,
  `direction` enum('inflow','outflow') NOT NULL COMMENT 'inflow=ورودی به سیستم , outflow=خروجی از سیستم ',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'روش پرداخت: cash, barter, bank_slip, mobile_transfer, internet_transfer, atm, pos, cheque, clearing_account',
  `method_details_payer_receiver` varchar(255) DEFAULT NULL COMMENT 'نام پرداخت/دریافت کننده نقدی',
  `method_details_clearing_type` varchar(100) DEFAULT NULL COMMENT 'نوع تهاتر',
  `method_details_slip_number` varchar(100) DEFAULT NULL COMMENT 'شماره فیش بانکی',
  `method_details_slip_date` date DEFAULT NULL COMMENT 'تاریخ فیش بانکی',
  `method_details_bank_agent` varchar(150) DEFAULT NULL COMMENT 'نام بانک عامل (فیش/چک)',
  `method_details_tracking_code` varchar(100) DEFAULT NULL COMMENT 'شماره پیگیری (انتقال/ATM/POS)',
  `method_details_transfer_date` date DEFAULT NULL COMMENT 'تاریخ انتقال/واریز',
  `method_details_source_dest_info` varchar(255) DEFAULT NULL COMMENT 'اطلاعات کارت/حساب مبدا/مقصد',
  `method_details_terminal_id` varchar(100) DEFAULT NULL COMMENT 'شماره پایانه (ATM/POS)',
  `method_details_pos_holder` varchar(150) DEFAULT NULL COMMENT 'دارنده POS',
  `method_details_cheque_holder_nid` varchar(20) DEFAULT NULL COMMENT 'کد ملی صاحب حساب چک',
  `method_details_cheque_account_number` varchar(50) DEFAULT NULL COMMENT 'شماره حساب چک',
  `method_details_cheque_holder_name` varchar(150) DEFAULT NULL COMMENT 'نام صاحب حساب چک',
  `method_details_cheque_type` varchar(50) DEFAULT NULL COMMENT 'نوع چک',
  `method_details_cheque_serial` varchar(100) DEFAULT NULL COMMENT 'سری و سریال چک',
  `method_details_cheque_sayad_id` varchar(30) DEFAULT NULL COMMENT 'شماره صیاد چک',
  `method_details_cheque_due_date` date DEFAULT NULL COMMENT 'تاریخ سررسید چک',
  `paying_contact_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'واریز کننده (از جدول contacts - مثلا بدهکار شما)',
  `paying_details` varchar(255) DEFAULT NULL COMMENT 'جزئیات واریز کننده اگر در مخاطبین نیست',
  `receiving_contact_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'گیرنده (از جدول contacts - مثلا حساب مقصد)',
  `receiving_details` varchar(255) DEFAULT NULL COMMENT 'جزئیات گیرنده (مثلا شماره حساب مقصد)',
  `related_transaction_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'لینک به معامله طلای مرتبط',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'نام کامل و دقیق محصول',
  `category_id` int(10) UNSIGNED NOT NULL COMMENT 'شناسه دسته بندی محصول',
  `product_code` varchar(100) DEFAULT NULL COMMENT 'کد یا شناسه یکتای محصول (اختیاری)',
  `unit_of_measure` enum('gram','count') NOT NULL DEFAULT 'gram' COMMENT 'واحد سنجش اصلی محصول (وزنی یا تعدادی)',
  `description` text DEFAULT NULL COMMENT 'توضیحات بیشتر در مورد محصول',
  `quantity` decimal(15,4) DEFAULT NULL,
  `weight` decimal(15,4) DEFAULT NULL,
  `coin_year` int(11) DEFAULT NULL,
  `default_carat` int(11) DEFAULT NULL COMMENT 'عیار پیش‌فرض محصول (برای محصولات وزنی)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'آیا محصول فعال است؟',
  `capital_quantity` decimal(15,4) DEFAULT NULL COMMENT 'سرمایه یا موجودی هدف تعدادی (برای سکه و ...)',
  `capital_weight_grams` decimal(15,4) DEFAULT NULL COMMENT 'سرمایه یا موجودی هدف وزنی (بر اساس عیار مبنا)',
  `capital_reference_carat` int(11) DEFAULT 750 COMMENT 'عیار مبنای سرمایه یا موجودی هدف وزنی (پیشفرض 750)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='لیست محصولات یا کالاها';

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category_id`, `product_code`, `unit_of_measure`, `description`, `quantity`, `weight`, `coin_year`, `default_carat`, `is_active`, `capital_quantity`, `capital_weight_grams`, `capital_reference_carat`, `created_at`, `updated_at`) VALUES
(31, 'آبشده نقدی', 20, '110', 'gram', '', NULL, NULL, NULL, NULL, 1, NULL, NULL, 750, '2025-05-14 21:36:46', '2025-05-14 21:36:46'),
(32, 'سکه امامی', 21, 'Sekeh_Emami', 'count', '', NULL, NULL, NULL, NULL, 1, NULL, NULL, 750, '2025-05-14 21:37:03', '2025-05-14 21:37:24'),
(33, 'نیم سکه بهار آزادی', 21, '', 'count', '', NULL, NULL, NULL, NULL, 1, NULL, NULL, 750, '2025-05-14 21:37:14', '2025-05-14 21:37:14'),
(35, 'مروارید', 27, 'Jewel', 'gram', '', NULL, NULL, NULL, NULL, 1, NULL, NULL, 750, '2025-05-14 21:38:26', '2025-05-14 21:38:26'),
(36, 'شمش 10 گرمی رزبد', 23, 'Bullion-10G', 'gram', '', 0.0000, NULL, NULL, NULL, 1, 0.0000, NULL, 750, '2025-05-17 17:23:52', '2025-06-01 08:46:50'),
(37, 'دستبند کارتیه - سایز 10', 22, 'Kartieh-10-Bracelt', 'gram', '', 5.0000, NULL, NULL, NULL, 1, 10.0000, NULL, 750, '2025-05-17 17:25:31', '2025-05-17 17:25:31');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL COMMENT 'نام دسته بندی محصول',
  `code` varchar(50) DEFAULT NULL COMMENT 'کد کوتاه و یکتای دسته بندی (اختیاری)',
  `base_category` varchar(32) NOT NULL,
  `description` text DEFAULT NULL COMMENT 'توضیحات دسته بندی',
  `unit_of_measure` varchar(50) DEFAULT NULL COMMENT 'واحد اندازه گیری پیشفرض این دسته (عدد، گرم، مثقال و...)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='دسته‌بندی یا انواع اصلی محصولات';

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `code`, `base_category`, `description`, `unit_of_measure`, `created_at`, `is_active`, `updated_at`) VALUES
(20, 'آبشده', '1', 'MELTED', '', 'گرم', '2025-05-14 21:33:41', 1, '2025-05-14 21:33:41'),
(21, 'سکه', '2', 'COIN', '', 'عدد', '2025-05-14 21:34:00', 1, '2025-05-14 21:34:00'),
(22, 'ساخته شده', '3', 'MANUFACTURED', '', 'گرم', '2025-05-14 21:34:19', 1, '2025-05-14 21:34:19'),
(23, 'شمش طلا', '4', 'BULLION', '', 'گرم', '2025-05-14 21:34:42', 1, '2025-05-14 21:34:42'),
(27, 'جواهر', '6', 'JEWELRY', '', 'قیراط', '2025-05-14 21:36:12', 1, '2025-05-14 21:36:12'),
(28, 'شمش نقره', '5', 'BULLION', '', 'گرم', '2025-05-15 09:18:51', 1, '2025-05-15 09:18:51');

-- --------------------------------------------------------

--
-- Table structure for table `product_initial_balances`
--

CREATE TABLE `product_initial_balances` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL COMMENT 'شناسه محصول',
  `balance_date` date NOT NULL COMMENT 'تاریخ ثبت یا تاریخ مبنای موجودی اولیه',
  `quantity` decimal(15,4) DEFAULT NULL COMMENT 'مقدار/تعداد اولیه',
  `weight_grams` decimal(15,4) DEFAULT NULL COMMENT 'وزن اولیه کل به گرم (اگر محصول وزنی است)',
  `carat` int(11) DEFAULT NULL COMMENT 'عیار واقعی موجودی اولیه (اگر با پیش‌فرض محصول متفاوت است)',
  `average_purchase_price_per_unit` decimal(25,2) NOT NULL COMMENT 'بهای تمام شده میانگین هر واحد/گرم از موجودی اولیه',
  `total_purchase_value` decimal(25,2) NOT NULL COMMENT 'ارزش کل بهای تمام شده موجودی اولیه',
  `notes` text DEFAULT NULL COMMENT 'یادداشت های مربوط به موجودی اولیه',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه کاربر ثبت کننده',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `target_capital` decimal(25,2) NOT NULL DEFAULT 0.00 COMMENT 'سرمایه هدف برای این محصول',
  `performance_balance` decimal(25,2) NOT NULL DEFAULT 0.00 COMMENT 'تراز عملکرد',
  `last_performance_update` timestamp NULL DEFAULT NULL COMMENT 'آخرین بروزرسانی تراز عملکرد'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='موجودی اول دوره محصولات';

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL COMMENT 'نام نقش (e.g., admin, editor, viewer)',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'اختیارات به صورت JSON (optional)' CHECK (json_valid(`permissions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `permissions`) VALUES
(1, 'admin', '{\"add\": true, \"edit\": true, \"view\": true, \"users\": true, \"delete\": true}'),
(2, 'editor', '{\"add\": true, \"edit\": true, \"view\": true, \"users\": false, \"delete\": false}');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'items_per_page', 's:2:\"15\";', NULL, '0000-00-00 00:00:00', '2025-04-29 01:17:19'),
(2, 'app_name', 's:34:\"حسابداری رایان طلا\";', NULL, '0000-00-00 00:00:00', '2025-04-29 01:17:19'),
(15, 'customer_name', 's:37:\"شرکت رایان اندیش رشد\";', NULL, '0000-00-00 00:00:00', '2025-04-29 01:17:19'),
(16, 'gold_price_api_url', 's:83:\"https://BrsApi.ir/Api/Market/Gold_Currency.php?key=FreeKdZp8JKIoBNfKgbxgoeT2pueIoF1\";', NULL, '0000-00-00 00:00:00', '2025-04-29 01:17:19'),
(17, 'gold_price_api_key', 's:32:\"FreeKdZp8JKIoBNfKgbxgoeT2pueIoF1\";', NULL, '0000-00-00 00:00:00', '2025-04-29 01:17:19'),
(26, 'update_server_url', 'https://update.example.com/api/check', NULL, '0000-00-00 00:00:00', '2025-04-28 22:19:55'),
(31, 'gold_price_api_interval', 's:1:\"5\";', NULL, '2025-04-29 01:17:19', '2025-04-29 01:17:19'),
(64, 'api_handshake_string', 's:132:\"sfuitaCirdGXn6N64IbUd8mWcqJmokA5KgQ4wIffgMgWSfMXiENA7w4i3N8jFmkLKwADDt4PlIFpAzPHim28cJTlbqqhTS3kcYPaYGi8g8QflPblV0nrbqJlj5ah3I9t0Mjj\";', NULL, '2025-05-27 21:39:04', '2025-05-27 21:39:04'),
(65, 'api_handshake_map', 's:84:\"eyJhcGlfa2V5IjpbMCwzOV0sImFwaV9zZWNyZXQiOls0MCw5OV0sImhtYWNfc2FsdCI6WzEwMCwxMzFdfQ==\";', NULL, '2025-05-27 21:39:04', '2025-05-27 21:39:04'),
(66, 'api_handshake_expires', 'i:1748455744;', NULL, '2025-05-27 21:39:04', '2025-05-27 21:39:04'),
(67, 'api_system_id', 's:1:\"6\";', NULL, '2025-05-27 21:39:04', '2025-05-27 21:39:04');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('buy','sell') NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه محصول فروخته شده یا خریداری شده',
  `transaction_date` datetime NOT NULL,
  `calculated_weight_grams` decimal(15,4) DEFAULT NULL,
  `price_per_reference_gram` decimal(20,2) DEFAULT NULL,
  `mazaneh_price` decimal(20,0) DEFAULT NULL,
  `total_value_rials` decimal(25,2) NOT NULL COMMENT 'مبلغ کل معامله به ریال',
  `usd_rate_ref` decimal(15,2) DEFAULT NULL COMMENT 'نرخ دلار مرجع در زمان معامله',
  `counterparty_contact_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'طرف حساب اصلی (از جدول contacts)',
  `total_items_value_rials` decimal(20,2) DEFAULT NULL COMMENT 'مجموع مبلغ کل اقلام معامله',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_status` enum('pending_delivery','pending_receipt','completed','canceled') NOT NULL DEFAULT 'pending_receipt' COMMENT 'وضعیت تحویل فیزیکی طلا',
  `delivery_person` varchar(100) DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL COMMENT 'تاریخ واقعی تحویل/دریافت',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `total_profit_wage_commission_rials` decimal(25,2) DEFAULT NULL COMMENT 'جمع سود، اجرت و کارمزد کل اقلام',
  `total_general_tax_rials` decimal(25,2) DEFAULT NULL COMMENT 'جمع مالیات عمومی (غیر از ارزش افزوده)',
  `total_before_vat_rials` decimal(25,2) DEFAULT NULL COMMENT 'جمع کل قبل از محاسبه ارزش افزوده',
  `total_vat_rials` decimal(25,2) DEFAULT NULL COMMENT 'جمع مالیات بر ارزش افزوده کل',
  `final_payable_amount_rials` decimal(25,2) DEFAULT NULL COMMENT 'مبلغ نهایی قابل پرداخت/دریافت کل معامله'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) DEFAULT NULL COMMENT 'تعداد (برای کالاهای تعدادی)',
  `weight_grams` decimal(15,4) DEFAULT NULL COMMENT 'وزن (گرم)',
  `carat` int(11) DEFAULT NULL COMMENT 'عیار (برای آبشده/ساخته)',
  `unit_price_rials` decimal(20,2) NOT NULL COMMENT 'قیمت واحد (بر اساس واحد محصول: هر عدد یا قیمت پایه وزنی)',
  `total_value_rials` decimal(20,2) NOT NULL COMMENT 'مبلغ کل محاسبه شده برای این قلم',
  `tag_number` varchar(50) DEFAULT NULL COMMENT 'شماره انگ (آبشده)',
  `assay_office_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'شناسه مرکز ری گیری (آبشده)',
  `coin_year` int(11) DEFAULT NULL COMMENT 'سال ضرب (سکه)',
  `seal_name` varchar(100) DEFAULT NULL COMMENT 'نام پلمپ (سکه)',
  `is_bank_coin` tinyint(1) DEFAULT NULL COMMENT 'بانکی؟ (سکه)',
  `ajrat_rials` decimal(15,2) DEFAULT NULL COMMENT 'اجرت ساخت (ساخته)',
  `workshop_name` varchar(100) DEFAULT NULL COMMENT 'نام کارگاه (ساخته)',
  `stone_weight_grams` decimal(10,4) DEFAULT NULL COMMENT 'وزن سنگ (ساخته)',
  `description` text DEFAULT NULL COMMENT 'توضیحات اضافی قلم',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='اقلام هر معامله';

-- --------------------------------------------------------

--
-- Table structure for table `update_history`
--

CREATE TABLE `update_history` (
  `id` int(11) NOT NULL,
  `version` varchar(32) NOT NULL,
  `update_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `log` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `activation_code` varchar(64) NOT NULL,
  `name` varchar(300) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role_id` tinyint(3) UNSIGNED DEFAULT 2 COMMENT 'FK to roles table',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT NULL,
  `role` enum('admin','user','moderator') DEFAULT 'user',
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `activation_code`, `name`, `email`, `role_id`, `created_at`, `updated_at`, `is_active`, `role`, `last_login`) VALUES
(1, 'errick', '$2y$10$3uvtsJk0Hay0nr9w8.URweFbacTF5xH1Ci.uQR6aEdf6BqmNI8A7O', '', 'مدیر سامانه', NULL, 1, '2025-04-08 13:43:37', '2025-04-18 14:36:43', 1, 'user', NULL),
(2, 'ehram', '$2y$10$JNGEzH3VA7iSbAFlpeA1wOmm0kJxh.CEqgD1cy9dYusgTEhjQorFK', '', 'مدیر فروشگاه', NULL, 2, '2025-04-17 06:57:48', '2025-04-17 06:57:48', 1, 'user', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `allowed_ips`
--
ALTER TABLE `allowed_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `assay_offices`
--
ALTER TABLE `assay_offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_name` (`account_name`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank_account_id` (`bank_account_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `fk_bank_transactions_payment` (`related_payment_id`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `capital_accounts`
--
ALTER TABLE `capital_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`);

--
-- Indexes for table `capital_ledger`
--
ALTER TABLE `capital_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_capital_ledger_account_id` (`capital_account_id`),
  ADD KEY `fk_capital_ledger_payment_id` (`related_payment_id`),
  ADD KEY `fk_capital_ledger_user_id` (`created_by_user_id`);

--
-- Indexes for table `coin_inventory`
--
ALTER TABLE `coin_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_coin_type` (`coin_type`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `carat` (`carat`),
  ADD KEY `idx_carat` (`carat`);

--
-- Indexes for table `inventory_calculations`
--
ALTER TABLE `inventory_calculations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_ledger_product_date` (`product_id`,`movement_date`),
  ADD KEY `fk_inventory_ledger_product_id` (`product_id`),
  ADD KEY `fk_inventory_ledger_transaction_id` (`related_transaction_id`),
  ADD KEY `fk_inventory_ledger_initial_balance_id` (`related_initial_balance_id`),
  ADD KEY `fk_inventory_ledger_payment_id` (`related_payment_id`),
  ADD KEY `fk_inventory_ledger_user_id` (`created_by_user_id`),
  ADD KEY `fk_inventory_ledger_product_idx` (`product_id`),
  ADD KEY `fk_inventory_ledger_transaction_item_idx` (`transaction_item_id`),
  ADD KEY `fk_inventory_ledger_transaction` (`transaction_id`);

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activation_status` (`activation_status`),
  ADD KEY `idx_activation_challenge` (`activation_challenge`),
  ADD KEY `idx_activation_expires` (`activation_challenge_expires`),
  ADD KEY `idx_license_hardware_domain` (`hardware_id`,`domain`),
  ADD KEY `idx_license_request_code` (`request_code`);

--
-- Indexes for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license_id` (`license_id`);

--
-- Indexes for table `license_activation_logs`
--
ALTER TABLE `license_activation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_license_id` (`license_id`),
  ADD KEY `idx_step_status` (`step`,`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `license_checks`
--
ALTER TABLE `license_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license_id` (`license_id`);

--
-- Indexes for table `license_logs`
--
ALTER TABLE `license_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `license_requests`
--
ALTER TABLE `license_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_domain_active` (`domain`,`is_active`),
  ADD KEY `idx_activation_step` (`activation_step`),
  ADD KEY `idx_challenge_expires` (`challenge_expires`),
  ADD KEY `idx_client_nonce` (`client_nonce`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username_ip` (`username`,`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `fk_payments_paying_contact` (`paying_contact_id`),
  ADD KEY `fk_payments_receiving_contact` (`receiving_contact_id`),
  ADD KEY `fk_payments_transaction` (`related_transaction_id`),
  ADD KEY `idx_payment_method` (`payment_method`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_code` (`product_code`),
  ADD KEY `fk_products_category_id` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_category_code` (`code`);

--
-- Indexes for table `product_initial_balances`
--
ALTER TABLE `product_initial_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_initial_balance_per_product` (`product_id`),
  ADD KEY `fk_initial_balances_product_id` (`product_id`),
  ADD KEY `fk_initial_balances_user_id` (`created_by_user_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_action` (`ip`,`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `fk_transactions_counterparty` (`counterparty_contact_id`),
  ADD KEY `fk_transactions_created_by` (`created_by_user_id`),
  ADD KEY `fk_transactions_product_id` (`product_id`),
  ADD KEY `fk_transactions_updated_by` (`updated_by_user_id`);

--
-- Indexes for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transaction_items_transaction_idx` (`transaction_id`),
  ADD KEY `fk_transaction_items_product_idx` (`product_id`),
  ADD KEY `fk_transaction_items_assay_office_idx` (`assay_office_id`);

--
-- Indexes for table `update_history`
--
ALTER TABLE `update_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `update_time` (`update_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4198;

--
-- AUTO_INCREMENT for table `allowed_ips`
--
ALTER TABLE `allowed_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assay_offices`
--
ALTER TABLE `assay_offices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `capital_accounts`
--
ALTER TABLE `capital_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `capital_ledger`
--
ALTER TABLE `capital_ledger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coin_inventory`
--
ALTER TABLE `coin_inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `inventory_calculations`
--
ALTER TABLE `inventory_calculations`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `license_activations`
--
ALTER TABLE `license_activations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_activation_logs`
--
ALTER TABLE `license_activation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_checks`
--
ALTER TABLE `license_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_logs`
--
ALTER TABLE `license_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `license_requests`
--
ALTER TABLE `license_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `product_initial_balances`
--
ALTER TABLE `product_initial_balances`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `update_history`
--
ALTER TABLE `update_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `capital_ledger`
--
ALTER TABLE `capital_ledger`
  ADD CONSTRAINT `fk_capital_ledger_account_id` FOREIGN KEY (`capital_account_id`) REFERENCES `capital_accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_capital_ledger_payment_id` FOREIGN KEY (`related_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_capital_ledger_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory_calculations`
--
ALTER TABLE `inventory_calculations`
  ADD CONSTRAINT `inventory_calculations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `inventory_ledger`
--
ALTER TABLE `inventory_ledger`
  ADD CONSTRAINT `fk_inventory_ledger_initial_balance_id` FOREIGN KEY (`related_initial_balance_id`) REFERENCES `product_initial_balances` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_ledger_payment_id` FOREIGN KEY (`related_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_ledger_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_inventory_ledger_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_ledger_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_ledger_transaction_id` FOREIGN KEY (`related_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_ledger_transaction_item` FOREIGN KEY (`transaction_item_id`) REFERENCES `transaction_items` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_inventory_ledger_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `license_activations`
--
ALTER TABLE `license_activations`
  ADD CONSTRAINT `fk_license_activations_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `license_activation_logs`
--
ALTER TABLE `license_activation_logs`
  ADD CONSTRAINT `fk_activation_logs_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `license_checks`
--
ALTER TABLE `license_checks`
  ADD CONSTRAINT `fk_license_checks_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_initial_balances`
--
ALTER TABLE `product_initial_balances`
  ADD CONSTRAINT `fk_initial_balances_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_initial_balances_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `fk_transaction_items_assay_office` FOREIGN KEY (`assay_office_id`) REFERENCES `assay_offices` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_transaction_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_transaction_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
