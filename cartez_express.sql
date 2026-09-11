-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql_db
-- Generation Time: Wrz 11, 2026 at 08:30 PM
-- Wersja serwera: 26.7.0
-- Wersja PHP: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `cartez_express`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `cargo_items`
--

CREATE TABLE `cargo_items` (
  `cargo_id` int NOT NULL,
  `order_id` int NOT NULL,
  `assigned_vehicle_id` int DEFAULT NULL,
  `assigned_warehouse_zone_id` int DEFAULT NULL,
  `weight_kg` decimal(10,2) NOT NULL,
  `volume_m3` decimal(6,2) NOT NULL,
  `handling_type` enum('general','hazardous','perishable','high_value') DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `companies_partner`
--

CREATE TABLE `companies_partner` (
  `partner_id` int NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `customer_orders`
--

CREATE TABLE `customer_orders` (
  `order_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `route_id` int NOT NULL,
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_price` decimal(10,2) NOT NULL,
  `order_status` enum('pending','sorted','in_transit','delivered','canceled') DEFAULT 'pending',
  `delivery_address_string` varchar(255) NOT NULL,
  `delivery_latitude` decimal(10,8) NOT NULL,
  `delivery_longitude` decimal(11,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `customer_order_history_snapshots`
--

CREATE TABLE `customer_order_history_snapshots` (
  `snapshot_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `previous_delivery_address` varchar(255) NOT NULL,
  `previous_latitude` decimal(10,8) NOT NULL,
  `previous_longitude` decimal(11,8) NOT NULL,
  `last_used_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `freight_routes`
--

CREATE TABLE `freight_routes` (
  `route_id` int NOT NULL,
  `origin_name` varchar(100) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `distance_km` decimal(10,2) NOT NULL,
  `estimated_duration_hours` decimal(6,2) NOT NULL,
  `transport_chain_type` enum('land_only','sea_land','air_land','tri_modal_intermodal') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `gps_telemetry`
--

CREATE TABLE `gps_telemetry` (
  `telemetry_id` bigint NOT NULL,
  `vehicle_id` int NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed_kmh` decimal(5,2) DEFAULT '0.00',
  `heading_degrees` decimal(5,2) DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `pallet_inventory`
--

CREATE TABLE `pallet_inventory` (
  `pallet_batch_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `pallet_type` enum('euro_wooden','standard_plastic','industrial_metal') DEFAULT 'euro_wooden',
  `condition_grade` enum('new','used_grade_a','used_grade_b') DEFAULT 'new',
  `quantity_for_sale` int DEFAULT '0',
  `price_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `route_legs`
--

CREATE TABLE `route_legs` (
  `leg_id` int NOT NULL,
  `route_id` int NOT NULL,
  `sequence_order` int NOT NULL,
  `leg_transport_type` enum('land_truck','air_cargo','water_vessel') NOT NULL,
  `departure_point` varchar(100) NOT NULL,
  `arrival_point` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int NOT NULL,
  `user_id` int NOT NULL,
  `plan_tier` enum('standard','silver_express','gold_prime') DEFAULT 'standard',
  `status` enum('active','canceled','expired') DEFAULT 'active',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','employee','admin') DEFAULT 'customer',
  `hire_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int NOT NULL,
  `vin` varchar(17) NOT NULL,
  `license_plate` varchar(20) DEFAULT NULL,
  `vehicle_type` enum('land_truck','air_cargo','water_vessel') NOT NULL,
  `model_name` varchar(50) NOT NULL,
  `max_payload_kg` decimal(10,2) NOT NULL,
  `current_mileage_km` decimal(10,2) DEFAULT '0.00',
  `is_rented_from_partner` tinyint(1) DEFAULT '0',
  `partner_id` int DEFAULT NULL,
  `status` enum('available','in_transit','maintenance','rented_by_employee') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `vehicle_rentals`
--

CREATE TABLE `vehicle_rentals` (
  `rental_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `rental_start` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rental_end` timestamp NULL DEFAULT NULL,
  `purpose` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `warehouses`
--

CREATE TABLE `warehouses` (
  `warehouse_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `city` varchar(50) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `total_pallet_capacity` int NOT NULL,
  `available_pallet_spaces` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `warehouse_zones`
--

CREATE TABLE `warehouse_zones` (
  `zone_id` int NOT NULL,
  `warehouse_id` int NOT NULL,
  `zone_code` varchar(10) NOT NULL,
  `segregation_category` enum('general','hazardous','perishable','high_value') DEFAULT 'general',
  `max_weight_capacity_kg` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `cargo_items`
--
ALTER TABLE `cargo_items`
  ADD PRIMARY KEY (`cargo_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_vehicle_id` (`assigned_vehicle_id`),
  ADD KEY `idx_cargo_zone` (`assigned_warehouse_zone_id`);

--
-- Indeksy dla tabeli `companies_partner`
--
ALTER TABLE `companies_partner`
  ADD PRIMARY KEY (`partner_id`);

--
-- Indeksy dla tabeli `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `idx_orders_customer` (`customer_id`);

--
-- Indeksy dla tabeli `customer_order_history_snapshots`
--
ALTER TABLE `customer_order_history_snapshots`
  ADD PRIMARY KEY (`snapshot_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indeksy dla tabeli `freight_routes`
--
ALTER TABLE `freight_routes`
  ADD PRIMARY KEY (`route_id`);

--
-- Indeksy dla tabeli `gps_telemetry`
--
ALTER TABLE `gps_telemetry`
  ADD PRIMARY KEY (`telemetry_id`),
  ADD KEY `idx_gps_vehicle` (`vehicle_id`,`updated_at` DESC);

--
-- Indeksy dla tabeli `pallet_inventory`
--
ALTER TABLE `pallet_inventory`
  ADD PRIMARY KEY (`pallet_batch_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indeksy dla tabeli `route_legs`
--
ALTER TABLE `route_legs`
  ADD PRIMARY KEY (`leg_id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indeksy dla tabeli `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeksy dla tabeli `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `vin` (`vin`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `partner_id` (`partner_id`);

--
-- Indeksy dla tabeli `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  ADD PRIMARY KEY (`rental_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indeksy dla tabeli `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`warehouse_id`);

--
-- Indeksy dla tabeli `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD PRIMARY KEY (`zone_id`),
  ADD UNIQUE KEY `warehouse_id` (`warehouse_id`,`zone_code`);

--
-- AUTO_INCREMENT dla zrzuconych tabel
--

--
-- AUTO_INCREMENT dla tabeli `cargo_items`
--
ALTER TABLE `cargo_items`
  MODIFY `cargo_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `companies_partner`
--
ALTER TABLE `companies_partner`
  MODIFY `partner_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `customer_order_history_snapshots`
--
ALTER TABLE `customer_order_history_snapshots`
  MODIFY `snapshot_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `freight_routes`
--
ALTER TABLE `freight_routes`
  MODIFY `route_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `gps_telemetry`
--
ALTER TABLE `gps_telemetry`
  MODIFY `telemetry_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `pallet_inventory`
--
ALTER TABLE `pallet_inventory`
  MODIFY `pallet_batch_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `route_legs`
--
ALTER TABLE `route_legs`
  MODIFY `leg_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  MODIFY `rental_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `warehouse_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  MODIFY `zone_id` int NOT NULL AUTO_INCREMENT;

--
-- Ograniczenia dla zrzutów tabel
--

--
-- Ograniczenia dla tabeli `cargo_items`
--
ALTER TABLE `cargo_items`
  ADD CONSTRAINT `cargo_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cargo_items_ibfk_2` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cargo_items_ibfk_3` FOREIGN KEY (`assigned_warehouse_zone_id`) REFERENCES `warehouse_zones` (`zone_id`) ON DELETE SET NULL;

--
-- Ograniczenia dla tabeli `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `customer_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `customer_orders_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `freight_routes` (`route_id`);

--
-- Ograniczenia dla tabeli `customer_order_history_snapshots`
--
ALTER TABLE `customer_order_history_snapshots`
  ADD CONSTRAINT `customer_order_history_snapshots_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `gps_telemetry`
--
ALTER TABLE `gps_telemetry`
  ADD CONSTRAINT `gps_telemetry_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `pallet_inventory`
--
ALTER TABLE `pallet_inventory`
  ADD CONSTRAINT `pallet_inventory_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `route_legs`
--
ALTER TABLE `route_legs`
  ADD CONSTRAINT `route_legs_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `freight_routes` (`route_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `companies_partner` (`partner_id`) ON DELETE SET NULL;

--
-- Ograniczenia dla tabeli `vehicle_rentals`
--
ALTER TABLE `vehicle_rentals`
  ADD CONSTRAINT `vehicle_rentals_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `vehicle_rentals_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);

--
-- Ograniczenia dla tabeli `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD CONSTRAINT `warehouse_zones_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`warehouse_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
