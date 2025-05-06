-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 06, 2025 at 11:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `forest_tourism`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `participants` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `trip_id`, `booking_date`, `status`, `participants`, `price`) VALUES
(26, 4, 2, '2025-05-02 17:44:57', 'confirmed', 1, 0.00),
(32, 4, 6, '2025-05-03 17:46:01', 'confirmed', 1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `trip_id`, `content`, `created_at`) VALUES
(2, 2, 4, 'fff', '2025-05-02 00:00:23'),
(3, 2, 4, 'good', '2025-05-02 00:01:42'),
(7, 1, 4, 'dddd', '2025-05-02 00:26:38'),
(8, 4, 5, 'Voyage agréable', '2025-05-02 21:28:34');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `trip_id`, `created_at`) VALUES
(55, 4, 6, '2025-05-06 18:53:17'),
(56, 4, 2, '2025-05-06 18:53:21'),
(57, 4, 1, '2025-05-06 18:53:26'),
(58, 4, 5, '2025-05-06 20:20:49');

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trips_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`id`, `user_id`, `trips_id`, `created_at`) VALUES
(3, 4, 2, '2025-05-02 09:38:11'),
(6, 4, 6, '2025-05-03 17:51:16'),
(7, 4, 4, '2025-05-06 16:55:47');

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 4, 'Réservation envoyée pour \'Trip to Atlas\'', 1, '2025-04-30 13:18:34'),
(2, 1, 'Nouvelle demande de réservation pour \'Trip to Atlas\'', 1, '2025-04-30 13:18:34'),
(3, 1, 'Réservation envoyée pour \'erer\'', 1, '2025-04-30 13:23:43'),
(4, 1, 'Nouvelle demande de réservation pour \'erer\'', 1, '2025-04-30 13:23:43'),
(5, 4, 'Réservation envoyée pour \'hyy\'', 1, '2025-04-30 16:17:04'),
(6, 1, 'Nouvelle demande de réservation pour \'hyy\'', 1, '2025-04-30 16:17:04'),
(7, 4, 'Réservation envoyée pour \'hyy\'', 1, '2025-04-30 19:03:28'),
(8, 1, 'Nouvelle demande de réservation pour \'hyy\'', 1, '2025-04-30 19:03:28'),
(9, 4, 'Réservation envoyée pour \'hyy\'', 1, '2025-04-30 19:08:00'),
(10, 1, 'Nouvelle demande de réservation pour \'hyy\'', 1, '2025-04-30 19:08:00'),
(11, 1, 'تم رفض رحلتك \'hyy\'', 1, '2025-05-02 10:46:57'),
(12, 1, 'تم رفض رحلتك \'hyy\'', 1, '2025-05-02 10:47:01'),
(13, 2, 'تم رفض رحلتك \'djamel\'', 1, '2025-05-02 10:47:53'),
(14, 1, 'تم رفض رحلتك \'hyy\'', 1, '2025-05-02 10:48:00'),
(15, 2, 'تم رفض رحلتك \'djamel\'', 1, '2025-05-02 10:48:24'),
(16, 1, 'تم رفض رحلتك \'hyy\'', 1, '2025-05-02 10:48:28'),
(17, 1, 'تم حذف رحلتك \'hyy\' بواسطة الإدارة', 1, '2025-05-02 10:51:29'),
(18, 2, 'تمت الموافقة على رحلتك \'djamel\'', 1, '2025-05-02 10:53:21'),
(19, 2, 'تمت الموافقة على رحلتك \'djamel\'', 1, '2025-05-02 10:53:22'),
(20, 2, 'تمت الموافقة على رحلتك \'djamel\'', 1, '2025-05-02 10:53:23'),
(21, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:37'),
(22, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:38'),
(23, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:41'),
(24, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:42'),
(25, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:42'),
(26, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:42'),
(27, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\'', 1, '2025-05-02 10:53:43'),
(28, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 10:59:15'),
(29, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 10:59:18'),
(30, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 10:59:18'),
(31, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:24'),
(32, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:28'),
(33, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:29'),
(34, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:45'),
(35, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:46'),
(36, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:46'),
(37, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 10:59:47'),
(38, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:54'),
(39, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 10:59:54'),
(40, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 11:13:11'),
(41, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 11:13:12'),
(42, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 11:13:13'),
(43, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 11:13:13'),
(44, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 11:13:13'),
(45, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 11:21:12'),
(46, 1, 'تمت الموافقة على رحلتك \'Trip to Atlas\' بواسطة الإدارة', 1, '2025-05-02 11:21:13'),
(47, 2, 'تمت الموافقة على رحلتك \'djamel\' بواسطة الإدارة', 1, '2025-05-02 11:22:13'),
(48, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 17:42:24'),
(49, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 17:44:07'),
(50, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 17:44:15'),
(51, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 17:44:17'),
(52, 4, 'Vous avez supprimé votre réservation pour le voyage \'erer\'', 1, '2025-05-02 17:44:40'),
(53, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 17:44:43'),
(54, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 18:08:21'),
(55, 4, 'Vous avez supprimé votre réservation pour le voyage \'erer\'', 1, '2025-05-02 18:08:25'),
(56, 4, 'Vous avez supprimé votre réservation pour le voyage \'djamel\'', 1, '2025-05-02 18:08:27'),
(57, 4, 'Vous avez supprimé votre réservation pour le voyage \'djamel\'', 1, '2025-05-02 18:08:32'),
(58, 4, 'Vous avez supprimé votre réservation pour le voyage \'djamel\'', 1, '2025-05-02 18:08:34'),
(59, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:37'),
(60, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:39'),
(61, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:41'),
(62, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:43'),
(63, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:45'),
(64, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:47'),
(65, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 18:08:48'),
(66, 4, 'Vous avez supprimé votre réservation pour le voyage \'djamel\'', 1, '2025-05-02 18:09:11'),
(67, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 21:33:54'),
(68, 4, 'Vous avez supprimé votre réservation pour le voyage \'Trip to Atlas\'', 1, '2025-05-02 21:33:58'),
(69, 4, 'Vous avez supprimé votre réservation pour le voyage \'djamel\'', 1, '2025-05-02 21:34:02'),
(70, 4, 'Vous avez supprimé votre réservation pour le voyage \'dj\'', 1, '2025-05-02 21:34:08'),
(71, 4, 'Vous avez supprimé votre réservation pour le voyage \'bouira\'', 1, '2025-05-06 18:53:52');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `max_participants` int(11) NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved` tinyint(1) DEFAULT 0,
  `difficulty_level` enum('easy','moderate','difficult','expert') NOT NULL,
  `forest_type` enum('deciduous','coniferous','mixed','rainforest','mangrove') NOT NULL,
  `vehicle_type` enum('bus','bus_climatise','voiture','voiture_4x4','minibus','autre') DEFAULT 'bus',
  `status` enum('actif','complet','annulé') NOT NULL DEFAULT 'actif',
  `safety_tips` text DEFAULT NULL COMMENT 'Conseils de sécurité pour la région (ex: forêt inflammable, chasse interdite...)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `organizer_id`, `title`, `description`, `location`, `start_date`, `end_date`, `price`, `max_participants`, `featured_image`, `created_at`, `updated_at`, `approved`, `difficulty_level`, `forest_type`, `vehicle_type`, `status`, `safety_tips`) VALUES
(1, 1, 'erer', 'dfgadfg', 'erter', '2025-09-09', '2025-09-10', 435.00, 3534, 'uploads/trips/trip_1_1746023414.jpeg', '2025-04-30 10:12:36', '2025-05-01 23:46:34', 1, 'difficult', 'mangrove', 'bus', 'actif', '1. Une forêt hautement inflammable\\n2. Attention à ne pas allumer de feu\\n3. Attention aux jets de verre\\n4. La chasse est interdite'),
(2, 1, 'Trip to Atlas', 'Explore the forest...', 'Kabylie', '2025-05-20', '2025-05-25', 120.00, 10, 'uploads/trips/trip_2_1746014360.jpeg', '2025-04-30 11:01:30', '2025-05-02 18:08:37', 0, 'moderate', 'mixed', 'bus', 'actif', NULL),
(4, 2, 'djamel', 'dddddddd', 'djamel,djamel', '2025-05-29', '2025-05-30', 44.00, 66, 'uploads/trips/trip_1746139972_912f7bcf3bddda92.jpeg', '2025-05-01 22:52:52', '2025-05-02 11:22:13', 1, 'easy', 'deciduous', 'bus', 'actif', '1. غابة قابلة للاشتعال بشدة\\n2. احذر من إشعال النيران\r\n1. حيوانات برية خطيرة\\n2. لا تبتعد عن المجموعة\r\n1. الصيد ممنوع\\n2. لا تلمس الأعشاش أو البيض'),
(5, 1, 'dj', '555', 'sss', '2025-05-29', '2025-05-30', 333.00, 55, 'uploads/trips/trip_5_1746209965.png', '2025-05-02 17:21:53', '2025-05-02 18:19:25', 0, 'easy', 'deciduous', 'bus', 'actif', '1. Forêt hautement inflammable . Se méfier des incendies\r\n1. Des animaux sauvages dangereux . Ne vous éloignez pas du groupe\r\n1. La pêche est interdite /n2. Ne pas toucher aux nids et aux œufs'),
(6, 1, 'bouira', 'eee', 'bouira_bouira', '2025-05-22', '2025-05-30', 22.00, 33, 'uploads/trips/trip_1746266971_045ab6288f8d7725.png', '2025-05-03 10:09:31', '2025-05-06 19:12:00', 0, 'easy', 'deciduous', 'bus_climatise', 'actif', '1. Forêt hautement inflammable . Se méfier des incendies\r\n1. Des animaux sauvages dangereux . Ne vous éloignez pas du groupe');

-- --------------------------------------------------------

--
-- Table structure for table `trip_activities`
--

CREATE TABLE `trip_activities` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `activity` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_activities`
--

INSERT INTO `trip_activities` (`id`, `trip_id`, `activity`) VALUES
(1, 1, 'hiking'),
(2, 1, 'bird_watching'),
(6, 4, 'bird_watching'),
(7, 5, 'bird_watching'),
(8, 6, 'bird_watching');

-- --------------------------------------------------------

--
-- Table structure for table `trip_safety_tips`
--

CREATE TABLE `trip_safety_tips` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `tip_text` text NOT NULL,
  `importance` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_safety_tips`
--

INSERT INTO `trip_safety_tips` (`id`, `trip_id`, `tip_text`, `importance`, `created_at`) VALUES
(1, 1, 'Une forêt hautement inflammable', 'high', '2025-05-01 22:15:55'),
(2, 1, 'Attention à ne pas allumer de feu', 'high', '2025-05-01 22:15:55'),
(3, 1, 'Attention aux jets de verre', 'medium', '2025-05-01 22:15:55'),
(4, 1, 'La chasse est interdite', 'high', '2025-05-01 22:15:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(555) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `user_type` enum('admin','organizer','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity` timestamp NULL DEFAULT NULL,
  `v_org` varchar(2) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `profile_pic`, `bio`, `user_type`, `created_at`, `updated_at`, `last_activity`, `v_org`, `phone`, `last_login`) VALUES
(1, 'djamelrb', 'djamelrebiai4@gmail.com', '$2y$10$67cBcsn7UsSaJGRPrr9HDuW3DZKIoP5afb7TF1caaqcU0bwVIyuKi', 'djamel', 'uploads/user/user_1_6815fa9eed39a.jpg', NULL, 'organizer', '2025-04-29 20:16:22', '2025-05-03 11:14:38', '2025-05-02 00:26:38', '0', '0', NULL),
(2, 'djamelrb1', 'djamel.rebiai@univ-bouira.dz', '$2y$10$iRN/xD6z/MA8h46lql7W.OTgaavyZ4Ew4160TVtQgfAEizsAaPhDO', 'djamel1', NULL, NULL, 'admin', '2025-04-29 20:55:48', '2025-05-02 00:01:54', '2025-05-02 00:01:54', '0', '0', NULL),
(4, 'djamel2', 'djamel@gmail.com', '$2y$10$ovn7/DhmUzX3PzWtLhlWK.AYJ..3g/bz5j.oWZ8YW8y.0heWGLiK.', 'djamel', 'uploads/user/user_4_6815fad41e78a.jpeg', '', 'user', '2025-04-30 10:18:16', '2025-05-03 11:15:32', '2025-05-02 21:28:34', '', '123456789', NULL),
(5, 'djamel3', 'abc@gmail.com', '$2y$10$aOvpxvQcfLL8sHA3JemsbeE2dwXO101no4FndDoqd2LPstUD8GXGe', 'djamel33', 'uploads/user/user_5_6815f5d5ea91f.png', NULL, 'user', '2025-05-03 10:37:45', '2025-05-03 10:54:13', NULL, '', '1123456789', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`trip_id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`trips_id`),
  ADD KEY `trips_id` (`trips_id`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `idx_trips_start_date` (`start_date`),
  ADD KEY `idx_trips_location` (`location`);

--
-- Indexes for table `trip_activities`
--
ALTER TABLE `trip_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `trip_safety_tips`
--
ALTER TABLE `trip_safety_tips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `trip_activities`
--
ALTER TABLE `trip_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `trip_safety_tips`
--
ALTER TABLE `trip_safety_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`trips_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `media_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `trip_activities`
--
ALTER TABLE `trip_activities`
  ADD CONSTRAINT `trip_activities_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `trip_safety_tips`
--
ALTER TABLE `trip_safety_tips`
  ADD CONSTRAINT `trip_safety_tips_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
