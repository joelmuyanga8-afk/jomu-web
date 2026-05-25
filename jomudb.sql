-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 25, 2026 at 11:41 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jomudb`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `log_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `target_type` varchar(60) NOT NULL,
  `target_id` int UNSIGNED DEFAULT NULL,
  `details` text,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_admin_logs_target` (`target_type`,`target_id`),
  KEY `idx_admin_logs_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`log_id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:02:50'),
(2, 1, 'hide_listing', 'listing', 126, 'See', '::1', '2026-05-10 15:04:00'),
(3, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:05:38'),
(4, 1, 'hide_listing', 'listing', 125, 'chech', '::1', '2026-05-10 15:05:42'),
(5, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:07:22'),
(6, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:08:31'),
(7, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:09:31'),
(8, 1, 'login', 'admin', 1, '', '::1', '2026-05-10 15:12:25'),
(9, 1, 'inactive_account', 'user', 93, '', '::1', '2026-05-10 15:42:55'),
(10, 1, 'activate_account', 'user', 93, '', '::1', '2026-05-10 15:43:13'),
(11, 1, 'send_system_message', 'user', 93, 'warning', '::1', '2026-05-10 20:15:19'),
(12, 1, 'send_system_message', 'user', 93, 'terms', '::1', '2026-05-10 20:16:20'),
(13, 1, 'send_system_message', 'user', 93, 'support', '::1', '2026-05-10 20:16:44'),
(14, 1, 'login', 'admin', 1, '', '::1', '2026-05-11 13:00:16'),
(15, 1, 'login', 'admin', 1, '', '::1', '2026-05-11 13:24:10'),
(16, 1, 'login', 'admin', 1, '', '::1', '2026-05-11 17:33:19'),
(17, 1, 'login', 'admin', 1, '', '::1', '2026-05-11 17:34:01'),
(18, 1, 'hide_listing', 'listing', 99, 'Dresses', '::1', '2026-05-11 18:28:36'),
(19, 1, 'unhide_listing', 'listing', 99, 'Dresses', '::1', '2026-05-11 18:28:43'),
(20, 1, 'inactive_account', 'user', 99, '14 days: Spamming', '::1', '2026-05-11 18:28:59'),
(21, 1, 'activate_account', 'user', 99, '', '::1', '2026-05-11 18:30:44'),
(22, 1, 'inactive_account', 'user', 99, '7 days: Old', '::1', '2026-05-11 18:30:58'),
(23, 1, 'activate_account', 'user', 99, '', '::1', '2026-05-11 18:31:06'),
(24, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-11 18:31:35'),
(25, 1, 'unhide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-11 18:31:55'),
(26, 1, 'update_site_link', 'site_link', NULL, 'app', '::1', '2026-05-11 18:32:09'),
(27, 1, 'approve_listing', 'listing', 128, 'Clothes many', '::1', '2026-05-11 18:54:35'),
(28, 1, 'send_system_message', 'user', 99, 'warning', '::1', '2026-05-11 18:59:46'),
(29, 1, 'hide_listing', 'listing', 98, 'Cocoa', '::1', '2026-05-11 19:02:37'),
(30, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-11 19:19:29'),
(31, 1, 'unhide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-11 19:19:38'),
(32, 1, 'hide_bulk_order_comment', 'bulk_order_post', 13, 'Okay today', '::1', '2026-05-11 19:27:31'),
(33, 1, 'unhide_bulk_order_comment', 'bulk_order_post', 13, 'Okay today', '::1', '2026-05-11 19:27:36'),
(34, 1, 'inactive_account', 'user', 91, '28 days: Spamming too', '::1', '2026-05-11 21:14:14'),
(35, 1, 'activate_account', 'user', 91, '', '::1', '2026-05-11 21:14:21'),
(36, 1, 'login', 'admin', 1, '', '::1', '2026-05-11 21:27:38'),
(37, 1, 'send_system_message', 'user', 91, 'terms', '::1', '2026-05-11 23:55:18'),
(38, 1, 'login', 'admin', 1, '', '::1', '2026-05-12 00:00:01'),
(39, 1, 'login', 'admin', 1, '', '::1', '2026-05-12 00:00:28'),
(40, 1, 'send_system_message', 'user', 91, 'support', '::1', '2026-05-12 00:01:36'),
(41, 1, 'login', 'admin', 1, '', '::1', '2026-05-12 16:16:53'),
(42, 1, 'suspend_account', 'user', 99, '7 days: hhshs', '::1', '2026-05-12 16:22:59'),
(43, 1, 'activate_account', 'user', 99, '', '::1', '2026-05-12 16:23:10'),
(44, 1, 'login', 'admin', 1, '', '::1', '2026-05-12 16:36:25'),
(45, 1, 'login', 'admin', 1, '', '::1', '2026-05-12 16:50:24'),
(46, 1, 'suspend_account', 'user', 101, '21 days: Spamming messages', '::1', '2026-05-12 17:16:35'),
(47, 1, 'activate_account', 'user', 101, '', '::1', '2026-05-12 17:18:51'),
(48, 1, 'send_system_message', 'user', 101, 'terms', '::1', '2026-05-12 17:21:05'),
(49, 1, 'send_system_message', 'user', 101, 'JoMu Watches: Take note of your language on our platfor.', '::1', '2026-05-12 17:22:32'),
(50, 1, 'send_system_message', 'user', 101, 'JoMu Watches: Take note of your language on our platform.', '::1', '2026-05-12 17:23:11'),
(51, 1, 'send_system_message', 'user', 101, 'warning', '::1', '2026-05-12 17:23:35'),
(52, 1, 'send_system_message', 'user', 101, 'support', '::1', '2026-05-12 17:24:32'),
(53, 1, 'terminate_account', 'user', 101, 'Illegal posts.', '::1', '2026-05-12 17:25:31'),
(54, 1, 'terminate_account', 'user', 102, 'Unwanted', '::1', '2026-05-12 17:30:44'),
(55, 1, 'terminate_account', 'user', 94, 'Just', '::1', '2026-05-12 17:33:33'),
(56, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-12 17:58:15'),
(57, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-12 17:58:32'),
(58, 1, 'unhide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-12 18:00:30'),
(59, 1, 'hide_listing', 'listing', 123, 'chech', '::1', '2026-05-12 18:01:30'),
(60, 1, 'unhide_listing', 'listing', 123, 'chech', '::1', '2026-05-12 18:02:33'),
(61, 1, 'approve_listing', 'listing', 127, 'sajvxyeqvu eohvfuewhfw sajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu ', '::1', '2026-05-12 18:02:37'),
(62, 1, 'suspend_account', 'user', 103, '7 days: nothing', '::1', '2026-05-12 22:40:38'),
(63, 1, 'activate_account', 'user', 103, '', '::1', '2026-05-12 22:42:51'),
(64, 1, 'suspend_account', 'user', 103, '7 days: ibviybvub ubuvwabiu ubvnv ibquvnu iyhbiunvi iniunhviuaq ibviybvub ubuvwabiu ubvnv ibquvnu iyhbiunvi iniunhviuaq ibviybvub ubuvwabiu ubvnv ibquvnu iyhbiunvi iniunhviuaq', '::1', '2026-05-12 22:43:13'),
(65, 1, 'terminate_account', 'user', 103, 'nothing', '::1', '2026-05-12 22:44:12'),
(66, 1, 'login', 'admin', 1, '', '::1', '2026-05-13 22:02:28'),
(67, 1, 'hide_listing', 'listing', 123, 'chech', '::1', '2026-05-13 23:34:59'),
(68, 1, 'unhide_listing', 'listing', 123, 'chech', '::1', '2026-05-13 23:35:45'),
(69, 1, 'purge_listing', 'listing', 125, 'chech', '::1', '2026-05-13 23:35:52'),
(70, 1, 'login', 'admin', 1, '', '::1', '2026-05-14 10:56:35'),
(71, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-14 10:59:34'),
(72, 1, 'login', 'admin', 1, '', '::1', '2026-05-14 11:05:05'),
(73, 1, 'purge_listing', 'listing', 98, 'Cocoa', '::1', '2026-05-14 11:23:33'),
(74, 1, 'hide_listing', 'listing', 124, 'chech', '::1', '2026-05-14 11:24:14'),
(75, 1, 'unhide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-14 11:25:55'),
(76, 1, 'login', 'admin', 1, '', '::1', '2026-05-15 15:34:57'),
(77, 1, 'hide_listing', 'listing', 127, 'sajvxyeqvu eohvfuewhfw sajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu ', '::1', '2026-05-15 15:47:02'),
(78, 1, 'hide_bulk_order_comment', 'bulk_order_post', 14, 'We are looking for kids\' hats suppliers (bulk/wholesale). Kindly inbox if you deal in this.', '::1', '2026-05-15 16:05:58'),
(79, 1, 'hide_listing', 'listing', 99, 'Dresses', '::1', '2026-05-15 16:07:28'),
(80, 1, 'login', 'admin', 1, '', '::1', '2026-05-15 16:27:35'),
(81, 1, 'hide_listing', 'listing', 96, 'Kids\' Wear', '::1', '2026-05-15 16:29:46'),
(82, 1, 'suspend_account', 'user', 104, '21 days: Spamming messages', '::1', '2026-05-15 16:36:21'),
(83, 1, 'activate_account', 'user', 104, '', '::1', '2026-05-15 16:47:09'),
(84, 1, 'terminate_account', 'user', 104, 'Spamming again.', '::1', '2026-05-15 16:48:15'),
(85, 1, 'send_system_message', 'user', 105, 'terms', '::1', '2026-05-15 17:24:23'),
(86, 1, 'send_system_message', 'user', 105, 'Hello There', '::1', '2026-05-15 17:25:00'),
(87, 1, 'suspend_account', 'user', 105, '28 days: Spamming', '::1', '2026-05-15 17:32:50'),
(88, 1, 'terminate_account', 'user', 105, 'Spamming', '::1', '2026-05-15 18:15:39'),
(89, 1, 'send_system_message_all', 'users', NULL, '3 recipients: Hello there guys', '::1', '2026-05-15 18:19:34'),
(90, 1, 'suspend_account', 'user', 106, '7 days: Not', '::1', '2026-05-15 18:42:10'),
(91, 1, 'unhide_listing', 'listing', 99, 'Dresses', '::1', '2026-05-15 18:47:01'),
(92, 1, 'login', 'admin', 1, '', '::1', '2026-05-16 23:52:19'),
(93, 1, 'hide_listing', 'listing', 123, 'chech', '::1', '2026-05-17 00:42:04'),
(94, 1, 'hide_listing', 'listing', 128, 'Clothes many', '::1', '2026-05-17 00:42:57'),
(95, 1, 'hide_listing', 'listing', 119, 'Catering charcoal', '::1', '2026-05-17 00:43:47'),
(96, 1, 'unhide_listing', 'listing', 96, 'Kids\' Wear', '::1', '2026-05-17 00:45:17'),
(97, 1, 'unhide_listing', 'listing', 119, 'Catering charcoal', '::1', '2026-05-17 00:45:22'),
(98, 1, 'unhide_listing', 'listing', 127, 'sajvxyeqvu eohvfuewhfw sajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu eohvfuewhfwsajvxyeqvu ', '::1', '2026-05-17 00:45:30'),
(99, 1, 'unhide_listing', 'listing', 128, 'Clothes many', '::1', '2026-05-17 00:45:38'),
(100, 1, 'unhide_listing', 'listing', 128, 'Clothes many', '::1', '2026-05-17 00:45:38'),
(101, 1, 'login', 'admin', 1, '', '::1', '2026-05-17 07:55:58'),
(102, 1, 'send_system_message_all', 'users', NULL, '4 recipients: Hey there All.', '::1', '2026-05-17 08:06:43'),
(103, 1, 'unhide_listing', 'listing', 123, 'chech', '::1', '2026-05-17 08:33:51'),
(104, 1, 'login', 'admin', 1, '', '::1', '2026-05-17 10:40:31'),
(105, 1, 'send_system_message_all', 'users', NULL, '4 recipients: Okay see you all.', '::1', '2026-05-17 11:29:04'),
(106, 1, 'send_system_message', 'user', 106, 'support', '::1', '2026-05-17 11:29:51'),
(107, 1, 'send_system_message', 'user', 93, 'warning', '::1', '2026-05-17 11:30:08'),
(108, 1, 'send_system_message_all', 'users', NULL, '4 recipients: No', '::1', '2026-05-17 11:30:35'),
(109, 1, 'update_site_link', 'site_link', NULL, 'facebook', '::1', '2026-05-17 18:21:07'),
(110, 1, 'update_site_link', 'site_link', NULL, 'instagram', '::1', '2026-05-17 18:21:12'),
(111, 1, 'update_site_link', 'site_link', NULL, 'tiktok', '::1', '2026-05-17 18:21:15'),
(112, 1, 'update_site_link', 'site_link', NULL, 'x', '::1', '2026-05-17 18:21:19'),
(113, 1, 'update_site_link', 'site_link', NULL, 'support_phone', '::1', '2026-05-17 19:44:32'),
(114, 1, 'update_site_link', 'site_link', NULL, 'support_whatsapp', '::1', '2026-05-17 19:45:12'),
(115, 1, 'update_site_link', 'site_link', NULL, 'support_whatsapp', '::1', '2026-05-17 19:57:57'),
(116, 1, 'login', 'admin', 1, '', '::1', '2026-05-17 22:51:15'),
(117, 1, 'login', 'admin', 1, '', '::1', '2026-05-18 01:15:28'),
(118, 1, 'approve_listing', 'listing', 130, 'Natural Pebble Gravel', '::1', '2026-05-18 01:15:53'),
(119, 1, 'approve_listing', 'listing', 131, 'Concrete Paver Blocks', '::1', '2026-05-18 01:16:02'),
(120, 1, 'approve_listing', 'listing', 132, 'Hardwood & Softwood Timber', '::1', '2026-05-18 01:16:09'),
(121, 1, 'approve_listing', 'listing', 133, 'Hardcore Stones', '::1', '2026-05-18 01:16:14'),
(122, 1, 'approve_listing', 'listing', 134, 'Construction Pipe Systems', '::1', '2026-05-18 01:16:19'),
(123, 1, 'approve_listing', 'listing', 135, 'Local White Rice', '::1', '2026-05-18 01:16:24'),
(124, 1, 'approve_listing', 'listing', 136, 'Sugar', '::1', '2026-05-18 01:16:29'),
(125, 1, 'approve_listing', 'listing', 137, 'Parboiled Rice', '::1', '2026-05-18 01:16:33'),
(126, 1, 'approve_listing', 'listing', 138, 'Raw Cocoa Beans', '::1', '2026-05-18 01:16:38'),
(127, 1, 'approve_listing', 'listing', 139, 'Second-hand Laptops - Fully Tested', '::1', '2026-05-18 01:16:42'),
(128, 1, 'approve_listing', 'listing', 140, 'UK Used Phones', '::1', '2026-05-18 01:16:47'),
(129, 1, 'approve_bulk_order_comment', 'bulk_order_post', 19, 'We are looking for a reliable supplier of shirts made from locally produced bark cloth in bulk. Consistent quality and large scale supply is required. Kindly reach out to us as soon as possible.', '::1', '2026-05-18 01:26:10'),
(130, 1, 'login', 'admin', 1, '', '::1', '2026-05-18 10:28:46'),
(131, 1, 'login', 'admin', 1, '', '::1', '2026-05-21 12:14:34'),
(132, 1, 'update_site_link', 'site_link', NULL, 'instagram', '::1', '2026-05-21 12:20:08'),
(133, 1, 'hide_listing', 'listing', 144, 'Catering', '::1', '2026-05-21 13:29:18'),
(134, 1, 'hide_listing', 'listing', 145, 'Catering', '::1', '2026-05-21 13:30:33'),
(135, 1, 'purge_listing', 'listing', 144, 'Catering', '::1', '2026-05-21 13:31:02'),
(136, 1, 'purge_listing', 'listing', 145, 'Catering', '::1', '2026-05-21 13:31:14'),
(137, 1, 'hide_listing', 'listing', 131, 'Concrete Paver Blocks', '::1', '2026-05-21 13:32:20'),
(138, 1, 'unhide_listing', 'listing', 131, 'Concrete Paver Blocks', '::1', '2026-05-21 13:34:04'),
(139, 1, 'login', 'admin', 1, '', '::1', '2026-05-22 17:08:10'),
(140, 1, 'login', 'admin', 1, '', '::1', '2026-05-22 17:08:11'),
(141, 1, 'hide_listing', 'listing', 135, 'Local White Rice', '::1', '2026-05-22 17:19:00'),
(142, 1, 'unhide_listing', 'listing', 135, 'Local White Rice', '::1', '2026-05-22 17:20:07'),
(143, 1, 'login', 'admin', 1, '', '::1', '2026-05-24 17:51:03'),
(144, 1, 'hide_listing', 'listing', 132, 'Hardwood & Softwood Timber', '::1', '2026-05-24 19:15:50'),
(145, 1, 'unhide_listing', 'listing', 132, 'Hardwood & Softwood Timber', '::1', '2026-05-24 19:30:05'),
(146, 1, 'login', 'admin', 1, '', '::1', '2026-05-25 10:59:46');

-- --------------------------------------------------------

--
-- Table structure for table `admin_message_batches`
--

DROP TABLE IF EXISTS `admin_message_batches`;
CREATE TABLE IF NOT EXISTS `admin_message_batches` (
  `batch_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `message_text` text NOT NULL,
  `recipient_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`),
  KEY `idx_admin_message_batches_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_message_batches`
--

INSERT INTO `admin_message_batches` (`batch_id`, `admin_id`, `message_text`, `recipient_count`, `created_at`) VALUES
(1, 1, 'Okay see you all.', 4, '2026-05-17 11:29:04'),
(2, 1, 'No', 4, '2026-05-17 11:30:34');

-- --------------------------------------------------------

--
-- Table structure for table `admin_password_resets`
--

DROP TABLE IF EXISTS `admin_password_resets`;
CREATE TABLE IF NOT EXISTS `admin_password_resets` (
  `reset_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` int UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  KEY `idx_admin_password_resets_token` (`token_hash`),
  KEY `idx_admin_password_resets_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_password_resets`
--

INSERT INTO `admin_password_resets` (`reset_id`, `admin_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 1, 'a224c3407ae9a7c4fe2af77f0725c7acf7ac51fa53c49d33402cea58468e750b', '2026-05-10 15:40:24', NULL, '2026-05-10 15:10:24'),
(2, 1, 'e422eaab0d9c6f45e6074a486b8fe74f76860668874c5736ea6e6a45ce918443', '2026-05-13 22:31:47', NULL, '2026-05-13 22:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `admin_terminated_users`
--

DROP TABLE IF EXISTS `admin_terminated_users`;
CREATE TABLE IF NOT EXISTS `admin_terminated_users` (
  `terminated_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `businessname` varchar(255) DEFAULT NULL,
  `emailormobilenumber` varchar(255) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `terminated_by_admin_id` int UNSIGNED DEFAULT NULL,
  `terminated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`terminated_id`),
  KEY `idx_admin_terminated_users_user` (`user_id`),
  KEY `idx_admin_terminated_users_at` (`terminated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_terminated_users`
--

INSERT INTO `admin_terminated_users` (`terminated_id`, `user_id`, `businessname`, `emailormobilenumber`, `reason`, `terminated_by_admin_id`, `terminated_at`) VALUES
(1, 101, 'Gooder Limited', 'gooder@gmail.com', 'Illegal posts.', 1, '2026-05-12 17:25:31'),
(2, 102, 'Gooder Meeeee', 'me@gmail.com', 'Unwanted', 1, '2026-05-12 17:30:44'),
(3, 94, 'Ntake Catering Services', 'ntake@gmail.com', 'Just', 1, '2026-05-12 17:33:33'),
(4, 103, 'Gooder Limited', 'gooder@gmail.com', 'nothing', 1, '2026-05-12 22:44:12'),
(5, 104, 'Meeeee Limited', 'meeeee@gmail.com', 'Spamming again.', 1, '2026-05-15 16:48:15'),
(6, 105, 'Looooo Inter', 'looooo@gmail.com', 'Spamming', 1, '2026-05-15 18:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `admin_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT 'JoMu Admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `email`, `password`, `name`, `created_at`, `updated_at`) VALUES
(1, 'joelmuyanga8@gmail.com', '$2y$10$L0DMBYGKJPvI3..fNPA.seq7b/xOU16QfUCWrwsm1rgpaXewP6Hpa', 'JoMu Admin', '2026-05-10 14:58:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bulk_order_posts`
--

DROP TABLE IF EXISTS `bulk_order_posts`;
CREATE TABLE IF NOT EXISTS `bulk_order_posts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `business_name` varchar(255) NOT NULL,
  `profilepic` varchar(500) NOT NULL DEFAULT 'assets/images/Caterers2.webp',
  `content` text NOT NULL,
  `fulfilled` tinyint(1) NOT NULL DEFAULT '0',
  `fulfilled_at` datetime DEFAULT NULL,
  `moderation_status` varchar(20) NOT NULL DEFAULT 'visible',
  `hidden_reason` varchar(255) DEFAULT NULL,
  `hidden_at` datetime DEFAULT NULL,
  `hidden_by_admin_id` int UNSIGNED DEFAULT NULL,
  `admin_reviewed_at` datetime DEFAULT NULL,
  `admin_purged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_fulfilled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bulk_order_posts_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bulk_order_posts`
--

INSERT INTO `bulk_order_posts` (`id`, `user_id`, `business_name`, `profilepic`, `content`, `fulfilled`, `fulfilled_at`, `moderation_status`, `hidden_reason`, `hidden_at`, `hidden_by_admin_id`, `admin_reviewed_at`, `admin_purged_at`, `created_at`, `is_fulfilled`) VALUES
(19, 112, 'WearPoint Fashions Ltd', 'uploads/profile/media_6a0a3b9593b273.42451034.jpg', 'We are looking for a reliable supplier of shirts made from locally produced bark cloth in bulk. Consistent quality and large scale supply is required. Kindly reach out to us as soon as possible.', 0, NULL, 'visible', NULL, NULL, NULL, '2026-05-18 01:26:10', NULL, '2026-05-18 01:25:12', 0);

-- --------------------------------------------------------

--
-- Table structure for table `bulk_order_post_likes`
--

DROP TABLE IF EXISTS `bulk_order_post_likes`;
CREATE TABLE IF NOT EXISTS `bulk_order_post_likes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bulk_order_post_like` (`post_id`,`user_id`),
  KEY `idx_bulk_order_post_likes_post_id` (`post_id`),
  KEY `idx_bulk_order_post_likes_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bulk_order_post_likes`
--

INSERT INTO `bulk_order_post_likes` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(36, 14, 91, '2026-04-28 20:36:50'),
(39, 14, 93, '2026-05-09 00:15:20'),
(41, 13, 91, '2026-05-17 11:04:39'),
(46, 19, 112, '2026-05-18 01:25:16'),
(49, 19, 108, '2026-05-22 17:39:06');

-- --------------------------------------------------------

--
-- Table structure for table `business_messages`
--

DROP TABLE IF EXISTS `business_messages`;
CREATE TABLE IF NOT EXISTS `business_messages` (
  `message_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_user_id` int UNSIGNED NOT NULL,
  `receiver_user_id` int UNSIGNED NOT NULL,
  `message_type` varchar(20) NOT NULL DEFAULT 'text',
  `message_text` text,
  `media_path` varchar(255) DEFAULT NULL,
  `reply_to_message_id` int UNSIGNED DEFAULT NULL,
  `is_system_message` tinyint(1) NOT NULL DEFAULT '0',
  `admin_message_batch_id` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_sender_created` (`sender_user_id`,`created_at`),
  KEY `idx_receiver_created` (`receiver_user_id`,`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `business_messages`
--

INSERT INTO `business_messages` (`message_id`, `sender_user_id`, `receiver_user_id`, `message_type`, `message_text`, `media_path`, `reply_to_message_id`, `is_system_message`, `admin_message_batch_id`, `created_at`) VALUES
(196, 100, 107, 'text', 'Your listing has been restored on JoMu. Listing: http://localhost:900/purchasewholesale.html?listing_id=132&owner_view=1', NULL, NULL, 1, NULL, '2026-05-24 19:30:05'),
(189, 100, 107, 'text', 'This listing has been hidden for being against the JoMu Terms of Use.\n\nListing: http://localhost:900/purchasewholesale.html?listing_id=145&owner_view=1', NULL, NULL, 1, NULL, '2026-05-21 13:30:33'),
(188, 100, 107, 'text', 'This listing has been hidden for being against the JoMu Terms of Use.\n\nListing: http://localhost:900/purchasewholesale.html?listing_id=144&owner_view=1', NULL, NULL, 1, NULL, '2026-05-21 13:29:18'),
(187, 109, 113, 'text', 'Hi, regarding your purchase request for Raw Cocoa Beans: http://localhost:900/purchasewholesale.html?image=%2Fphp%2Fuploads%2Fprofile%2Fmedia_6a0a3523b651f9.01184494.jpg&title=Raw+Cocoa+Beans&price=USh+400%2C000+-+650%2C000+%2F+unit&raw_price=0.00&price_from=400000&price_to=650000&description=Actively+sourcing+and+purchasing+fermented+and+dried+cocoa+beans+from+reliable+farmers%2C+cooperatives%2C+and+suppliers+for+export+and+industrial+use.&category=Agriculture+%26+Produce&seller_businessname=W%26A+Cocoa+Exporters&seller_profilepic=uploads%2Fprofile%2Fmedia_6a0a34324e0172.97638551.jpg&seller_id=109&listing_id=138&listing_type=product&request_view=1&req_amount=500000&req_payment_mode=MM&req_delivery_method=Delivery&req_location=Kisubi', NULL, 0, 0, NULL, '2026-05-18 11:01:17'),
(195, 100, 107, 'text', 'This listing has been hidden for being against the JoMu Terms of Use.\n\nListing: http://localhost:900/purchasewholesale.html?listing_id=132&owner_view=1', NULL, NULL, 1, NULL, '2026-05-24 19:15:50'),
(194, 100, 108, 'text', 'Your listing has been restored on JoMu. Listing: http://localhost:900/purchasewholesale.html?listing_id=135&owner_view=1', NULL, NULL, 1, NULL, '2026-05-22 17:20:07'),
(193, 100, 108, 'text', 'This listing has been hidden for being against the JoMu Terms of Use.\n\nListing: http://localhost:900/purchasewholesale.html?listing_id=135&owner_view=1', NULL, NULL, 1, NULL, '2026-05-22 17:19:00'),
(192, 108, 107, 'text', 'Hi, regarding your purchase request for Local White Rice: http://localhost:900/purchasewholesale.html?image=%2Fphp%2Fuploads%2Fprofile%2Fmedia_6a0a2f673fb468.43035346.jpg&title=Local+White+Rice&price=USh+180%2C000+-+260%2C000+%2F+unit&raw_price=0.00&price_from=180000&price_to=260000&description=High+quality+rice+available+in+different+grades+including+long+grain%2C+parboiled%2C+and+broken+rice+with+reliable+supply+and+competitive+wholesale+pricing.&category=Wholesale+%26+Retail&seller_businessname=Nansubuga+HarvestHub+Foods&seller_profilepic=uploads%2Fprofile%2Fmedia_6a0a2e11eb3a00.34679624.jpg&seller_id=108&listing_id=135&listing_type=product&request_view=1&req_amount=30+Bags&req_payment_mode=Cash&req_delivery_method=Self+Pick&req_location=Kazo', NULL, 0, 0, NULL, '2026-05-22 17:16:14'),
(191, 100, 107, 'text', 'Your listing has been restored on JoMu. Listing: http://localhost:900/purchasewholesale.html?listing_id=131&owner_view=1', NULL, NULL, 1, NULL, '2026-05-21 13:34:04'),
(190, 100, 107, 'text', 'This listing has been hidden for being against the JoMu Terms of Use.\n\nListing: http://localhost:900/purchasewholesale.html?listing_id=131&owner_view=1', NULL, NULL, 1, NULL, '2026-05-21 13:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `business_message_hidden_for_user`
--

DROP TABLE IF EXISTS `business_message_hidden_for_user`;
CREATE TABLE IF NOT EXISTS `business_message_hidden_for_user` (
  `message_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `hidden_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`,`user_id`),
  KEY `idx_user_hidden` (`user_id`,`hidden_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `business_message_hidden_for_user`
--

INSERT INTO `business_message_hidden_for_user` (`message_id`, `user_id`, `hidden_at`) VALUES
(80, 91, '2026-04-25 12:36:22'),
(82, 91, '2026-04-23 17:34:32'),
(81, 91, '2026-04-23 17:34:18'),
(79, 91, '2026-04-21 12:53:32'),
(78, 91, '2026-04-21 12:53:32'),
(69, 93, '2026-05-08 12:13:01'),
(70, 93, '2026-05-08 12:13:01'),
(79, 93, '2026-05-08 12:13:01'),
(78, 93, '2026-05-08 12:13:01'),
(77, 93, '2026-05-08 12:13:01'),
(76, 93, '2026-05-08 12:13:01'),
(75, 93, '2026-05-08 12:13:01'),
(74, 93, '2026-05-08 12:13:01'),
(73, 93, '2026-05-08 12:13:01'),
(72, 93, '2026-05-08 12:13:01'),
(71, 93, '2026-05-08 12:13:01'),
(77, 91, '2026-04-21 12:53:32'),
(76, 91, '2026-04-21 12:53:32'),
(75, 91, '2026-04-21 12:53:32'),
(74, 91, '2026-04-21 12:53:32'),
(73, 91, '2026-04-21 12:53:32'),
(70, 91, '2026-04-21 12:53:32'),
(69, 91, '2026-04-21 12:53:32'),
(72, 91, '2026-04-21 12:53:32'),
(71, 91, '2026-04-21 12:53:32'),
(68, 91, '2026-04-21 12:53:32'),
(65, 91, '2026-04-21 12:53:32'),
(64, 91, '2026-04-21 12:53:32'),
(66, 91, '2026-04-21 12:53:32'),
(67, 91, '2026-04-19 11:10:34'),
(64, 93, '2026-05-08 12:13:01'),
(65, 93, '2026-05-08 12:13:01'),
(68, 93, '2026-05-08 12:13:01'),
(66, 93, '2026-05-08 12:13:01'),
(86, 91, '2026-04-25 13:46:50'),
(87, 91, '2026-04-25 13:47:12'),
(86, 93, '2026-05-08 12:13:01'),
(80, 93, '2026-05-08 12:13:01'),
(82, 93, '2026-05-08 12:13:01'),
(81, 93, '2026-05-08 12:13:01'),
(89, 93, '2026-05-08 12:13:01'),
(93, 93, '2026-05-08 12:13:01'),
(92, 93, '2026-05-08 12:13:01'),
(97, 91, '2026-05-07 12:05:33'),
(96, 91, '2026-05-07 12:05:35'),
(95, 91, '2026-05-07 12:05:38'),
(94, 91, '2026-05-07 12:05:42'),
(98, 93, '2026-05-08 12:13:01'),
(100, 93, '2026-05-08 12:13:01'),
(99, 93, '2026-05-08 12:13:01'),
(97, 93, '2026-05-08 12:13:01'),
(95, 93, '2026-05-08 12:13:01'),
(96, 93, '2026-05-08 12:13:01'),
(91, 93, '2026-05-08 12:13:01'),
(94, 93, '2026-05-08 12:13:01'),
(87, 93, '2026-05-08 12:13:01'),
(88, 93, '2026-05-08 12:13:01'),
(90, 93, '2026-05-08 12:13:01'),
(84, 93, '2026-05-08 12:13:01'),
(85, 93, '2026-05-08 12:13:01'),
(83, 93, '2026-05-08 12:13:01'),
(189, 107, '2026-05-22 17:24:21');

-- --------------------------------------------------------

--
-- Table structure for table `business_message_reads`
--

DROP TABLE IF EXISTS `business_message_reads`;
CREATE TABLE IF NOT EXISTS `business_message_reads` (
  `user_id` int UNSIGNED NOT NULL,
  `partner_user_id` int UNSIGNED NOT NULL,
  `last_read_message_id` int UNSIGNED NOT NULL DEFAULT '0',
  `last_read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`partner_user_id`),
  KEY `idx_partner_user` (`partner_user_id`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `business_message_reads`
--

INSERT INTO `business_message_reads` (`user_id`, `partner_user_id`, `last_read_message_id`, `last_read_at`) VALUES
(92, 94, 0, '2026-04-18 14:25:06'),
(91, 92, 67, '2026-04-18 21:19:13'),
(92, 91, 0, '2026-04-17 14:04:12'),
(91, 93, 100, '2026-05-17 08:50:00'),
(93, 92, 0, '2026-04-17 13:48:35'),
(93, 91, 126, '2026-05-17 00:49:05'),
(91, 94, 0, '2026-05-11 23:54:45'),
(91, 99, 0, '2026-04-25 12:50:09'),
(93, 99, 0, '2026-05-05 11:40:16'),
(91, 100, 176, '2026-05-17 08:34:23'),
(93, 100, 184, '2026-05-17 11:40:37'),
(112, 110, 0, '2026-05-18 01:18:51'),
(109, 113, 0, '2026-05-18 11:01:33'),
(107, 100, 196, '2026-05-24 19:30:30'),
(108, 107, 0, '2026-05-25 11:03:48'),
(107, 108, 192, '2026-05-24 18:00:25'),
(108, 100, 194, '2026-05-25 11:03:26');

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

DROP TABLE IF EXISTS `listings`;
CREATE TABLE IF NOT EXISTS `listings` (
  `listing_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `region` varchar(20) DEFAULT NULL,
  `city_town` varchar(120) DEFAULT NULL,
  `media` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `stockname` varchar(100) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `hashtags` varchar(220) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `price_from` varchar(30) DEFAULT NULL,
  `price_to` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `listing_type` varchar(20) NOT NULL DEFAULT 'product',
  `moderation_status` varchar(20) NOT NULL DEFAULT 'visible',
  `hidden_reason` varchar(255) DEFAULT NULL,
  `hidden_at` datetime DEFAULT NULL,
  `hidden_by_admin_id` int UNSIGNED DEFAULT NULL,
  `admin_reviewed_at` datetime DEFAULT NULL,
  `admin_purged_at` datetime DEFAULT NULL,
  `views` int NOT NULL DEFAULT '0',
  `out_of_stock` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`listing_id`)
) ENGINE=MyISAM AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`listing_id`, `user_id`, `category`, `region`, `city_town`, `media`, `stockname`, `description`, `hashtags`, `price`, `price_from`, `price_to`, `created_at`, `listing_type`, `moderation_status`, `hidden_reason`, `hidden_at`, `hidden_by_admin_id`, `admin_reviewed_at`, `admin_purged_at`, `views`, `out_of_stock`) VALUES
(143, 112, 'Apparel', 'Central', 'Entebbe', 'uploads/profile/media_6a0a3d6f0338a9.03012268.jpg', 'Jerseys', 'Breathable fabric, durable stitching ensuring long-lasting wear and even after repeated washing.', NULL, 0.00, '15000', '150000', '2026-05-17 22:13:03', 'product', 'visible', NULL, NULL, NULL, NULL, NULL, 0, 0),
(142, 112, 'Apparel', 'Central', 'Entebbe', 'uploads/profile/media_6a0a3c910d3951.68313469.jpg', 'Designer Fashion Belts', 'Available in leather, synthetic leather, and designer styles.', NULL, 0.00, '25000', '60000', '2026-05-17 22:09:21', 'product', 'visible', NULL, NULL, NULL, NULL, NULL, 0, 0),
(141, 111, 'Wholesale & Retail', 'Western', 'Mbarara', 'uploads/profile/media_6a0a3afc708752.71747369.jpg', 'Casual & Fashion Shoes', 'Comfortable, durable and everyday use shoes. Available in multiple sizes, colors and designs.', NULL, 0.00, '25000', '80000', '2026-05-17 22:02:36', 'product', 'visible', NULL, NULL, NULL, NULL, NULL, 10, 0),
(140, 110, 'Electronics & Gadgets', 'Northern', 'Gulu', 'uploads/profile/media_6a0a388aa6cd89.38088211.jpg', 'UK Used Phones', 'Graded used smartphones from United Kingdom. Carefully inspected, categorized by condition and offered at competitive wholesale prices.', NULL, 0.00, '150000', '900000', '2026-05-17 21:52:10', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:47', NULL, 3, 0),
(138, 109, 'Agriculture & Produce', 'Western', 'Mbarara', 'uploads/profile/media_6a0a3523b651f9.01184494.jpg', 'Raw Cocoa Beans', 'Actively sourcing and purchasing fermented and dried cocoa beans from reliable farmers, cooperatives, and suppliers for export and industrial use.', NULL, 0.00, '400000', '650000', '2026-05-17 21:37:39', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:38', NULL, 2, 0),
(139, 110, 'Electronics & Gadgets', 'Northern', 'Gulu', 'uploads/profile/media_6a0a37acc38c21.22926828.jpg', 'Second-hand Laptops - Fully Tested', 'Tested used laptops available. Affordable, reliable and graded for quality.', NULL, 0.00, '400000', '800000', '2026-05-17 21:48:28', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:42', NULL, 7, 0),
(136, 108, 'Wholesale & Retail', 'Central', 'Kampala', 'uploads/profile/media_6a0a3089690f35.51462449.jpg', 'Sugar', 'Refined, food-grade, clean and consistently sourced sugar from different manufacturers all over Uganda.', NULL, 0.00, '220000', '350000', '2026-05-17 21:18:01', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:29', NULL, 1, 0),
(137, 108, 'Wholesale & Retail', 'Central', 'Kampala', 'uploads/profile/media_6a0a31afe69c98.45917237.jpg', 'Parboiled Rice', 'Steam processed to improve quality, durability, and cooking performance, ensuring reliable supply for food businesses and distributors.', NULL, 0.00, '240000', '340000', '2026-05-17 21:22:55', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:33', NULL, 1, 0),
(135, 108, 'Wholesale & Retail', 'Central', 'Kampala', 'uploads/profile/media_6a0a2f673fb468.43035346.jpg', 'Local White Rice', 'High quality rice available in different grades including long grain, parboiled, and broken rice with reliable supply and competitive wholesale pricing.', NULL, 0.00, '180000', '260000', '2026-05-17 21:13:11', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:24', NULL, 7, 0),
(134, 107, 'Construction & Building Materials', 'Eastern', 'Jinja', 'uploads/profile/media_6a0a27a57407a8.78605224.jpg', 'Construction Pipe Systems', 'Constructions pipes for water supply, drainage, and infrastructure systems. Available in bulk supply for contractors and developers. Currently on discount.', NULL, 0.00, '1500000', '4000000', '2026-05-17 20:40:05', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:19', NULL, 2, 0),
(133, 107, 'Construction & Building Materials', 'Eastern', 'Jinja', 'uploads/profile/media_6a0a2666bda314.35129958.jpg', 'Hardcore Stones', 'Durable hardcore stones for foundations, road base, drainage, and heavy construction works. Supplied in bulk truckloads.', NULL, 0.00, '350000', '900000', '2026-05-17 20:34:46', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:14', NULL, 2, 0),
(131, 107, 'Construction & Building Materials', 'Eastern', 'Jinja', 'uploads/profile/media_6a0a23ddc13a07.59716848.jpg', 'Concrete Paver Blocks', 'Machine made interlocking concrete paver blocks designed for pavements, driveways, walkways, parking areas, compounds and landscaping. Manufactured for durability, strength, weather resistance, and long-lasting surface performance.', NULL, 0.00, '800000', '1800000', '2026-05-17 20:23:57', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:02', NULL, 2, 0),
(132, 107, 'Construction & Building Materials', 'Eastern', 'Jinja', 'uploads/profile/media_6a0a2521a1b451.22843992.jpg', 'Hardwood & Softwood Timber', 'Premium construction timber for roofing, framing, and general building works. Available in bulk for contractors and developers.', NULL, 0.00, '1200000', '3000000', '2026-05-17 20:29:21', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:16:09', NULL, 4, 0),
(130, 107, 'Construction & Building Materials', 'Eastern', 'Jinja', 'uploads/profile/media_6a0a21cedb6f57.14915856.jpg', 'Natural Pebble Gravel', 'High quality decorative gravel stones for landscaping, construction finishing, gardens, pathways, and outdoor beautification projects.', NULL, 0.00, '250000', '600000', '2026-05-17 20:15:10', 'product', 'visible', NULL, NULL, NULL, '2026-05-18 01:15:53', NULL, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `listing_gallery_images`
--

DROP TABLE IF EXISTS `listing_gallery_images`;
CREATE TABLE IF NOT EXISTS `listing_gallery_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `listing_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_listing_gallery_images_listing_id` (`listing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `listing_gallery_images`
--

INSERT INTO `listing_gallery_images` (`id`, `listing_id`, `image_path`, `sort_order`, `created_at`) VALUES
(11, 96, 'uploads/profile/media_69e207a15e23c5.72410563.jpg', 1, '2026-04-17 13:12:49'),
(12, 96, 'uploads/profile/media_69e207a160c425.83997554.jpg', 2, '2026-04-17 13:12:49'),
(13, 96, 'uploads/profile/media_69e207a161ff91.07473733.jpg', 3, '2026-04-17 13:12:49'),
(14, 114, 'uploads/profile/media_69ea11455dda87.36475290.png', 1, '2026-04-23 15:32:05'),
(15, 114, 'uploads/profile/media_69ea11455f0190.01862815.png', 2, '2026-04-23 15:32:05'),
(16, 114, 'uploads/profile/media_69ea11456005a8.05632066.png', 3, '2026-04-23 15:32:05'),
(17, 114, 'uploads/profile/media_69ea114560e6c2.40421675.png', 4, '2026-04-23 15:32:05'),
(18, 114, 'uploads/profile/media_69ea114561c594.17251744.png', 5, '2026-04-23 15:32:05'),
(19, 115, 'uploads/profile/media_69ea11c49c24c9.36076478.jpg', 1, '2026-04-23 15:34:12'),
(20, 115, 'uploads/profile/media_69ea11c49e47e3.67394187.jpg', 2, '2026-04-23 15:34:12'),
(21, 115, 'uploads/profile/media_69ea11c4a01418.40182119.jpg', 3, '2026-04-23 15:34:12'),
(22, 115, 'uploads/profile/media_69ea11c4a206c5.76860277.jpg', 4, '2026-04-23 15:34:12'),
(23, 115, 'uploads/profile/media_69ea11c4a42802.12487507.jpg', 5, '2026-04-23 15:34:12'),
(24, 116, 'uploads/profile/media_69ea594e5d6256.47770207.png', 1, '2026-04-23 20:39:26'),
(25, 116, 'uploads/profile/media_69ea594e5e6c74.56745334.jpg', 2, '2026-04-23 20:39:26'),
(26, 116, 'uploads/profile/media_69ea594e603456.23368122.jpg', 3, '2026-04-23 20:39:26'),
(27, 116, 'uploads/profile/media_69ea594e61dac8.98931235.jpg', 4, '2026-04-23 20:39:27'),
(28, 116, 'uploads/profile/media_69ea594e636c68.85368631.jpg', 5, '2026-04-23 20:39:27'),
(29, 117, 'uploads/profile/media_69ea594fd52967.90240205.png', 1, '2026-04-23 20:39:27'),
(30, 117, 'uploads/profile/media_69ea594fd64326.60541024.jpg', 2, '2026-04-23 20:39:27'),
(31, 117, 'uploads/profile/media_69ea594fd7d8a0.12295158.jpg', 3, '2026-04-23 20:39:28'),
(32, 117, 'uploads/profile/media_69ea594fd9aa59.47400284.jpg', 4, '2026-04-23 20:39:28'),
(33, 117, 'uploads/profile/media_69ea594fdb9413.36789518.jpg', 5, '2026-04-23 20:39:28'),
(34, 118, 'uploads/profile/media_69ea59e31d2e01.93628763.png', 1, '2026-04-23 20:41:55'),
(35, 118, 'uploads/profile/media_69ea59e31e25a8.79814163.jpg', 2, '2026-04-23 20:41:55'),
(36, 118, 'uploads/profile/media_69ea59e31f9ff1.35967897.jpg', 3, '2026-04-23 20:41:55'),
(37, 118, 'uploads/profile/media_69ea59e3217dc9.87124870.jpg', 4, '2026-04-23 20:41:55'),
(38, 118, 'uploads/profile/media_69ea59e3232170.70203144.jpg', 5, '2026-04-23 20:41:55'),
(39, 127, 'uploads/profile/media_69fcd852a1d184.24787977.png', 1, '2026-05-07 21:22:10'),
(40, 127, 'uploads/profile/media_69fcd852a2d351.25820169.png', 2, '2026-05-07 21:22:10'),
(41, 127, 'uploads/profile/media_69fcd852a4be17.06033564.jpg', 3, '2026-05-07 21:22:10'),
(42, 127, 'uploads/profile/media_69fcd852a60da1.78599227.jpg', 4, '2026-05-07 21:22:10'),
(43, 127, 'uploads/profile/media_69fcd852a78cb4.64261553.webp', 5, '2026-05-07 21:22:10'),
(44, 129, 'uploads/profile/media_6a095438842c13.03482674.png', 1, '2026-05-17 08:38:00'),
(45, 129, 'uploads/profile/media_6a095438852858.42579324.jpg', 2, '2026-05-17 08:38:00'),
(46, 129, 'uploads/profile/media_6a095438869252.79367125.webp', 3, '2026-05-17 08:38:00'),
(47, 130, 'uploads/profile/media_6a0a21cede9092.20546216.jpg', 1, '2026-05-17 23:15:10'),
(48, 130, 'uploads/profile/media_6a0a21cee10074.51395668.jpg', 2, '2026-05-17 23:15:10'),
(49, 130, 'uploads/profile/media_6a0a21cee293a6.87018733.jpg', 3, '2026-05-17 23:15:11'),
(50, 130, 'uploads/profile/media_6a0a21cee3e819.68325237.jpg', 4, '2026-05-17 23:15:11'),
(51, 131, 'uploads/profile/media_6a0a23ddc52c16.69889059.jpg', 1, '2026-05-17 23:23:57'),
(52, 131, 'uploads/profile/media_6a0a23ddc6eb49.29048038.jpg', 2, '2026-05-17 23:23:57'),
(53, 131, 'uploads/profile/media_6a0a23ddc85ae5.19919951.jpg', 3, '2026-05-17 23:23:57'),
(54, 132, 'uploads/profile/media_6a0a2521a4b232.09556453.jpg', 1, '2026-05-17 23:29:21'),
(55, 132, 'uploads/profile/media_6a0a2521a72086.06558838.jpg', 2, '2026-05-17 23:29:21'),
(56, 133, 'uploads/profile/media_6a0a2666c21743.75639534.jpg', 1, '2026-05-17 23:34:46'),
(57, 134, 'uploads/profile/media_6a0a27a575ca14.90687596.jpg', 1, '2026-05-17 23:40:05'),
(58, 134, 'uploads/profile/media_6a0a27a57765b3.69811673.jpg', 2, '2026-05-17 23:40:05'),
(59, 135, 'uploads/profile/media_6a0a2f67439f85.62327193.jpg', 1, '2026-05-18 00:13:11');

-- --------------------------------------------------------

--
-- Table structure for table `listing_owner_views`
--

DROP TABLE IF EXISTS `listing_owner_views`;
CREATE TABLE IF NOT EXISTS `listing_owner_views` (
  `listing_id` int UNSIGNED NOT NULL,
  `owner_user_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`listing_id`,`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `listing_view_stats`
--

DROP TABLE IF EXISTS `listing_view_stats`;
CREATE TABLE IF NOT EXISTS `listing_view_stats` (
  `user_id` int NOT NULL,
  `listing_id` int NOT NULL,
  `view_count` int NOT NULL DEFAULT '1',
  `last_viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`listing_id`),
  KEY `idx_listing_view_stats_listing_id` (`listing_id`),
  KEY `idx_listing_view_stats_last_viewed` (`last_viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `listing_view_stats`
--

INSERT INTO `listing_view_stats` (`user_id`, `listing_id`, `view_count`, `last_viewed_at`) VALUES
(91, 96, 1, '2026-05-17 11:03:02'),
(91, 97, 55, '2026-05-07 15:46:03'),
(91, 98, 23, '2026-05-07 15:46:56'),
(91, 99, 1, '2026-05-17 11:04:59'),
(91, 100, 9, '2026-04-18 17:52:29'),
(91, 101, 10, '2026-04-18 21:37:35'),
(91, 102, 1, '2026-04-18 20:51:49'),
(91, 103, 12, '2026-04-19 10:31:44'),
(91, 104, 13, '2026-04-18 21:18:23'),
(91, 114, 7, '2026-05-07 15:46:51'),
(91, 115, 4, '2026-05-07 11:29:01'),
(91, 117, 1, '2026-04-23 20:39:34'),
(91, 118, 1, '2026-05-07 12:13:50'),
(91, 119, 6, '2026-05-07 21:33:56'),
(91, 120, 1, '2026-05-07 11:42:56'),
(91, 121, 1, '2026-05-07 12:05:47'),
(91, 122, 1, '2026-05-07 21:34:00'),
(91, 123, 1, '2026-05-08 13:15:37'),
(91, 124, 1, '2026-05-10 15:10:51'),
(91, 127, 21, '2026-05-09 15:37:01'),
(91, 128, 9, '2026-05-17 11:03:19'),
(91, 129, 1, '2026-05-17 11:03:23'),
(92, 97, 1, '2026-04-18 14:23:53'),
(92, 98, 1, '2026-04-17 14:02:49'),
(92, 99, 1, '2026-04-17 14:02:59'),
(92, 100, 1, '2026-04-18 14:24:06'),
(93, 96, 35, '2026-05-17 19:49:32'),
(93, 97, 27, '2026-05-08 03:47:16'),
(93, 98, 1, '2026-05-08 18:01:46'),
(93, 99, 50, '2026-05-17 20:01:13'),
(93, 101, 1, '2026-04-18 21:37:13'),
(93, 102, 3, '2026-04-18 20:55:50'),
(93, 103, 1, '2026-04-18 20:56:58'),
(93, 104, 1, '2026-04-18 20:58:48'),
(93, 114, 1, '2026-05-17 18:17:43'),
(93, 115, 1, '2026-05-07 11:30:08'),
(93, 118, 6, '2026-05-07 11:27:44'),
(93, 119, 12, '2026-05-17 07:57:13'),
(93, 120, 1, '2026-05-07 11:44:42'),
(93, 121, 1, '2026-05-07 12:04:35'),
(93, 122, 11, '2026-05-15 15:48:08'),
(93, 123, 12, '2026-05-17 13:51:41'),
(93, 124, 14, '2026-05-14 11:24:10'),
(93, 125, 6, '2026-05-08 02:17:35'),
(93, 127, 1, '2026-05-15 15:46:42'),
(93, 128, 1, '2026-05-17 13:52:58'),
(99, 119, 1, '2026-04-25 12:42:03'),
(107, 130, 1, '2026-05-17 23:15:21'),
(107, 131, 1, '2026-05-24 21:13:37'),
(107, 132, 1, '2026-05-24 19:30:35'),
(107, 133, 1, '2026-05-20 11:36:22'),
(107, 134, 1, '2026-05-22 17:54:30'),
(107, 135, 4, '2026-05-24 18:00:57'),
(107, 139, 4, '2026-05-20 11:24:48'),
(107, 140, 2, '2026-05-20 11:08:14'),
(107, 141, 6, '2026-05-20 11:36:06'),
(108, 132, 2, '2026-05-19 23:26:21'),
(108, 134, 1, '2026-05-24 18:13:55'),
(108, 135, 1, '2026-05-25 11:04:04'),
(108, 136, 1, '2026-05-18 10:52:47'),
(108, 137, 1, '2026-05-22 17:28:49'),
(108, 141, 4, '2026-05-24 19:52:50'),
(110, 139, 1, '2026-05-18 00:52:34'),
(112, 131, 1, '2026-05-18 01:29:43'),
(112, 132, 1, '2026-05-18 01:28:41'),
(112, 133, 1, '2026-05-18 01:28:49'),
(112, 135, 2, '2026-05-18 01:28:24'),
(112, 139, 2, '2026-05-18 01:18:31'),
(112, 140, 1, '2026-05-18 01:18:23'),
(113, 138, 2, '2026-05-18 10:58:40');

-- --------------------------------------------------------

--
-- Table structure for table `profile_pinned_listings`
--

DROP TABLE IF EXISTS `profile_pinned_listings`;
CREATE TABLE IF NOT EXISTS `profile_pinned_listings` (
  `user_id` int NOT NULL,
  `listing_id` int NOT NULL,
  `pinned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`listing_id`),
  KEY `idx_profile_pinned_listings_user_time` (`user_id`,`pinned_at`),
  KEY `idx_profile_pinned_listings_listing` (`listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `profile_pinned_listings`
--

INSERT INTO `profile_pinned_listings` (`user_id`, `listing_id`, `pinned_at`) VALUES
(91, 96, '2026-05-07 14:31:33'),
(91, 122, '2026-05-07 14:31:37'),
(91, 125, '2026-05-07 14:31:41'),
(91, 99, '2026-05-07 14:31:46'),
(91, 123, '2026-05-07 14:31:54'),
(91, 124, '2026-05-07 14:32:42'),
(93, 98, '2026-05-07 15:44:32'),
(107, 134, '2026-05-21 13:27:07');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
CREATE TABLE IF NOT EXISTS `purchase_requests` (
  `request_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `listing_id` int UNSIGNED NOT NULL,
  `seller_user_id` int UNSIGNED NOT NULL,
  `buyer_user_id` int UNSIGNED NOT NULL,
  `listing_type` varchar(20) NOT NULL DEFAULT 'product',
  `amount` varchar(255) NOT NULL,
  `payment_mode` varchar(255) NOT NULL,
  `delivery_method` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_seller_created` (`seller_user_id`,`created_at`),
  KEY `idx_listing_created` (`listing_id`,`created_at`),
  KEY `idx_buyer_created` (`buyer_user_id`,`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`request_id`, `listing_id`, `seller_user_id`, `buyer_user_id`, `listing_type`, `amount`, `payment_mode`, `delivery_method`, `location`, `status`, `created_at`) VALUES
(42, 135, 108, 107, 'product', '30 Bags', 'Cash', 'Self Pick', 'Kazo', 'proceeded', '2026-05-22 17:09:27'),
(40, 138, 109, 113, 'product', '500000', 'MM', 'Delivery', 'Kisubi', 'proceeded', '2026-05-18 10:59:40');

-- --------------------------------------------------------

--
-- Table structure for table `recent_listing_views`
--

DROP TABLE IF EXISTS `recent_listing_views`;
CREATE TABLE IF NOT EXISTS `recent_listing_views` (
  `viewer_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listing_id` int UNSIGNED NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`viewer_key`,`listing_id`),
  KEY `idx_recent_listing_views_viewed_at` (`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recent_listing_views`
--

INSERT INTO `recent_listing_views` (`viewer_key`, `listing_id`, `viewed_at`) VALUES
('user:90', 68, '2026-04-06 02:05:37'),
('user:90', 73, '2026-04-06 02:06:25');

-- --------------------------------------------------------

--
-- Table structure for table `site_assets`
--

DROP TABLE IF EXISTS `site_assets`;
CREATE TABLE IF NOT EXISTS `site_assets` (
  `asset_key` varchar(80) NOT NULL,
  `label` varchar(160) NOT NULL,
  `asset_type` varchar(20) NOT NULL DEFAULT 'other',
  `page` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`asset_key`),
  KEY `idx_site_assets_type` (`asset_type`),
  KEY `idx_site_assets_page` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_links`
--

DROP TABLE IF EXISTS `site_links`;
CREATE TABLE IF NOT EXISTS `site_links` (
  `link_key` varchar(40) NOT NULL,
  `label` varchar(80) NOT NULL,
  `url` varchar(500) NOT NULL DEFAULT '',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`link_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `site_links`
--

INSERT INTO `site_links` (`link_key`, `label`, `url`, `updated_at`) VALUES
('app', 'JoMu Application (components/nav.php)', '', '2026-05-11 18:32:09'),
('facebook', 'Facebook (components/nav.php, index.php, support.html)', 'https://www.facebook.com/jomuofficial', '2026-05-17 18:21:07'),
('instagram', 'Instagram (components/nav.php, index.php, support.html)', 'https://www.instagram.com/jomu.ug_official', '2026-05-21 12:20:08'),
('privacy_email', 'Privacy policy email (privacypolicy.html)', 'ContactJoMu@gmail.com', NULL),
('support_email', 'Support email (support.html)', '', NULL),
('support_phone', 'Support phone call (support.html)', '+256 793707974', '2026-05-17 19:44:32'),
('support_whatsapp', 'Support WhatsApp (support.html)', '+256 793707974', '2026-05-17 19:57:57'),
('tiktok', 'Tiktok (components/nav.php, index.php, support.html)', 'https://www.tiktok.com/@jomu_official', '2026-05-17 18:21:15'),
('x', 'X (components/nav.php, index.php, support.html)', 'https://x.com/jomu_official', '2026-05-17 18:21:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `profilepic` varchar(250) NOT NULL,
  `businessname` varchar(100) NOT NULL,
  `businessnameupdated_at` datetime DEFAULT NULL,
  `emailormobilenumber` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `entry` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bio` text,
  `business_contact` varchar(60) DEFAULT NULL,
  `business_email` varchar(120) DEFAULT NULL,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `inactive_until` datetime DEFAULT NULL,
  `status_reason` varchar(255) DEFAULT NULL,
  `inactive_since` datetime DEFAULT NULL,
  `terminated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emailormobilenumber` (`emailormobilenumber`)
) ENGINE=MyISAM AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `profilepic`, `businessname`, `businessnameupdated_at`, `emailormobilenumber`, `password`, `entry`, `bio`, `business_contact`, `business_email`, `account_status`, `inactive_until`, `status_reason`, `inactive_since`, `terminated_at`) VALUES
(111, 'uploads/profile/media_6a0a3a5290ab57.64169760.jpg', 'Mayanja & Sons Shoe Center', NULL, 'mayanja&sons@gmail.com', '$2y$10$Q.2c9ojfYgIhWwjZt84Dw.C4aTcwyb6Q05UWt.NU/p/xAabY5akpe', '2026-05-17 21:56:20', 'Wholesalers of a wide range of footwear in bulk including sneakers, leather shoes, sandals, and casual wear for men, women, and children. Suitable for distributors and retail shops plus boutiques.', '', '', 'active', NULL, NULL, NULL, NULL),
(110, 'uploads/profile/media_6a0a36644d40a7.06836181.jpg', 'Gulu Tech Hub', NULL, 'gulutechhub@gmail.com', '$2y$10$FtIM.dj2.2YwObVA2t6FLuL27t8o4ykWkRiuQyEPgzzk3HrCdIDxW', '2026-05-17 21:41:47', 'Laptops, phones and accessories for businesses, schools, government projects, and resellers. HP, Dell, Lenovo, Samsung, Iphones, Techno, Infinix etc with warranty options depending on order size.', '', '', 'active', NULL, NULL, NULL, NULL),
(109, 'uploads/profile/media_6a0a34324e0172.97638551.jpg', 'W&A Cocoa Exporters', NULL, 'cocoaexporters@gmail.com', '$2y$10$vMHtCH5Jzhzejz6CKxWKAuzv6S/4U3RbE1IMsS3BomfhcsY.64ege', '2026-05-17 21:30:00', 'We are a cocoa buying company that purchases high-quality cocoa beans in bulk from farmers and suppliers for export and processing. We do not engage in retail or direct sales.', '', '', 'active', NULL, NULL, NULL, NULL),
(100, '../assets/images/JoMu logo redesigned.png', 'JoMu', NULL, 'system@jomu.local', '$2y$10$EZ5Z7qYbqw1kYZgVSLbWW.DOaOJ.THWo9IZ8wGNwVBUnUhWknX0uG', '2026-05-10 12:02:50', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL),
(108, 'uploads/profile/media_6a0a2e11eb3a00.34679624.jpg', 'Nansubuga HarvestHub Foods', NULL, 'nansubuga@gmail.com', '$2y$10$X2V1LN7ikOGsIk4CI2r4Q.k7tJcDwi/6Nzt/4EysQp1WwJxB.p/Yy', '2026-05-17 20:49:00', 'Trusted wholesaler of bulk staple foods including rice, maize flour, beans, wheat, and other essential grains. We serve retailers, supermarkets, institutions, and food distributors country wide.', '', '', 'active', NULL, NULL, NULL, NULL),
(107, 'uploads/profile/media_6a0a1fd8a1dba7.12588289.jpg', 'BuildLink Supplies Co.', NULL, 'buildlink@gmail.com', '$2y$10$Na5ClatHSaoTCYcEWsK5aOMbhzjgX8xFF7LUFWCVhveTaoyboY5nG', '2026-05-17 19:59:47', 'Reliable suppliers of quality building materials for contractors, engineers, hardware stores, construction projects, and businesses.', '', '', 'active', NULL, NULL, NULL, NULL),
(112, 'uploads/profile/media_6a0a3b9593b273.42451034.jpg', 'WearPoint Fashions Ltd', NULL, 'wearpoint@gmail.com', '$2y$10$ZnH43GHNNltFeDZHKn7Fe.iPved0mf2VUmFTTu306MrMWfD.wqds2', '2026-05-17 22:04:34', 'Dealers in men\'s, women\'s and children\'s wear including casual wear, official clothing, and fashion apparel with consistent supply and competitive wholesale pricing.', '', '', 'active', NULL, NULL, NULL, NULL),
(113, '', 'DigitoMove Co. LTD', NULL, 'digitomovecoltd@gmail.com', '$2y$10$V9CWz3BV34cxC1tpQUrExOj5mUwRoUU08y5o4o/HpJpfnycGIW03.', '2026-05-18 07:35:06', NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_search_interest`
--

DROP TABLE IF EXISTS `user_search_interest`;
CREATE TABLE IF NOT EXISTS `user_search_interest` (
  `user_id` int NOT NULL,
  `search_term` varchar(190) NOT NULL,
  `search_count` int NOT NULL DEFAULT '1',
  `last_searched_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`search_term`),
  KEY `idx_user_search_interest_last` (`last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
