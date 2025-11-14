-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2025 at 11:43 PM
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
(1, 4, 'Logged in', '2025-11-15 05:08:38'),
(2, 4, 'Issued 20 x Syringes 3ml to Jode Somera', '2025-11-15 05:58:15'),
(3, 4, 'Logged out', '2025-11-15 06:25:48'),
(4, 4, 'Logged in', '2025-11-15 06:26:28'),
(5, 4, 'Added user: Rein', '2025-11-15 06:26:53'),
(6, 4, 'Logged out', '2025-11-15 06:26:55'),
(7, 16, 'Logged in', '2025-11-15 06:26:59'),
(8, 16, 'Logged out', '2025-11-15 06:28:20'),
(9, 4, 'Logged in', '2025-11-15 06:28:26'),
(10, 4, 'Added medicine: Paracetamol', '2025-11-15 06:29:57'),
(11, 4, 'Issued 60 x Paracetamol to Adam', '2025-11-15 06:34:44');

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
(9, 'EQ-001', 'Blood Pressure Monitor (BP Set)', 1, 'MedServe Supply', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 10),
(10, 'EQ-002', 'Stethoscope', 1, 'Wellness Medical', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 11),
(11, 'EQ-003', 'Thermometer (Digital)', 1, 'HealthLine Devices', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 12),
(12, 'EQ-004', 'Nebulizer Machine', 1, 'AirLife Medical', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 13),
(13, 'EQ-005', 'Weighing Scale (Adult)', 1, 'MedEquip Center', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 14),
(14, 'EQ-006', 'Weighing Scale (Infant)', 1, 'BabyCare Medical', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 10),
(15, 'EQ-007', 'Glucometer Set', 1, 'LifeCheck Diagnostics', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 11),
(16, 'EQ-008', 'Oxygen Tank (Portable)', 1, 'AirMed Supply', 'Good', 'Available', '2025-11-15 03:33:19', '2025-01-15', 12);

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
(8, 'Medicine', 'MED-001', 'Paracetamol', 12, 'Pedro Dela Cruz', 'Headache relief', 4, '2024-11-02 00:00:00'),
(9, 'Medicine', 'MED-002', 'Amoxicillin', 8, 'Ana Martinez', 'Antibiotic treatment', 4, '2024-11-03 00:00:00'),
(12, 'Medicine', 'MED-004', 'Cough Syrup', 6, 'Jomar Reyes', 'Cough remedy', 4, '2024-11-06 00:00:00'),
(30, 'Supply', 'SUP-001', 'Cotton Balls', 200, 'EMMAN BAS', 'STAFF USED', 4, '2025-11-15 00:52:09'),
(32, 'Medicine', 'MED-001', 'Paracetamol', 200, 'Patient ', 'fever', 15, '2025-11-15 03:24:45'),
(33, 'Supply', 'SUP-002', 'Syringes 3ml', 20, 'Jode Somera', 'Patient needs', 4, '2025-11-15 05:58:15'),
(34, 'Medicine', 'MED-009', 'Paracetamol', 60, 'Adam', 'fever', 4, '2025-11-15 06:34:44');

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
(17, 'MED-001', 'Paracetamol', '500mg', '2026-12-31', 0, 'Tablet', 'Zuellig Pharma', 'Available', '2025-01-10', 10, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(18, 'MED-002', 'Ibuprofen', '400mg', '2027-03-15', 150, 'Tablet', 'Unilab', 'Available', '2025-01-10', 11, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(19, 'MED-003', 'Amoxicillin', '500mg', '2026-10-20', 180, 'Capsule', 'Pfizer', 'Available', '2025-01-10', 12, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(20, 'MED-004', 'Co-trimoxazole', '800mg/160mg', '2027-04-05', 120, 'Tablet', 'Roche', 'Available', '2025-01-10', 13, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(21, 'MED-005', 'ORS Powder', 'Standard Pack', '2028-01-30', 250, 'Powder', 'NutriAsia Medical', 'Available', '2025-01-10', 14, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(22, 'MED-006', 'Cetirizine', '10mg', '2027-08-21', 160, 'Tablet', 'Unilab', 'Available', '2025-01-10', 10, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(23, 'MED-007', 'Ferrous Sulfate', '325mg', '2026-11-18', 300, 'Tablet', 'PharmaTech', 'Available', '2025-01-10', 11, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(24, 'MED-008', 'Mefenamic Acid', '500mg', '2027-02-10', 140, 'Capsule', 'Lloyd Laboratories', 'Available', '2025-01-10', 12, '2025-11-14 19:23:11', '2025-11-14 19:23:11'),
(25, 'MED-009', 'Paracetamol', '500 mg', '2025-12-25', 0, 'Adult', 'LGU mingla', 'Available', '0000-00-00', 4, '2025-11-14 22:29:56', NULL);

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
(5, 'SUP-001', 'Cotton Balls', 0, 'MedSupply Co.', 'Available', '2025-11-14 04:04:38', '2025-02-06', 4),
(6, 'SUP-002', 'Syringes 3ml', 100, 'Medical Depot', 'Available', '2025-11-14 04:04:38', '2025-02-02', 4),
(7, 'SUP-003', 'Face Masks', 300, 'HealthGuard', 'Available', '2025-11-14 04:04:38', '2025-01-30', 4),
(8, 'SUP-004', 'Alcohol 70%', 90, 'CleanMed', 'Low Stock', '2025-11-14 04:04:38', '2025-01-26', 4),
(9, 'SUP-001', 'Syringes (3ml)', 40, 'Zuellig Pharma', 'Available', '2025-11-15 03:28:45', '2025-01-12', 10),
(10, 'SUP-002', 'Alcohol 70%', 12, 'Unilab', 'Available', '2025-11-15 03:28:45', '2025-01-12', 11),
(11, 'SUP-003', 'Cotton Balls', 20, 'MedLine Supply', 'Available', '2025-11-15 03:28:45', '2025-01-12', 12),
(12, 'SUP-004', 'Face Masks (Disposable)', 50, 'HealthGuard Corp', 'Available', '2025-11-15 03:28:45', '2025-01-12', 13),
(13, 'SUP-005', 'Gloves (Latex)', 30, 'Glovetech Medical', 'Available', '2025-11-15 03:28:45', '2025-01-12', 14),
(14, 'SUP-006', 'Bandages (Elastic)', 15, 'Lifeline Medical', 'Available', '2025-11-15 03:28:45', '2025-01-12', 10),
(15, 'SUP-007', 'Thermometer Strips', 25, 'MedicTrust', 'Available', '2025-11-15 03:28:45', '2025-01-12', 11),
(16, 'SUP-008', 'BP Cuff Replacement Parts', 8, 'Wellness Medical', 'Available', '2025-11-15 03:28:45', '2025-01-12', 12);

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
(4, 'Aaron', 'Aaron', '$2y$10$zlN8ApjKmE2pjXdHgPleZePqH8FHbrKABsHwinKhnoZvJcCvcu/1e', 'Head BHW', 'Active', '2025-11-13 19:45:41'),
(10, 'Maria Lopez', 'bhw_mlopez', 'password123', 'BHW', 'Active', '2025-11-15 03:06:49'),
(11, 'Ana Dela Cruz', 'bhw_adcruz', 'password123', 'BHW', 'Active', '2025-11-15 03:06:49'),
(12, 'Joan Ramirez', 'bhw_jramirez', 'password123', 'BHW', 'Active', '2025-11-15 03:06:49'),
(13, 'Ella Santiago', 'bhw_esantiago', 'password123', 'BHW', 'Active', '2025-11-15 03:06:49'),
(14, 'Grace Villanueva', 'bhw_gvillanueva', 'password123', 'BHW', 'Active', '2025-11-15 03:06:49'),
(15, 'HEAD', 'HEAD', '$2y$10$1uWiFCs1caRt0y3eqhuAJu.1G2qEWw2BO2aCNekQ/ad7bo4qGgaVC', 'Head BHW', 'Active', '2025-11-15 03:08:26'),
(16, 'BHW', 'Rein', '$2y$10$gRXXwLYIjFihpvGRzb1uH.dmZpCU6imEfn4FlQIwjjnCFNkdKkPWi', 'BHW', 'Active', '2025-11-15 06:26:53');

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `equipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `issuance`
--
ALTER TABLE `issuance`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `medicine`
--
ALTER TABLE `medicine`
  MODIFY `med_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `supply_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
