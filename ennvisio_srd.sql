-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 13, 2022 at 03:05 PM
-- Server version: 5.7.40
-- PHP Version: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ennvisio_srd`
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

--
-- Dumping data for table `boilers`
--

INSERT INTO `boilers` (`id`, `vessel_id`, `boiler_num`, `manu_name`, `manu_address`, `loaded_pressure`, `boiler_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, '1', 'ALFA LAVAL', 'ALFA LAVAL', '1500Kg/h', 'Cylindrical vertical type boiler with burner and feed water  Regulator (on/off control)', 1, '2019-10-12 23:59:13', '2019-10-12 23:59:13'),
(2, 5, '02', 'Alfa Laval (Shanghai) Technologies Co./25/F,', 'Golden Bell Plaza, 98 Huai Hai Road M. Shanghai 200021 China', '7 kg', 'Composite-Aalborg OC-TC; Auxiliary - Aalborg OL', 1, '2019-12-10 00:18:22', '2019-12-10 00:18:22'),
(3, 7, '2( one Auxiliary & One Composite)', 'Alfa Laval Ltd,Qingdao.', 'China', '0.7 MPA', 'Aalborg OL (Auxiliary). Aalborg OC-TCI (Composite).', 1, '2019-12-10 00:56:35', '2019-12-10 00:56:35'),
(4, 2, '2( one Auxiliary & One Composite)', 'Alfa Laval Ltd,Qingdao.', 'China', '0.7 MPA', 'Aalborg OL (Auxiliary). Aalborg OC-TCI (Composite).', 1, '2020-02-09 00:52:53', '2020-02-09 00:52:53'),
(5, 4, '1', 'ALFA LAVAL', 'ALFA LAVAL', '1500Kg/h', 'Cylindrical vertical type boiler with burner and feed water Regulator', 1, '2020-02-09 01:27:07', '2020-02-09 01:27:07'),
(6, 6, '1', 'ALFA LAVAL', 'ALFA LAVAL', '1500Kg/h', 'Cylindrical vertical type boiler with burner and feed water Regulator', 1, '2020-02-09 02:30:31', '2020-02-09 02:30:31');

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
(1, 'Deck Store', 'DK DTR', 'Super Admin', '', 0, '2019-03-12 04:07:11', '2019-10-09 22:36:05'),
(2, 'Stationary', 'STN', 'Super Admin', '', 1, '2019-03-12 04:07:39', '2019-03-12 04:07:39'),
(3, 'Safety Items', 'SFT', 'Super Admin', '', 1, '2019-03-12 04:08:30', '2019-03-12 04:08:30'),
(4, 'Paint', 'PNT', 'Super Admin', '', 1, '2019-03-12 04:09:13', '2019-04-02 00:36:58'),
(5, 'IMO SYMBOLS', 'SYM', 'Super Admin', '', 1, '2019-04-11 05:42:11', '2019-04-11 05:42:11'),
(6, 'SMPEP ITEMS', 'SMPEP', 'Super Admin', '', 1, '2019-04-15 23:29:37', '2019-04-15 23:29:37'),
(7, 'SAMPLE BOTTLE', 'SMPL', 'Super Admin', '', 1, '2019-04-15 23:41:24', '2019-04-15 23:41:24'),
(8, 'SALOON STORE', 'SLN', 'Super Admin', '', 1, '2019-04-15 23:46:02', '2019-04-15 23:46:02'),
(9, 'PUBLICATION', 'PUB', 'Super Admin', '', 1, '2019-04-16 00:07:35', '2019-04-16 00:07:35'),
(10, 'FLAG ITEMS', 'FLG', 'Super Admin', '', 1, '2019-04-16 00:17:15', '2019-04-16 00:17:15'),
(11, 'DK EMERGENCY', 'E\'CY', 'Super Admin', '', 0, '2019-04-16 00:21:01', '2019-10-09 22:42:20'),
(12, 'BA COMPRESSOR', 'EM\'CY', 'Super Admin', '', 1, '2019-04-16 00:38:59', '2019-04-16 00:38:59'),
(13, 'WALL WASH TEST KIT', 'E\'CY-', 'Super Admin', '', 1, '2019-04-16 00:59:17', '2019-04-16 00:59:17'),
(14, 'sdasda', 'asd', 'Amzad Khan', '', 0, '2019-04-23 07:45:29', '2019-04-23 07:45:41'),
(15, 'Electrical Store', 'ELEC STR', 'Super Admin', '', 1, '2019-10-09 22:39:07', '2019-10-09 22:39:07'),
(16, 'DECK   Store', 'Dk str', 'Super Admin', '', 1, '2019-10-09 22:40:51', '2019-10-09 22:40:51');

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

--
-- Dumping data for table `dimensions`
--

INSERT INTO `dimensions` (`id`, `vessel_id`, `length_LL`, `length_OA`, `breadth`, `depth`, `length_eng_room`, `draft`, `suez_geo_ton`, `suez_net_ton`, `pana_ton`, `class`, `class_not`, `hp`, `spreed`, `hold_cap`, `car_gear`, `car_hold`, `bunk_cap`, `ball_cap`, `water_cap`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, '176.978', '179.91 meter/177.00 meter', '32.00 meter', '15.00 meter', '30.75m', '10.50 meter', '26689.94', '23143.54', '21561', 'LR', '*100A1 Bulk Carrier, CSR, BC-A, GRAB[20], Hold Nos. 2 and  4 May Be Empty,ESP,', '9088.5HP', '14 knot', '51498.31 m3', '04, ELECTRO-HYDRAULIC SINGLE DECK CRANES', '05 NOS', '1803 m3', '12400 m3', '250m3', 1, '2019-10-12 23:49:18', '2019-10-12 23:53:43'),
(2, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2019-12-09 23:26:42', '2019-12-09 23:26:42'),
(3, 5, '181.953 m', '185 m& 182 m', '28 m', '16.6 m', '13 m', '11.7 m', '25459.80 MT', '21966.80 MT', '20156 MT', 'LR', 'LR,+100A1,Double Hull Oil and Chemical Tanker ,Ship Type 2& ship Type 3,', '9092.12 BHP', '11.9 Kts (Loaded)', '43726.433 m3	(98%) (Liquid Cargo Capacity)', 'Midship Horse Crane', '14 (cargo Oil Tanks)', 'Fuel Oil : 1013.94Cu Metres 100%  Diesel Oil : 70.682 Cu. Metres 100% Gas Oil: 93.621 Cu. Metres100%', '16493.1 m3', '1412.32 m3', 1, '2019-12-10 00:07:51', '2019-12-10 00:07:51'),
(4, 7, '185 m', '185 m & 85 m', '28 m', '16.6 m', '24 m', '11.7', '24167', '11512', '81194', '100A1 Double Hull Oil & Chemical Tanker Ship', 'Type 2&3, ESP,CSR, Ship Right (ACS(B),(CM), IWS, LISPM4,ECO (BWR,EEDI-1,IHM', '9960', '13 Knt', 'N/A', 'N/A', 'N/A', '1000Mt (100%) HFO', '16493 M3', '598M3', 1, '2019-12-10 00:44:37', '2019-12-10 00:46:55'),
(5, 2, '185 m', '185 m & 85 m', '28 m', '16.6 m', '24 m', '11.7', '24167', '11512', '81194', '100A1 Double Hull Oil & Chemical Tanker Ship', 'Type 2&3, ESP,CSR, Ship Right (ACS(B),(CM), IWS, LISPM4,ECO (BWR,EEDI-1,IHM', '9960', NULL, 'N/A', 'N/A', 'N/A', '1000Mt (100%) HFO', '16493 M3', '598M3', 1, '2020-02-09 00:47:20', '2020-02-09 00:47:20'),
(6, 4, '176.978', '179.91 meter/177.00 meter', '32.00 meter', '15.00 meter', '30.75m', '30.75m', '26689.94', '23143.54', '21561', 'LR', '*100A1 Bulk Carrier, CSR, BC-A, GRAB[20], Hold Nos. 2 and 4 May Be Empty,ESP,', '9088.5HP', '14 knots', '51498.31 m3', '04, ELECTRO-HYDRAULIC SINGLE DECK CRANES', '05 NOS', '1803 m3', '12400 m3', '250m3', 1, '2020-02-09 01:22:16', '2020-02-09 01:22:16'),
(7, 6, '176.978', '179.91 meter/177.00 meter', '32.00 meter', '15.00 meter', '30.75m', '10.50 meter', '26689.94', '23143.54', '21561', 'LR', '*100A1 Bulk Carrier, CSR, BC-A, GRAB[20], Hold Nos. 2 and 4 May Be Empty,ESP,', '9088.5HP', '14 knots', '51498.31 m3', '04, ELECTRO-HYDRAULIC SINGLE DECK CRANES', '05 NOS', '1803 m3', '12400 m3', '250m3', 1, '2020-02-09 02:24:57', '2020-02-09 02:24:57');

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

--
-- Dumping data for table `engines`
--

INSERT INTO `engines` (`id`, `vessel_id`, `manu_name`, `manu_address`, `type`, `mod_num`, `sets_no`, `no_cyl_set`, `diam_cyl`, `length_stroke`, `power_kw`, `rpm`, `speed`, `charger`, `fuel`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'Man B & W Huddong', 'Man B & W Huddong', 'Two stroke, Single acting Marine diesel Engine, Direct Reversible', 'Man 6S50 MC-C, MARK 8.2, TIER – II', '1', '6', '500mm', '2000mm', '6780KW,  14 knots', '108rpm', '14 knots', 'ABB', 'HFO, MDO, IFO', 1, '2019-10-12 23:57:37', '2019-10-12 23:57:37'),
(2, 5, 'Hudong Heavy Machineries Co. Ltd', '2851 Pudong Dadao', 'HHH- MAN B&W', '6S50MC-C8.2', '1', '6', '50 cm', '2 m', NULL, NULL, NULL, '01;ABB A165-L37', NULL, 1, '2019-12-10 00:12:55', '2019-12-10 00:12:55'),
(3, 7, 'Hudong Heavy Machineries Co. Ltd', 'Shanhai , China', 'HHH- MAN B&W 6S50MC-C8.2', 'HE5004A', '1 SET', '6', '500', '2000', NULL, NULL, NULL, '1 Set.ABB Turbo System. Model: A165-L37', NULL, 1, '2019-12-10 00:52:05', '2019-12-10 00:52:05'),
(4, 2, 'Shanhai , China', 'Shanhai , China', 'HHH- MAN B&W 6S50MC-C8.2', 'HE5004A', '1 SET', '06', '500', '2000', NULL, NULL, NULL, '1 Set.ABB Turbo System.', 'Model: A165-L37', 1, '2020-02-09 00:51:23', '2020-02-09 00:51:23'),
(5, 4, 'Man B & W Huddong', NULL, 'Two stroke, Single acting Marine diesel Engine, Direct Reversible', 'Man 6S50 MC-C, MARK 8.2, TIER – II', '1', '06', '500mm', '2000mm', '6780KW, 14 knots, 108rpm, 14 knots', '14 knots,', '14 knots', 'ABB', 'HFO, MDO, IFO', 1, '2020-02-09 01:25:43', '2020-02-09 01:25:43'),
(6, 6, 'Man B & W Huddong', 'China', 'Two stroke, Single acting Marine diesel Engine, Direct Reversible', 'Man 6S50 MC-C, MARK 8.2, TIER – II', '1', '06', '500mm', '2000mm', '6780KW, 14 knots,', '14 knots,', '14 knots', 'ABB', 'HFO, MDO, IFO', 1, '2020-02-09 02:29:45', '2020-02-09 02:29:45');

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

--
-- Dumping data for table `framework_descriptions`
--

INSERT INTO `framework_descriptions` (`id`, `vessel_id`, `bulk_no`, `length_stem_rudder`, `main_breadth`, `dept_tonnag_ceil`, `shaft_no`, `eng_set_no`, `loaded_pressure`, `gro_ton`, `net_ton`, `cert_accom`, `lifeboat_num`, `rafts_num`, `per_accom_num`, `rafts_req_num`, `buoys_num`, `jack_num`, `imm_suit_num`, `therm_pro_num`, `trans_rud_num`, `propeller`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, '10', '149.20m+30.75m=179.95m', '32.00m', '13.694m', '2', '1', '1500Kg/h', '1500Kg/h', '13275', '35P (Including owner and pilot)', '2(Rescue-6P, Lifeboat-42P)', '5(25P*4, 6P*1)', '42P', '3(25P*2, 6P*1)', '12', '42', '54(Children’s suit 04nos, Suit 50 Nos)', '4', '2', 'FPP, 4 Blade, 6m Dia, 108.0rpm', 1, '2019-10-12 23:32:39', '2019-10-12 23:32:39'),
(2, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2019-12-09 23:25:47', '2019-12-09 23:25:47'),
(3, 5, 'N/A', '185 m', '28 m', '15.34 m', '01', '01', '7 kg', '24167 MT', '11520 MT', '35', '01', '05', '106', NULL, NULL, '48', '48', '07', '02', '01', 1, '2019-12-09 23:49:26', '2019-12-09 23:49:26'),
(4, 7, '10', '182 m', '28 m', '15.9 m', '01', '01', NULL, '24,144', '11462', 'For 35 person', '01', '05', '106', NULL, NULL, '04', '48', '48', '02', 'FPP', 1, '2019-12-10 00:37:41', '2019-12-10 00:37:41'),
(5, 2, '10', '182 m', '28 m', '16.6 m', '01', '01', NULL, '24,144', '11462', 'For 35 person', '01', '05', '106', NULL, NULL, '04', '48', '48', '02', 'FPP', 1, '2020-02-09 00:42:20', '2020-02-09 00:42:20'),
(6, 4, '10', '149.20m+30.75m=179.95m', '32.00m', '15.00 meter', '02', '01', '1500Kg/h', '1500Kg/h', '13275', '35P (Including owner and pilot)', '2(Rescue-6P, Lifeboat-42P)', '5(25P*4, 6P*1)', '42P', '3(25P*2, 6P*1)', '12', '42', '54(Children’s suit 04nos, Suit 50 Nos)', '4', '02', 'FPP, 4 Blade, 6m Dia, 108.0rpm', 1, '2020-02-09 01:17:02', '2020-02-09 01:17:02'),
(7, 6, '10', '149.20m+30.75m=179.95m', '32.00m', '15.00 meter', '02', '01', '1500Kg/h', '1500Kg/h', '13275', '35P (Including owner and pilot)', '2(Rescue-6P, Lifeboat-42P)', '5(25P*4, 6P*1)', '42P', '3(25P*2, 6P*1)', '12', '42', '54(Children’s suit 04nos, Suit 50 Nos)', '4', '02', 'FPP, 4 Blade, 6m Dia, 108.0rpm', 1, '2020-02-09 02:18:50', '2020-02-09 02:18:50');

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
(166, 13, '-', 'WALL WASH KIT-AN ABSOLUTE TEST KIT CAPABLE OF PERFORMING CHLORIDE TEST,HC TEST,PTT TIME TEST,PH TEST,A ACID WASH COLOR TEST AND STAINLESS STEEL PASSIVATION TEST', 'SET', 'Super Admin', '', 1, '2019-04-16 00:59:48', '2019-04-16 00:59:48'),
(167, 16, '613243', 'DOUBLE END ANGLE SCRAPERS (LENGTH = 300 mm)', 'PC', 'Super Admin', '', 1, '2019-10-09 22:45:07', '2019-10-09 22:45:07'),
(168, 16, '612503', 'BALL PEIN HAMMERS HANDLED, No. : 3/4,           WEIGHT = 330g', 'PC', 'Super Admin', '', 1, '2019-10-09 22:47:40', '2019-10-09 22:47:40'),
(169, 16, '211291', 'STRAND HEAVING LINES 200 m Length, CIR= 1\",           DIA = 8 mm', 'COIL', 'Super Admin', '', 1, '2019-10-09 22:49:13', '2019-10-09 22:49:13'),
(170, 16, '210204', 'STRAND POLYPROPYLENE MONO-FILAMENT ROPE, DIA 12 mm, L=200 m', 'COIL', 'Super Admin', '', 1, '2019-10-09 22:50:58', '2019-10-09 22:50:58'),
(171, 16, '210203', 'STRAND POLYPROPYLENE MONO-FILAMENT ROPE DIA 10 mm, L=200 m', 'COIL', 'Super Admin', '', 1, '2019-10-09 22:51:58', '2019-10-09 22:51:58'),
(172, 16, '174292', 'RUBBER SQUEEGEES BLADE WIDTH= 400 mm', 'PC', 'Super Admin', '', 1, '2019-10-09 22:53:16', '2019-10-09 22:53:16'),
(173, 16, '174294', 'REFILL BLADE WIDTH = 400 mm', 'PC', 'Super Admin', '', 1, '2019-10-09 22:54:47', '2019-10-09 22:54:47'),
(174, 16, '510603', 'COIR DECK BRUSHES WITH LONG HANDLE', 'PC', 'Super Admin', '', 1, '2019-10-09 22:56:06', '2019-10-09 22:56:06'),
(175, 16, '510623', 'SOFT BRISTLE SWEEPING BRUSH WITH LONG HANDLE', 'PC', 'Super Admin', '', 1, '2019-10-09 22:58:16', '2019-10-09 22:58:16'),
(176, 16, '190101', 'COTTON WORKING GLOVES', 'DOZEN', 'Super Admin', '', 1, '2019-10-09 23:00:17', '2019-10-09 23:00:17'),
(177, 16, '190109', 'LETHER PALM WORKING GLOVES', 'DOZEN', 'Super Admin', '', 1, '2019-10-09 23:01:33', '2019-10-09 23:01:33'),
(178, 16, '190132', 'PLASTIC GLOVES (OIL/ACID RESISTANT), LONG', 'PAIR', 'Super Admin', '', 1, '2019-10-09 23:03:23', '2019-10-09 23:03:23'),
(179, 16, '331148', 'FACE SHIELDS WITH FORE HEAD PROTECTION', 'PC', 'Super Admin', '', 1, '2019-10-09 23:04:40', '2019-10-09 23:04:40'),
(180, 16, '331104', 'SAFETY HARNESS FULL BODY TYPE', 'PC', 'Super Admin', '', 1, '2019-10-09 23:06:10', '2019-10-09 23:06:10'),
(181, 16, '232333', 'STEEL MARLIN SPIKES, L = 250 mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:08:38', '2019-10-09 23:08:38'),
(182, 16, '615958', 'NON SPARK ROUND SHOVELS SPECIAL ALUMINIUM BRONZE BLADE = (225x290) mm,                             OVERALL LENGTH=970 mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:11:03', '2019-10-09 23:11:03'),
(183, 16, '251466', 'FINE FISH OIL IN TIN(18L/CAN)', 'Can', 'Super Admin', '', 1, '2019-10-09 23:13:23', '2019-10-09 23:13:23'),
(184, 16, '211411', 'POLYPROPYLENE MARLINE (DIA 3mm)', 'Hank', 'Super Admin', '', 1, '2019-10-09 23:14:18', '2019-10-09 23:14:18'),
(185, 16, '174122', 'BUCKET PLASTIC 10L, Dia 275mm, Height 265mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:15:57', '2019-10-09 23:15:57'),
(186, 16, '551081', 'CARECLEAN  RUST  25 LTR CAN( RUST & SCALE REMOVER)', 'Can', 'Super Admin', '', 1, '2019-10-09 23:16:56', '2019-10-09 23:16:56'),
(187, 16, '230820', 'STANDARD WIRE CLIPS (CAST IRON  )', 'PC', 'Super Admin', '', 1, '2019-10-09 23:17:53', '2019-10-09 23:17:53'),
(188, 16, '230821', 'STANDARD WIRE CLIPS (CAST IRON)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:18:42', '2019-10-09 23:18:42'),
(189, 16, '510165', 'ANGLE RADIATOR BRUSHES, WIDTH 25mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:19:35', '2019-10-09 23:19:35'),
(190, 16, '510166', 'ANGLE RADIATOR BRUSHES, WIDTH 40mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:20:51', '2019-10-09 23:20:51'),
(191, 16, '811611', 'CERAMIC YARN, GLASS,DIA 10mm', 'ROLL', 'Super Admin', '', 1, '2019-10-09 23:22:02', '2019-10-09 23:22:02'),
(192, 16, '174141', 'PLASTIC DUST PAN Size (320x270x495)mm', 'PC', 'Super Admin', '', 1, '2019-10-09 23:24:35', '2019-10-09 23:24:35'),
(193, 16, '613689', 'PLASTIC SHOVEL', 'PC', 'Super Admin', '', 1, '2019-10-09 23:27:42', '2019-10-09 23:27:42'),
(194, 16, '617704', 'GREASE GUN', 'NOS', 'Super Admin', '', 1, '2019-10-09 23:31:09', '2019-10-09 23:31:09'),
(195, 16, '650891', 'OIL FINDING  PASTE', 'PC', 'Super Admin', '', 1, '2019-10-09 23:32:45', '2019-10-09 23:32:45'),
(196, 16, '650890', 'WATER FINDING PASTE', 'PC', 'Super Admin', '', 1, '2019-10-09 23:33:45', '2019-10-09 23:33:45'),
(197, 16, '232162', 'GANGWAY  SAFETY NET , NET SIZE , (4X16)M Mesh size 150mm, Rim Rope size : dia 10mm  Mesh rope size : dia : 8 mm , Name of synthetic fiber rope : plopropylin', 'NOS', 'Super Admin', '', 1, '2019-10-09 23:35:44', '2019-10-09 23:35:44'),
(198, 16, '174275', 'MOP LONG HANDLED', 'PCS', 'Super Admin', '', 1, '2019-10-09 23:37:40', '2019-10-09 23:37:40'),
(199, 16, '614051', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:38:44', '2019-10-09 23:38:44'),
(200, 16, '614052', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:39:45', '2019-10-09 23:39:45'),
(201, 16, '614056', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:42:40', '2019-10-09 23:42:40'),
(202, 16, '614057', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:43:49', '2019-10-09 23:43:49'),
(203, 16, '614054', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:45:39', '2019-10-09 23:45:39'),
(204, 16, '614058', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:46:39', '2019-10-09 23:46:39'),
(205, 16, '614059', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:47:38', '2019-10-09 23:47:38'),
(206, 16, '614060', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:48:13', '2019-10-09 23:48:13'),
(207, 16, '614061', 'HOSE BAND / PIPE CLAMPS (JUBLEE CLAMP)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:48:47', '2019-10-09 23:48:47'),
(208, 16, '615501', 'NON SPARK 12 POINT SINGLE END ANGLE WRENCHES            (15 DEGREE ANGLE)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:49:21', '2019-10-09 23:49:21'),
(209, 16, '615502', 'NON SPARK 12 POINT SINGLE END ANGLE WRENCHES            (15 DEGREE ANGLE)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:50:24', '2019-10-09 23:50:24'),
(210, 16, '615506', 'NON SPARK 12 POINT SINGLE END ANGLE WRENCHES            (15 DEGREE ANGLE)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:51:04', '2019-10-09 23:51:04'),
(211, 16, '615508', 'NON SPARK 12 POINT SINGLE END ANGLE WRENCHES            (15 DEGREE ANGLE)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:51:54', '2019-10-09 23:51:54'),
(212, 16, '615509', 'NON SPARK 12 POINT SINGLE END ANGLE WRENCHES            (15 DEGREE ANGLE)', 'PC', 'Super Admin', '', 1, '2019-10-09 23:52:47', '2019-10-09 23:52:47'),
(213, 16, '610665', '12 POINT SINGLE END WRENCHES (40 DEGREE BEND)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:10:01', '2019-10-10 00:10:01'),
(214, 16, '610666', '12 POINT SINGLE END WRENCHES (40 DEGREE BEND)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:11:28', '2019-10-10 00:11:28'),
(215, 16, '610668', '12 POINT SINGLE END WRENCHES (40 DEGREE BEND)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:14:08', '2019-10-10 00:14:08'),
(216, 16, '610670', '12 POINT SINGLE END WRENCHES (40 DEGREE BEND)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:14:54', '2019-10-10 00:14:54'),
(217, 16, '610671', '12 POINT SINGLE END WRENCHES (40 DEGREE BEND)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:15:37', '2019-10-10 00:15:37'),
(218, 16, '615580', 'NON STRIKING SINGLE OPEN END WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:16:23', '2019-10-10 00:16:23'),
(219, 16, '615581', 'NON STRIKING SINGLE OPEN END WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:17:06', '2019-10-10 00:17:06'),
(220, 16, '615584', 'NON STRIKING SINGLE OPEN END WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:17:56', '2019-10-10 00:17:56'),
(221, 16, '615603', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:18:51', '2019-10-10 00:18:51'),
(222, 16, '615604', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 0, '2019-10-10 00:21:00', '2019-10-10 01:27:55'),
(223, 16, '615604', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:22:15', '2019-10-10 00:22:15'),
(224, 16, '615606', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:23:31', '2019-10-10 00:23:31'),
(225, 16, '615607', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:24:22', '2019-10-10 00:24:22'),
(226, 16, '615608', 'NON SPARK STRIKING 12 POINT RING WRENCHES', 'PC', 'Super Admin', '', 1, '2019-10-10 00:25:21', '2019-10-10 00:25:21'),
(227, 16, '610855', 'POINT T-TYPE WRENCHES (HANDLE FIXED NOT REMOVABLE)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:27:32', '2019-10-10 00:27:32'),
(228, 16, '610857', 'POINT T-TYPE WRENCHES (HANDLE FIXED NOT REMOVABLE)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:28:17', '2019-10-10 00:28:17'),
(229, 16, '610859', 'POINT T-TYPE WRENCHES (HANDLE FIXED NOT REMOVABLE)', 'PC', 'Super Admin', '', 1, '2019-10-10 00:29:02', '2019-10-10 00:29:02'),
(230, 16, '510662', 'WIRE BRUSH', 'PC', 'Super Admin', '', 1, '2019-10-10 00:29:48', '2019-10-10 00:29:48'),
(231, 16, '510662', 'WIRE BRUSH', 'DOZEN', 'Super Admin', '', 1, '2019-10-10 00:31:52', '2019-10-10 00:31:52'),
(232, 16, '450702', 'WD-40 (360 mL-SPRAY)', 'DOZEN', 'Super Admin', '', 1, '2019-10-10 00:33:12', '2019-10-10 00:33:12'),
(233, 16, '812262', 'DAVCON', 'PC', 'Super Admin', '', 1, '2019-10-10 00:34:05', '2019-10-10 00:34:05'),
(234, 16, '812352', 'BELZONA', 'PC', 'Super Admin', '', 1, '2019-10-10 00:34:52', '2019-10-10 00:34:52'),
(235, 16, '812302', 'CONDOBOND WITH BANDAGE', 'PC', 'Super Admin', '', 1, '2019-10-10 00:35:26', '2019-10-10 00:35:26'),
(236, 16, '232758', 'COMMON WIRE NAILS', 'KG', 'Super Admin', '', 1, '2019-10-10 00:36:35', '2019-10-10 00:36:35'),
(237, 16, '550267', 'RAPID HAND CLEANER  1X6 TIN', 'CTN', 'Super Admin', '', 1, '2019-10-10 00:41:36', '2019-10-10 00:41:36'),
(238, 16, '650109', 'OUTSIDE CALIPERS', 'NO', 'Super Admin', '', 1, '2019-10-10 00:42:55', '2019-10-10 00:42:55'),
(239, 16, '650125', 'SPRING OUTSIDE CALIPERS', 'NO', 'Super Admin', '', 1, '2019-10-10 00:43:48', '2019-10-10 00:43:48'),
(240, 16, '812367', 'PIPE REPAIR KIT', 'SET', 'Super Admin', '', 1, '2019-10-10 00:44:38', '2019-10-10 00:44:38'),
(241, 16, '612366', 'HEXAGON NUT DRIVER SET S', 'SET', 'Super Admin', '', 1, '2019-10-10 00:45:21', '2019-10-10 00:45:21'),
(242, 16, '590105', 'PNEUMATIC IMPACT WRENCHES', 'NO', 'Super Admin', '', 1, '2019-10-10 00:46:24', '2019-10-10 00:46:24'),
(243, 16, '590234', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 00:54:44', '2019-10-10 00:54:44'),
(244, 16, '590236', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 00:58:29', '2019-10-10 00:58:29'),
(245, 16, '590238', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 00:59:42', '2019-10-10 00:59:42'),
(246, 16, '590238', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 01:01:21', '2019-10-10 01:01:21'),
(247, 16, '590241', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 01:02:09', '2019-10-10 01:02:09'),
(248, 16, '590243', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 01:02:48', '2019-10-10 01:02:48'),
(249, 16, '590244', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 01:05:41', '2019-10-10 01:05:41'),
(250, 16, '590245', 'SOCKET FOR PNEUMATIC IMPACT WRENCH', 'PC', 'Super Admin', '', 1, '2019-10-10 01:06:30', '2019-10-10 01:06:30'),
(251, 16, '613056', 'PUNCHING TOOL SET (6-36 mm) 16 PCS PUNCHING DIES (6,7,8,9,10,11,12,13,16,19,22,25,28,32,35,38) ; 01 PC PUNCHING TABLE', 'SET', 'Super Admin', '', 1, '2019-10-10 01:07:38', '2019-10-10 01:07:38'),
(252, 16, '351051', 'AIR HOSE COUPLING (CAST BRONZE AIR HOSE COUPLINGS M42 X 2)', 'PC', 'Super Admin', '', 1, '2019-10-10 01:08:27', '2019-10-10 01:08:27'),
(253, 16, '351052', 'AIR HOSE COUPLING (CAST BRONZE AIR HOSE COUPLINGS M42 X 2)', 'PC', 'Super Admin', '', 1, '2019-10-10 01:11:53', '2019-10-10 01:11:53'),
(254, 16, '590302', 'PNEUMATIC GRINDER', 'PC', 'Super Admin', '', 1, '2019-10-10 01:12:36', '2019-10-10 01:12:36'),
(255, 16, '590316', 'PNEUMATIC GRINDER DISC', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:13:21', '2019-10-10 01:13:21'),
(256, 16, '590317', 'PNEUMATIC GRINDER DISC', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:14:10', '2019-10-10 01:14:10'),
(257, 16, '611773', 'HAND SNIPS', 'NO', 'Super Admin', '', 1, '2019-10-10 01:15:13', '2019-10-10 01:15:13'),
(258, 16, '612262', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) SLOTTED', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:18:51', '2019-10-10 01:18:51'),
(259, 16, '612263', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) SLOTTED', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:19:28', '2019-10-10 01:19:28'),
(260, 16, '612264', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) SLOTTED', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:21:03', '2019-10-10 01:21:03'),
(261, 16, '612265', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) SLOTTED', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:21:46', '2019-10-10 01:21:46'),
(262, 16, '612272', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) PHILIPS', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:22:38', '2019-10-10 01:22:38'),
(263, 16, '612274', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) PHILIPS', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:24:16', '2019-10-10 01:24:16'),
(264, 16, '612275', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) PHILIPS', 'NOS', 'Super Admin', '', 1, '2019-10-10 01:25:00', '2019-10-10 01:25:00'),
(265, 16, '612276', 'SCREW DRIVERS, PLASTIC HANDLE (HEAVY DUTY) PHILIPS', 'NOS', 'Super Admin', '', 1, '2019-10-10 02:39:54', '2019-10-10 02:39:54'),
(266, 16, '351221', 'QUICK CONNECT COUPLERS (SH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:40:47', '2019-10-10 02:40:47'),
(267, 16, '351222', 'QUICK CONNECT COUPLERS (SH SERIES SOCKET)', 'NOS', 'Super Admin', '', 0, '2019-10-10 02:41:40', '2019-10-10 02:44:05'),
(268, 16, '351224', 'QUICK CONNECT COUPLERS (SH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:43:08', '2019-10-10 02:43:08'),
(269, 16, '351222', 'QUICK CONNECT COUPLERS (SH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:45:50', '2019-10-10 02:45:50'),
(270, 16, '351251', 'QUICK CONNECT COUPLERS (PH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:47:05', '2019-10-10 02:47:05'),
(271, 16, '351252', 'QUICK CONNECT COUPLERS (PH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:48:03', '2019-10-10 02:48:03'),
(272, 16, '351254', 'QUICK CONNECT COUPLERS (PH SERIES SOCKET)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:48:44', '2019-10-10 02:48:44'),
(273, 16, '650512', 'VERNIER CALIPERS', 'PC', 'Super Admin', '', 1, '2019-10-10 02:50:07', '2019-10-10 02:50:07'),
(274, 16, '650873', 'OIL GAUGING TAPE STAINLESS STEEL', 'NOS', 'Super Admin', '', 1, '2019-10-10 02:51:53', '2019-10-10 02:51:53'),
(275, 16, '232251', 'SYNTHETIC FIBRE TARPULIN CANVAS (NYLON)', 'ROLL', 'Super Admin', '', 1, '2019-10-10 02:52:56', '2019-10-10 02:52:56'),
(276, 16, '211451', 'SEIZING WIRE 2.5 mm', 'KG', 'Super Admin', '', 1, '2019-10-10 02:54:03', '2019-10-10 02:54:03'),
(277, 16, '190265', 'BOOTS & SHOES OIL AND ACID RESISTANT (SIZE 26) MUST BE SOFT', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:55:09', '2019-10-10 02:55:09'),
(278, 16, '190267', 'BOOTS & SHOES OIL AND ACID RESISTANT (SIZE 27) MUST BE SOFT', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:56:05', '2019-10-10 02:56:05'),
(279, 16, '711226', 'HYDRAULIC PIPE STAINLESS STEEL  (DIA 10 mm)', 'METER', 'Super Admin', '', 1, '2019-10-10 02:57:13', '2019-10-10 02:57:13'),
(280, 16, '711226', 'HYDRAULIC PIPE (DIA 10 mm, UNION 15 mm)', 'PCS', 'Super Admin', '', 1, '2019-10-10 02:58:13', '2019-10-10 02:58:13'),
(281, 16, '234101', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 02:59:05', '2019-10-10 02:59:05'),
(282, 16, '234102', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:00:51', '2019-10-10 03:00:51'),
(283, 16, '234103', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:01:45', '2019-10-10 03:01:45'),
(284, 16, '234105', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:02:26', '2019-10-10 03:02:26'),
(285, 16, '234109', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:03:22', '2019-10-10 03:03:22'),
(286, 16, '234110', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:03:58', '2019-10-10 03:03:58'),
(287, 16, '234112', 'FORGED CHAIN SHACKLE', 'NOS', 'Super Admin', '', 1, '2019-10-10 03:04:36', '2019-10-10 03:04:36'),
(288, 16, '591519', 'AIR DUCT  FOR GAS FREERING FAN  (300mm x 30m)', 'PCS', 'Super Admin', '', 1, '2019-10-10 03:05:13', '2019-10-10 03:05:13'),
(289, 16, 'LATEST EDITION', 'ASTM TABLE  FOR ALL AREA  ( 5A,6A,5B,6B,6C,23A,23B,24A24B,24C,54A,54B,54C)  EACH  TABLE  ONE PICE TO BE SUPPLIED', 'Each One pice', 'Super Admin', '', 1, '2019-10-10 03:06:00', '2019-10-10 03:06:00'),
(290, 16, '232485', 'SCUPPER PLUG   (d= 93 mm , D =112 mm)', 'PC', 'Super Admin', '', 1, '2019-10-10 03:07:13', '2019-10-10 03:07:13'),
(291, 16, '232486', 'SCUPPER PLUG   (d= 115  mm , D =137 mm)', 'PC', 'Super Admin', '', 1, '2019-10-10 03:08:08', '2019-10-10 03:08:08'),
(292, 16, '490503', 'PAD LOCK   (40 mm)', 'PC', 'Super Admin', '', 1, '2019-10-10 03:08:44', '2019-10-10 03:08:44'),
(293, 16, '330560', 'TUBE TYPE GAS DETECTOR ,DRAEGER MODEL: ACCURO IMPA: 330560', 'PC', 'Super Admin', '', 1, '2019-10-10 03:12:17', '2019-10-10 03:12:17'),
(294, 16, '330578', 'EXTENSION HOSE ,DRAGER ACCURO & DRAGER X-ACT 5000, 15m,INCLUDING ADAPTER FOR TUBES,ADAPTER FOR HOSE.', 'PC', 'Super Admin', '', 0, '2019-10-10 03:12:59', '2019-10-10 03:13:45'),
(295, 16, '330578', 'EXTENSION HOSE ,DRAGER ACCURO & DRAGER X-ACT 5000, 15m,INCLUDING ADAPTER FOR TUBES,ADAPTER FOR HOSE.', 'SET', 'Super Admin', '', 1, '2019-10-10 03:14:45', '2019-10-10 03:14:45'),
(296, 16, '67 28 561', 'BENZENE , ORDER NUMBER: 67 28 561', 'PKT', 'Super Admin', '', 1, '2019-10-10 03:15:33', '2019-10-10 03:15:33');

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
(5, 3, 8, 'DK/SLN/01/2019', '2019-09-10', 'jingjing', 'approved by srd-assistant-general-manager', NULL, NULL, NULL, 'chief-officer', 4, NULL, 1, '2019-09-10 12:18:32', '2020-02-17 00:40:08'),
(6, 3, 4, 'DK/PNT/02/2019', '2019-10-07', 'singapore', 'approved by srd-general-manager', NULL, NULL, NULL, 'second-engineer', 20, NULL, 1, '2019-10-07 00:38:11', '2019-10-14 00:14:44'),
(7, 8, 16, 'DK/Dk str/01/2019', '2019-10-23', 'Chattogram', ' forwarded to Asst. Manager (srd) by Asst. General-manager (srd)', NULL, NULL, NULL, 'second-engineer', 27, NULL, 1, '2019-10-22 23:32:33', '2019-10-23 00:00:39'),
(8, 8, 16, 'DK/Dk str/01/2020', '2020-02-17', 'Chattogram', 'approved by srd-assistant-general-manager', NULL, NULL, NULL, 'second-engineer', 27, NULL, 1, '2020-02-16 23:13:05', '2020-02-17 04:57:59'),
(13, 9, 5, 'DK/SYM/01/2020', '2020-09-30', 'Singapore', 'ready', NULL, NULL, NULL, 'second-engineer', 22, NULL, 1, '2020-09-30 00:18:18', '2020-09-30 00:18:18');

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
(5, 5, 4, NULL, 5, NULL, 8, 7, 2, 7, 8, 9, NULL, NULL, '2019-09-10 12:18:32', '2019-10-07 00:45:31'),
(6, 6, NULL, 20, NULL, 6, NULL, NULL, NULL, NULL, 8, 9, NULL, NULL, '2019-10-07 00:38:11', '2019-10-07 00:45:12'),
(7, 7, NULL, 27, NULL, 28, 8, 7, NULL, NULL, NULL, NULL, NULL, NULL, '2019-10-22 23:32:33', '2019-10-23 00:00:39'),
(8, 8, NULL, 27, NULL, 28, 8, 7, 2, 7, NULL, NULL, NULL, NULL, '2020-02-16 23:13:05', '2020-02-17 04:57:59'),
(9, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2020-09-30 00:18:18', '2020-09-30 00:18:18');

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
(41, 5, 99, 3321825, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-11-27 21:43:06'),
(42, 5, 101, 1, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(43, 5, 108, 1, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(44, 5, 107, 2, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(45, 5, 105, 2, NULL, NULL, 1, '2019-09-10 12:18:32', '2019-09-10 12:18:32'),
(46, 6, 10, 1, NULL, NULL, 1, '2019-10-07 00:38:11', '2019-10-07 00:38:11'),
(47, 7, 273, 3, NULL, NULL, 1, '2019-10-22 23:32:33', '2019-10-22 23:32:33'),
(48, 7, 296, 1, NULL, NULL, 1, '2019-10-22 23:32:33', '2019-10-22 23:32:33'),
(49, 8, 176, 24, NULL, NULL, 1, '2020-02-16 23:13:05', '2020-02-17 00:40:58'),
(50, 8, 276, 12, NULL, NULL, 1, '2020-02-16 23:13:05', '2020-02-16 23:13:05'),
(51, 13, 70, 1, NULL, NULL, 1, '2020-09-30 00:18:18', '2020-09-30 00:18:18');

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
(3, 3, 3, 'operator', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-12 04:05:23', '2019-10-16 21:20:53'),
(4, 4, 3, 'chief-officer', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-12 04:35:54', '2020-02-16 23:10:10'),
(5, 5, 3, 'master', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-12 04:36:50', '2020-02-16 23:10:04'),
(6, 6, 3, 'chief-engineer', 'ship', 'Super Admin', 'Super Admin', 0, '2019-03-12 04:38:41', '2020-02-16 23:09:57'),
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
(18, 20, 3, 'second-engineer', 'ship', 'Super Admin', 'Super Admin', 0, '2019-09-10 06:54:13', '2020-02-16 23:09:19'),
(19, 21, 9, 'master', 'ship', 'M Asib Raihan', 'M Asib Raihan', 1, '2019-10-14 00:40:09', '2019-10-14 00:40:09'),
(20, 22, 9, 'second-engineer', 'ship', 'M Asib Raihan', 'M Asib Raihan', 1, '2019-10-14 00:41:05', '2019-10-14 00:41:05'),
(21, 23, 9, 'chief-officer', 'ship', 'M Asib Raihan', 'Super Admin', 1, '2019-10-14 00:42:07', '2019-10-16 21:15:31'),
(22, 24, 9, 'chief-engineer', 'ship', 'M Asib Raihan', 'Super Admin', 1, '2019-10-14 00:51:37', '2019-10-16 21:21:37'),
(23, 25, 8, 'master', 'ship', 'Super Admin', 'Super Admin', 1, '2019-10-16 21:16:58', '2019-10-16 21:16:58'),
(24, 26, 8, 'chief-officer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-10-16 21:18:16', '2019-10-16 21:18:16'),
(25, 27, 8, 'second-engineer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-10-16 21:19:29', '2019-10-16 21:19:29'),
(26, 28, 8, 'chief-engineer', 'ship', 'Super Admin', 'Super Admin', 1, '2019-10-16 21:20:43', '2019-10-16 21:20:43'),
(27, 29, NULL, 'am-ssm', 'ssm', 'Super Admin', 'Super Admin', 1, '2019-10-16 23:58:03', '2019-10-16 23:58:03'),
(28, 30, NULL, 'am-ssm', 'ssm', 'Super Admin', 'Super Admin', 1, '2019-10-17 00:00:17', '2019-10-17 00:00:17'),
(29, 31, 2, 'master', 'ship', 'M Asib Raihan', 'M Asib Raihan', 1, '2020-08-17 03:04:09', '2020-08-17 03:04:09');

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
(1, 'Super Admin', 'superadmin@bsc.com', NULL, 'images/userphoto/1556089337.jpg', 'images/signature/1556089411.jpg', '$2y$10$HTX1HGHE.oBvCA1g.4ZFZeuP/aU3yIQ2RZ43sVv4uZ5CDTLnHlm.S', 1, 'qyYiMyG3zemj46XJCa5axxuLD2PIogvCYpsegV7RGnoIpC4QH00s3k3noRfK', '2019-03-12 03:30:23', '2019-04-24 01:03:31'),
(2, 'Abu Md. Safiul Alam', 'am-srd@bsc.com', NULL, 'images/userphoto/1574309453.jpg', 'images/signature/1581915498.jpg', '$2y$10$s0T98USf9MnBBAP7xMp0s.5gED3BzG6NvEfqqQWNzYRs0K1ymCE7y', 1, 's5oeKFyLCwx76WqjCvBGulpBOWwN5QUALQeAKG51hIkl79DcdktJhAFgM7bf', '2019-03-12 04:04:04', '2020-02-16 22:58:18'),
(3, 'Amzad Khan', 'operator1@bsc.com', NULL, 'images/userphoto/1556102907.jpg', 'images/signature/1556102893.jpg', '$2y$10$mcn8xF9giGqcI1tFJt/wKuPma0bqqg0hd2Vjcraah.I.icGRG5wXa', 0, 'BqJv14nPEDSSHRWZAbxuodJQ5Sp3hMXCzrEOHQb83jukXmyXDH8LaW5N59z8', '2019-03-12 04:05:23', '2019-10-16 21:20:53'),
(4, 'Shekh Jamal', 'chief-officer1@bsc.com', NULL, 'images/userphoto/1556103133.jpg', 'images/signature/1556103133.png', '$2y$10$LDqrEs.FbteM.qfeQrj0burbH9R2o6vzMLxgq15ZPauA32xbDm.3i', 0, 'G44IsL8fo0FuR7LNCMGiwog9jVBM0eEIu7JpTGfHOcUOZMGncljjr7UNrHbZ', '2019-03-12 04:35:54', '2020-02-16 23:10:10'),
(5, 'Ship Master', 'master1@bsc.com', NULL, 'images/userphoto/1556103087.jpg', 'images/signature/1552474498.jpg', '$2y$10$ZeQx9k.yVY5SghSSAT02V.zP/zMuJ6QLIc5Ui3gxQxPiOsTVI4s3q', 0, 'uKYlsy55zQYC0xVzl1UyD2VOnjj4sIRIpg1wOESmcem3LLVgHYbuyHwdL8Wz', '2019-03-12 04:36:50', '2020-02-16 23:10:04'),
(6, 'Md. Miron Ahmed', 'chief-engineer1@bsc.com', NULL, 'images/userphoto/1555415069.jpg', 'images/signature/1555415069.png', '$2y$10$qEECekMRc2ieq8Cl36arSuRgWz1MrAuUQMm7MQtFYC3mABJ.hHvLC', 0, 'gWDFrUitKPiUVqffCw3vqXGeemeWKy4m7qEXMboSqHDZmbzFyafSiQDcR0Go', '2019-03-12 04:38:41', '2020-02-16 23:09:57'),
(7, 'SM Sakhawat Mahmud', 'agm-srd@bsc.com', NULL, 'images/userphoto/1572246095.jpg', 'images/signature/1572246095.jpg', '$2y$10$7PA48ZwfUFYRm3u2k3T3SeuinveKQejPlObIzURK.nWTG6tyZ./ha', 1, '2Kje3UMJS4XOf2WDKcuc89cWL1adLZh0xlqBOfegGyHghsApG7EEkeUuIXGL', '2019-03-12 04:45:56', '2019-10-28 23:29:07'),
(8, 'M Asib Raihan', 'gm-srd@bsc.com', NULL, 'images/userphoto/1555412371.jpg', 'images/signature/1556103265.jpg', '$2y$10$QXhRQ3SUoUY0O.8EN1TJ3OxzmmhrbvZykNpakaGmk.NzqfGg8szxe', 1, 'zQnzzHIAYCwJ6LDV820TEmg4BUmnAsyyFe3bPHxjLNkjUYbtQU7ic0m27oM9', '2019-03-12 04:48:34', '2019-04-24 04:54:25'),
(9, 'Md. Abdur Jabbar', 'dgm-ssm@bsc.com', NULL, 'images/userphoto/1555413924.jpg', 'images/signature/1556103296.jpg', '$2y$10$sRc0xz/tswjWsI/nPbMsQ.LbY/O92dy.qk6TDsH65BeyrZGeSR5dm', 1, 'jekobonGVHwGMYkecF5fHLnlAyAJRMnwOCuES593jAOf3Caz3FJCDm3QhD2i', '2019-03-12 04:49:41', '2019-04-24 04:54:56'),
(10, 'Md. Masud Mia', 'agm-ssm@bsc.com', NULL, 'images/userphoto/1555412983.jpg', 'images/signature/1556105625.jpg', '$2y$10$Fic1aEmju6ptifVN8kXBJe99CQS4eIYaqVJXcEwFpg7y62JskGyeS', 1, '1N1xIpLrnjSIf52hl07KUV0mH5FEH9gU12ALxfXo0gJ9KHg6R3tfayNUadNX', '2019-03-12 04:51:20', '2019-04-24 05:33:45'),
(11, 'Tonima Afroz', 'am-ssm@bsc.com', NULL, 'images/userphoto/1556103202.jpg', 'images/signature/1556103202.png', '$2y$10$HP1CIOL2ivoLsLGwBnvKsOWtfGw8zANl70bsTh5AW5wXaaQ3wmGlG', 1, 'PmUlcl2yABcataCIdHyNko5ToXiJvtI8thssNUvFvecyblw8pjgyOtMqSWOt', '2019-03-12 04:52:25', '2019-04-24 04:53:22'),
(12, 'Md. Admin Ahmed', 'admin@bsc.com', NULL, NULL, NULL, '$2y$10$8sxrdoCWRhOBxfZcYxMxCuBJrhECYG0DPAb/zb0XhAUFJGq8NJS1y', 0, NULL, '2019-03-12 04:54:07', '2019-03-24 03:06:56'),
(13, 'rafiq', 'am-ssm222@bsc.com', NULL, NULL, NULL, '$2y$10$yZw0G6mEaES4ChavwPbrzOSoikT9u9yN4flA6IwUPnr6fEtkVa.Ca', 0, 'YGn2ijrDHSajAE026Rxj1A9E6X0BkCPOEdHU8iM6orQjQJepANROy207dzK1', '2019-03-12 05:53:31', '2019-04-16 05:41:29'),
(14, 'bayejid89', 'bayejid89@gmail.com', NULL, NULL, NULL, '$2y$10$Rpr9F2yWec28vpfnvwDtS.McjEgXWppXHTKl.lUS6i.p/vNwvzLQu', 0, NULL, '2019-03-20 06:04:56', '2019-04-16 05:54:38'),
(15, 'dsfsdfsda', 'superadmindfdf@bsc.com', NULL, NULL, NULL, '$2y$10$0oLvC.IMo6PMmr4vnqA9o.WyfjiZMk4nbvdbyT8mEQlhc5kiOh0LG', 0, NULL, '2019-03-20 06:09:54', '2019-04-16 05:50:08'),
(16, 'AM-SSM 2', 'am-ssm-2@bsc.com', NULL, NULL, NULL, '$2y$10$MGe1TSYqnYOKIjSBcSi5iuIjpzfySImAp18bXCWSUfiKZv33bS08y', 1, NULL, '2019-03-24 02:50:54', '2019-03-24 02:50:54'),
(17, 'AM-SSM 2', 'am-ssm-13@bsc.com', NULL, NULL, NULL, '$2y$10$9zN7agCs/CinbEqxjhiMl.0.cqvY8N8nva5pqzT7dWLxtuu.hbbGm', 1, NULL, '2019-03-24 02:51:24', '2019-03-24 02:51:24'),
(18, 'AM-SSM 2', 'am-ssm-137@bsc.com', NULL, NULL, NULL, '$2y$10$k1tDJ8YVbLQGzlowZzN7nOtA48MbQM/I.N4Rp8jLtTtt7cqUeUEUS', 0, NULL, '2019-03-24 02:52:40', '2019-04-16 05:41:36'),
(19, 'Md. Mainul Islam', 'am-srd@gmail.com', NULL, 'images/userphoto/1555412028.jpg', 'images/signature/1555412028.png', '$2y$10$LIW29ytBr3s4l4hRbEX7OOfVsSOTFKP2HsOXC3uSzuK4GFil9ljju', 1, 'wq10tDTXxO4lOspdJkpaxG4eFpMvY72OVjiW2yPIOHaqyXzOWt1oUdk3pk4z', '2019-04-16 04:49:24', '2019-04-16 05:40:17'),
(20, 'Second Engineer', 'second_eng@bsc.com', NULL, NULL, NULL, '$2y$10$C5E/0fHU0.aBVFmGwaxmXeK5e1ibgsaHB079LVkOUTDeSj9qmgMW6', 0, 'wdDX80GgO5uoHqIEAEwDN8YyxKs4ApRRODgYhUXJHmwApFZPnZdmj7CT3D7j', '2019-09-10 06:54:13', '2020-02-16 23:09:19'),
(21, 'Master Shourabh', 'mastershourabh@bsc.com', NULL, NULL, NULL, '$2y$10$jp02/BcEUzK3T.tFn1bAy.YHUHqlOAGJiUXnMMm4bQnP3Eq2abxjG', 1, NULL, '2019-10-14 00:40:09', '2019-10-14 00:40:09'),
(22, 'Second Engineer Shourabh', 'secondengrshourabh@bsc.com', NULL, NULL, NULL, '$2y$10$yMqrs4iWl9sgHgBYswnrsOunC8BMRCon9rBID02POFSLG/YkfjV62', 1, 'E65VMF7HXKNG1JOHPP2X7CvRfYOJsZoQZtVenLR1I7LBSx9X15goE4Ao9BPg', '2019-10-14 00:41:05', '2019-10-14 00:41:05'),
(23, 'Chief Officer Shourabh', 'chiefofficershourabh@bsc.com', NULL, NULL, NULL, '$2y$10$YYjcjBoYcCXrVLbOMjTp1OA4gOT68WvcA.h1KU5olRDnVPCHJehQ.', 1, NULL, '2019-10-14 00:42:07', '2019-10-16 21:15:31'),
(24, 'Chief Engineer Shourabh', 'chiefengrshourabh@bsc.com', NULL, NULL, NULL, '$2y$10$bRc2gqDORDsxuhglQUPfkeeLTo1cVlKU0v8pt7xvpLWZ9jhZ9Jql.', 1, '4Q6fFVkVovlvRtxbBDTWOYuMaaYtxE66QFM2HVAWdG89YAL1ggRjS99oh6KX', '2019-10-14 00:51:37', '2019-10-16 21:21:37'),
(25, 'Master Jyoti', 'masterjyoti@bsc.com', NULL, NULL, NULL, '$2y$10$sCJQykvRCfxCx/A0Ipayt.N2JHEqEFw.3OJ6JKx58dOCcftmTpNcy', 1, 'ur4MN8Fn1Hoc8D0DI45VwpnLcnG1tC2kO9Bt9oDzAfrzVCgdEGTgk1GIVrh9', '2019-10-16 21:16:58', '2019-10-16 21:16:58'),
(26, 'Chief Officer Jyoti', 'chiefofficerjyoti@bsc.com', NULL, NULL, NULL, '$2y$10$NWqNF4abJdf1ZLsyanC0HOzzWoSgf0lfJH00155/kFFPWudD7GqdW', 1, NULL, '2019-10-16 21:18:16', '2019-10-16 21:18:16'),
(27, 'Second Engineer Jyoti', 'secondengrjyoti@bsc.com', NULL, NULL, NULL, '$2y$10$IAfdXgUJq12vcIRTWCoA8.LpYX4K6cN9g6yFE.gGD3JU/EWsG.Iym', 1, 'gvjZ6k1K2i2I6NfysDTrLoUtAabDuzALCTm7mkBqu2txGnfXk1QnTUIw6SEw', '2019-10-16 21:19:29', '2019-10-16 21:19:29'),
(28, 'Chief Engineer Jyoti', 'chiefengrjyoti@bsc.com', NULL, NULL, NULL, '$2y$10$LpOhz1wL/GNEYE.t1xQKGuhu9UVQX3FhT1NSTNI03DLPt3vSCGUuW', 1, 'm5U3NarNuRBHUDWZ9ftq53zGCIbDZ4RKbNhPR5wAGvdNq2xQ2U12pH0ZQ4pX', '2019-10-16 21:20:43', '2019-10-16 21:20:43'),
(29, 'MD Didarul Alam', 'am-ssm2@bsc.com', NULL, NULL, NULL, '$2y$10$CINi363jEGAlWzbyLPqmlelmYf/Z6JqRnCk1FZ6X6LcV2h1s8Cas6', 1, NULL, '2019-10-16 23:58:03', '2020-02-16 23:05:19'),
(30, 'Farjana Yasmin', 'am-ssm3@bsc.com', NULL, NULL, NULL, '$2y$10$SQmOa9UU7nBdQM245vHvpePmU8l3kIfGj4rkPHPIiKAWao5ac2rM.', 1, NULL, '2019-10-17 00:00:17', '2019-10-17 00:00:17'),
(31, 'Capt Abdul Quader', 'agrajatra@bsc.com', NULL, NULL, NULL, '$2y$10$aaJXLcDpajW12KqzAEp4EeL6py5uhE8xxLMmQSDZd..dxoIgXTWWO', 1, 'VS31JuFBP3fPZKqENQ2tApmzMOIOAPsifmSuiDRlab5kJjHyj5K0VWktNpHU', '2020-08-17 03:04:09', '2020-08-17 03:04:09');

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
(1, 4, 7, 'asdsa', '2019-04-23', '2019-04-22', 'images/cert_copy/1556013229.pdf', 0, '1', NULL, '2019-04-23 03:53:49', '2019-10-22 10:30:16'),
(2, 6, 7, 'wewe', '2019-04-16', '2019-04-30', 'images/cert_copy/1556013575.pdf', 0, '1', '1', '2019-04-23 03:59:35', '2019-10-22 09:10:45'),
(3, 4, 4, 'Lloyds Register', '2018-09-25', '2019-09-24', 'images/cert_copy/1571757028.pdf', 1, '1', NULL, '2019-10-22 09:10:28', '2019-10-22 09:10:28'),
(4, 5, 4, 'Lloyds Register', '2018-09-25', '2023-09-24', 'images/cert_copy/1571757126.pdf', 1, '1', NULL, '2019-10-22 09:12:06', '2019-10-22 09:12:06'),
(5, 5, 4, 'Lloyds Register', '2018-09-25', '2023-09-24', 'images/cert_copy/1571757130.pdf', 0, '1', NULL, '2019-10-22 09:12:10', '2019-10-22 09:12:22'),
(6, 4, 6, 'Lloyds Register', '2019-05-21', '2024-05-20', 'images/cert_copy/1571757251.pdf', 1, '1', NULL, '2019-10-22 09:14:11', '2019-10-22 09:14:11'),
(7, 5, 6, 'Lloyds Register', '2019-05-21', '2024-05-20', 'images/cert_copy/1571757350.pdf', 1, '1', NULL, '2019-10-22 09:15:50', '2019-10-22 09:15:50'),
(8, 4, 3, 'Lloyds Register', '2018-10-24', '2023-10-23', 'images/cert_copy/1571757465.pdf', 0, '1', NULL, '2019-10-22 09:17:45', '2019-10-22 09:23:01'),
(9, 4, 3, 'Lloyds Register', '2018-09-21', '2023-09-20', 'images/cert_copy/1571757862.pdf', 1, '1', NULL, '2019-10-22 09:24:22', '2019-10-22 09:24:22'),
(10, 5, 5, 'Lloyds Register', '2019-07-18', '2024-10-23', 'images/cert_copy/1571810573.pdf', 1, '1', '8', '2019-10-22 09:26:37', '2019-10-23 00:02:53'),
(11, 7, 2, 'gfd', '2019-11-29', '2019-11-28', 'images/cert_copy/1572862454.pdf', 1, '1', NULL, '2019-11-04 04:14:14', '2019-11-04 04:14:14'),
(12, 4, 3, 'MMD', '2019-11-20', '2019-11-28', 'images/cert_copy/1574309662.pdf', 1, '2', NULL, '2019-11-20 22:14:22', '2019-11-20 22:14:22'),
(13, 7, 6, 'MMD', '2019-11-19', '2019-11-12', 'images/cert_copy/1574309753.pdf', 1, '1', NULL, '2019-11-20 22:15:53', '2019-11-20 22:15:53'),
(14, 2, 4, 'MMD', '2019-11-12', '2019-11-23', 'images/cert_copy/1574310222.pdf', 1, '1', NULL, '2019-11-20 22:23:42', '2019-11-20 22:23:42'),
(15, 2, 9, 'khjhd', '2019-12-17', '2020-03-11', 'images/cert_copy/1575445156.PDF', 1, '1', NULL, '2019-12-04 01:39:16', '2019-12-04 01:39:16'),
(16, 9, 6, 'MMD', '2019-12-17', '2019-12-22', 'images/cert_copy/1575453120.PDF', 1, '1', NULL, '2019-12-04 03:52:00', '2019-12-04 03:52:00'),
(17, 1, 9, 'Bangladesh — Narsingdi', '2019-12-25', '2019-12-27', 'images/cert_copy/1575537103.pdf', 1, '1', '1', '2019-12-05 03:03:04', '2019-12-05 03:11:43'),
(18, 4, 3, 'BV', '2020-01-21', '2022-05-12', 'images/cert_copy/1578993949.pdf', 1, '2', NULL, '2020-01-14 03:25:49', '2020-01-14 03:25:49');

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
(1, 9, 'OIL TANKER VESSEL', 'BANGLADESH', 'S2BH', '8508955', '8672', '4562', 'Demo', '151', '1987-06-26', '2019-04-01', '1987-10-14', '2019-04-01', '1987', 'DENMARK', 'M.T. BANGLAR SHOURABH', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 'Demo', 1, '2019-04-23 02:12:31', '2019-12-10 00:58:21'),
(2, 3, 'BULK CARRIER', 'BANGLADESH', 'S2AI6', '9793832', '25818', '13275', '38896.4', '282', '2015-12-21', '2018-06-14', '2018-09-27', '2018-08-08', '2018', 'CHINA', 'MOTOR,SINGLE SCREW PROPELLER', 'Jiangsu New Yangzi Shipbuilding Co.,Ltd', '1 # lianyi Road Jiangyin - JingJiang  Industry Zone ,Jingjang City,Jiangsu  Province, P.R.China', '6', '6', 'Not', 'Raked', 'Transom', 'Carvel', 1, '2019-10-12 23:24:11', '2019-10-12 23:24:11'),
(3, 5, 'Oil/Chemical Tanker,Type 2&3', 'BANGLADESH', 'S2AI2', '9793868', '24167 MT', '11520 MT', '38919 MT', '280', '2015-12-21', '2018-07-25', '2019-02-25', '2018-10-16', '2019', 'Jiangsu New Yangzi Shipbuilding Co Ltd,China', 'MOTOR-driven', 'Yangzijian Shipbuilding Group Ltd.', 'Add:No1 Lianyi Road, jingjian City, Jiangsu provine,P.R. China. Post Code: 214532', '10(Accommodation); cargo Deck : N/A', '2', '0', 'N/A', 'N/A', 'N/A', 1, '2019-12-09 23:44:33', '2019-12-09 23:44:33'),
(4, 7, 'IMO Type 2&3 Oil tanker', 'CHATTOGRAM,', 'S2AI3', '9793870', '24167', '11512', '38867', '284', '2015-12-21', '2018-09-13', '2019-05-22', '2019-10-22', '2019', 'New Yangzi Shipbuilding Co Ltd,China', 'MOTOR Handed Fixed propeller', 'New Yangzi Shipbuilding Co.,Ltd China', NULL, '07', '02', '01', '01', '01', '2019', 1, '2019-12-10 00:33:26', '2019-12-10 00:33:26'),
(5, 2, 'IMO Type 2&3 Oil tanker', 'CHATTOGRAM', 'S2AI3', '9793870', '24167', '21151', '24167', '284', '2015-12-21', '2018-09-13', '2019-05-22', '2019-10-22', '2019', 'New Yangzi Shipbuilding Co Ltd,China', 'MOTOR Handed Fixed propeller', 'New Yangzi Shipbuilding Co.,Ltd China,', 'China,', '07', '02', '01', '01', '01', '2019', 1, '2020-02-09 00:33:56', '2020-02-09 00:33:56'),
(6, 4, 'BULK CARRIER', 'BANGLADESH', 'S2AI6', '9793832', '25818', '13275', '25818', '282', '2015-12-21', '2018-06-14', '2018-09-27', '2018-08-08', '2018', 'CHINA', 'MOTOR,SINGLE SCREW PROPELLER', 'Jiangsu New Yangzi Shipbuilding Co.,Ltd,', '# lianyi Road Jiangyin - JingJiang Industry Zone ,Jingjang City,Jiangsu Province, P.R.China', '6', '6', 'Not', 'Raked', 'Transom', 'Carvel', 1, '2020-02-09 01:08:15', '2020-02-09 01:08:15'),
(7, 6, 'BULK CARRIER', 'BANGLADESH', 'S2AI6', '9793832', '25818', '13275', '25818', '282', '2015-12-21', '2018-06-14', '2018-09-27', '2018-08-08', '2018', 'CHINA', 'MOTOR,SINGLE SCREW PROPELLER', 'New Yangzi Shipbuilding Co.,Ltd China,', '1 # lianyi Road Jiangyin - JingJiang Industry Zone ,Jingjang City,Jiangsu Province, P.R.China', '6', '6', 'Not', 'Raked', 'Transom', 'Carvel', 1, '2020-02-09 02:14:50', '2020-02-09 02:14:50');

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
(1, 6, 1, 'qwe', '2019-04-23', '2019-04-24', 0, 1, NULL, '2019-04-23 06:23:35', '2020-02-10 00:43:39'),
(2, 6, 4, 'qwe', '2019-04-23', '2019-04-27', 0, 1, 1, '2019-04-23 06:25:37', '2020-02-10 00:43:51'),
(3, 5, 1, 'LR', '2020-02-25', '2020-05-24', 1, 1, NULL, '2020-02-09 22:47:39', '2020-02-09 22:47:39'),
(4, 5, 4, 'LR', '2024-02-24', '2024-02-24', 1, 1, NULL, '2020-02-09 22:52:21', '2020-02-09 22:52:21'),
(5, 5, 3, 'LR', '2022-02-24', '2022-05-24', 1, 1, NULL, '2020-02-09 22:57:43', '2020-02-09 22:57:43'),
(6, 5, 2, 'LR', '2024-02-24', '2024-05-24', 1, 1, NULL, '2020-02-09 23:06:09', '2020-02-09 23:06:09'),
(7, 2, 1, 'LR', '2021-01-22', '2021-04-21', 1, 1, NULL, '2020-02-09 23:07:37', '2020-02-09 23:07:37'),
(8, 2, 3, 'LR', '2022-01-22', '2022-04-21', 1, 1, NULL, '2020-02-09 23:08:40', '2020-02-09 23:08:40'),
(9, 2, 4, 'LR', '2023-10-21', '2024-01-21', 1, 1, NULL, '2020-02-09 23:09:37', '2020-02-09 23:09:37'),
(10, 2, 2, 'LR', '2024-01-21', '2024-01-21', 1, 1, NULL, '2020-02-09 23:10:13', '2020-02-09 23:10:13'),
(11, 7, 1, 'LR', '2020-05-22', '2020-08-21', 1, 1, NULL, '2020-02-09 23:11:16', '2020-02-09 23:11:16'),
(12, 7, 4, 'LR', '2024-02-22', '2024-05-21', 1, 1, NULL, '2020-02-09 23:12:46', '2020-02-09 23:12:46'),
(13, 7, 3, 'LR', '2022-05-22', '2022-08-21', 1, 1, NULL, '2020-02-09 23:14:12', '2020-02-09 23:14:12'),
(14, 4, 1, 'BV', '2020-07-19', '2020-10-18', 1, 1, NULL, '2020-02-09 23:18:19', '2020-02-09 23:18:19'),
(15, 4, 4, 'BV', '2023-04-19', '2023-07-18', 1, 1, NULL, '2020-02-09 23:26:03', '2020-02-09 23:26:03'),
(16, 4, 4, 'BV', '2023-04-19', '2023-07-18', 1, 1, NULL, '2020-02-09 23:29:24', '2020-02-09 23:29:24'),
(17, 4, 3, 'BV', '2021-07-19', '2021-10-18', 1, 1, NULL, '2020-02-09 23:37:51', '2020-02-09 23:37:51'),
(18, 4, 2, 'BV', '2023-07-18', '2023-07-18', 1, 1, NULL, '2020-02-09 23:39:27', '2020-02-09 23:39:27'),
(19, 3, 3, 'BV', '2021-09-25', '2021-12-24', 1, 1, NULL, '2020-02-09 23:48:20', '2020-02-09 23:48:20'),
(20, 3, 4, 'BV', '2023-06-25', '2023-09-24', 1, 1, NULL, '2020-02-09 23:49:05', '2020-02-09 23:49:05'),
(21, 3, 1, 'BV', '2020-09-25', '2020-12-24', 1, 1, NULL, '2020-02-10 00:42:25', '2020-02-10 00:42:25'),
(22, 3, 2, 'BV', '2023-09-24', '2023-12-24', 1, 1, NULL, '2020-02-10 00:43:08', '2020-02-10 00:43:08'),
(23, 6, 1, 'BV', '2019-12-26', '2020-03-25', 1, 1, NULL, '2020-02-10 00:45:50', '2020-02-10 00:45:50'),
(24, 6, 3, 'BV', '2021-12-26', '2022-03-25', 1, 1, NULL, '2020-02-10 00:46:41', '2020-02-10 00:46:41'),
(25, 6, 4, 'BV', '2023-09-26', '2023-12-25', 1, 1, NULL, '2020-02-10 00:47:50', '2020-02-10 00:47:50'),
(26, 6, 2, 'BV', '2023-12-25', '2023-12-25', 1, 1, NULL, '2020-02-10 00:49:15', '2020-02-10 00:49:15'),
(27, 7, 2, 'LR', '2024-05-21', '2024-05-21', 1, 1, NULL, '2020-02-10 00:52:11', '2020-02-10 00:52:11'),
(28, 9, 1, 'BV', '2020-05-22', '2020-08-22', 1, 1, NULL, '2020-03-17 23:41:08', '2020-03-17 23:41:08'),
(29, 9, 2, 'BV', '2020-08-22', '2020-08-22', 1, 1, NULL, '2020-03-17 23:43:10', '2020-03-17 23:43:10'),
(30, 9, 4, 'BV', '2020-08-22', '2020-08-22', 1, 1, NULL, '2020-03-17 23:44:26', '2020-03-17 23:44:26'),
(31, 9, 3, 'BV', '2020-08-22', '2020-08-22', 1, 1, NULL, '2020-03-17 23:50:34', '2020-03-17 23:50:34'),
(32, 8, 1, 'BV', '2020-06-04', '2020-06-04', 1, 1, NULL, '2020-03-17 23:51:32', '2020-03-17 23:51:32'),
(33, 8, 2, 'BV', '2022-09-26', '2022-09-26', 1, 1, NULL, '2020-03-17 23:52:35', '2020-03-17 23:52:35'),
(34, 8, 4, 'BV', '2020-06-04', '2020-06-04', 1, 1, NULL, '2020-03-17 23:54:06', '2020-03-17 23:54:06'),
(35, 8, 3, 'BV', '2020-06-04', '2020-06-04', 1, 1, NULL, '2020-03-17 23:54:37', '2020-03-17 23:54:37');

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `dimensions`
--
ALTER TABLE `dimensions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `engines`
--
ALTER TABLE `engines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `framework_descriptions`
--
ALTER TABLE `framework_descriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=297;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `order_approvals`
--
ALTER TABLE `order_approvals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `vessels`
--
ALTER TABLE `vessels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vessel_certificates`
--
ALTER TABLE `vessel_certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vessel_particulars`
--
ALTER TABLE `vessel_particulars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vessel_surveys`
--
ALTER TABLE `vessel_surveys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
