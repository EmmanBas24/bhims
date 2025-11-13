-- BHIS Database for Barangay Health Inventory System
CREATE DATABASE IF NOT EXISTS bhis DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bhis;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('Head BHW','BHW') NOT NULL DEFAULT 'BHW',
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  date_created DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medicine (
  med_id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(100),
  item_name VARCHAR(255),
  expiry_date DATE,
  quantity INT DEFAULT 0,
  supplier VARCHAR(255),
  status ENUM('Available','Low Stock','Expired') DEFAULT 'Available',
  date_received DATE,
  added_by INT,
  FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE supplies (
  supply_id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(100),
  item_name VARCHAR(255),
  quantity INT DEFAULT 0,
  supplier VARCHAR(255),
  status ENUM('Available','Low Stock','Out of Stock') DEFAULT 'Available',
  date_received DATE,
  added_by INT,
  FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE equipment (
  equipment_id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(100),
  item_name VARCHAR(255),
  quantity INT DEFAULT 0,
  supplier VARCHAR(255),
  `condition` ENUM('Good','Broken','Damaged') DEFAULT 'Good',
  status ENUM('Available','Unavailable') DEFAULT 'Available',
  date_received DATE,
  added_by INT,
  FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE issuance (
  issue_id INT AUTO_INCREMENT PRIMARY KEY,
  category ENUM('Medicine','Supply'),
  item_code VARCHAR(100),
  item_name VARCHAR(255),
  quantity_issued INT,
  issued_to VARCHAR(255),
  purpose TEXT,
  issued_by INT,
  date_issued DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  activity_description TEXT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create sample Head BHW user (username: admin, password: admin123)
INSERT INTO users (name, username, password, role) VALUES
('Head BHW','admin', '$2y$10$wHc2K1hQjEps2fE4y8SxEOqCqvPq4sYq9x6pM9l6QeZbq2y1Kf1aG', 'Head BHW');
-- Password above is bcrypt for "admin123"
