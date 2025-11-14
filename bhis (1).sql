-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2025 at 11:33 AM
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
-- Database: `bhis`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_description` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `activity_description`, `timestamp`) VALUES
(1, 4, 'Logged in', '2025-11-13 19:45:46'),
(2, 4, 'Logged out', '2025-11-13 19:45:49'),
(3, 4, 'Logged in', '2025-11-13 19:45:54'),
(4, 4, 'Added user: RICA', '2025-11-13 20:05:27'),
(5, 4, 'Logged out', '2025-11-13 20:05:31'),
(6, 5, 'Logged in', '2025-11-13 20:05:35'),
(7, 5, 'Updated profile', '2025-11-13 20:58:02'),
(8, 5, 'Updated profile', '2025-11-13 20:58:03'),
(9, 5, 'Updated profile', '2025-11-13 20:58:04'),
(10, 5, 'Updated profile', '2025-11-13 20:58:05'),
(11, 5, 'Logged out', '2025-11-13 21:02:50'),
(12, 4, 'Logged in', '2025-11-13 21:02:55'),
(13, 4, 'Issued 10 x Vitamin C 500mg to EMMAN BAS', '2025-11-13 21:04:39'),
(14, 4, 'Added user: BWHAdmin', '2025-11-13 21:25:05'),
(15, 4, 'Logged out', '2025-11-13 21:25:22'),
(16, 4, 'Logged in', '2025-11-13 21:25:33'),
(17, 4, 'Updated user ID 8', '2025-11-13 21:32:01'),
(18, 4, 'Updated user ID 8', '2025-11-13 21:32:11'),
(19, 8, 'Logged in', '2025-11-13 21:32:32'),
(20, 8, 'Updated supply ID 4', '2025-11-13 21:35:04'),
(21, 4, 'Updated user ID 8', '2025-11-13 21:35:59'),
(22, 8, 'Deleted medicine ID 12', '2025-11-13 21:38:32'),
(23, 4, 'Updated medicine ID 9', '2025-11-13 21:42:35'),
(24, 4, 'Updated medicine ID 10', '2025-11-13 22:18:09'),
(25, 4, 'Updated medicine ID 9', '2025-11-13 22:18:17'),
(26, 4, 'Updated medicine ID 11', '2025-11-13 22:18:55'),
(27, 4, 'Updated medicine ID 11', '2025-11-13 22:33:17'),
(28, 4, 'Updated medicine ID 9', '2025-11-13 22:33:21'),
(29, 4, 'Updated medicine ID 10', '2025-11-13 22:33:27'),
(30, 4, 'Deleted equipment ID 4', '2025-11-13 22:50:40'),
(31, 4, 'Issued 1 x asdad to ', '2025-11-13 22:54:43'),
(32, 8, 'Logged in', '2025-11-13 23:03:54'),
(33, 4, 'Updated profile details', '2025-11-13 23:05:04'),
(34, 4, 'Updated supply ID 4', '2025-11-13 23:37:27'),
(35, 4, 'Logged in', '2025-11-14 00:57:44'),
(36, 4, 'Deleted supply ID 3', '2025-11-14 02:23:31'),
(37, 4, 'Deleted issuance ID 2', '2025-11-14 03:36:58'),
(38, 4, 'Deleted issuance ID 1', '2025-11-14 03:37:00'),
(39, 4, 'Logged out', '2025-11-14 18:08:31'),
(40, 4, 'Logged in', '2025-11-14 18:11:35'),
(41, 4, 'Logged out', '2025-11-14 18:14:25'),
(42, 4, 'Logged in', '2025-11-14 18:18:58'),
(43, 4, 'Added user: rein', '2025-11-14 18:19:27'),
(44, 4, 'Logged out', '2025-11-14 18:19:29'),
(45, 9, 'Logged in', '2025-11-14 18:19:42'),
(46, 9, 'Logged out', '2025-11-14 18:20:49'),
(47, 4, 'Logged in', '2025-11-14 18:21:06');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `equipment_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `supplier` varchar(255) DEFAULT NULL,
  `condition` enum('Good','Broken','Damaged') DEFAULT 'Good',
  `status` enum('Available','Unavailable') DEFAULT 'Available',
  `updated_at` varchar(50) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`equipment_id`, `item_code`, `item_name`, `quantity`, `supplier`, `condition`, `status`, `updated_at`, `date_received`, `added_by`) VALUES
(5, 'EQ-001', 'Blood Pressure Monitor', 1, 'MedTech Solutions', 'Good', 'Available', '2025-11-14 04:04:38', '2025-02-01', 4),
(6, 'EQ-002', 'Stethoscope', 1, 'DoctorLine', 'Good', 'Available', '2025-11-14 04:04:38', '2025-01-20', 4),
(7, 'EQ-003', 'Weighing Scale', 1, 'HealthEquip', 'Broken', 'Unavailable', '2025-11-14 04:04:38', '2025-01-10', 4),
(8, 'EQ-004', 'Nebulizer Machine', 1, 'AirMed Supply', 'Damaged', 'Unavailable', '2025-11-14 04:04:38', '2025-01-14', 4);

-- --------------------------------------------------------

--
-- Table structure for table `issuance`
--

CREATE TABLE `issuance` (
  `issue_id` int(11) NOT NULL,
  `category` enum('Medicine','Supply') DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `quantity_issued` int(11) DEFAULT NULL,
  `issued_to` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `date_issued` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issuance`
--

INSERT INTO `issuance` (`issue_id`, `category`, `item_code`, `item_name`, `quantity_issued`, `issued_to`, `purpose`, `issued_by`, `date_issued`) VALUES
(3, 'Medicine', 'MED-001', 'Paracetamol', 10, 'Juan Dela Cruz', 'Fever Treatment', 4, '2025-02-11 00:00:00'),
(4, 'Medicine', 'MED-002', 'Amoxicillin', 5, 'Maria Santos', 'Antibiotic Dose', 4, '2025-02-09 00:00:00'),
(7, 'Medicine', 'MED-003', 'ORS Solution', 8, 'JODE ', 'Diarrhea Response', 4, '2025-02-04 00:00:00'),
(8, 'Medicine', 'MED-001', 'Paracetamol', 12, 'Pedro Dela Cruz', 'Headache relief', 4, '2024-11-02 00:00:00'),
(9, 'Medicine', 'MED-002', 'Amoxicillin', 8, 'Ana Martinez', 'Antibiotic treatment', 4, '2024-11-03 00:00:00'),
(12, 'Medicine', 'MED-004', 'Cough Syrup', 6, 'Jomar Reyes', 'Cough remedy', 4, '2024-11-06 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `medicine`
--

CREATE TABLE `medicine` (
  `med_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `status` enum('Available','Low Stock','Expired') DEFAULT 'Available',
  `date_received` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine`
--

INSERT INTO `medicine` (`med_id`, `item_code`, `item_name`, `dosage`, `expiry_date`, `quantity`, `category`, `supplier`, `status`, `date_received`, `added_by`, `created_at`, `updated_at`) VALUES
(13, 'MED-001', 'Paracetamol', '500 mg', '2026-05-12', 150, 'Adult', 'UNILAB', 'Available', '2025-02-10', 4, '2025-11-13 20:04:38', '2025-11-13 20:04:38'),
(14, 'MED-002', 'Amoxicillin', '250 mg', '2026-11-30', 80, 'Pediatric', 'Mercury Pharma', 'Available', '2025-02-02', 4, '2025-11-13 20:04:38', '2025-11-13 20:04:38'),
(15, 'MED-003', 'ORS Solution', '500 ml', '2027-01-15', 60, 'General', 'HydraLife', 'Available', '2025-01-21', 4, '2025-11-13 20:04:38', '2025-11-13 20:04:38'),
(16, 'MED-004', 'Cough Syrup', '120 ml', '2025-12-10', 40, 'Child', 'PharmaHealth', 'Low Stock', '2025-01-28', 4, '2025-11-13 20:04:38', '2025-11-13 20:04:38');

-- --------------------------------------------------------

--
-- Table structure for table `supplies`
--

CREATE TABLE `supplies` (
  `supply_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `supplier` varchar(255) DEFAULT NULL,
  `status` enum('Available','Low Stock','Out of Stock') DEFAULT 'Available',
  `updated_at` varchar(50) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplies`
--

INSERT INTO `supplies` (`supply_id`, `item_code`, `item_name`, `quantity`, `supplier`, `status`, `updated_at`, `date_received`, `added_by`) VALUES
(5, 'SUP-001', 'Cotton Balls', 200, 'MedSupply Co.', 'Available', '2025-11-14 04:04:38', '2025-02-06', 4),
(6, 'SUP-002', 'Syringes 3ml', 120, 'Medical Depot', 'Available', '2025-11-14 04:04:38', '2025-02-02', 4),
(7, 'SUP-003', 'Face Masks', 300, 'HealthGuard', 'Available', '2025-11-14 04:04:38', '2025-01-30', 4),
(8, 'SUP-004', 'Alcohol 70%', 90, 'CleanMed', 'Low Stock', '2025-11-14 04:04:38', '2025-01-26', 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Head BHW','BHW') NOT NULL DEFAULT 'BHW',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `password`, `role`, `status`, `date_created`) VALUES
(4, 'CHANNNN', 'admin', '$2y$10$zNWlWN3WDo5CH5Vl.M9JQ.Ztq5IHfJcJ7nVoLTY5EtOyuMEe71Mku', 'Head BHW', 'Active', '2025-11-13 19:45:41'),
(5, 'Rica Mae', 'RICA', '$2y$10$XfaEEqKumJSoL1SspD/ZX.Jnk8FJXlSM.utLrm8YJCkMwl3sEa4EC', 'BHW', 'Active', '2025-11-13 20:05:27'),
(8, 'Worker', 'user', '$2y$10$zNWlWN3WDo5CH5Vl.M9JQ.Ztq5IHfJcJ7nVoLTY5EtOyuMEe71Mku', 'BHW', 'Active', '2025-11-13 21:25:05'),
(9, 'REIN CORONADO', 'rein', '$2y$10$EtYrU0eYN9d18GXKatnl1eG2opCO2DRm.kO/6y07qqJQN34BvMKX2', 'BHW', 'Active', '2025-11-14 18:19:27');

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
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`equipment_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `issuance`
--
ALTER TABLE `issuance`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `medicine`
--
ALTER TABLE `medicine`
  ADD PRIMARY KEY (`med_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`supply_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `equipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `issuance`
--
ALTER TABLE `issuance`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `medicine`
--
ALTER TABLE `medicine`
  MODIFY `med_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `supply_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `issuance`
--
ALTER TABLE `issuance`
  ADD CONSTRAINT `issuance_ibfk_1` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine`
--
ALTER TABLE `medicine`
  ADD CONSTRAINT `medicine_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `supplies`
--
ALTER TABLE `supplies`
  ADD CONSTRAINT `supplies_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
