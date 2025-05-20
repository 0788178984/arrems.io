-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 20, 2025 at 11:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `arrems_realestate_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL,
  `type` enum('in_person','virtual') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ar_interactions`
--

CREATE TABLE `ar_interactions` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interaction_type` enum('view','measure','annotate','walkthrough') NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('house','apartment','commercial','land') NOT NULL,
  `status` enum('available','sold','rented','pending') NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `area_size` decimal(10,2) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `title`, `description`, `type`, `status`, `price`, `area_size`, `bedrooms`, `bathrooms`, `address`, `city`, `state`, `zip_code`, `latitude`, `longitude`, `agent_id`, `created_at`, `updated_at`) VALUES
(1, 'Modern Beachfront Villa', 'Luxurious 4-bedroom villa with direct beach access and panoramic ocean views.', 'house', 'available', 1200000.00, 350.00, 4, 3, '123 Coastal Drive', 'Miami Beach', 'Florida', '33139', 25.76170000, -80.19180000, 1, '2025-05-15 15:31:10', '2025-05-15 15:31:10');

-- --------------------------------------------------------

--
-- Table structure for table `property_analytics`
--

CREATE TABLE `property_analytics` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `views_count` int(11) DEFAULT 0,
  `ar_views_count` int(11) DEFAULT 0,
  `favorite_count` int(11) DEFAULT 0,
  `last_viewed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_analytics`
--

INSERT INTO `property_analytics` (`id`, `property_id`, `views_count`, `ar_views_count`, `favorite_count`, `last_viewed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 133, 48, 10, '2025-05-15 15:31:11', '2025-05-15 15:31:11', '2025-05-15 15:31:11');

-- --------------------------------------------------------

--
-- Table structure for table `property_features`
--

CREATE TABLE `property_features` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `feature_name` varchar(100) NOT NULL,
  `feature_value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_features`
--

INSERT INTO `property_features` (`id`, `property_id`, `feature_name`, `feature_value`, `created_at`) VALUES
(1, 1, 'Swimming Pool', 'Yes', '2025-05-15 15:31:10'),
(2, 1, 'Parking', '2 Cars', '2025-05-15 15:31:10'),
(3, 1, 'Air Conditioning', 'Central', '2025-05-15 15:31:10'),
(4, 1, 'Security System', 'Advanced', '2025-05-15 15:31:10'),
(5, 1, 'Year Built', '2020', '2025-05-15 15:31:10');

-- --------------------------------------------------------

--
-- Table structure for table `property_media`
--

CREATE TABLE `property_media` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `media_type` enum('image','3d_model','ar_content','video') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'draft',
  `view_url` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_media`
--

INSERT INTO `property_media` (`id`, `property_id`, `media_type`, `file_path`, `title`, `description`, `is_primary`, `created_at`, `status`, `view_url`, `updated_at`) VALUES
(1, 1, 'image', 'sample_house_1.jpg', 'Front View', NULL, 1, '2025-05-15 15:31:10', 'draft', NULL, '2025-05-20 09:00:02'),
(2, 1, 'image', 'sample_house_2.jpg', 'Living Room', NULL, 0, '2025-05-15 15:31:11', 'draft', NULL, '2025-05-20 09:00:02'),
(3, 1, '3d_model', 'house_model.glb', '3D Tour', NULL, 0, '2025-05-15 15:31:11', 'draft', NULL, '2025-05-20 09:00:02'),
(4, 1, 'video', 'property_tour.mp4', 'Video Tour', NULL, 0, '2025-05-15 15:31:11', 'draft', NULL, '2025-05-20 09:00:02');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_media`
--

CREATE TABLE `tour_media` (
  `media_id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `media_type` enum('image','video','model') NOT NULL DEFAULT 'image',
  `media_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','agent','client','manager','buyer','seller','stakeholder') NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `phone`, `role`, `status`, `profile_image`, `created_at`, `updated_at`) VALUES
(1, 'John', 'Doe', 'agent1@example.com', '$2y$10$isCj.niEk.7ifYRfqJ0Fke2JcnVglC9pO4xc9zPcy6GrsAtVvmdbu', '+1234567890', 'agent', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(2, 'Jane', 'Smith', 'agent2@example.com', '$2y$10$Cif.WkX8Cm9bjTBI3mUWAu4F1pP8xhqsNbSDczqhZZCDaw54OV1hK', '+1234567891', 'agent', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(3, 'Admin', 'User', 'admin@example.com', '$2y$10$sRjWaCOu2Pr1QBSBs.Nx2eQv9gTFvkom.8H29.d5oHgGlavwAmNFC', '+1234567892', 'admin', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(4, 'Mike', 'Manager', 'manager@example.com', '$2y$10$0qO1zaBrGQ4QZLo4CSI80u5Uvun5tgSbR3iONZfp2WirGoBcuqCTm', '+1234567893', 'manager', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(5, 'Bob', 'Buyer', 'buyer@example.com', '$2y$10$YveHfauYclMD99sn6QpbF.v.C9uQVc5lmG7XH.e1rU7/dEbyT/4ZO', '+1234567894', 'buyer', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(6, 'Sarah', 'Seller', 'seller@example.com', '$2y$10$J0NM5a2PrBx5./eTW7C77Ov3ifHQOVHQLD0eLPs9rmDJoXqlq/Rii', '+1234567895', 'seller', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(7, 'Tom', 'Client', 'client@example.com', '$2y$10$gEHowx23odeYZ4nXOtVvN.KfiW5lreBrav33p9UkS3zuKo.CNv7ti', '+1234567896', 'client', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10'),
(8, 'Steve', 'Stakeholder', 'stakeholder@example.com', '$2y$10$VbF/25u2pDRX.8rbuFirzusoAWlOcLgAEaDHTs7hqC3U0UnpbA/06', '+1234567897', 'stakeholder', 'active', NULL, '2025-05-15 15:31:10', '2025-05-15 15:31:10');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `profile_photo_url` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`profile_id`, `user_id`, `phone_number`, `profile_photo_url`, `address`, `bio`, `created_at`, `updated_at`) VALUES
(1, 3, '0788178984', NULL, '2 Kagadi', 'hhh', '2025-05-15 10:43:20', '2025-05-15 10:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `virtual_tours`
--

CREATE TABLE `virtual_tours` (
  `tour_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tour_url` varchar(255) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `ar_interactions`
--
ALTER TABLE `ar_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`property_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `property_analytics`
--
ALTER TABLE `property_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_features`
--
ALTER TABLE `property_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_media`
--
ALTER TABLE `property_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tour_media`
--
ALTER TABLE `tour_media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `tour_id` (`tour_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `virtual_tours`
--
ALTER TABLE `virtual_tours`
  ADD PRIMARY KEY (`tour_id`),
  ADD KEY `property_id` (`property_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ar_interactions`
--
ALTER TABLE `ar_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_analytics`
--
ALTER TABLE `property_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_features`
--
ALTER TABLE `property_features`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `property_media`
--
ALTER TABLE `property_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_media`
--
ALTER TABLE `tour_media`
  MODIFY `media_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `virtual_tours`
--
ALTER TABLE `virtual_tours`
  MODIFY `tour_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ar_interactions`
--
ALTER TABLE `ar_interactions`
  ADD CONSTRAINT `ar_interactions_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ar_interactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `property_analytics`
--
ALTER TABLE `property_analytics`
  ADD CONSTRAINT `property_analytics_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_features`
--
ALTER TABLE `property_features`
  ADD CONSTRAINT `property_features_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_media`
--
ALTER TABLE `property_media`
  ADD CONSTRAINT `property_media_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
