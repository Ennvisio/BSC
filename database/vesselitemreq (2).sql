-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2019 at 09:47 PM
-- Server version: 5.7.25
-- PHP Version: 7.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vesselitemreq`
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
(4, 'Paint', 'PNT', 'Super Admin', '', 1, '2019-03-12 04:09:13', '2019-04-02 00:36:58'),
(5, 'IMO SYMBOLS', 'SYM', 'Super Admin', '', 1, '2019-04-11 05:42:11', '2019-04-11 05:42:11'),
(6, 'SMPEP ITEMS', 'SMPEP', 'Super Admin', '', 1, '2019-04-15 23:29:37', '2019-04-15 23:29:37'),
(7, 'SAMPLE BOTTLE', 'SMPL', 'Super Admin', '', 1, '2019-04-15 23:41:24', '2019-04-15 23:41:24'),
(8, 'SALOON STORE', 'SLN', 'Super Admin', '', 1, '2019-04-15 23:46:02', '2019-04-15 23:46:02'),
(9, 'PUBLICATION', 'PUB', 'Super Admin', '', 1, '2019-04-16 00:07:35', '2019-04-16 00:07:35'),
(10, 'FLAG ITEMS', 'FLG', 'Super Admin', '', 1, '2019-04-16 00:17:15', '2019-04-16 00:17:15'),
(11, 'DK EMERGENCY', 'E\'CY', 'Super Admin', '', 1, '2019-04-16 00:21:01', '2019-04-16 00:21:01'),
(12, 'BA COMPRESSOR', 'EM\'CY', 'Super Admin', '', 1, '2019-04-16 00:38:59', '2019-04-16 00:38:59'),
(13, 'WALL WASH TEST KIT', 'E\'CY-', 'Super Admin', '', 1, '2019-04-16 00:59:17', '2019-04-16 00:59:17'),
(14, 'sdasda', 'asd', 'Amzad Khan', '', 0, '2019-04-23 07:45:29', '2019-04-23 07:45:41');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cert_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `name`, `cert_code`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'LOAD LINE', 'LOAD LINE', 1, 1, NULL, NULL, NULL),
(2, 'SAFCOM', 'SAFCOM', 1, 1, NULL, NULL, '2019-04-23 04:45:10'),
(3, 'MARJQE', 'MARJQE', 1, 1, NULL, NULL, NULL),
(4, 'SAFTY EQUEP', 'SAFTY EQUEP', 1, 1, NULL, NULL, NULL),
(5, 'SAFTY RADIO', 'SAFTY RADIO', 1, 1, NULL, NULL, NULL),
(6, 'LIFAATT', 'LIFAATT', 1, 1, NULL, NULL, NULL),
(7, 'DEEATT', 'DEEATT', 1, 1, NULL, NULL, NULL),
(8, 'C LEAR QUARE', 'C LEAR QUARE', 1, 1, NULL, NULL, NULL),
(9, 'C AEA# ANNUAL', 'C AEA# ANNUAL', 1, 1, NULL, NULL, NULL),
(10, 'MOBILE #TATHEM', 'MOBILE #TATHEM', 1, 1, NULL, NULL, NULL),
(11, 'CO2', 'CO2', 1, 1, NULL, NULL, NULL);

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
  `impa_code` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
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
(1, 4, '251247', 'NON SLIP FINISH PAINT', '20L', 'Super Admin', '', 1, '2019-03-12 04:11:14', '2019-03-12 04:11:14'),
(2, 3, '190102', 'COTTON WORKING GLOVES', 'PAIR', 'Super Admin', '', 1, '2019-03-12 04:12:35', '2019-03-12 04:12:35'),
(3, 2, '47015', 'HARD COVER NOT BOOK, A4 SIZE, 100PG', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:14:06', '2019-03-12 04:14:06'),
(4, 2, '470121', 'SPIRAL BACK NOTEBOOK, 80 PAGES', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:15:52', '2019-03-12 04:15:52'),
(6, 2, '470138', 'POCKET NOTE BOOK, HARD COVER, 150 PG', 'VOL', 'Super Admin', '', 1, '2019-03-12 04:16:55', '2019-03-12 04:16:55'),
(7, 2, '470127', 'REPORT PADS LINED, 50 SHEET/PAD', 'PAD', 'Super Admin', '', 1, '2019-03-12 04:18:25', '2019-03-12 04:18:25'),
(8, 2, '470164', 'THIN ONION PAPER', 'REAM', 'Super Admin', '', 1, '2019-03-12 04:19:45', '2019-03-12 04:19:45'),
(9, 2, '470163', 'THICK BOND PAPER', 'REAM', 'Super Admin', '', 1, '2019-03-12 04:20:30', '2019-03-12 04:20:30'),
(10, 4, '251275', 'LOMINOUS FINISH PAINT(RED)', '20L', 'Super Admin', '', 1, '2019-03-12 04:24:10', '2019-03-12 04:24:10'),
(11, 4, '25127', 'HARDTOP XP NONE(JOTUN)', '20L', 'Super Admin', '', 1, '2019-03-12 04:26:22', '2019-04-02 00:36:58'),
(12, 5, '334100', 'LIFE BOAT (150X150)MM', 'PC', 'Super Admin', '', 1, '2019-04-11 05:44:44', '2019-04-11 05:44:44'),
(13, 5, '334123', 'LIFE BOAT (300X300)MM', 'PC', 'Super Admin', '', 1, '2019-04-11 05:48:07', '2019-04-11 05:48:07'),
(14, 5, '33.4102', 'LIFE RAFT(150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:55:35', '2019-04-15 22:55:35'),
(15, 5, '33.4103', 'DAVIT LAUNCH LIFE RAFT (LIFE RAFT(150X150)MM', 'PC', 'Super Admin', '', 1, '2019-04-15 22:56:09', '2019-04-15 22:56:09'),
(16, 5, '33.4104', 'EMBERKATION LADDER LIFE RAFT(150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:56:46', '2019-04-15 22:56:46'),
(17, 5, '33.4105', 'EMBERKATION SLIDE LIFE RAFT(150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:57:11', '2019-04-15 22:57:11'),
(18, 5, '33.4106', 'LIFE BUOY (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:57:32', '2019-04-15 22:57:32'),
(19, 5, '33.4107', 'LIFE BUOY WITH LINE (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:57:51', '2019-04-15 22:57:51'),
(20, 5, '33.4108', 'LIFE BUOY WITH LIGHT (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:58:18', '2019-04-15 22:58:18'),
(21, 5, '33.4109', 'LIFEBUOY WITH LINE & LIGHT(150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:58:40', '2019-04-15 22:58:40'),
(22, 5, '33.4110', 'LIFE JACKET (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:58:58', '2019-04-15 22:58:58'),
(23, 5, '33.4111', 'CHILD\'S LIFE JACKET (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:59:20', '2019-04-15 22:59:20'),
(24, 5, '33.4142', 'INFANT LIFE JACKET (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:59:36', '2019-04-15 22:59:36'),
(25, 5, '33.4143', 'LARGE LIFE JACKET (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 22:59:54', '2019-04-15 22:59:54'),
(26, 5, '33.4112', 'IMMERSION SUIT (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:00:12', '2019-04-15 23:00:12'),
(27, 5, '33.4113', 'SURVIVAL CRAFT PORTABLE RADIO (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:00:29', '2019-04-15 23:00:29'),
(28, 5, '33.4114', 'EPIRB', 'PC', 'Super Admin', '', 1, '2019-04-15 23:00:51', '2019-04-15 23:00:51'),
(29, 5, '33.4115', 'RADAR TRANSPONDER (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:01:08', '2019-04-15 23:01:08'),
(30, 5, '33.4116', 'SURVIVAL CRAFT DISTRESS SIGNAL (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:01:28', '2019-04-15 23:01:28'),
(31, 5, '33.4117', 'ROCKET PARACHUTE FLARE (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:01:44', '2019-04-15 23:01:44'),
(32, 5, '33.4118', 'LINE THROWING APPLIANCE (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:02:01', '2019-04-15 23:02:01'),
(33, 5, '33.4119', 'ASSEMBLY STATION (150X150)mm, (300X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:02:22', '2019-04-15 23:02:22'),
(34, 5, '33.4121', 'STRETCHER (150X150)mm', 'PC', 'Super Admin', 'Super Admin', 1, '2019-04-15 23:02:48', '2019-04-15 23:03:43'),
(35, 5, '33.4125', 'THERMAL PROTECTIVE AID (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:03:05', '2019-04-15 23:03:05'),
(36, 5, '33.4126', 'ANTI-EXPOSURE SUIT (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:03:22', '2019-04-15 23:03:22'),
(37, 5, '33.4127', 'MEDICAL LOCKER (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:05:30', '2019-04-15 23:05:30'),
(38, 5, '33.4129', 'EEBD', 'PC', 'Super Admin', '', 1, '2019-04-15 23:05:47', '2019-04-15 23:05:47'),
(39, 5, '33.4141', 'MUSTER STATION (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:06:05', '2019-04-15 23:06:05'),
(40, 5, '33.4420', 'RIGHT ARROW(150mm X 150 mm)', 'PC', 'Super Admin', '', 1, '2019-04-15 23:06:21', '2019-04-15 23:06:21'),
(41, 5, '33.4421', 'ANGLE ARROW(150mm X 150 mm)', 'PC', 'Super Admin', '', 1, '2019-04-15 23:06:40', '2019-04-15 23:06:40'),
(42, 5, '33.4171', 'FIRST AID (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:06:59', '2019-04-15 23:06:59'),
(43, 5, '33.4176', 'EMERGENCY SHOWER (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:07:14', '2019-04-15 23:07:14'),
(44, 5, '33.4177', 'EMERGENCY EYE WASH (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:07:43', '2019-04-15 23:07:43'),
(45, 5, '33.4178', 'EMERGENCY TELEPHONE (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:07:58', '2019-04-15 23:07:58'),
(46, 5, '33.4179', 'EMERGENCY STOCK (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:08:17', '2019-04-15 23:08:17'),
(47, 5, '33.4180', 'DRINKING WATER (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:09:11', '2019-04-15 23:09:11'),
(48, 5, '33.4181', 'POLLUTION CONTROL EQUIPMENT (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:09:27', '2019-04-15 23:09:27'),
(49, 5, '33.4182', 'BREATHING APPARATUS (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:09:47', '2019-04-15 23:09:47'),
(50, 5, '33.4184', 'SAFETY EQUIPMENT (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:10:05', '2019-04-15 23:10:05'),
(51, 5, '33.4185', 'SMOKING AREA (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:10:28', '2019-04-15 23:10:28'),
(52, 5, '33.4187', 'BREAK GLASS IN EMERGENCY (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:10:45', '2019-04-15 23:10:45'),
(53, 5, '33.4188', 'ESCAPE LADDER (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:11:02', '2019-04-15 23:11:02'),
(54, 5, '33.4155', 'GENERAL ALARM (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:11:17', '2019-04-15 23:11:17'),
(55, 5, '33.4427', '(150X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:11:44', '2019-04-15 23:11:44'),
(56, 5, '33.4401', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:12:03', '2019-04-15 23:12:03'),
(57, 5, '33.4409', 'EXIT (150X40)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:12:19', '2019-04-15 23:12:19'),
(58, 5, '33.4422', '(150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:12:36', '2019-04-15 23:12:36'),
(59, 5, '33.4404', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:12:53', '2019-04-15 23:12:53'),
(60, 5, '33.4402', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:13:37', '2019-04-15 23:13:37'),
(61, 5, '33.4406', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:14:02', '2019-04-15 23:14:02'),
(62, 5, '33.4403', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:14:26', '2019-04-15 23:14:26'),
(63, 5, '33.4405', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:14:53', '2019-04-15 23:14:53'),
(64, 5, '33.4407', 'EXIT (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:15:14', '2019-04-15 23:15:14'),
(65, 5, '33.4343', 'ESCAPE DOOR (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:15:31', '2019-04-15 23:15:31'),
(66, 5, '33.4345', 'EMERGENCY ESCAPE (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:15:48', '2019-04-15 23:15:48'),
(67, 5, '33.4480', 'PUSH TO OPEN (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:16:09', '2019-04-15 23:16:09'),
(68, 5, '33.4481', 'PULL TO OPEN (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:16:25', '2019-04-15 23:16:25'),
(69, 5, '33.4482', 'SLIDE TO OPEN (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:16:41', '2019-04-15 23:16:41'),
(70, 5, '33.4483', 'SLIDE TO OPEN (100X300)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:16:55', '2019-04-15 23:16:55'),
(71, 5, '33.6001', 'FIRE PLAN (150X400)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:17:15', '2019-04-15 23:17:15'),
(72, 5, '33.6002', 'SWITCH FOR FIRE ALARM (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:19:06', '2019-04-15 23:19:06'),
(73, 5, '33.6003', 'HORN,FIRE ALARM (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:19:27', '2019-04-15 23:19:27'),
(74, 5, '33.6004', 'BELL,FIRE ALARM (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:19:45', '2019-04-15 23:19:45'),
(75, 5, '33.6007', 'SPACE PROTECTED BY CO2 (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:20:03', '2019-04-15 23:20:03'),
(76, 5, '33.6008', 'CO2 HORN (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:20:22', '2019-04-15 23:20:22'),
(77, 5, '33.6009', 'CO2 RELEASE STATION (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:21:24', '2019-04-15 23:21:24'),
(78, 5, '33.6013', 'FOAM INSTALLATION (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:21:41', '2019-04-15 23:21:41'),
(79, 5, '33.6014', 'FOAM MONITOR (GUN) (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:21:57', '2019-04-15 23:21:57'),
(80, 5, '33.6015', 'FOAM NOZZLE (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:22:14', '2019-04-15 23:22:14'),
(81, 5, '33.6017', 'FOAM VALVE (150X150)mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:22:34', '2019-04-15 23:22:34'),
(82, 6, '-', 'SORBENT BOOM 3mX10cm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:31:46', '2019-04-15 23:31:46'),
(83, 6, '-', 'SORBENT PADS 48cmX43cm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:32:05', '2019-04-15 23:32:05'),
(84, 6, '-', 'SORBENT PADS 97cmX 86 cm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:32:28', '2019-04-15 23:32:28'),
(85, 6, '-', 'SORBENT PADS 44mX48 cm', 'ROLL', 'Super Admin', '', 1, '2019-04-15 23:32:55', '2019-04-15 23:32:55'),
(86, 6, '-', 'SEACARE OSD', 'LTR', 'Super Admin', '', 1, '2019-04-15 23:33:17', '2019-04-15 23:33:17'),
(87, 6, '-', 'JET SPRAY', 'LTR', 'Super Admin', '', 1, '2019-04-15 23:33:41', '2019-04-15 23:33:41'),
(88, 6, '-', 'RUBBER BOOTS(42)', 'PAIR', 'Super Admin', '', 1, '2019-04-15 23:34:12', '2019-04-15 23:34:12'),
(89, 6, '-', 'RUBBER BOOTS(43)', 'PAIR', 'Super Admin', '', 1, '2019-04-15 23:34:30', '2019-04-15 23:34:30'),
(90, 6, '-', 'RUBBER BOOTS(44)', 'PAIR', 'Super Admin', '', 1, '2019-04-15 23:34:48', '2019-04-15 23:34:48'),
(91, 6, '-', 'RUBBER BOOTS(45)', 'PAIR', 'Super Admin', '', 1, '2019-04-15 23:35:07', '2019-04-15 23:35:07'),
(92, 6, '-', 'OIL / CHEM RESISTANT GLOVES', 'PAIR', 'Super Admin', '', 1, '2019-04-15 23:35:31', '2019-04-15 23:35:31'),
(93, 6, '-', 'OVERALL SUITS', 'PCS', 'Super Admin', '', 1, '2019-04-15 23:35:54', '2019-04-15 23:35:54'),
(94, 7, '-', 'MANIFOLD SAMPLE COLLECTING BOTTLE,TRANSPARENT THICK GLASS MADE , 1000CC, Ht 10 inch & DIAMTETRE 3.5 inch', 'PC', 'Super Admin', '', 1, '2019-04-15 23:42:08', '2019-04-15 23:42:08'),
(95, 7, '651377', 'SAMPLING BOTTLE BOTTOM-COLLECT STAINLESS STEEL 500CC', 'PC', 'Super Admin', '', 1, '2019-04-15 23:43:20', '2019-04-15 23:43:20'),
(96, 7, '651375', 'SAMPLING BOTTLE BOTTOM-COLLECT BRASS 500CC', 'PC', 'Super Admin', '', 1, '2019-04-15 23:43:43', '2019-04-15 23:43:43'),
(97, 7, '651371', 'SAMPLING BOTTLE MOUTH-COLLECT BRASS 500CC', 'PC', 'Super Admin', '', 1, '2019-04-15 23:44:04', '2019-04-15 23:44:04'),
(98, 7, '651373', 'SAMPLING BOTTLE MOUTH-COLLECT STAINLESS STEEL 500CC', 'PC', 'Super Admin', '', 1, '2019-04-15 23:44:39', '2019-04-15 23:44:39'),
(99, 8, '170101', 'DINNER KNIFE,233mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:46:54', '2019-04-15 23:46:54'),
(100, 8, '170102', 'DINNER FORK,210 mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:47:17', '2019-04-15 23:47:17'),
(101, 8, '170103', 'TABLE SPOON,203 mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:47:34', '2019-04-15 23:47:34'),
(102, 8, '170104', 'SOUP SPOON,172mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:47:53', '2019-04-15 23:47:53'),
(103, 8, '170117', 'TEA SPOON,203mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:48:11', '2019-04-15 23:48:11'),
(104, 8, '170120', 'SERVING SPOON,300mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:48:29', '2019-04-15 23:48:29'),
(105, 8, '170121', 'SERVING FORK,220mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:48:58', '2019-04-15 23:48:58'),
(106, 8, '170107', 'DESSERT KNIFE,213mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:49:19', '2019-04-15 23:49:19'),
(107, 8, '170123', 'SUGAR LADLE,115mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:49:35', '2019-04-15 23:49:35'),
(108, 8, '170267', 'CURTLY BOXES,BENCH STAND,180 X 440 X 230 mm', 'PC', 'Super Admin', '', 1, '2019-04-15 23:49:54', '2019-04-15 23:49:54'),
(109, 2, '470105', 'HARD COVER NOTE BOOK, A4 SIZE,100 PG', 'VOL', 'Super Admin', '', 1, '2019-04-15 23:53:07', '2019-04-15 23:53:07'),
(110, 2, '470121', 'SPIRAL BACK NOEBOOK,80 PAGES', 'VOL', 'Super Admin', 'Super Admin', 1, '2019-04-15 23:53:41', '2019-04-15 23:56:27'),
(111, 2, '470138', 'POCKET NOTE BOOK, HARD COVER,150 PG', 'VOL', 'Super Admin', 'Super Admin', 1, '2019-04-15 23:54:08', '2019-04-15 23:56:14'),
(112, 2, '470127', 'REPORT PADS LINED, 50 SHEET/PAD', 'PAD', 'Super Admin', 'Super Admin', 1, '2019-04-15 23:54:32', '2019-04-15 23:55:58'),
(113, 2, '470164', 'THIN ONION PAPER', 'REAM', 'Super Admin', '', 1, '2019-04-15 23:55:16', '2019-04-15 23:55:16'),
(114, 2, '470163', 'THICK BOND PAPER', 'REAM', 'Super Admin', 'Super Admin', 1, '2019-04-15 23:57:09', '2019-04-15 23:57:26'),
(115, 2, '470131', 'MEMORANDUM PADS, 125 X 88', 'PAD', 'Super Admin', '', 1, '2019-04-16 00:04:19', '2019-04-16 00:04:19'),
(116, 2, '470171', 'SECTIONED PAPER,40/PAD', 'PAD', 'Super Admin', '', 1, '2019-04-16 00:04:39', '2019-04-16 00:04:39'),
(117, 2, '470142', 'SCRATCH PAD', 'PAD', 'Super Admin', '', 1, '2019-04-16 00:04:58', '2019-04-16 00:04:58'),
(118, 2, '470194', 'CARBON PAPER,100 SHEET', 'BOX', 'Super Admin', '', 1, '2019-04-16 00:05:29', '2019-04-16 00:05:29'),
(119, 9, '-', 'BRIDGE PROCEDURES GUIDE WITH CD', 'PC', 'Super Admin', '', 1, '2019-04-16 00:09:06', '2019-04-16 00:09:06'),
(120, 9, '-', 'ICS TANKER SAFETY GUIDE FOR CHEMICAL', 'PC', 'Super Admin', '', 1, '2019-04-16 00:09:34', '2019-04-16 00:09:34'),
(121, 9, '-', 'ICS GUIDE TO HELICOPTER/ SHIP OPERATION WITH CD', 'PC', 'Super Admin', '', 1, '2019-04-16 00:10:05', '2019-04-16 00:10:05'),
(122, 9, '-', 'INTERNATIONAL CODE OF SIGNALS', 'PC', 'Super Admin', '', 1, '2019-04-16 00:10:28', '2019-04-16 00:10:28'),
(123, 9, '-', 'TANK CLEANING GUIDE', 'PC', 'Super Admin', '', 1, '2019-04-16 00:11:06', '2019-04-16 00:11:06'),
(124, 9, '-', 'GUIDE TO PORT ENTRY(ALL VOL)', 'PC', 'Super Admin', '', 1, '2019-04-16 00:11:26', '2019-04-16 00:11:26'),
(125, 9, '-', 'NORIES NAUTICAL TABLE', 'PC', 'Super Admin', '', 1, '2019-04-16 00:11:54', '2019-04-16 00:11:54'),
(126, 9, '-', 'SIGHT REDUCTION TABLE', 'PC', 'Super Admin', '', 1, '2019-04-16 00:12:14', '2019-04-16 00:12:14'),
(127, 9, '-', 'STAR FINDER', 'PC', 'Super Admin', '', 1, '2019-04-16 00:12:28', '2019-04-16 00:12:28'),
(128, 9, '-', 'OCIMF MOORING GUIDELINES', '-', 'Super Admin', '', 1, '2019-04-16 00:13:16', '2019-04-16 00:13:16'),
(129, 10, '-', 'ALL INTERNATIONAL FLAGS(NATIONAL)', 'SET', 'Super Admin', '', 1, '2019-04-16 00:18:06', '2019-04-16 00:18:06'),
(130, 10, '371502', 'ALPHABETICAL FLAGS(A-Z) 26\'s/SET', 'SET', 'Super Admin', '', 1, '2019-04-16 00:18:26', '2019-04-16 00:18:26'),
(131, 10, '371909', 'ENSIGN BANGLADESH FLAG(3 X 4 )', 'PC', 'Super Admin', '', 1, '2019-04-16 00:18:42', '2019-04-16 00:18:42'),
(132, 11, 'JRC NCR-333', 'NAVTEX,PRINTER PAPER NCR-333', 'ROLL', 'Super Admin', '', 1, '2019-04-16 00:21:41', '2019-04-16 00:21:41'),
(133, 11, 'GLN 4500', 'IG SYSTEM PRINTER PAPER SERIES Gln 4500-0.15 FU Doc nr 11215053R00 Basssed on 65010A053R08', 'ROLL', 'Super Admin', '', 1, '2019-04-16 00:22:18', '2019-04-16 00:22:18'),
(134, 11, '470293', 'LAMINATING POUCH FILMS, 20\'S/PKT', 'PKT', 'Super Admin', '', 1, '2019-04-16 00:22:44', '2019-04-16 00:22:44'),
(135, 11, '232907', 'RAGS OVER 90% COTTON', 'KG', 'Super Admin', '', 1, '2019-04-16 00:23:03', '2019-04-16 00:23:03'),
(136, 11, '232946', 'SAW DUST', 'KG', 'Super Admin', '', 1, '2019-04-16 00:23:19', '2019-04-16 00:23:19'),
(137, 11, '174643', 'REFRIGERATOR 229V 100 LTR FOR CCR', 'SET', 'Super Admin', '', 1, '2019-04-16 00:23:38', '2019-04-16 00:23:38'),
(138, 11, '174513', 'ELECTRIC CATLEE 2.1 LTR 220V', 'PC', 'Super Admin', '', 1, '2019-04-16 00:23:57', '2019-04-16 00:23:57'),
(139, 11, '174122', 'BUCKET PLASTIC 10 LTR', 'PC', 'Super Admin', '', 1, '2019-04-16 00:24:12', '2019-04-16 00:24:12'),
(140, 11, '172227', 'TEA STRAINER DIA 85 mm', 'PC', 'Super Admin', '', 1, '2019-04-16 00:24:37', '2019-04-16 00:24:37'),
(141, 11, '170415', 'MUG PLASTIC 260CC', 'PC', 'Super Admin', '', 1, '2019-04-16 00:25:02', '2019-04-16 00:25:02'),
(142, 11, 'PKT', 'GARBAGE BAG COLOUR BLACK', '174169', 'Super Admin', '', 1, '2019-04-16 00:25:18', '2019-04-16 00:25:18'),
(143, 11, '175096', 'MICRO WAVE OVEN CAPACITY 20 LTR 220V FOR MAKING HOT', 'PC', 'Super Admin', '', 1, '2019-04-16 00:25:38', '2019-04-16 00:25:38'),
(144, 11, 'PKT', 'A4 SIZE PAPER 500 PAGE/RIM', '472186', 'Super Admin', '', 1, '2019-04-16 00:25:56', '2019-04-16 00:25:56'),
(145, 12, '-', 'MIRACLE CARGO TANK CLEANING GUIDE&DATABASE( TANK CLEANING SOFTWARE)', 'PC', 'Super Admin', '', 1, '2019-04-16 00:39:30', '2019-04-16 00:39:30'),
(146, 12, '-', 'BREATHING APPARATUS COMPRESSOR,MODEL MCH 13/ET,CHARGING RATE 215Ltr PER Min(13 M3/hr),7.5 cfm,WORKING PRESSURE 225bar-3.20 psi,POWER 4KW, 34 X 25.4 X 18\", NAME OF Mfr AEROTECNICA COLTRI', 'SET', 'Super Admin', '', 1, '2019-04-16 00:40:00', '2019-04-16 00:40:00'),
(147, 11, '174643', 'REFRIGERATOR 220V 100 LTR FOR CCR', 'SET', 'Super Admin', '', 1, '2019-04-16 00:42:16', '2019-04-16 00:42:16'),
(148, 11, '174513', 'ELECTRIC CATLEE 2.1 LTR 220V', 'PC', 'Super Admin', '', 1, '2019-04-16 00:42:38', '2019-04-16 00:42:38'),
(149, 11, '174122', 'BUCKET PLASTIC 10 LTR', 'PC', 'Super Admin', '', 1, '2019-04-16 00:42:56', '2019-04-16 00:42:56'),
(150, 11, '172227', 'TEA STRAINER DIA 85 mm', 'PC', 'Super Admin', '', 1, '2019-04-16 00:43:16', '2019-04-16 00:43:16'),
(151, 11, '170415', 'MUG PLASTIC 260CC', 'PC', 'Super Admin', '', 1, '2019-04-16 00:43:37', '2019-04-16 00:43:37'),
(152, 11, '174169', 'GARBAGE BAG COLOUR BLACK', 'PKT', 'Super Admin', '', 1, '2019-04-16 00:44:03', '2019-04-16 00:44:03'),
(153, 11, '175096', 'MICRO WAVE OVEN CAPACITY 20 LTR 220V FOR MAKING HOT', 'PC', 'Super Admin', '', 1, '2019-04-16 00:44:27', '2019-04-16 00:44:27'),
(154, 11, '472186', 'A4 SIZE PAPER 500 PAGE/RIM', 'PKT', 'Super Admin', '', 1, '2019-04-16 00:44:45', '2019-04-16 00:44:45'),
(155, 11, '331225', 'ALCHOHOL TESTER MOUTH PIECE,100\'s/PKT', 'PKT', 'Super Admin', '', 1, '2019-04-16 00:45:12', '2019-04-16 00:45:12'),
(156, 11, 'JRC NCR-333', 'NAVTEX,PRINTER PAPER NCR-333', 'ROLL', 'Super Admin', '', 1, '2019-04-16 00:51:53', '2019-04-16 00:51:53'),
(157, 11, 'GLN 4500', 'IG SYSTEM PRINTER PAPER SERIES Gln 4500-0.15 FU Doc nr 11215053R00 Basssed on 65010A053R08', 'ROLL', 'Super Admin', '', 1, '2019-04-16 00:52:23', '2019-04-16 00:52:23'),
(158, 11, '470293', 'LAMINATING POUCH FILMS, 20\'S/PKT', 'PKT', 'Super Admin', '', 1, '2019-04-16 00:52:44', '2019-04-16 00:52:44'),
(159, 11, '232907', 'RAGS OVER 90% COTTON', 'KG', 'Super Admin', '', 1, '2019-04-16 00:53:14', '2019-04-16 00:53:14'),
(160, 11, '232946', 'SAW DUST', 'KG', 'Super Admin', '', 1, '2019-04-16 00:53:49', '2019-04-16 00:53:49'),
(161, 11, '174643', 'REFRIGERATOR 220V 100 LTR FOR CCR', 'SET', 'Super Admin', '', 1, '2019-04-16 00:54:10', '2019-04-16 00:54:10'),
(162, 11, '174513', 'ELECTRIC CATLEE 2.1 LTR 220V', 'PC', 'Super Admin', '', 1, '2019-04-16 00:54:28', '2019-04-16 00:54:28'),
(163, 11, '174122', 'BUCKET PLASTIC 10 LTR', 'PC', 'Super Admin', '', 1, '2019-04-16 00:54:45', '2019-04-16 00:54:45'),
(164, 11, '172227', 'TEA STRAINER DIA 85 mm', 'PC', 'Super Admin', '', 1, '2019-04-16 00:55:04', '2019-04-16 00:55:04'),
(165, 11, '170415', 'MUG PLASTIC 260CC', 'PC', 'Super Admin', '', 1, '2019-04-16 00:55:24', '2019-04-16 00:55:24'),
(166, 13, '-', 'WALL WASH KIT-AN ABSOLUTE TEST KIT CAPABLE OF PERFORMING CHLORIDE TEST,HC TEST,PTT TIME TEST,PH TEST,A ACID WASH COLOR TEST AND STAINLESS STEEL PASSIVATION TEST', 'SET', 'Super Admin', '', 1, '2019-04-16 00:59:48', '2019-04-16 00:59:48');

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
(5, '2019_02_13_084750_create_vessels_table', 1),
(6, '2019_02_14_041739_create_categories_table', 1),
(7, '2019_02_14_041843_create_items_table', 1),
(9, '2019_02_14_041907_create_order_items_table', 1),
(10, '2019_02_17_065914_create_boilers_table', 1),
(11, '2019_02_17_070347_create_vessel_particulars_table', 1),
(12, '2019_02_17_071117_create_dimensions_table', 1),
(13, '2019_02_17_084024_create_engines_table', 1),
(14, '2019_02_17_084318_create_framework_descriptions_table', 1),
(15, '2019_02_26_103228_create_roles_table', 1),
(17, '2019_02_12_063600_create_certificates_table', 3),
(20, '2019_04_23_092447_create_vessel_certificates_table', 4),
(21, '2019_02_11_091238_create_surveys_table', 5),
(22, '2019_04_23_092421_create_vessel_surveys_table', 6),
(23, '2019_02_14_041855_create_orders_table', 7),
(24, '2019_04_24_070601_create_order_approvals_table', 7);

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
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on process',
  `status_from_am` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliver_date` date DEFAULT NULL,
  `rcv_date` date DEFAULT NULL,
  `created_by_role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int(191) NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ord_status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `vessel_id`, `category_id`, `req_no`, `req_date`, `port_name`, `status`, `status_from_am`, `deliver_date`, `rcv_date`, `created_by_role`, `created_by`, `updated_by`, `ord_status`, `created_at`, `updated_at`) VALUES
(5, 3, 8, 'DK/SLN/01/2019', '2019-09-10', 'jingjing', 'approved by srd-general-manager', NULL, NULL, NULL, 'chief-officer', 4, NULL, 1, '2019-09-10 12:18:32', '2019-09-29 13:47:01');

-- --------------------------------------------------------

--
-- Table structure for table `order_approvals`
--

CREATE TABLE `order_approvals` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(11) NOT NULL,
  `cheif_ofcr_app` int(11) DEFAULT NULL,
  `second_eng_app` int(11) DEFAULT NULL,
  `master_app` int(11) DEFAULT NULL,
  `chief_eng_app` int(11) DEFAULT NULL,
  `forwarded_to_agm_by_gm_srd` int(11) DEFAULT NULL,
  `forwarded_to_am_by_agm_srd` int(11) DEFAULT NULL,
  `ast_m_app` int(11) DEFAULT NULL,
  `agm_app` int(11) DEFAULT NULL,
  `gm_app` int(11) DEFAULT NULL,
  `dgm_app_ssm` int(11) DEFAULT NULL,
  `agm_app_ssm` int(11) DEFAULT NULL,
  `am_app_ssm` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_approvals`
--

INSERT INTO `order_approvals` (`id`, `order_id`, `cheif_ofcr_app`, `second_eng_app`, `master_app`, `chief_eng_app`, `forwarded_to_agm_by_gm_srd`, `forwarded_to_am_by_agm_srd`, `ast_m_app`, `agm_app`, `gm_app`, `dgm_app_ssm`, `agm_app_ssm`, `am_app_ssm`, `created_at`, `updated_at`) VALUES
(5, 5, 4, NULL, 5, NULL, 8, 7, 2, 7, 8, NULL, NULL, NULL, '2019-09-10 12:18:32', '2019-09-29 13:47:01');

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
(41, 5, 99, 3, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(42, 5, 101, 1, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(43, 5, 108, 1, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(44, 5, 107, 2, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(45, 5, 105, 2, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32');

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
  `user_type` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `user_id`, `vessel_id`, `role`, `user_type`, `created_by`, `updated_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'super-admin', 'admin', '', NULL, 1, NULL, NULL),
(2, 2, NULL, 'am-srd', 'srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:04:04', '2019-03-12 04:04:04'),
(3, 3, 3, 'operator', 'ship', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:05:23', '2019-03-12 04:05:23'),
(4, 4, 3, 'chief-officer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:35:54', '2019-03-12 04:35:54'),
(5, 5, 3, 'master', 'ship', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:36:50', '2019-04-24 04:12:47'),
(6, 6, 3, 'chief-engineer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:38:41', '2019-04-24 04:12:04'),
(7, 7, NULL, 'agm-srd', 'srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:45:56', '2019-03-12 04:45:56'),
(8, 8, NULL, 'gm-srd', 'srd', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:48:34', '2019-03-12 04:48:34'),
(9, 9, NULL, 'dgm-ssm', 'ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:49:41', '2019-03-12 04:49:41'),
(10, 10, NULL, 'agm-ssm', 'ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:51:20', '2019-03-12 04:51:20'),
(11, 11, NULL, 'am-ssm', 'ssm', 'Super Admin', 'Super Admin', 1, '2019-03-12 04:52:25', '2019-03-12 04:52:25'),
(13, 13, NULL, 'agm-srd', 'srd', 'Super Admin', 'Super Admin', 0, '2019-03-12 05:53:31', '2019-04-16 05:41:29'),
(14, 14, 2, 'chief-officer', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-20 06:04:56', '2019-04-16 05:54:38'),
(15, 15, 2, 'chief-engineer', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-20 06:09:54', '2019-04-16 05:50:08'),
(16, 18, NULL, 'am-ssm', 'ssm', 'Super Admin', 'Super Admin', 0, '2019-03-24 02:52:40', '2019-04-16 05:41:36'),
(17, 19, NULL, 'am-srd', 'srd', 'Super Admin', 'Super Admin', 1, '2019-04-16 04:49:24', '2019-04-16 04:49:24'),
(18, 20, 3, 'second-engineer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-09-10 06:54:13', '2019-09-10 06:54:13');

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE `surveys` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `name`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'ANNUAL SURVAY', 1, 1, NULL, NULL, NULL),
(2, 'DOCKING SURVAY', 1, 1, NULL, NULL, NULL),
(3, 'INTERMEDIATE SURVAY', 1, 1, NULL, NULL, NULL),
(4, 'RENEWAL SURVAY', 1, 1, NULL, NULL, NULL);

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
(1, 'Super Admin', 'superadmin@bsc.com', NULL, 'images/userphoto/1556089337.jpg', 'images/signature/1556089411.jpg', '$2y$10$HTX1HGHE.oBvCA1g.4ZFZeuP/aU3yIQ2RZ43sVv4uZ5CDTLnHlm.S', 1, 'fx1uZAHEYk0QeoqMP2yCInRWwhrpetBuhYA8mhGHi2LwgXIO84lG9tiaQWxU', '2019-03-12 03:30:23', '2019-04-24 01:03:31'),
(2, 'Md. Sofiul Alam', 'am-srd@bsc.com', NULL, 'images/userphoto/1556103169.jpg', 'images/signature/1556103169.png', '$2y$10$yBZWOZINXPecSqT1.lT3WuYmO4ztQyvZ1hSyILBBnQEyFDTC91C2S', 1, 'HoZ6LsFC12WH1nFvlhabvtlKesgjNQPFDwnZA8rZxXTyShOliiCYHWlXSYKz', '2019-03-12 04:04:04', '2019-04-24 04:52:49'),
(3, 'Amzad Khan', 'operator1@bsc.com', NULL, 'images/userphoto/1556102907.jpg', 'images/signature/1556102893.jpg', '$2y$10$mcn8xF9giGqcI1tFJt/wKuPma0bqqg0hd2Vjcraah.I.icGRG5wXa', 1, 'BqJv14nPEDSSHRWZAbxuodJQ5Sp3hMXCzrEOHQb83jukXmyXDH8LaW5N59z8', '2019-03-12 04:05:23', '2019-04-24 04:48:27'),
(4, 'Shekh Jamal', 'chief-officer1@bsc.com', NULL, 'images/userphoto/1556103133.jpg', 'images/signature/1556103133.png', '$2y$10$LDqrEs.FbteM.qfeQrj0burbH9R2o6vzMLxgq15ZPauA32xbDm.3i', 1, 'G44IsL8fo0FuR7LNCMGiwog9jVBM0eEIu7JpTGfHOcUOZMGncljjr7UNrHbZ', '2019-03-12 04:35:54', '2019-04-24 04:52:13'),
(5, 'Ship Master', 'master1@bsc.com', NULL, 'images/userphoto/1556103087.jpg', 'images/signature/1552474498.jpg', '$2y$10$ZeQx9k.yVY5SghSSAT02V.zP/zMuJ6QLIc5Ui3gxQxPiOsTVI4s3q', 1, '6ImmI8RlBNQuqt9LTUzAdVVpnIBr3uv3QGEhiDTNMbC8LIcHw4Shmh8TSbam', '2019-03-12 04:36:50', '2019-04-24 04:51:27'),
(6, 'Md. Miron Ahmed', 'chief-engineer1@bsc.com', NULL, 'images/userphoto/1555415069.jpg', 'images/signature/1555415069.png', '$2y$10$qEECekMRc2ieq8Cl36arSuRgWz1MrAuUQMm7MQtFYC3mABJ.hHvLC', 1, 'ANOyH2oxODo1XFzoP4YdtJDLKCRFFqEJfLFc1cU91N6D7BvbOSDeoTKtfcQ6', '2019-03-12 04:38:41', '2019-04-16 05:44:29'),
(7, 'SM Shakhawat Mahmud', 'agm-srd@bsc.com', NULL, 'images/userphoto/1555412748.jpg', 'images/signature/1556103232.png', '$2y$10$7PA48ZwfUFYRm3u2k3T3SeuinveKQejPlObIzURK.nWTG6tyZ./ha', 1, 'A2v31cVtmiGEjjwIi6HctMYTPguNoYvo7701OuuJsh94r4HMgkoVtO8eqkWh', '2019-03-12 04:45:56', '2019-04-24 04:53:52'),
(8, 'M Asib Raihan', 'gm-srd@bsc.com', NULL, 'images/userphoto/1555412371.jpg', 'images/signature/1556103265.jpg', '$2y$10$QXhRQ3SUoUY0O.8EN1TJ3OxzmmhrbvZykNpakaGmk.NzqfGg8szxe', 1, 'Cq6LW1KOiRxdI7inM6AVrrjydK8wG0MaSbGBTNWEPMisuDT8D8drltzdzZSl', '2019-03-12 04:48:34', '2019-04-24 04:54:25'),
(9, 'Md. Abdur Jabbar', 'dgm-ssm@bsc.com', NULL, 'images/userphoto/1555413924.jpg', 'images/signature/1556103296.jpg', '$2y$10$sRc0xz/tswjWsI/nPbMsQ.LbY/O92dy.qk6TDsH65BeyrZGeSR5dm', 1, 'JIr14joEm8gwWGLkdMcOhc0NvzE2eCsnQ5luJ2PXjYVbebNicixgV5LGTr8j', '2019-03-12 04:49:41', '2019-04-24 04:54:56'),
(10, 'Md. Masud Mia', 'agm-ssm@bsc.com', NULL, 'images/userphoto/1555412983.jpg', 'images/signature/1556105625.jpg', '$2y$10$Fic1aEmju6ptifVN8kXBJe99CQS4eIYaqVJXcEwFpg7y62JskGyeS', 1, '1N1xIpLrnjSIf52hl07KUV0mH5FEH9gU12ALxfXo0gJ9KHg6R3tfayNUadNX', '2019-03-12 04:51:20', '2019-04-24 05:33:45'),
(11, 'Tonima Afroz', 'am-ssm@bsc.com', NULL, 'images/userphoto/1556103202.jpg', 'images/signature/1556103202.png', '$2y$10$HP1CIOL2ivoLsLGwBnvKsOWtfGw8zANl70bsTh5AW5wXaaQ3wmGlG', 1, 'y6GSf5iGzmmoFo2MJJOUCjXaG7MTedwDcOSIcFY1ZfNPb4lw8oXonAqN3oAw', '2019-03-12 04:52:25', '2019-04-24 04:53:22'),
(12, 'Md. Admin Ahmed', 'admin@bsc.com', NULL, NULL, NULL, '$2y$10$8sxrdoCWRhOBxfZcYxMxCuBJrhECYG0DPAb/zb0XhAUFJGq8NJS1y', 0, NULL, '2019-03-12 04:54:07', '2019-03-24 03:06:56'),
(13, 'rafiq', 'am-ssm222@bsc.com', NULL, NULL, NULL, '$2y$10$yZw0G6mEaES4ChavwPbrzOSoikT9u9yN4flA6IwUPnr6fEtkVa.Ca', 0, 'YGn2ijrDHSajAE026Rxj1A9E6X0BkCPOEdHU8iM6orQjQJepANROy207dzK1', '2019-03-12 05:53:31', '2019-04-16 05:41:29'),
(14, 'bayejid89', 'bayejid89@gmail.com', NULL, NULL, NULL, '$2y$10$Rpr9F2yWec28vpfnvwDtS.McjEgXWppXHTKl.lUS6i.p/vNwvzLQu', 0, NULL, '2019-03-20 06:04:56', '2019-04-16 05:54:38'),
(15, 'dsfsdfsda', 'superadmindfdf@bsc.com', NULL, NULL, NULL, '$2y$10$0oLvC.IMo6PMmr4vnqA9o.WyfjiZMk4nbvdbyT8mEQlhc5kiOh0LG', 0, NULL, '2019-03-20 06:09:54', '2019-04-16 05:50:08'),
(16, 'AM-SSM 2', 'am-ssm-2@bsc.com', NULL, NULL, NULL, '$2y$10$MGe1TSYqnYOKIjSBcSi5iuIjpzfySImAp18bXCWSUfiKZv33bS08y', 1, NULL, '2019-03-24 02:50:54', '2019-03-24 02:50:54'),
(17, 'AM-SSM 2', 'am-ssm-13@bsc.com', NULL, NULL, NULL, '$2y$10$9zN7agCs/CinbEqxjhiMl.0.cqvY8N8nva5pqzT7dWLxtuu.hbbGm', 1, NULL, '2019-03-24 02:51:24', '2019-03-24 02:51:24'),
(18, 'AM-SSM 2', 'am-ssm-137@bsc.com', NULL, NULL, NULL, '$2y$10$k1tDJ8YVbLQGzlowZzN7nOtA48MbQM/I.N4Rp8jLtTtt7cqUeUEUS', 0, NULL, '2019-03-24 02:52:40', '2019-04-16 05:41:36'),
(19, 'Md. Mainul Islam', 'am-srd@gmail.com', NULL, 'images/userphoto/1555412028.jpg', 'images/signature/1555412028.png', '$2y$10$LIW29ytBr3s4l4hRbEX7OOfVsSOTFKP2HsOXC3uSzuK4GFil9ljju', 1, 'wq10tDTXxO4lOspdJkpaxG4eFpMvY72OVjiW2yPIOHaqyXzOWt1oUdk3pk4z', '2019-04-16 04:49:24', '2019-04-16 05:40:17'),
(20, 'Second Engineer', 'second_eng@bsc.com', NULL, NULL, NULL, '$2y$10$C5E/0fHU0.aBVFmGwaxmXeK5e1ibgsaHB079LVkOUTDeSj9qmgMW6', 1, '25AasXnyDNIeAVa8CxesyMv4IJoW1DgZUZv9HeVs3CXoYCnoEnETfxvTHtRg', '2019-09-10 06:54:13', '2019-09-10 06:54:13');

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
(1, 'asdsadsa', 'd asdsa', 'dsa d', 'sadasd', 'asdas', 'asda', 'sadsa', '2019-03-28', 'asdasasd', 'asdasd', '2019-03-30', NULL, NULL, 0, '2019-03-12 03:59:57', '2019-04-15 22:07:31'),
(2, 'MT Banglar Agrajatra', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Global Radiance Ship Management Pvt Ltd', 'Singapore', 'Abdul Hai', 'Coc5809767', '2019-03-31', 'Nafees Ahmed Romel', 'CoC570977', '2019-03-31', NULL, NULL, 1, '2019-03-12 04:00:54', '2019-04-15 22:14:52'),
(3, 'MV BANGLAR SAMRIDDHI', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Miron MD. Saifuddin', 'COC0024746', '2019-02-14', 'Mohammad Kamal Hossain', 'BD200123', '2019-08-16', NULL, NULL, 1, '2019-03-12 04:03:01', '2019-04-15 22:16:57'),
(4, 'MV BANGLAR JOYJATRA', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Miron Muhammad Saifuddin', 'CoC234223', '2019-05-09', 'Abdul Wahab', 'CoC3425088', '2019-05-11', NULL, NULL, 1, '2019-04-15 22:12:32', '2019-04-15 22:12:32'),
(5, 'MT Banglar Agradoot', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Global Radiance Ship Management Pvt Ltd', 'Singapore', 'Sheikh Sadi', 'CoC', '2019-05-24', 'Robiul', 'CoC', '2019-08-17', NULL, NULL, 1, '2019-04-16 00:02:37', '2019-04-16 00:02:37'),
(6, 'MV BANGLAR ARJAN', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'JAKIR HOSSAIN', 'CoC', '2019-04-26', 'ENAM', 'CoC', '2019-04-25', NULL, NULL, 1, '2019-04-16 00:08:04', '2019-04-16 00:08:04'),
(7, 'MT BANGLAR AGRAGOTI', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Global Radiance Ship Management Pvt Ltd', 'SINGAPORE', 'Mohammed Hossain', 'CoC', '2020-05-15', 'Jamil', 'CoC', '2022-05-05', NULL, NULL, 1, '2019-04-16 00:10:09', '2019-04-16 00:10:09'),
(8, 'MT BANGLAR JYOTI', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Moajjem hossain', 'CoC', '2019-04-18', 'Shahidullah', 'CoC', '2019-04-27', NULL, NULL, 1, '2019-04-16 00:14:29', '2019-04-16 00:14:29'),
(9, 'MT BANGLAR SHOURABH', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'BANGLADESH SHIPPING CORPORATION', 'BSC BHABAN, SALTGOLA ROAD, CHATTOGRAM', 'Monir Uddin Monsury', 'CoC', '2019-04-30', 'Golam Moktadir Emel', 'CoC', '2019-04-26', NULL, NULL, 1, '2019-04-16 00:21:07', '2019-04-16 00:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_certificates`
--

CREATE TABLE `vessel_certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_id` int(11) NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `issue_auth` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NOT NULL,
  `exp_date` date NOT NULL,
  `cert_copy` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vessel_certificates`
--

INSERT INTO `vessel_certificates` (`id`, `certificate_id`, `vessel_id`, `issue_auth`, `issue_date`, `exp_date`, `cert_copy`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 4, 7, 'asdsa', '2019-04-23', '2019-04-22', 'images/cert_copy/1556013229.pdf', 1, '1', NULL, '2019-04-23 03:53:49', '2019-04-23 03:53:49'),
(2, 6, 7, 'wewe', '2019-04-16', '2019-04-30', 'images/cert_copy/1556013575.pdf', 1, '1', '1', '2019-04-23 03:59:35', '2019-04-23 05:02:57');

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
-- Dumping data for table `vessel_particulars`
--

INSERT INTO `vessel_particulars` (`id`, `vessel_id`, `type`, `flag`, `call_sign`, `imo_no`, `grt`, `nrt`, `dwt`, `off_no`, `keel_lay_date`, `launch_date`, `delivery_date`, `cert_date`, `built_year`, `built_loc`, `steam_motor_propelled`, `builder_name`, `builder_address`, `deck_no`, `mast_no`, `rigged`, `stem`, `stern`, `build`, `status`, `created_at`, `updated_at`) VALUES
(1, 9, 'Demo', 'Demo', 'Demo', '122Demo', 'Demo', 'Demo', 'Demo', '2121212', '2019-04-01', '2019-04-01', '2019-04-01', '2019-04-01', '2000', 'Dhaka', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 1, '2019-04-23 02:12:31', '2019-04-23 02:12:31');

-- --------------------------------------------------------

--
-- Table structure for table `vessel_surveys`
--

CREATE TABLE `vessel_surveys` (
  `id` int(10) UNSIGNED NOT NULL,
  `vessel_id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `society_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `survey_date` date NOT NULL,
  `survey_exp_date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vessel_surveys`
--

INSERT INTO `vessel_surveys` (`id`, `vessel_id`, `survey_id`, `society_name`, `survey_date`, `survey_exp_date`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'qwe', '2019-04-23', '2019-04-24', 1, 1, NULL, '2019-04-23 06:23:35', '2019-04-23 06:23:35'),
(2, 6, 4, 'qwe', '2019-04-23', '2019-04-27', 1, 1, 1, '2019-04-23 06:25:37', '2019-04-23 06:33:11');

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
-- Indexes for table `order_approvals`
--
ALTER TABLE `order_approvals`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `vessel_certificates`
--
ALTER TABLE `vessel_certificates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vessel_particulars`
--
ALTER TABLE `vessel_particulars`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vessel_surveys`
--
ALTER TABLE `vessel_surveys`
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_approvals`
--
ALTER TABLE `order_approvals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `vessels`
--
ALTER TABLE `vessels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vessel_certificates`
--
ALTER TABLE `vessel_certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vessel_particulars`
--
ALTER TABLE `vessel_particulars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vessel_surveys`
--
ALTER TABLE `vessel_surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
