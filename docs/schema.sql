-- Structure only (no data). Tables and indexes for the gallery database.
-- MariaDB 10.6. Generated with phpMyAdmin 5.2.3.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `<web-user>_emersoncam`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

DROP TABLE IF EXISTS `admin_settings`;
CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cam_images`
--

DROP TABLE IF EXISTS `cam_images`;
CREATE TABLE `cam_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `upload_date` datetime NOT NULL,
  `captured_date` datetime NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_archived` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `folder_date` date NOT NULL,
  `is_favorite` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `cam_images`
--
ALTER TABLE `cam_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `filename` (`filename`),
  ADD KEY `idx_captured_date` (`captured_date`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_is_archived` (`is_archived`),
  ADD KEY `idx_folder_date` (`folder_date`),
  ADD KEY `idx_is_favorite` (`is_favorite`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cam_images`
--
ALTER TABLE `cam_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
