-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 17, 2025 at 08:51 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `api_connectinc`
--

-- --------------------------------------------------------

--
-- Table structure for table `social_circle`
--

CREATE TABLE `social_circle` (
  `id` int NOT NULL,
  `name` varchar(555) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `logo` varchar(555) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `logo_url` varchar(555) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `order_by` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_flag` enum('N','Y') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `social_circle`
--

INSERT INTO `social_circle` (`id`, `name`, `logo`, `logo_url`, `order_by`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`, `deleted_flag`) VALUES
(1, 'Music', 'MusicIcon.png', 'uploads/logo/', 8, NULL, NULL, '2021-02-23 05:39:04', 3, NULL, NULL, 'N'),
(2, 'Sport', 'SportIcon.png', 'uploads/logo/', 2, NULL, NULL, '2021-02-23 05:39:49', 3, NULL, NULL, 'N'),
(3, 'Professionals', 'ProfessionalsIcon.png', 'uploads/logo/', 9, NULL, NULL, '2021-02-23 05:40:31', 3, NULL, NULL, 'N'),
(4, 'Gaming', 'GamingIcon.png', 'uploads/logo/', 6, NULL, NULL, '2021-02-23 05:42:40', 3, NULL, NULL, 'N'),
(5, 'Fashion', 'ImgFashin.png', 'uploads/logo/', 5, NULL, NULL, '2021-02-23 05:43:40', 3, NULL, NULL, 'N'),
(6, 'Health & Fitness', 'HealthFitnessIcon.png', 'uploads/logo/', 3, NULL, NULL, '2021-02-23 05:44:25', 3, NULL, NULL, 'N'),
(7, 'Foodies', 'FoodiesIcon.png', 'uploads/logo/', 13, NULL, NULL, '2021-02-23 05:45:01', 3, NULL, NULL, 'N'),
(8, 'Animal lovers', 'Animallovericon.png', 'uploads/logo/', 14, NULL, NULL, '2021-02-23 05:45:42', 3, NULL, NULL, 'N'),
(9, 'Party', 'partyIcon.png', 'uploads/logo/', 11, NULL, NULL, '2021-02-23 05:46:13', 3, NULL, NULL, 'N'),
(10, 'TV/Movies', 'MoviesIcon.png', 'uploads/logo/', 7, NULL, NULL, '2021-06-01 11:15:34', 3, NULL, NULL, 'N'),
(11, 'Connect Travel', 'ConnectTravelIcon.png', 'uploads/logo/', 15, NULL, NULL, '2021-02-23 05:47:21', 3, NULL, NULL, 'N'),
(23, 'Connect Shop', 'ConnectShopIcon.png', 'uploads/logo/', 16, '2021-03-23 12:28:59', 3, '2021-03-24 11:10:26', 3, NULL, NULL, 'N'),
(26, 'Just Connect!', 'JustConnect!.png', 'uploads/logo/', 1, '2021-06-01 11:03:25', 3, '2021-06-02 05:53:56', 3, NULL, NULL, 'N'),
(27, 'Business', 'Business.png', 'uploads/logo/', 4, '2021-06-01 11:06:03', 3, '2021-06-02 05:34:37', 3, NULL, NULL, 'N'),
(28, 'Education', 'Education.png', 'uploads/logo/', 10, '2021-06-01 11:16:28', 3, '2021-06-02 05:36:58', 3, NULL, NULL, 'N'),
(29, 'Politics', 'Politics.png', 'uploads/logo/', 12, '2021-06-01 11:17:18', 3, '2021-06-02 05:30:10', 3, NULL, NULL, 'N');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `social_circle`
--
ALTER TABLE `social_circle`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `social_circle`
--
ALTER TABLE `social_circle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
