-- --------------------------------------------------------
-- Host:                         185.223.30.96
-- Server version:               10.5.29-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.12.0.7122
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for sols_turf_wars
CREATE DATABASE IF NOT EXISTS `sols_turf_wars` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `sols_turf_wars`;

-- Dumping structure for table sols_turf_wars.characters
CREATE TABLE IF NOT EXISTS `characters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(32) DEFAULT NULL,
  `city_id` int(11) NOT NULL DEFAULT 1,
  `faction_id` int(11) DEFAULT NULL,
  `turf_id` int(11) NOT NULL DEFAULT 1,
  `last_action` int(11) NOT NULL DEFAULT 0,
  `level` int(11) DEFAULT 1,
  `xp` int(11) DEFAULT 0,
  `money` int(11) DEFAULT 2500,
  `respect` int(11) NOT NULL DEFAULT 0,
  `energy` int(11) DEFAULT 100,
  `energy_max` int(11) DEFAULT 100,
  `health` int(11) NOT NULL DEFAULT 100,
  `health_max` int(11) NOT NULL DEFAULT 100,
  `energy_updated_at` datetime DEFAULT NULL,
  `strength` int(11) DEFAULT 10,
  `intelligence` int(11) DEFAULT 10,
  `endurance` int(11) DEFAULT 10,
  `charisma` int(11) DEFAULT 10,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_crime` int(11) NOT NULL DEFAULT 0,
  `last_work` int(11) NOT NULL DEFAULT 0,
  `last_capture` int(11) NOT NULL DEFAULT 0,
  `last_drug_run` int(11) DEFAULT 0,
  `food` int(11) NOT NULL DEFAULT 100,
  `last_capture_time` int(11) DEFAULT 0,
  `capture_streak` int(11) DEFAULT 0,
  `last_war_attack` int(11) DEFAULT 0,
  `weapon_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_city` (`user_id`,`city_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_faction_id` (`faction_id`),
  KEY `idx_turf_id` (`turf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.characters: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `character_name` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `channel` enum('global','faction','zone') NOT NULL,
  `turf_id` int(11) DEFAULT NULL,
  `faction_id` int(11) DEFAULT NULL,
  `city_id` int(11) NOT NULL DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `channel` (`channel`),
  KEY `turf_id` (`turf_id`),
  KEY `faction_id` (`faction_id`),
  KEY `created_at` (`created_at`),
  KEY `idx_chat_city_channel` (`city_id`,`channel`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.chat_messages: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.chat_mutes
CREATE TABLE IF NOT EXISTS `chat_mutes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `character_id` int(10) unsigned DEFAULT NULL,
  `muted_by_user_id` int(10) unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_character_id` (`character_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.chat_mutes: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.cities
CREATE TABLE IF NOT EXISTS `cities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(10) NOT NULL,
  `map_image` varchar(255) DEFAULT NULL,
  `map_min_x` float NOT NULL DEFAULT -3000,
  `map_max_x` float NOT NULL DEFAULT 3000,
  `map_min_y` float NOT NULL DEFAULT -3000,
  `map_max_y` float NOT NULL DEFAULT 3000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.cities: ~1 rows (approximately)
INSERT INTO `cities` (`id`, `name`, `slug`, `map_image`, `map_min_x`, `map_max_x`, `map_min_y`, `map_max_y`) VALUES
	(1, 'Los Santos', 'ls', 'assets/img/map.png', -3000, 3000, -3000, 3000);

-- Dumping structure for table sols_turf_wars.factions
CREATE TABLE IF NOT EXISTS `factions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(50) DEFAULT NULL,
  `short_name` varchar(20) DEFAULT NULL,
  `type` enum('gang','law') DEFAULT NULL,
  `color` varchar(10) DEFAULT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `bank` int(11) NOT NULL DEFAULT 0,
  `income` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.factions: ~6 rows (approximately)
INSERT INTO `factions` (`id`, `city_id`, `name`, `short_name`, `type`, `color`, `icon_path`, `bank`, `income`) VALUES
	(1, 1, 'Grove Street', 'Grove', 'gang', '#4e994d', 'assets/img/factions/grove.gif', 0, 0),
	(2, 1, 'Ballas', 'Ballas', 'gang', '#a855f7', 'assets/img/factions/ballas.gif', 0, 0),
	(3, 1, 'Varrios Los Aztecas', 'Aztecas', 'gang', '#38bdf8', 'assets/img/factions/aztecas.gif', 0, 0),
	(4, 1, 'Los Santos Vagos', 'Vagos', 'gang', '#eab308', 'assets/img/factions/vagos.gif', 0, 0),
	(5, 1, 'Los Santos PD', 'LSPD', 'law', '#3b82f6', 'assets/img/factions/lspd.gif', 0, 0),
	(6, 1, 'Los Santos Triads', 'Triads', 'gang', '#FF3B3B', 'assets/img/factions/triads.gif', 0, 0);

-- Dumping structure for table sols_turf_wars.game_logs
CREATE TABLE IF NOT EXISTS `game_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `turf_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `context` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.game_logs: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.turfs
CREATE TABLE IF NOT EXISTS `turfs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(50) DEFAULT NULL,
  `value` int(11) NOT NULL DEFAULT 100,
  `heat` int(11) NOT NULL DEFAULT 0,
  `tier` int(11) NOT NULL DEFAULT 1,
  `heat_updated_at` datetime DEFAULT current_timestamp(),
  `war_cooldown_until` datetime DEFAULT NULL,
  `faction_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turfs: ~20 rows (approximately)
INSERT INTO `turfs` (`id`, `city_id`, `name`, `value`, `heat`, `tier`, `heat_updated_at`, `war_cooldown_until`, `faction_id`) VALUES
	(1, 1, 'Ganton', 100, 0, 1, '2026-05-20 19:22:27', '2026-05-20 22:19:17', 1),
	(2, 1, 'Idlewood', 100, 0, 1, '2026-05-19 22:10:31', '2026-05-20 17:31:43', 2),
	(3, 1, 'Jefferson', 100, 0, 1, '2026-05-18 10:54:53', '2026-05-16 16:03:40', 1),
	(4, 1, 'Willowfield', 100, 0, 1, '2026-05-11 19:31:53', '2026-05-07 17:21:58', 1),
	(5, 1, 'East Los Santos', 100, 0, 1, '2026-05-11 07:53:54', '2026-05-12 07:58:54', 1),
	(6, 1, 'El Corona', 100, 0, 1, '2026-05-09 17:14:13', NULL, 3),
	(7, 1, 'Ocean Docks', 100, 5, 1, '2026-05-17 20:31:14', NULL, 3),
	(8, 1, 'Playa del Seville', 100, 0, 1, '2026-05-08 11:15:09', '2026-05-04 21:04:14', 1),
	(9, 1, 'East Beach', 100, 0, 1, '2026-05-19 22:14:23', '2026-05-18 20:30:55', 1),
	(10, 1, 'Vinewood', 100, 0, 1, '2026-05-19 23:02:59', NULL, 4),
	(11, 1, 'Temple', 100, 0, 1, '2026-04-27 14:48:57', NULL, 6),
	(12, 1, 'Market', 100, 0, 1, '2026-05-13 06:28:50', '2026-04-25 20:16:32', 5),
	(13, 1, 'Rodeo', 100, 0, 1, '2026-03-23 06:30:43', NULL, 6),
	(14, 1, 'Richman', 100, 0, 1, '2026-03-23 05:29:35', NULL, 6),
	(15, 1, 'Marina', 100, 0, 1, '2026-05-04 07:14:14', NULL, 6),
	(16, 1, 'Verona Beach', 100, 0, 1, '2026-04-30 18:05:57', NULL, 4),
	(17, 1, 'Mulholland', 100, 0, 1, '2026-05-01 03:40:48', NULL, 2),
	(18, 1, 'Commerce', 100, 0, 1, '2026-05-13 05:16:14', '2026-04-29 07:42:37', 4),
	(19, 1, 'Pershing Square', 100, 0, 1, '2026-05-04 10:29:04', '2026-04-25 15:53:34', 5),
	(20, 1, 'Downtown Los Santos', 100, 0, 1, '2026-05-06 21:34:22', NULL, 2);

-- Dumping structure for table sols_turf_wars.turf_activity
CREATE TABLE IF NOT EXISTS `turf_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turf_id` int(11) DEFAULT NULL,
  `faction_id` int(11) DEFAULT NULL,
  `action_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_activity: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.turf_areas
CREATE TABLE IF NOT EXISTS `turf_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turf_id` int(11) NOT NULL,
  `min_x` float DEFAULT NULL,
  `min_y` float DEFAULT NULL,
  `max_x` float DEFAULT NULL,
  `max_y` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_areas: ~113 rows (approximately)
INSERT INTO `turf_areas` (`id`, `turf_id`, `min_x`, `min_y`, `max_x`, `max_y`) VALUES
	(1, 1, 2222.56, -1722.33, 2632.83, -1628.53),
	(2, 1, 2222.56, -1852.87, 2632.83, -1722.33),
	(3, 2, 1812.62, -1602.31, 2124.66, -1449.67),
	(4, 2, 1812.62, -1742.31, 1951.66, -1602.31),
	(5, 2, 1812.62, -1852.87, 1971.66, -1742.31),
	(6, 2, 1951.66, -1742.31, 2124.66, -1602.31),
	(7, 2, 1971.66, -1852.87, 2222.56, -1742.31),
	(8, 2, 2124.66, -1742.31, 2222.56, -1494.03),
	(9, 5, 2222.56, -1628.53, 2421.03, -1494.03),
	(10, 5, 2266.26, -1494.03, 2381.68, -1372.04),
	(11, 5, 2281.45, -1372.04, 2381.68, -1135.04),
	(12, 5, 2381.68, -1454.35, 2462.13, -1135.04),
	(13, 5, 2381.68, -1494.03, 2421.03, -1454.35),
	(14, 5, 2421.03, -1628.53, 2632.83, -1454.35),
	(15, 5, 2462.13, -1454.35, 2581.73, -1135.04),
	(16, 11, 952.66, -1130.84, 1096.47, -937.18),
	(17, 11, 1096.47, -1130.84, 1252.33, -1026.33),
	(18, 11, 1096.47, -1026.33, 1252.33, -910.17),
	(19, 11, 1252.33, -1130.85, 1378.33, -1026.33),
	(20, 11, 1252.33, -1026.33, 1391.05, -926.99),
	(21, 10, 647.56, -1227.28, 787.46, -1118.28),
	(22, 10, 647.71, -1416.25, 787.46, -1227.28),
	(23, 10, 787.46, -1310.21, 952.66, -1130.84),
	(24, 10, 787.46, -1130.84, 952.6, -954.66),
	(25, 18, 1323.9, -1722.26, 1440.9, -1577.59),
	(26, 18, 1323.9, -1842.27, 1701.9, -1722.26),
	(27, 18, 1370.85, -1577.59, 1463.9, -1384.95),
	(28, 18, 1463.9, -1577.59, 1667.96, -1430.87),
	(29, 18, 1583.5, -1722.26, 1758.9, -1577.59),
	(30, 18, 1667.96, -1577.59, 1812.62, -1430.87),
	(31, 16, 647.71, -2173.29, 930.22, -1804.21),
	(32, 16, 851.45, -1804.21, 1046.15, -1577.59),
	(33, 16, 930.22, -2006.78, 1073.22, -1804.21),
	(34, 16, 1046.15, -1722.26, 1161.52, -1577.59),
	(35, 16, 1161.52, -1722.26, 1323.9, -1577.59),
	(36, 15, 647.71, -1577.59, 807.92, -1416.25),
	(37, 15, 647.71, -1804.21, 851.45, -1577.59),
	(38, 15, 807.92, -1577.59, 926.92, -1416.25),
	(39, 19, 1440.9, -1722.26, 1583.5, -1577.59),
	(40, 20, 1370.85, -1170.87, 1463.9, -1130.85),
	(41, 20, 1370.85, -1384.95, 1463.9, -1170.87),
	(42, 20, 1378.33, -1130.85, 1463.9, -1026.33),
	(43, 20, 1391.05, -1026.33, 1463.9, -926.99),
	(44, 20, 1463.9, -1430.87, 1724.76, -1290.87),
	(45, 20, 1463.9, -1290.87, 1724.76, -1150.87),
	(46, 20, 1724.76, -1430.87, 1812.62, -1250.9),
	(47, 20, 1724.76, -1250.9, 1812.62, -1150.87),
	(48, 6, 1692.62, -2179.25, 1812.62, -1842.27),
	(49, 6, 1812.62, -2179.25, 1970.62, -1852.87),
	(50, 3, 1996.91, -1449.67, 2056.86, -1350.72),
	(51, 3, 2056.86, -1372.04, 2281.45, -1210.74),
	(52, 3, 2056.86, -1210.74, 2185.33, -1126.32),
	(53, 3, 2056.86, -1449.67, 2266.21, -1372.04),
	(54, 3, 2124.66, -1494.03, 2266.21, -1449.67),
	(55, 3, 2185.33, -1210.74, 2281.45, -1154.59),
	(56, 4, 1970.62, -2179.25, 2089, -1852.87),
	(57, 4, 2089, -2235.84, 2201.82, -1989.9),
	(58, 4, 2089, -1989.9, 2324, -1852.87),
	(59, 4, 2201.82, -2095, 2324, -1989.9),
	(60, 4, 2324, -2059.23, 2541.7, -1852.87),
	(61, 4, 2541.7, -1941.4, 2703.58, -1852.87),
	(62, 4, 2541.7, -2059.23, 2703.58, -1941.4),
	(63, 7, 2089, -2394.33, 2201.82, -2235.84),
	(64, 7, 2201.82, -2730.88, 2324, -2418.33),
	(65, 7, 2201.82, -2418.33, 2324, -2095),
	(66, 7, 2324, -2145.1, 2703.58, -2059.23),
	(67, 7, 2324, -2302.33, 2703.58, -2145.1),
	(68, 7, 2373.77, -2697.09, 2809.22, -2330.46),
	(69, 7, 2703.58, -2302.33, 2959.35, -2126.9),
	(70, 8, 2703.58, -2126.9, 2959.35, -1852.87),
	(71, 9, 2632.83, -1852.87, 2959.35, -1668.13),
	(72, 9, 2632.83, -1668.13, 2747.74, -1393.42),
	(73, 9, 2747.74, -1498.62, 2959.35, -1120.04),
	(74, 9, 2747.74, -1668.13, 2959.35, -1498.62),
	(75, 12, 787.46, -1416.25, 1072.66, -1310.21),
	(76, 12, 926.92, -1577.59, 1370.85, -1416.25),
	(77, 12, 952.66, -1310.21, 1072.66, -1130.85),
	(78, 12, 1072.66, -1416.25, 1370.85, -1130.85),
	(79, 13, 72.65, -1544.17, 225.16, -1404.97),
	(80, 13, 72.65, -1684.65, 225.16, -1544.17),
	(81, 13, 225.16, -1684.65, 312.8, -1501.95),
	(82, 13, 225.16, -1501.95, 334.5, -1369.62),
	(83, 13, 312.8, -1684.65, 422.68, -1501.95),
	(84, 13, 334.5, -1501.95, 422.68, -1406.05),
	(85, 13, 334.5, -1406.05, 466.22, -1292.07),
	(86, 13, 422.68, -1570.2, 466.22, -1406.05),
	(87, 13, 422.68, -1684.65, 558.1, -1570.2),
	(88, 13, 466.22, -1385.07, 647.52, -1235.07),
	(89, 13, 466.22, -1570.2, 558.1, -1385.07),
	(90, 13, 558.1, -1684.65, 647.52, -1384.93),
	(91, 14, 72.65, -1235.07, 321.36, -1008.15),
	(92, 14, 72.65, -1404.97, 225.16, -1235.07),
	(93, 14, 225.16, -1369.62, 334.5, -1292.07),
	(94, 14, 225.16, -1292.07, 466.22, -1235.07),
	(95, 14, 321.36, -768.03, 700.79, -674.89),
	(96, 14, 321.36, -1235.07, 647.52, -1044.07),
	(97, 14, 321.36, -1044.07, 647.56, -860.62),
	(98, 14, 321.36, -860.62, 687.8, -768.03),
	(99, 14, 647.56, -954.66, 768.69, -860.62),
	(100, 14, 647.56, -1118.28, 787.46, -954.66),
	(101, 17, 687.8, -860.62, 911.8, -768.03),
	(102, 17, 737.57, -768.03, 1142.29, -674.89),
	(103, 17, 768.69, -954.66, 952.6, -860.62),
	(104, 17, 861.09, -674.89, 1156.55, -600.9),
	(105, 17, 911.8, -860.62, 1096.47, -768.03),
	(106, 17, 952.6, -937.18, 1096.47, -860.62),
	(107, 17, 1096.47, -910.17, 1169.13, -768.03),
	(108, 17, 1169.13, -910.17, 1318.13, -768.03),
	(109, 17, 1269.13, -768.03, 1414.07, -452.42),
	(110, 17, 1281.13, -452.42, 1641.13, -290.91),
	(111, 17, 1318.13, -910.17, 1357, -768.03),
	(112, 17, 1357, -927, 1463.9, -768.03),
	(113, 17, 1414.07, -768.03, 1667.61, -452.42);

-- Dumping structure for table sols_turf_wars.turf_control
CREATE TABLE IF NOT EXISTS `turf_control` (
  `turf_id` int(11) NOT NULL,
  `faction_id` int(11) NOT NULL,
  `control_percent` int(11) DEFAULT 0,
  `last_updated` int(11) NOT NULL DEFAULT 0,
  `last_capture_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`turf_id`,`faction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_control: ~120 rows (approximately)
INSERT INTO `turf_control` (`turf_id`, `faction_id`, `control_percent`, `last_updated`, `last_capture_by`) VALUES
	(1, 1, 100, 0, NULL),
	(1, 2, 0, 0, NULL),
	(1, 3, 0, 0, NULL),
	(1, 4, 0, 0, NULL),
	(1, 5, 0, 0, NULL),
	(1, 6, 0, 0, NULL),
	(2, 1, 0, 0, NULL),
	(2, 2, 100, 0, NULL),
	(2, 3, 0, 0, NULL),
	(2, 4, 0, 0, NULL),
	(2, 5, 0, 0, NULL),
	(2, 6, 0, 0, NULL),
	(3, 1, 100, 0, NULL),
	(3, 2, 0, 0, NULL),
	(3, 3, 0, 0, NULL),
	(3, 4, 0, 0, NULL),
	(3, 5, 0, 0, NULL),
	(3, 6, 0, 0, NULL),
	(4, 1, 0, 0, NULL),
	(4, 2, 0, 0, NULL),
	(4, 3, 100, 0, NULL),
	(4, 4, 0, 0, NULL),
	(4, 5, 0, 0, NULL),
	(4, 6, 0, 0, NULL),
	(5, 1, 100, 0, NULL),
	(5, 2, 0, 0, NULL),
	(5, 3, 0, 0, NULL),
	(5, 4, 0, 0, NULL),
	(5, 5, 0, 0, NULL),
	(5, 6, 0, 0, NULL),
	(6, 1, 0, 0, NULL),
	(6, 2, 0, 0, NULL),
	(6, 3, 100, 0, NULL),
	(6, 4, 0, 0, NULL),
	(6, 5, 0, 0, NULL),
	(6, 6, 0, 0, NULL),
	(7, 1, 0, 0, NULL),
	(7, 2, 0, 0, NULL),
	(7, 3, 100, 0, NULL),
	(7, 4, 0, 0, NULL),
	(7, 5, 0, 0, NULL),
	(7, 6, 0, 0, NULL),
	(8, 1, 0, 0, NULL),
	(8, 2, 0, 0, NULL),
	(8, 3, 0, 0, NULL),
	(8, 4, 100, 0, NULL),
	(8, 5, 0, 0, NULL),
	(8, 6, 0, 0, NULL),
	(9, 1, 0, 0, NULL),
	(9, 2, 0, 0, NULL),
	(9, 3, 0, 0, NULL),
	(9, 4, 100, 0, NULL),
	(9, 5, 0, 0, NULL),
	(9, 6, 0, 0, NULL),
	(10, 1, 0, 0, NULL),
	(10, 2, 100, 0, NULL),
	(10, 3, 0, 0, NULL),
	(10, 4, 0, 0, NULL),
	(10, 5, 0, 0, NULL),
	(10, 6, 0, 0, NULL),
	(11, 1, 0, 0, NULL),
	(11, 2, 100, 0, NULL),
	(11, 3, 0, 0, NULL),
	(11, 4, 0, 0, NULL),
	(11, 5, 0, 0, NULL),
	(11, 6, 0, 0, NULL),
	(12, 1, 0, 0, NULL),
	(12, 2, 0, 0, NULL),
	(12, 3, 0, 0, NULL),
	(12, 4, 100, 0, NULL),
	(12, 5, 0, 0, NULL),
	(12, 6, 0, 0, NULL),
	(13, 1, 0, 0, NULL),
	(13, 2, 0, 0, NULL),
	(13, 3, 0, 0, NULL),
	(13, 4, 100, 0, NULL),
	(13, 5, 0, 0, NULL),
	(13, 6, 0, 0, NULL),
	(14, 1, 0, 0, NULL),
	(14, 2, 100, 0, NULL),
	(14, 3, 0, 0, NULL),
	(14, 4, 0, 0, NULL),
	(14, 5, 0, 0, NULL),
	(14, 6, 0, 0, NULL),
	(15, 1, 0, 0, NULL),
	(15, 2, 0, 0, NULL),
	(15, 3, 0, 0, NULL),
	(15, 4, 0, 0, NULL),
	(15, 5, 0, 0, NULL),
	(15, 6, 100, 0, NULL),
	(16, 1, 0, 0, NULL),
	(16, 2, 0, 0, NULL),
	(16, 3, 100, 0, NULL),
	(16, 4, 0, 0, NULL),
	(16, 5, 0, 0, NULL),
	(16, 6, 0, 0, NULL),
	(17, 1, 100, 0, NULL),
	(17, 2, 0, 0, NULL),
	(17, 3, 0, 0, NULL),
	(17, 4, 0, 0, NULL),
	(17, 5, 0, 0, NULL),
	(17, 6, 0, 0, NULL),
	(18, 1, 0, 0, NULL),
	(18, 2, 0, 0, NULL),
	(18, 3, 0, 0, NULL),
	(18, 4, 0, 0, NULL),
	(18, 5, 0, 0, NULL),
	(18, 6, 100, 0, NULL),
	(19, 1, 0, 0, NULL),
	(19, 2, 0, 0, NULL),
	(19, 3, 0, 0, NULL),
	(19, 4, 0, 0, NULL),
	(19, 5, 0, 0, NULL),
	(19, 6, 100, 0, NULL),
	(20, 1, 0, 0, NULL),
	(20, 2, 0, 0, NULL),
	(20, 3, 0, 0, NULL),
	(20, 4, 0, 0, NULL),
	(20, 5, 0, 0, NULL),
	(20, 6, 100, 0, NULL);

-- Dumping structure for table sols_turf_wars.turf_features
CREATE TABLE IF NOT EXISTS `turf_features` (
  `turf_id` int(11) NOT NULL,
  `feature_type` varchar(32) NOT NULL,
  PRIMARY KEY (`turf_id`,`feature_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_features: ~5 rows (approximately)
INSERT INTO `turf_features` (`turf_id`, `feature_type`) VALUES
	(2, 'cluckin_bell'),
	(3, 'hospital'),
	(10, 'burger_shot'),
	(12, 'ammu_nation'),
	(20, 'pizza');

-- Dumping structure for table sols_turf_wars.turf_wars
CREATE TABLE IF NOT EXISTS `turf_wars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turf_id` int(11) NOT NULL,
  `attacker_faction_id` int(11) NOT NULL,
  `defender_faction_id` int(11) NOT NULL,
  `attacker_score` int(11) DEFAULT 0,
  `defender_score` int(11) DEFAULT 0,
  `target_score` int(11) DEFAULT 1000,
  `status` enum('active','finished') DEFAULT 'active',
  `winner_faction_id` int(11) DEFAULT NULL,
  `started_by_char_id` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_wars: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.turf_war_logs
CREATE TABLE IF NOT EXISTS `turf_war_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `war_id` int(11) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_war_logs: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.turf_war_participants
CREATE TABLE IF NOT EXISTS `turf_war_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `war_id` int(11) NOT NULL,
  `char_id` int(11) NOT NULL,
  `faction_id` int(11) NOT NULL,
  `is_alive` tinyint(4) DEFAULT 1,
  `total_points` int(11) DEFAULT 0,
  `total_attacks` int(11) DEFAULT 0,
  `killed_at` datetime DEFAULT NULL,
  `current_hp` int(11) NOT NULL DEFAULT 100,
  `max_hp` int(11) NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.turf_war_participants: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(24) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_ip` varchar(45) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.users: ~0 rows (approximately)

-- Dumping structure for table sols_turf_wars.weapons
CREATE TABLE IF NOT EXISTS `weapons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `war_points_bonus` int(11) NOT NULL DEFAULT 0,
  `kill_chance_bonus` int(11) NOT NULL DEFAULT 0,
  `damage` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sols_turf_wars.weapons: ~8 rows (approximately)
INSERT INTO `weapons` (`id`, `city_id`, `name`, `description`, `price`, `war_points_bonus`, `kill_chance_bonus`, `damage`) VALUES
	(1, 1, 'Fists', 'Your bare hands.', 0, 0, 0, 10),
	(2, 1, 'Knife', 'A sharp blade.', 500, 1, 1, 18),
	(3, 1, 'Baseball Bat', 'Solid and reliable.', 800, 2, 0, 20),
	(4, 1, 'Pistol', 'Standard sidearm.', 2000, 3, 2, 25),
	(5, 1, 'Shotgun', 'Devastating at close range.', 4500, 4, 4, 35),
	(6, 1, 'SMG', 'High rate of fire.', 7500, 6, 3, 28),
	(7, 1, 'AK-47', 'Powerful assault rifle.', 13000, 8, 5, 30),
	(8, 1, 'M4', 'Military-grade firepower.', 22000, 10, 7, 35);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
