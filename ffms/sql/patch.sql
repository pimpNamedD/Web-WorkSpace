-- FFMS: Field Ledger - Schema Extension Patch
-- Adds tables for Fields, Inventory, Finances, Carbon Footprint, Token Economy, and Blockchain Traceability
USE `ffms_db`;

-- 1. Farm Fields table
CREATE TABLE IF NOT EXISTS `fields` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `size_hectares` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `soil_type` VARCHAR(100) NOT NULL DEFAULT 'Loam',
    `gps_boundary` TEXT NULL, -- JSON or coordinate string e.g. [{"lat":-15.387,"lng":28.322}]
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_fields_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Alter crops table to add digital twin fields if not present
SET @dbname = DATABASE();
SET @tablename = "crops";

SET @colname = "field_id";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  "SELECT 1",
  "ALTER TABLE crops ADD COLUMN field_id INT NULL AFTER farm_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = "yield_predicted";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  "SELECT 1",
  "ALTER TABLE crops ADD COLUMN yield_predicted DECIMAL(12,2) DEFAULT 0.00 AFTER actual_yield_kg"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = "digital_twin_data";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  "SELECT 1",
  "ALTER TABLE crops ADD COLUMN digital_twin_data LONGTEXT NULL AFTER yield_predicted"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = "ai_insights";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  "SELECT 1",
  "ALTER TABLE crops ADD COLUMN ai_insights LONGTEXT NULL AFTER digital_twin_data"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Inventory Items table (Stock Management & Alerts)
CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` ENUM('seed', 'fertilizer', 'pesticide', 'feed', 'equipment', 'veterinary', 'fuel', 'tools', 'other') NOT NULL DEFAULT 'other',
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'units',
    `low_stock_threshold` DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    `unit_cost_zmw` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `expiry_date` DATE NULL,
    `storage_location` VARCHAR(120) NULL,
    `notes` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventory_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Financial Transactions table (Double-Entry Cashflow & P&L)
CREATE TABLE IF NOT EXISTS `financial_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `description` TEXT NOT NULL,
    `transaction_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'mtn_momo', 'airtel_money', 'bank_transfer') NOT NULL DEFAULT 'cash',
    `reference_no` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_financial_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Carbon Footprints table (Automated Carbon Accounting)
CREATE TABLE IF NOT EXISTS `carbon_footprints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `source_type` ENUM('farm_audit', 'crop_cycle', 'livestock', 'equipment') NOT NULL DEFAULT 'farm_audit',
    `source_id` INT NULL,
    `scope_1_emissions` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- direct equipment diesel, livestock enteric, fertilizer N2O
    `scope_2_emissions` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- electricity, grid irrigation pumping
    `scope_3_emissions` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- supply chain inputs, transport
    `carbon_sequestration` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- crops, cover crops, agroforestry, soil carbon
    `net_footprint` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- emissions - sequestration
    `credits_earned` INT NOT NULL DEFAULT 0, -- floor(sequestration / 1000)
    `calculation_details` LONGTEXT NULL, -- JSON calculation breakdown
    `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_carbon_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Token Transactions table (Gamified Worker Rewards & Redemption)
CREATE TABLE IF NOT EXISTS `token_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `worker_id` INT NULL,
    `token_type` ENUM('reward', 'carbon', 'governance', 'redemption') NOT NULL DEFAULT 'reward',
    `amount` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `blockchain_tx_hash` VARCHAR(66) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_token_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Blockchain Records table (Farm-to-Fork Batch Traceability)
CREATE TABLE IF NOT EXISTS `blockchain_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `product_id` INT NULL,
    `product_type` ENUM('crop', 'livestock', 'dairy', 'poultry') NOT NULL DEFAULT 'crop',
    `product_name` VARCHAR(150) NOT NULL,
    `batch_code` VARCHAR(60) NOT NULL UNIQUE,
    `harvest_date` DATE NOT NULL,
    `quality_score` INT NOT NULL DEFAULT 95,
    `is_certified` TINYINT(1) NOT NULL DEFAULT 1,
    `tx_hash` VARCHAR(66) NOT NULL,
    `block_number` INT NOT NULL,
    `metadata` LONGTEXT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_blockchain_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Blockchain Chain of Custody Events
CREATE TABLE IF NOT EXISTS `blockchain_custody_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `record_id` INT NOT NULL,
    `stage` ENUM('harvest', 'quality_inspection', 'cold_storage', 'packaging', 'transport', 'retail_delivery') NOT NULL,
    `location` VARCHAR(150) NOT NULL,
    `handler_name` VARCHAR(120) NOT NULL,
    `notes` TEXT NULL,
    `tx_hash` VARCHAR(66) NOT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_custody_record` FOREIGN KEY (`record_id`) REFERENCES `blockchain_records`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA FOR NEW TABLES
-- ============================================================================

-- Seed Fields for Farm 1 and Farm 2
INSERT INTO `fields` (`id`, `farm_id`, `name`, `size_hectares`, `soil_type`, `gps_boundary`, `notes`) VALUES
(1, 1, 'North Pivot Pivot A', 50.00, 'Red Sandy Clay Loam', '[{"lat":-15.385,"lng":28.450},{"lat":-15.388,"lng":28.455},{"lat":-15.392,"lng":28.450},{"lat":-15.388,"lng":28.445}]', 'Center pivot irrigated block dedicated to summer maize and winter wheat.'),
(2, 1, 'Rainfed South Block B', 35.00, 'Sandy Clay Loam', '[{"lat":-15.394,"lng":28.450},{"lat":-15.398,"lng":28.456},{"lat":-15.402,"lng":28.451},{"lat":-15.398,"lng":28.444}]', 'Rainfed block with good organic matter and Rhizobium inoculation history.'),
(3, 1, 'East Meadow Pasture C', 65.00, 'Clay Loam', '[{"lat":-15.382,"lng":28.460},{"lat":-15.387,"lng":28.470},{"lat":-15.392,"lng":28.465},{"lat":-15.386,"lng":28.455}]', 'Fenced rotational grazing paddock with Rhodes grass and borehole water trough.'),
(4, 2, 'River Bend Section', 20.00, 'Alluvial Silt Loam', '[{"lat":-15.860,"lng":27.750},{"lat":-15.864,"lng":27.756},{"lat":-15.868,"lng":27.751},{"lat":-15.864,"lng":27.745}]', 'Kafue river frontage block with rich alluvial soils, prone to early seasonal moisture.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Update crops with field_ids and AI digital twin initial baseline data
UPDATE `crops` SET 
  `field_id` = 1,
  `yield_predicted` = 365000.00,
  `digital_twin_data` = '{"irrigation_mm": 120, "fertilizer_kg_ha": 150, "pesticide_l_ha": 15, "leaf_area_index": 4.2, "canopy_temp_c": 24.5}',
  `ai_insights` = '{"optimal_harvest_date": "2026-04-22", "water_stress": "low", "nutrient_balance": "optimal", "nitrogen_recommendation": "Maintain split Urea application before tasseling", "projected_margin_zmw": 142000.00}'
WHERE `id` = 1;

UPDATE `crops` SET 
  `field_id` = 2,
  `yield_predicted` = 89200.00,
  `digital_twin_data` = '{"irrigation_mm": 0, "fertilizer_kg_ha": 60, "pesticide_l_ha": 8, "leaf_area_index": 3.6, "canopy_temp_c": 26.1}',
  `ai_insights` = '{"optimal_harvest_date": "2026-04-12", "water_stress": "moderate", "nutrient_balance": "phosphorus_low", "nitrogen_recommendation": "Nodulation adequate; apply foliar zinc and boron booster", "projected_margin_zmw": 58000.00}'
WHERE `id` = 2;

-- Seed Inventory Items
INSERT INTO `inventory_items` (`id`, `farm_id`, `name`, `category`, `quantity`, `unit`, `low_stock_threshold`, `unit_cost_zmw`, `expiry_date`, `storage_location`, `notes`) VALUES
(1, 1, 'D-Compound Fertilizer', 'fertilizer', 85.00, '50kg bags', 20.00, 820.00, '2028-12-31', 'Main Fertilizer Shed A', 'Basal dressing fertilizer (10-20-10) for maize and soya.'),
(2, 1, 'Urea Top Dressing (46% N)', 'fertilizer', 14.00, '50kg bags', 25.00, 890.00, '2028-12-31', 'Main Fertilizer Shed A', 'LOW STOCK ALERT: Needs restocking before maize tasseling stage.'),
(3, 1, 'Seed Co SC647 Hybrid Maize Seed', 'seed', 18.00, '25kg pockets', 10.00, 950.00, '2027-06-30', 'Cold Seed Store B', 'Certified treated hybrid maize seed.'),
(4, 1, 'Boran Plunge Dip (Triatix Pour-on)', 'veterinary', 3.00, '5L canisters', 5.00, 1450.00, '2027-10-15', 'Vet Cabinet Chem Room', 'CRITICAL STOCK: Plunge dip running low for weekly cattle dipping.'),
(5, 1, 'Broiler Finisher Mash', 'feed', 60.00, '50kg bags', 15.00, 490.00, '2026-12-15', 'Poultry Feed Depot', 'Tiger animal feeds finisher pellet mash.'),
(6, 1, 'Diesel Fuel (Bulk Farm Storage)', 'fuel', 1250.00, 'Liters', 300.00, 31.50, NULL, 'Bunker Tank 1', 'Farm machinery and generator diesel.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Financial Transactions (Income and Expenses for P&L)
INSERT INTO `financial_transactions` (`id`, `farm_id`, `type`, `category`, `amount`, `description`, `transaction_date`, `payment_method`, `reference_no`) VALUES
(1, 1, 'income', 'Crop Sales', 438600.00, 'Sale of 292.4 metric tonnes Winter Wheat to National Milling Lusaka depot', '2026-10-04', 'bank_transfer', 'NML-STK-2026-904'),
(2, 1, 'expense', 'Input Purchases', 49200.00, '60 bags D-Compound Basal Fertilizer from Omnia Fertilizer depot', '2026-09-02', 'mtn_momo', 'MTN-ZM-MOMO-8839214'),
(3, 1, 'expense', 'Input Purchases', 11400.00, '12 pockets SC647 Hybrid Maize Seed from Seed Co Lusaka', '2026-09-05', 'airtel_money', 'AIRTEL-ZM-PAY-449120'),
(4, 1, 'income', 'Livestock Sales', 135000.00, 'Sale of 9 cull Boran steers to Zambeef Huntley Abattoir Chisamba', '2026-09-07', 'bank_transfer', 'ZBF-ABT-77192'),
(5, 1, 'expense', 'Labor & Wages', 22500.00, 'Monthly farm crew wages and tractor driver allowances', '2026-08-31', 'bank_transfer', 'PAY-AUG-2026-MAV'),
(6, 1, 'income', 'Carbon Credits', 18200.00, 'Monetization of 14 verified voluntary carbon credit tokens to AgriCarbon Fund', '2026-09-11', 'bank_transfer', 'TX-CARBON-99120')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Seed Carbon Footprints
INSERT INTO `carbon_footprints` (`id`, `farm_id`, `source_type`, `scope_1_emissions`, `scope_2_emissions`, `scope_3_emissions`, `carbon_sequestration`, `net_footprint`, `credits_earned`, `calculation_details`, `calculated_at`) VALUES
(1, 1, 'farm_audit', 24500.00, 7200.00, 4800.00, 52100.00, -15600.00, 52, '{"diesel_liters": 4200, "livestock_head": 46, "fertilizer_n_kg": 4500, "soil_sequestration_ha": 85, "agroforestry_trees": 450, "calculation_model": "IPCC Tier 2 African Agro-Ecological Zone"}', '2026-09-01 10:00:00'),
(2, 1, 'crop_cycle', 11200.00, 4500.00, 2100.00, 29400.00, -11600.00, 29, '{"crop_name": "Winter Wheat (Irrigated)", "cover_crop_benefit": "Rhodes grass rotation", "tillage": "Minimum tillage", "net_carbon_sink": true}', '2026-09-15 14:30:00')
ON DUPLICATE KEY UPDATE `credits_earned` = VALUES(`credits_earned`);

-- Seed Token Transactions (Worker Rewards & Eco-Gamification)
INSERT INTO `token_transactions` (`id`, `farm_id`, `user_id`, `worker_id`, `token_type`, `amount`, `reason`, `blockchain_tx_hash`, `created_at`) VALUES
(1, 1, 1, 1, 'reward', 50, 'Preventative maintenance on North Pivot gearbox avoiding breakdown during dry spell', '0x7f9a1b2c3d4e5f60718293a4b5c6d7e8f90123456789abcdef0123456789abcd', '2026-08-15 08:30:00'),
(2, 1, 1, 1, 'carbon', 30, 'Precision variable-rate fertilizer application reducing runoff and N2O emissions', '0x1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f809', '2026-08-30 11:20:00'),
(3, 1, 1, 2, 'reward', 40, 'Calf delivery assistance at night; 100% calf survival rate this breeding cycle', '0x8899aabbccddeeff00112233445566778899aabbccddeeff0011223344556677', '2026-09-02 06:15:00'),
(4, 1, 1, 3, 'reward', 25, 'Zero fuel spill and clean tractor maintenance checklist completion', '0x9900112233445566778899aabbccddeeff00112233445566778899aabbccddee', '2026-09-08 16:45:00'),
(5, 1, 1, 1, 'redemption', -40, 'Redeemed 40 tokens for Heavy Duty PVC Rain Boots and Safety Gloves at Farm Depot', '0xdeadbeef11223344556677889900aabbccddeeff00112233445566778899aabb', '2026-09-12 14:10:00')
ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`);

-- Seed Blockchain Records (Traceability Batch Certificates)
INSERT INTO `blockchain_records` (`id`, `farm_id`, `product_id`, `product_type`, `product_name`, `batch_code`, `harvest_date`, `quality_score`, `is_certified`, `tx_hash`, `block_number`, `metadata`, `recorded_at`) VALUES
(1, 1, 3, 'crop', 'Winter Wheat (Grade A Hard Red)', 'BATCH-ZM-2026-WHT-01', '2026-10-02', 98, 1, '0x4a9b8c7d6e5f40312a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a1b0c9d', 19842105, '{"moisture_pct": 10.4, "protein_pct": 13.8, "aflatoxin_ppb": 0.0, "pesticide_residue": "None detected", "origin_field": "North Pivot Block C", "coordinates": "-15.385, 28.450"}', '2026-10-03 09:00:00'),
(2, 1, 1, 'crop', 'White Maize (Seed Co SC647)', 'BATCH-ZM-2026-MZ-04', '2026-04-20', 95, 1, '0x6e5f4a3b2c1d0f9e8d7c6b5a4f3e2d1c0b9a8f7e6d5c4b3a2f1e0d9c8b7a6f5e', 19845210, '{"target_market": "Commercial Milling", "storage_type": "Grain Silo A", "origin_field": "Block A North Pivot", "coordinates": "-15.385, 28.450"}', '2026-09-10 11:30:00'),
(3, 1, 1, 'livestock', 'Boran Beef Steers (Pasture Raised)', 'BATCH-ZM-2026-BOF-09', '2026-09-07', 96, 1, '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', 19839912, '{"breed": "Purebred Boran", "feeding_regime": "100% Pasture grass with natural mineral licks", "withdrawal_period_observed": true, "traceable_ear_tags": ["TAG-BOR-2024/01-45", "TAG-BOR-2024/02-12"]}', '2026-09-07 14:00:00')
ON DUPLICATE KEY UPDATE `batch_code` = VALUES(`batch_code`);

-- Seed Blockchain Custody Events
INSERT INTO `blockchain_custody_events` (`id`, `record_id`, `stage`, `location`, `handler_name`, `notes`, `tx_hash`, `recorded_at`) VALUES
(1, 1, 'harvest', 'Maverick Plains Estate - Block C', 'Bwalya Phiri (Combine Operator)', 'Harvested with John Deere S670 combine at 10.4% moisture content.', '0x4a9b8c7d6e5f40312a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a1b0c9d', '2026-10-02 16:30:00'),
(2, 1, 'quality_inspection', 'Lusaka Grain Quality Lab Station', 'Dr. Mutale Banda (Chief Agronomist)', 'Certified Grade A Premium milling wheat. Zero mycotoxin or foreign matter.', '0x5b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a1b0c9d8e7f6a5b4c3d2e1f0a', '2026-10-03 10:15:00'),
(3, 1, 'cold_storage', 'Chongwe Grain Silo 4', 'Kondwani Zulu (Warehouse Lead)', 'Stored in aerated steel silo at 18 degrees Celsius.', '0x6c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a1b', '2026-10-03 15:45:00'),
(4, 1, 'transport', 'T1 Great East Road Transit', 'Cargo Trans Zambia (Truck ABX 4821)', 'Bulk transit in sealed food-grade tarp truck under GPS tracking.', '0x7d2e1f0a9b8c7d6e5f40312a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a', '2026-10-04 07:00:00'),
(5, 1, 'retail_delivery', 'National Milling Lusaka Depot', 'Depot Inward Receiving Team', 'Delivered and accepted. 292.4 tonnes weighed on certified bridge scale.', '0x8e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f40312a1b0c9d8e7f6a5b4c3d', '2026-10-04 11:30:00')
ON DUPLICATE KEY UPDATE `stage` = VALUES(`stage`);
