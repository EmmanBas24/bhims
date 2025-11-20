-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2025 at 10:25 PM
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
(11, 4, 'Issued 60 x Paracetamol to Adam', '2025-11-15 06:34:44'),
(12, 4, 'Logged in', '2025-11-15 09:30:00'),
(13, 4, 'Logged out', '2025-11-15 09:30:09'),
(14, 4, 'Logged in', '2025-11-15 09:31:02'),
(15, 4, 'Added medicine: Paracetamol', '2025-11-15 09:32:43'),
(16, 4, 'Updated medicine ID 26', '2025-11-15 09:33:28'),
(17, 4, 'Updated medicine ID 25', '2025-11-15 09:34:44'),
(18, 4, 'Added equipment: Nebulizer Machine', '2025-11-15 09:40:48'),
(19, 4, 'Issued 5 x Paracetamol to Adam', '2025-11-15 09:42:58'),
(20, 4, 'Issued 15 x Bandages (Elastic) to rein ', '2025-11-15 09:45:33'),
(21, 4, 'Added user: BHW-ADAM', '2025-11-15 09:48:01'),
(22, 4, 'Updated profile details', '2025-11-15 09:49:43'),
(23, 4, 'Logged out', '2025-11-15 09:49:47'),
(24, 16, 'Logged in', '2025-11-15 09:49:52'),
(25, 16, 'Logged out', '2025-11-15 09:50:34'),
(26, 4, 'Logged in', '2025-11-15 09:51:23'),
(27, 4, 'Logged out', '2025-11-15 09:51:54'),
(28, 16, 'Logged in', '2025-11-15 09:51:59'),
(29, 16, 'Logged out', '2025-11-15 09:52:19'),
(30, 4, 'Logged in', '2025-11-15 09:52:24'),
(31, 4, 'Logged in', '2025-11-20 02:19:03'),
(32, 4, 'Deleted medicine ID 18', '2025-11-20 02:37:53'),
(33, 4, 'Deleted medicine ID 25', '2025-11-20 02:37:55'),
(34, 4, 'Deleted medicine ID 26', '2025-11-20 02:37:57'),
(35, 4, 'Deleted medicine ID 23', '2025-11-20 02:38:01'),
(36, 4, 'Deleted medicine ID 20', '2025-11-20 02:38:04'),
(37, 4, 'Deleted medicine ID 21', '2025-11-20 02:38:07'),
(38, 4, 'Added medicine/batch: Lumay (qty 20)', '2025-11-20 02:42:10'),
(39, 4, 'Added medicine/batch: VEX (qty 20)', '2025-11-20 02:50:40'),
(40, 4, 'Added medicine/batch: Cotton Balls (code COTT-001 qty 8)', '2025-11-20 03:01:04'),
(41, 4, 'Updated medicine ID 17', '2025-11-20 03:06:10'),
(42, 4, 'Updated medicine ID 17', '2025-11-20 03:06:17'),
(43, 4, 'Deleted medicine ID 17', '2025-11-20 03:06:40'),
(44, 4, 'Updated medicine ID 24', '2025-11-20 03:07:10'),
(45, 4, 'Updated medicine ID 27', '2025-11-20 03:12:43'),
(46, 4, 'Added batch for medicine_id 19 qty 20', '2025-11-20 03:20:49'),
(47, 4, 'Added batch for medicine_id 29 qty 20', '2025-11-20 03:41:54'),
(48, 4, 'Marked batch 19 unusable (old remaining 20)', '2025-11-20 04:09:02'),
(49, 4, 'Marked batch 18 unusable (old remaining 8)', '2025-11-20 04:38:48'),
(50, 4, 'Added medicine/batch: Paracetamol (code PARA-001 qty 8)', '2025-11-20 04:48:17'),
(51, 4, 'Issued 175 x Amoxicillin to Christian Abendan', '2025-11-20 05:01:06'),
(52, 4, 'Issued 1 x Amoxicillin to 175 (issuance_id=38)', '2025-11-20 05:03:55'),
(53, 4, 'Issued 175 x Amoxicillin to Christian Abendan (issuance_id=39)', '2025-11-20 05:04:59'),
(54, 4, 'Archived issuance ID 32', '2025-11-20 11:13:53'),
(55, 4, 'Archived issuance ID 37', '2025-11-20 11:54:07'),
(56, 4, 'Archived issuance ID 37', '2025-11-20 11:54:17'),
(57, 4, 'Deleted medicine ID 19', '2025-11-20 11:58:03'),
(58, 4, 'Archived issuance ID 39', '2025-11-20 12:15:08'),
(59, 4, 'Archived issuance ID 39', '2025-11-20 12:15:10'),
(60, 4, 'Archived issuance ID 38', '2025-11-20 13:03:28'),
(61, 4, 'Archived issuance ID 36', '2025-11-20 13:03:35'),
(62, 4, 'Logged in', '2025-11-20 22:21:11'),
(63, 4, 'Issued 10 x Cetirizine to Jode Somera (issue_id=40)', '2025-11-21 01:31:40'),
(64, 4, 'Logged out', '2025-11-21 04:15:02'),
(65, 4, 'Logged in', '2025-11-21 04:23:53'),
(66, 4, 'Logged out', '2025-11-21 04:36:21'),
(67, 4, 'Logged in', '2025-11-21 04:47:15');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_no` varchar(150) DEFAULT NULL,
  `quantity_received` int(11) NOT NULL DEFAULT 0,
  `quantity_remaining` int(11) NOT NULL DEFAULT 0,
  `date_received` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`id`, `medicine_id`, `batch_no`, `quantity_received`, `quantity_remaining`, `date_received`, `expiry_date`, `supplier`, `created_at`, `updated_at`) VALUES
(5, 22, 'MIG-22-20251120022213', 160, 150, '2025-01-10', '2027-08-21', 'Unilab', '2025-11-20 02:22:14', '2025-11-21 01:31:40'),
(7, 24, 'MIG-24-20251120022213', 140, 140, '2025-01-10', '2027-02-10', 'Lloyd Laboratories', '2025-11-20 02:22:14', '2025-11-20 02:22:14'),
(16, 27, '1', 20, 20, '2025-11-20', '2026-02-19', 'LGU mingla', '2025-11-20 02:42:10', '2025-11-20 02:42:10'),
(17, 28, '1', 20, 20, '2025-11-20', '2026-06-25', 'DOH Supply Center', '2025-11-20 02:50:40', '2025-11-20 02:50:40'),
(20, 29, '2', 20, 20, '2025-11-20', '2025-11-29', 'LGU MINGLANILLA', '2025-11-20 03:41:54', '2025-11-20 03:41:54'),
(21, 30, '1', 8, 8, '2025-11-20', '2025-11-27', 'LGU mingla', '2025-11-20 04:48:17', '2025-11-20 04:48:17');

-- --------------------------------------------------------

--
-- Table structure for table `issuance`
--

CREATE TABLE `issuance` (
  `issue_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `quantity_issued` int(11) DEFAULT NULL,
  `issued_to` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `date_issued` datetime DEFAULT current_timestamp(),
  `status` varchar(32) DEFAULT 'Complete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issuance`
--

INSERT INTO `issuance` (`issue_id`, `item_code`, `item_name`, `quantity_issued`, `issued_to`, `purpose`, `issued_by`, `date_issued`, `status`) VALUES
(3, 'MED-001', 'Paracetamol', 10, 'Juan Dela Cruz', 'Fever Treatment', 4, '2025-02-11 00:00:00', 'Complete'),
(4, 'MED-002', 'Amoxicillin', 5, 'Maria Santos', 'Antibiotic Dose', 4, '2025-02-09 00:00:00', 'Complete'),
(8, 'MED-001', 'Paracetamol', 12, 'Pedro Dela Cruz', 'Headache relief', 4, '2024-11-02 00:00:00', 'Complete'),
(9, 'MED-002', 'Amoxicillin', 8, 'Ana Martinez', 'Antibiotic treatment', 4, '2024-11-03 00:00:00', 'Complete'),
(12, 'MED-004', 'Cough Syrup', 6, 'Jomar Reyes', 'Cough remedy', 4, '2024-11-06 00:00:00', 'Complete'),
(30, 'SUP-001', 'Cotton Balls', 200, 'EMMAN BAS', 'STAFF USED', 4, '2025-11-15 00:52:09', 'Complete'),
(32, 'MED-001', 'Paracetamol', 200, 'Patient ', 'fever', NULL, '2025-11-15 03:24:45', 'Complete'),
(33, 'SUP-002', 'Syringes 3ml', 20, 'Jode Somera', 'Patient needs', 4, '2025-11-15 05:58:15', 'Complete'),
(34, 'MED-009', 'Paracetamol', 60, 'Adam', 'fever', 4, '2025-11-15 06:34:44', 'Complete'),
(35, 'MED-10', 'Paracetamol', 5, 'Adam', 'treatment for fever', 4, '2025-11-15 09:42:57', 'Complete'),
(36, 'SUP-006', 'Bandages (Elastic)', 15, 'rein ', 'For treatment sa resident', 4, '2025-11-15 09:45:33', 'Archived'),
(37, 'MED-003', 'Amoxicillin', 175, 'Christian Abendan', 'PATIENT TREATMENT', 4, '2025-11-20 05:01:06', 'Complete'),
(38, 'MED-003', 'Amoxicillin', 1, '175', 'PATIENT TREATMENT', 4, '2025-11-20 05:03:55', 'Archived'),
(39, 'MED-003', 'Amoxicillin', 175, 'Christian Abendan', 'PATIENT TREATMENT', 4, '2025-11-20 05:04:59', 'Archived'),
(40, 'MED-006', 'Cetirizine', 10, 'Jode Somera', 'PATIENT TREATMENT', 4, '2025-11-21 01:31:40', 'Complete');

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
  `updated_at` timestamp NULL DEFAULT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `form` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `min_stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine`
--

INSERT INTO `medicine` (`med_id`, `item_code`, `item_name`, `dosage`, `expiry_date`, `quantity`, `category`, `supplier`, `status`, `date_received`, `added_by`, `created_at`, `updated_at`, `generic_name`, `form`, `unit`, `min_stock`) VALUES
(22, 'MED-006', 'Cetirizine', '10mg', '2027-08-21', 150, 'Tablet', 'Unilab', 'Available', '2025-01-10', NULL, '2025-11-14 19:23:11', '2025-11-14 19:23:11', NULL, NULL, 'pcs', 0),
(24, 'MED-008', 'Mefenamic Acid', '500mg', '2027-02-10', 140, 'Capsule', 'Lloyd Laboratories', 'Available', '2025-01-10', NULL, '2025-11-14 19:23:11', '2025-11-19 19:07:10', '', '', 'pcs', 0),
(27, 'MED-002', 'Lumay', '500 mg', NULL, 20, 'Child', NULL, 'Available', NULL, NULL, '2025-11-19 18:42:10', '2025-11-19 19:12:43', 'Generic', 'tablet', 'pcs', 30),
(28, 'MED-010', 'VEX', '50 ml', NULL, 20, '', NULL, 'Available', NULL, NULL, '2025-11-19 18:50:40', NULL, 'Generic', 'tablet', 'pcs', 15),
(29, 'COTT-001', 'Cotton Balls', '500 mg', NULL, 20, NULL, NULL, 'Available', NULL, NULL, '2025-11-19 19:01:04', NULL, 'Generic', 'tablet', 'pcs', 8),
(30, 'PARA-001', 'Paracetamol', '500mg', NULL, 8, NULL, NULL, 'Available', NULL, NULL, '2025-11-19 20:48:17', NULL, 'Generic', 'tablet', 'pcs', 15);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `issuance_item_id` int(11) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `movement_date` datetime DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `movement_type`, `medicine_id`, `batch_id`, `issuance_item_id`, `qty`, `unit`, `movement_date`, `note`) VALUES
(16, 'IN', 27, 16, NULL, 20, 'pcs', '2025-11-20 02:42:10', 'Received batch 1'),
(17, 'IN', 28, 17, NULL, 20, 'pcs', '2025-11-20 02:50:40', 'Received batch 1'),
(18, 'IN', 29, NULL, NULL, 8, 'pcs', '2025-11-20 03:01:04', 'Received batch 1'),
(20, 'IN', 29, 20, NULL, 20, 'pcs', '2025-11-20 03:41:54', 'Batch added via Add Batch (batch 2)'),
(22, '', 29, NULL, NULL, -8, 'pcs', '2025-11-20 04:38:48', 'Marked unusable: Expired / contaminated / damaged (old:8)'),
(23, 'IN', 30, 21, NULL, 8, 'pcs', '2025-11-20 04:48:17', 'Received batch 1'),
(26, 'OUT', 22, 5, NULL, 10, 'pcs', '2025-11-21 01:31:40', 'Issued (issue_id=40)');

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
(4, 'Aaronn', 'Aaron', '$2y$10$zlN8ApjKmE2pjXdHgPleZePqH8FHbrKABsHwinKhnoZvJcCvcu/1e', 'Head BHW', 'Active', '2025-11-13 19:45:41'),
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
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `date_received` (`date_received`);

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
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `movement_date` (`movement_date`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `issuance`
--
ALTER TABLE `issuance`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `medicine`
--
ALTER TABLE `medicine`
  MODIFY `med_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `fk_batches_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicine` (`med_id`) ON DELETE CASCADE;

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
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_moves_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_moves_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicine` (`med_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
