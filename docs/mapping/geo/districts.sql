-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 09:02 AM
-- Server version: 26.7.0
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrm_v1`
--

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `code`, `name`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(1, 'LK11', 'Colombo', 6.8480000, 80.0290000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(2, 'LK12', 'Gampaha', 7.1190000, 80.0210000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(3, 'LK13', 'Kalutara', 6.5760000, 80.1350000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(4, 'LK21', 'Kandy', 7.2150000, 80.6410000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(5, 'LK22', 'Matale', 7.6980000, 80.6600000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(6, 'LK23', 'Nuwara Eliya', 7.0130000, 80.7570000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(7, 'LK31', 'Galle', 6.2040000, 80.2310000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(8, 'LK32', 'Matara', 6.1580000, 80.5190000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(9, 'LK33', 'Hambantota', 6.2760000, 81.1780000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(10, 'LK41', 'Jaffna', 9.6440000, 79.9080000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(11, 'LK42', 'Mannar', 8.8810000, 80.1630000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(12, 'LK43', 'Vavuniya', 8.8360000, 80.4860000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(13, 'LK44', 'Mullaitivu', 9.1810000, 80.5260000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(14, 'LK45', 'Kilinochchi', 9.4480000, 80.3860000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(15, 'LK51', 'Batticaloa', 7.8340000, 81.3860000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(16, 'LK52', 'Ampara', 7.1250000, 81.6860000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(17, 'LK53', 'Trincomalee', 8.5680000, 81.0630000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(18, 'LK61', 'Kurunegala', 7.7280000, 80.2570000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(19, 'LK62', 'Puttalam', 7.9160000, 79.9130000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(20, 'LK71', 'Anuradhapura', 8.3690000, 80.4400000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(21, 'LK72', 'Polonnaruwa', 7.9940000, 81.0510000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(22, 'LK81', 'Badulla', 7.0820000, 81.0890000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(23, 'LK82', 'Monaragala', 6.8730000, 81.3710000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(24, 'LK91', 'Ratnapura', 6.5800000, 80.5900000, '2026-10-19 04:01:03', '2026-10-19 04:01:03'),
(25, 'LK92', 'Kegalle', 7.1130000, 80.3210000, '2026-10-19 04:01:03', '2026-10-19 04:01:03');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `districts_code_unique` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
