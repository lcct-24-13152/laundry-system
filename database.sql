-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2026 at 07:10 AM
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
-- Database: `laundry_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `activity` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addons`
--

CREATE TABLE `addons` (
  `id` int(11) NOT NULL,
  `addon_name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `size_value` float DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL,
  `usage_value` decimal(10,2) DEFAULT NULL,
  `usage_type` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`id`, `addon_name`, `category`, `price`, `size_value`, `unit`, `usage_value`, `usage_type`) VALUES
(15, 'Fabcon A', 'Fabric Softener', 3.00, 800, 'ml', NULL, NULL),
(16, 'Detergent B', 'Detergent', 3.00, 100, 'ml', NULL, NULL),
(18, 'Detergent A', 'Detergent', 3.00, 800, 'ml', NULL, NULL),
(19, 'Fabcon B', 'Fabric Softener', 3.00, 800, 'ml', NULL, NULL),
(27, 'Plastic', 'Other', 3.00, 0, '', NULL, NULL),
(28, 'Bleach B', 'Bleach', 3.00, 800, 'ml', NULL, NULL),
(29, 'Fold', 'Other', 25.00, 0, '', NULL, NULL),
(30, 'Detergent C', 'Detergent', 0.00, 800, 'ml', NULL, NULL),
(31, 'TESTING', 'Other', 100.00, 0, '', NULL, NULL),
(32, 'Bleach C', 'Bleach', 3.00, 800, 'ml', NULL, NULL),
(33, 'Bleach A', 'Bleach', 0.00, 800, 'ml', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `reset_token`, `token_expiry`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$c0NbekHqwyymglSJgax29.R/bVXLvKAcaAzuO8InoJJXmaf2MFJYy', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--


-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_user` varchar(255) NOT NULL,
  `smtp_pass` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL,
  `smtp_secure` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_settings`
--

INSERT INTO `email_settings` (`id`, `smtp_host`, `smtp_user`, `smtp_pass`, `smtp_port`, `smtp_secure`) VALUES
(1, 'smtp.gmail.com', '', '', 587, 'tls');

-- --------------------------------------------------------

--
-- Table structure for table `extra_services`
--

CREATE TABLE `extra_services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extra_services`
--

INSERT INTO `extra_services` (`id`, `service_name`, `price`) VALUES
(5, 'Extra Wash', 75.00),
(6, 'Extra Dry', 85.00),
(7, 'TESTING', 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `size_value` float DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `critical_level` float DEFAULT NULL,
  `notified` tinyint(4) DEFAULT 0,
  `usage_per_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usage_per_load` decimal(10,2) DEFAULT 0.00,
  `usage_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usage_type` varchar(10) NOT NULL DEFAULT 'kg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `category`, `quantity`, `unit`, `size_value`, `reorder_level`, `price`, `critical_level`, `notified`, `usage_per_kg`, `usage_per_load`, `usage_value`, `usage_type`) VALUES
(1, 'Detergent A', 'Detergent', -5, 'ml', 800, 15, 0.00, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(15, 'Detergent B', 'Detergent', 100, 'ml', 800, 15, 0.00, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(17, 'Fabcon B', 'Fabric Softener', 100, 'ml', 800, 15, 0.00, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(18, 'Fabcon A', 'Fabric Softener', -95, 'ml', 800, 15, 0.00, 10, 1, 0.00, 0.00, 5.00, 'kg'),
(21, 'Bleach B', 'Bleach', 100, 'ml', 800, 15, NULL, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(22, 'Detergent C', 'Detergent', 100, 'ml', 800, 15, NULL, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(23, 'Bleach C', 'Bleach', 100, 'ml', 800, 15, NULL, 10, 0, 0.00, 0.00, 5.00, 'kg'),
(24, 'Bleach A', 'Bleach', 70, 'ml', 800, 15, NULL, 10, 0, 0.00, 0.00, 5.00, 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `laundry_mode`
--

CREATE TABLE `laundry_mode` (
  `id` int(11) NOT NULL,
  `mode_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laundry_mode`
--

INSERT INTO `laundry_mode` (`id`, `mode_name`, `price`) VALUES
(1, 'Self Service', 0.00),
(2, 'CML Service', 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `laundry_pricing_settings`
--

CREATE TABLE `laundry_pricing_settings` (
  `id` int(11) NOT NULL,
  `max_load_kg` decimal(10,2) DEFAULT 7.00,
  `full_load_price` decimal(10,2) DEFAULT 218.00,
  `min_load_price` decimal(10,2) DEFAULT 203.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laundry_pricing_settings`
--

INSERT INTO `laundry_pricing_settings` (`id`, `max_load_kg`, `full_load_price`, `min_load_price`) VALUES
(1, 7.00, 218.00, 203.00);

-- --------------------------------------------------------

--
-- Table structure for table `laundry_types`
--

CREATE TABLE `laundry_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `price_per_kg` decimal(10,2) DEFAULT NULL,
  `pricing_type` varchar(10) DEFAULT 'kg',
  `price_per_load` decimal(10,2) DEFAULT 0.00,
  `min_kg` decimal(10,2) DEFAULT 6.00,
  `max_kg` decimal(10,2) DEFAULT 999.00,
  `is_heavy` tinyint(1) DEFAULT 0,
  `min_load_price` decimal(10,2) DEFAULT 203.00,
  `min_kg_only` decimal(10,2) DEFAULT NULL,
  `max_kg_only` decimal(10,2) DEFAULT NULL,
  `min_kg_price` decimal(10,2) DEFAULT 0.00,
  `max_kg_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laundry_types`
--

INSERT INTO `laundry_types` (`id`, `type_name`, `price_per_kg`, `pricing_type`, `price_per_load`, `min_kg`, `max_kg`, `is_heavy`, `min_load_price`, `min_kg_only`, `max_kg_only`, `min_kg_price`, `max_kg_price`) VALUES
(6, 'Under Wear', 180.00, 'kg', 190.00, 6.00, 7.00, 0, 175.00, 0.00, 0.00, 0.00, 0.00),
(7, 'Clothes', 180.00, 'kg', 190.00, 6.00, 7.00, 0, 175.00, 0.00, 0.00, 0.00, 0.00),
(8, 'Comforter', 180.00, 'kg', 190.00, 4.90, 5.00, 0, 175.00, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_config`
--

CREATE TABLE `loyalty_config` (
  `id` int(11) NOT NULL,
  `config_name` varchar(100) DEFAULT NULL,
  `value` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_discounts`
--

CREATE TABLE `loyalty_discounts` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_points`
--

CREATE TABLE `loyalty_points` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_rules`
--

CREATE TABLE `loyalty_rules` (
  `id` int(11) NOT NULL,
  `washes_required` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty_rules`
--

INSERT INTO `loyalty_rules` (`id`, `washes_required`, `discount_amount`) VALUES
(3, 2, 10.00),
(14, 5, 15.00);

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_settings`
--

CREATE TABLE `loyalty_settings` (
  `id` int(11) NOT NULL,
  `required_washes` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_settings`
--

CREATE TABLE `pricing_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT NULL,
  `value` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receipt_settings`
--

CREATE TABLE `receipt_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) DEFAULT NULL,
  `price_per_kg` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `price_per_kg`, `is_active`) VALUES
(1, 'Full Service', 50.00, 0),
(2, 'Wash', 50.00, 0),
(3, 'Dry', 50.00, 0),
(4, 'Wash and Dry ', 50.00, 0),
(5, 'Wash and Dry ', 50.00, 0),
(6, 'Wash and Dry ', 50.00, 0),
(7, 'Wash and Dry ', 50.00, 0),
(8, 'Wash and Dry ', 50.00, 0),
(9, 'Wash and Dry ', 50.00, 0),
(10, 'Wash and Dry ', 50.00, 0),
(11, 'Full Service', 0.00, 0),
(12, 'Wash', 0.00, 0),
(13, 'Dry', 0.00, 1),
(14, 'Wash and Dry', 0.00, 1),
(15, 'Rush Service (same day)', 0.00, 1),
(16, 'Wash', 0.00, 0),
(17, 'Full Service', 0.00, 0),
(18, 'Full Service', 0.00, 1),
(19, 'TESTING', 100.00, 1),
(20, 'TESTING2', 150.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `service_supplies`
--

CREATE TABLE `service_supplies` (
  `id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `detergent_ml` decimal(10,2) DEFAULT NULL,
  `fabcon_ml` decimal(10,2) DEFAULT NULL,
  `bleach_ml` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplies_usage`
--

CREATE TABLE `supplies_usage` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `detergent_ml` decimal(10,2) DEFAULT NULL,
  `fabcon_ml` decimal(10,2) DEFAULT NULL,
  `bleach_ml` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tracking_code` varchar(50) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_price` decimal(10,2) DEFAULT NULL,
  `laundry_type` text DEFAULT NULL,
  `min_kg_used` decimal(10,2) DEFAULT NULL,
  `max_kg_used` decimal(10,2) DEFAULT NULL,
  `laundry_mode` varchar(100) DEFAULT NULL,
  `load_type` varchar(100) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `detergent` decimal(10,2) DEFAULT NULL,
  `fabcon` decimal(10,2) DEFAULT NULL,
  `bleach` decimal(10,2) DEFAULT NULL,
  `addons` text DEFAULT NULL,
  `extra_service` varchar(100) DEFAULT NULL,
  `extra_service_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `loyalty_discount` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `loyalty_count` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `downpayment` decimal(10,2) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT NULL,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `gcash_receipt` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `date_received` datetime DEFAULT current_timestamp(),
  `customer_id` int(11) DEFAULT NULL,
  `supplies_summary` text DEFAULT NULL,
  `delivery` varchar(20) NOT NULL DEFAULT 'Pick-up'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `addons`
--
ALTER TABLE `addons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `extra_services`
--
ALTER TABLE `extra_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_name` (`item_name`,`category`);

--
-- Indexes for table `laundry_mode`
--
ALTER TABLE `laundry_mode`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laundry_pricing_settings`
--
ALTER TABLE `laundry_pricing_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laundry_types`
--
ALTER TABLE `laundry_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_config`
--
ALTER TABLE `loyalty_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_discounts`
--
ALTER TABLE `loyalty_discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loyalty_rules`
--
ALTER TABLE `loyalty_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_washes_required` (`washes_required`);

--
-- Indexes for table `loyalty_settings`
--
ALTER TABLE `loyalty_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pricing_settings`
--
ALTER TABLE `pricing_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addons`
--
ALTER TABLE `addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `extra_services`
--
ALTER TABLE `extra_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `laundry_mode`
--
ALTER TABLE `laundry_mode`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laundry_pricing_settings`
--
ALTER TABLE `laundry_pricing_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loyalty_rules`
--
ALTER TABLE `loyalty_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
