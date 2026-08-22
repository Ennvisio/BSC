-- phpMyAdmin SQL Dump
-- version 4.7.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 21, 2019 at 10:54 AM
-- Server version: 10.1.26-MariaDB
-- PHP Version: 7.1.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ennvisio_bsc`
--

-- --------------------------------------------------------

--
-- Table structure for table `boilers`
--

CREATE TABLE `boilers` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `boiler_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loaded_pressure` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boiler_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `symbol`, `created_by`, `updated_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Deck Store', 'DK DTR', 'Super Admin', '', 1, '2019-03-12 04:07:11', '2019-03-12 04:07:11'),
(2, 'Stationary', 'STN', 'Super Admin', '', 1, '2019-03-12 04:07:39', '2019-03-12 04:07:39'),
(3, 'Safety Items', 'SFT', 'Super Admin', '', 1, '2019-03-12 04:08:30', '2019-03-12 04:08:30'),
(4, 'Paint', 'PNT', 'Super Admin', '', 1, '2019-03-12 04:09:13', '2019-03-12 04:09:13');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_auth` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NOT NULL,
  `exp_date` date NOT NULL,
  `cert_copy` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `vessel_id`, `name`, `issue_auth`, `issue_date`, `exp_date`, `cert_copy`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'MCMC', 'Bangladesh Shipping Corporation', '2019-03-27', '2019-03-29', 'images/cert_copy/1553065100.jpg', 1, '2019-03-18 04:28:21', '2019-03-20 00:58:20'),
(2, 3, 'Hello', 'SFSD', '2019-03-28', '2019-03-22', 'images/cert_copy/1553077751.JPG', 1, '2019-03-20 04:29:11', '2019-03-20 04:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `dimensions`
--

CREATE TABLE `dimensions` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `length_LL` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_OA` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `breadth` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `depth` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_eng_room` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draft` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suez_geo_ton` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suez_net_ton` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pana_ton` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_not` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spreed` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hold_cap` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_gear` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_hold` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bunk_cap` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ball_cap` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `water_cap` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engines`
--

CREATE TABLE `engines` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `manu_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manu_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mod_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sets_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_cyl_set` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diam_cyl` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_stroke` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power_kw` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rpm` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `speed` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `charger` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fuel` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `framework_descriptions`
--

CREATE TABLE `framework_descriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `bulk_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_stem_rudder` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `main_breadth` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dept_tonnag_ceil` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shaft_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eng_set_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loaded_pressure` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gro_ton` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_ton` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cert_accom` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lifeboat_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rafts_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `per_accom_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rafts_req_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buoys_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jack_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imm_suit_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `therm_pro_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trans_rud_num` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `propeller` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(11) NOT NULL,
  `impa_code` int(11) NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `category_id`, `impa_code`, `name`, `unit`, `created_by`, `updated_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 251247, 'NON SLIP FINISH PAINT', '20L', 'Super Admin', '', 1, '2019-03-12 04:11:14', '2019-03-12 04:11:14'),
(2, 3, 190102, 'COTTON WORKING GLOVES', 'PAIR', 'Super Admin', '', 1, '2019-03-12 04:12:35', '2019-03-12 04:12:35'),
(3, 2, 47015, 'HARD COVER NOT BOOK, A4 SIZE, 100PG', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:14:06', '2019-03-12 04:14:06'),
(4, 2, 470121, 'SPIRAL BACK NOTEBOOK, 80 PAGES', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:15:52', '2019-03-12 04:15:52'),
(6, 2, 470138, 'POCKET NOTE BOOK, HARD COVER, 150 PG', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:16:55', '2019-03-12 04:16:55'),
(7, 2, 470127, 'REPORT PADS LINED, 50 SHEET/PAD', 'PAD', 'Super Admin', '', 1, '2019-03-12 04:18:25', '2019-03-12 04:18:25'),
(8, 2, 470164, 'THIN ONION PAPER', 'REAM', 'Super Admin', '', 1, '2019-03-12 04:19:45', '2019-03-12 04:19:45'),
(9, 2, 470163, 'THICK BOND PAPER', 'REAM', 'Super Admin', '', 1, '2019-03-12 04:20:30', '2019-03-12 04:20:30'),
(10, 4, 251275, 'LOMINOUS FINISH PAINT(RED)', '20L', 'Super Admin', '', 1, '2019-03-12 04:24:10', '2019-03-12 04:24:10'),
(11, 4, 25127, 'HARDTOP XP NONE(JOTUN)', '20L', 'Super Admin', '', 1, '2019-03-12 04:26:22', '2019-03-12 04:26:22');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2019_02_11_091238_create_surveys_table', 1),
(4, '2019_02_12_063600_create_certificates_table', 1),
(5, '2019_02_13_084750_create_vessels_table', 1),
(6, '2019_02_14_041739_create_categories_table', 1),
(7, '2019_02_14_041843_create_items_table', 1),
(8, '2019_02_14_041855_create_orders_table', 1),
(9, '2019_02_14_041907_create_order_items_table', 1),
(10, '2019_02_17_065914_create_boilers_table', 1),
(11, '2019_02_17_070347_create_vessel_particulars_table', 1),
(12, '2019_02_17_071117_create_dimensions_table', 1),
(13, '2019_02_17_084024_create_engines_table', 1),
(14, '2019_02_17_084318_create_framework_descriptions_table', 1),
(15, '2019_02_26_103228_create_roles_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `req_no` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `req_date` date NOT NULL,
  `port_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cheif_ofcr_app` tinyint(1) DEFAULT NULL,
  `master_app` tinyint(1) DEFAULT NULL,
  `chief_eng_app` tinyint(1) DEFAULT NULL,
  `ast_m_app` tinyint(1) DEFAULT NULL,
  `agm_app` tinyint(1) DEFAULT NULL,
  `gm_app` tinyint(1) DEFAULT NULL,
  `dgm_app_ssm` tinyint(1) DEFAULT NULL,
  `agm_app_ssm` tinyint(1) DEFAULT NULL,
  `am_app_ssm` tinyint(1) DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on process',
  `status_from_am` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliver_date` date DEFAULT NULL,
  `rcv_date` date DEFAULT NULL,
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ord_status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `vessel_id`, `category_id`, `req_no`, `req_date`, `port_name`, `cheif_ofcr_app`, `master_app`, `chief_eng_app`, `ast_m_app`, `agm_app`, `gm_app`, `dgm_app_ssm`, `agm_app_ssm`, `am_app_ssm`, `status`, `status_from_am`, `deliver_date`, `rcv_date`, `created_by`, `updated_by`, `ord_status`, `created_at`, `updated_at`) VALUES
(1, 3, 3, 'DK/SFT/01/2019', '2019-03-12', 'singapore', 1, 1, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'approved by srd-assistant-manager', NULL, NULL, NULL, 'Amzad Khan', NULL, 1, '2019-03-12 04:44:41', '2019-03-12 05:02:14'),
(12, 3, 4, 'DK/PNT/01/2019', '2019-03-12', 'singapore', 1, 1, NULL, 1, 1, 1, 1, 1, 1, 'delivered', 'Supplied to Ship', NULL, NULL, 'Amzad Khan', NULL, 1, '2019-03-12 04:49:02', '2019-03-13 06:41:07'),
(13, 3, 2, 'DK/STN/01/2019', '2019-03-13', 'zxcxzczx', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ready', NULL, NULL, NULL, 'Amzad Khan', NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(18, 3, 2, 'DK/STN/04/2019', '2019-03-13', 'CTG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ready', NULL, NULL, NULL, 'Amzad Khan', NULL, 1, '2019-03-13 03:09:41', '2019-03-13 03:09:41');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_qty` int(11) NOT NULL,
  `del_item_qty` int(11) DEFAULT NULL,
  `rcv_item_qty` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_id`, `item_qty`, `del_item_qty`, `rcv_item_qty`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 4, NULL, NULL, 1, '2019-03-12 04:44:41', '2019-03-12 04:44:41'),
(2, 12, 1, 3, 3, 2, 1, '2019-03-12 04:49:02', '2019-03-18 23:38:31'),
(3, 12, 10, 3, 3, 3, 1, '2019-03-12 04:49:02', '2019-03-18 23:38:31'),
(4, 12, 11, 3, 3, 3, 1, '2019-03-12 04:49:02', '2019-03-12 05:56:53'),
(5, 12, 2, 6, 5, 5, 1, '2019-03-12 04:49:02', '2019-03-12 05:56:53'),
(6, 13, 4, 4, NULL, NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(7, 13, 3, 1, NULL, NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(8, 13, 6, 3, NULL, NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(9, 13, 7, 2, NULL, NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(10, 13, 9, 2, NULL, NULL, 1, '2019-03-12 23:39:52', '2019-03-12 23:39:52'),
(11, 18, 6, 6, NULL, NULL, 1, '2019-03-13 03:09:41', '2019-03-13 03:09:41'),
(12, 18, 3, 5, NULL, NULL, 1, '2019-03-13 03:09:41', '2019-03-13 03:09:41');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `vessel_id` int(11) DEFAULT NULL,
  `role` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `user_id`, `vessel_id`, `role`, `created_by`, `updated_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'super-admin', '', NULL, 1, NULL, NULL),
(2, 2, NULL, 'am-srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:04:04', '2019-03-12 04:04:04'),
(3, 3, 3, 'operator', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:05:23', '2019-03-12 04:05:23'),
(4, 4, 3, 'chief-officer', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:35:54', '2019-03-12 04:35:54'),
(5, 5, 3, 'master', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:36:50', '2019-03-12 04:36:50'),
(6, 6, NULL, 'chief-engineer', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:38:41', '2019-03-12 04:50:01'),
(7, 7, NULL, 'agm-srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:45:56', '2019-03-12 04:45:56'),
(8, 8, NULL, 'gm-srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:48:34', '2019-03-12 04:48:34'),
(9, 9, NULL, 'dgm-ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:49:41', '2019-03-12 04:49:41'),
(10, 10, NULL, 'agm-ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:51:20', '2019-03-12 04:51:20'),
(11, 11, NULL, 'am-ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:52:25', '2019-03-12 04:52:25'),
(12, 12, NULL, 'admin', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:54:07', '2019-03-12 04:54:07'),
(13, 13, NULL, 'am-ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 05:53:31', '2019-03-12 05:53:31'),
(14, 14, 2, 'chief-officer', 'Super Admin', 'Super Admin', 0, '2019-03-20 06:04:56', '2019-03-20 08:12:20'),
(15, 15, 2, 'chief-engineer', 'Super Admin', 'Super Admin', 0, '2019-03-20 06:09:54', '2019-03-20 08:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE `surveys` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `serial` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `society_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `survey_date` date NOT NULL,
  `survey_exp_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `vessel_id`, `serial`, `name`, `status`, `society_name`, `survey_date`, `survey_exp_date`, `created_at`, `updated_at`) VALUES
(1, 3, 's#54268809', 'DDGG', 1, 'DFDRE', '2019-03-29', '2019-03-24', '2019-03-20 04:31:12', '2019-03-20 04:31:12'),
(2, 2, 's#10997394', 'dsfsd', 1, 'sdfsdf', '2019-03-27', '2019-03-30', '2019-03-20 04:33:43', '2019-03-20 04:33:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `photo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sign` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `photo`, `sign`, `password`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'superadmin@bsc.com', NULL, NULL, NULL, '$2y$10$HTX1HGHE.oBvCA1g.4ZFZeuP/aU3yIQ2RZ43sVv4uZ5CDTLnHlm.S', 1, 'rUGNvlsuaKUkN6hTgtcVw1t5vIk0aLjbOOhWl7soezRq5tUsa0RQOx3O2w7f', '2019-03-12 03:30:23', '2019-03-12 03:30:23'),
(2, 'AM SRD', 'am-srd@bsc.com', NULL, 'images/userphoto/1552388513.JPG', 'images/signature/1552388513.png', '$2y$10$yBZWOZINXPecSqT1.lT3WuYmO4ztQyvZ1hSyILBBnQEyFDTC91C2S', 1, 'fbD6DoEhqLm8K3302GjsXEuDFNTlYUdFrT6ZbDonQVGGHmkGX2aaJHixR83d', '2019-03-12 04:04:04', '2019-03-12 05:01:53'),
(3, 'Amzad Khan', 'operator1@bsc.com', NULL, 'images/userphoto/1552474972.jpg', 'images/signature/1552474972.jpg', '$2y$10$mcn8xF9giGqcI1tFJt/wKuPma0bqqg0hd2Vjcraah.I.icGRG5wXa', 1, 'drjsf2c91MMo51gOlU2OKNauqJfzAnJB5DIjmqvIDgmylrNt3VpqysKRqhhE', '2019-03-12 04:05:23', '2019-03-13 05:02:52'),
(4, 'Shakh Jamal', 'chief-officer1@bsc.com', NULL, 'images/userphoto/1552475242.png', 'images/signature/1552475242.jpg', '$2y$10$LDqrEs.FbteM.qfeQrj0burbH9R2o6vzMLxgq15ZPauA32xbDm.3i', 1, 'q7zjPlrSG1u8EHaRfCON4KRSi3zwlJLeb4GhhzOszixbFKUNUe4TUJycAnlE', '2019-03-12 04:35:54', '2019-03-13 05:07:22'),
(5, 'Master Surja Sen', 'master1@bsc.com', NULL, 'images/userphoto/1552474498.jpg', 'images/signature/1552474498.jpg', '$2y$10$ZeQx9k.yVY5SghSSAT02V.zP/zMuJ6QLIc5Ui3gxQxPiOsTVI4s3q', 1, 'wuwITbMujENKE42mKmrHbtEy9JYa6a3GPURWRyHYzY8FxjZRzNIZya8z6yc4', '2019-03-12 04:36:50', '2019-03-13 04:54:58'),
(6, 'Md. Miron Ahmed', 'chief-engineer1@bsc.com', NULL, NULL, NULL, '$2y$10$qEECekMRc2ieq8Cl36arSuRgWz1MrAuUQMm7MQtFYC3mABJ.hHvLC', 1, NULL, '2019-03-12 04:38:41', '2019-03-12 04:50:01'),
(7, 'Md. Jamal Ahmed', 'agm-srd@bsc.com', NULL, NULL, NULL, '$2y$10$7PA48ZwfUFYRm3u2k3T3SeuinveKQejPlObIzURK.nWTG6tyZ./ha', 1, 'Dcezj4YLByCBTGSxAZ9oZ1Qefc8B35u33qHjRttJud3ngCWlKaC7wGbToTKV', '2019-03-12 04:45:56', '2019-03-12 04:50:30'),
(8, 'Md. Rajan Ahmed', 'gm-srd@bsc.com', NULL, NULL, NULL, '$2y$10$QXhRQ3SUoUY0O.8EN1TJ3OxzmmhrbvZykNpakaGmk.NzqfGg8szxe', 1, 'BZbF4Uzy3z7mm0O5SfMggSS3s4WB2Jf1v3frHXolnuxphGH0Rk4Q9jkPHJsj', '2019-03-12 04:48:34', '2019-03-12 04:50:14'),
(9, 'Md. Kairul Ahmed', 'dgm-ssm@bsc.com', NULL, NULL, NULL, '$2y$10$sRc0xz/tswjWsI/nPbMsQ.LbY/O92dy.qk6TDsH65BeyrZGeSR5dm', 1, 'LiyEvqghRdM1Bn392NWrfeahoaLKFcnqCxmfLADU0HqXEFh2eVAeB11RufTK', '2019-03-12 04:49:41', '2019-03-12 04:49:41'),
(10, 'Md. Khalil Ahmed', 'agm-ssm@bsc.com', NULL, NULL, NULL, '$2y$10$Fic1aEmju6ptifVN8kXBJe99CQS4eIYaqVJXcEwFpg7y62JskGyeS', 1, 'EGPBdrwcwjagRBLXhsP2RecEIoJn0LVWFOxIryhecsPW8F7BGUNuo96HOADD', '2019-03-12 04:51:20', '2019-03-12 04:51:20'),
(11, 'Md. Elias Ahmed', 'am-ssm@bsc.com', NULL, NULL, NULL, '$2y$10$HP1CIOL2ivoLsLGwBnvKsOWtfGw8zANl70bsTh5AW5wXaaQ3wmGlG', 1, 'fWStv3RwAqO8L6TiI4lv5r2xDI92xXNyEvVqTmcDI3AkD0CN9aWwjY27BX1U', '2019-03-12 04:52:25', '2019-03-12 04:52:25'),
(12, 'Md. Admin Ahmed', 'admin@bsc.com', NULL, NULL, NULL, '$2y$10$8sxrdoCWRhOBxfZcYxMxCuBJrhECYG0DPAb/zb0XhAUFJGq8NJS1y', 1, NULL, '2019-03-12 04:54:07', '2019-03-12 04:54:07'),
(13, 'rafiq', 'am-ssm2@bsc.com', NULL, NULL, NULL, '$2y$10$yZw0G6mEaES4ChavwPbrzOSoikT9u9yN4flA6IwUPnr6fEtkVa.Ca', 1, 'YGn2ijrDHSajAE026Rxj1A9E6X0BkCPOEdHU8iM6orQjQJepANROy207dzK1', '2019-03-12 05:53:31', '2019-03-12 05:53:31'),
(14, 'bayejid89', 'bayejid89@gmail.com', NULL, NULL, NULL, '$2y$10$Rpr9F2yWec28vpfnvwDtS.McjEgXWppXHTKl.lUS6i.p/vNwvzLQu', 0, NULL, '2019-03-20 06:04:56', '2019-03-20 08:12:20'),
(15, 'dsfsdfsda', 'superadmindfdf@bsc.com', NULL, NULL, NULL, '$2y$10$0oLvC.IMo6PMmr4vnqA9o.WyfjiZMk4nbvdbyT8mEQlhc5kiOh0LG', 0, NULL, '2019-03-20 06:09:54', '2019-03-20 08:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `vessels`
--

CREATE TABLE `vessels` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `master_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `master_cert_no` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `master_cert_validity` date NOT NULL,
  `ch_eng_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ch_eng_cert_no` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ch_eng_cert_validity` date NOT NULL,
  `prev_port_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_reg_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vessels`
--

INSERT INTO `vessels` (`id`, `name`, `owner_name`, `owner_address`, `manager_name`, `manager_address`, `master_name`, `master_cert_no`, `master_cert_validity`, `ch_eng_name`, `ch_eng_cert_no`, `ch_eng_cert_validity`, `prev_port_no`, `prev_reg_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'asdsadsa', 'd asdsa', 'dsa d', 'sadasd', 'asdas', 'asda', 'sadsa', '2019-03-28', 'asdasasd', 'asdasd', '2019-03-30', NULL, NULL, 0, '2019-03-12 03:59:57', '2019-03-12 04:00:11'),
(2, 'Banglar Agrajatra', 'Md. Hasan Jubayer', 'BSC Babhan, Chattragram', 'Rafiq Islam', 'Janata Gangrad', 'Raj Hanas', 'CDGDFDn', '2019-03-31', 'Digital Pvt', 'CCDFdfd', '2019-03-31', NULL, NULL, 1, '2019-03-12 04:00:54', '2019-03-12 04:00:54'),
(3, 'M. V. BANGLAR SAMRIDDHI', 'Bangladesh Shipping Corporation', 'Chottrogram', '-', 'Chottrogram', 'Miron MD. Saifuddin', 'COC0024746', '2019-02-14', 'Mohammad Kamal Hossain', 'BD200123', '2019-08-16', NULL, NULL, 1, '2019-03-12 04:03:01', '2019-03-12 04:03:01');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_particulars`
--

CREATE TABLE `vessel_particulars` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_sign` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imo_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nrt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dwt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `off_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keel_lay_date` date DEFAULT NULL,
  `launch_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `cert_date` date DEFAULT NULL,
  `built_year` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `built_loc` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `steam_motor_propelled` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `builder_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `builder_address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mast_no` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rigged` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stem` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stern` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `build` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `boilers`
--
ALTER TABLE `boilers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_name_unique` (`name`),
  ADD UNIQUE KEY `categories_symbol_unique` (`symbol`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dimensions`
--
ALTER TABLE `dimensions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `engines`
--
ALTER TABLE `engines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `framework_descriptions`
--
ALTER TABLE `framework_descriptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_req_no_unique` (`req_no`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surveys_serial_unique` (`serial`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `vessels`
--
ALTER TABLE `vessels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vessel_particulars`
--
ALTER TABLE `vessel_particulars`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `boilers`
--
ALTER TABLE `boilers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `dimensions`
--
ALTER TABLE `dimensions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `engines`
--
ALTER TABLE `engines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `framework_descriptions`
--
ALTER TABLE `framework_descriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
--
-- AUTO_INCREMENT for table `vessels`
--
ALTER TABLE `vessels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `vessel_particulars`
--
ALTER TABLE `vessel_particulars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
