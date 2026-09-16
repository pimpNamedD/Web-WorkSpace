-- FFMS: Field Ledger Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ on XAMPP and free-tier hosts

CREATE DATABASE IF NOT EXISTS `ffms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ffms_db`;

-- 1. Users table (Farmers / Farm Managers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `location_district` VARCHAR(100) DEFAULT 'Lusaka',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Farms table
CREATE TABLE IF NOT EXISTS `farms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `farm_name` VARCHAR(150) NOT NULL,
    `location` VARCHAR(150) NOT NULL,
    `weather_city` VARCHAR(100) NULL,
    `size_hectares` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `farm_type` ENUM('crop', 'livestock', 'mixed') NOT NULL DEFAULT 'mixed',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_farms_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crops table
CREATE TABLE IF NOT EXISTS `crops` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `crop_name` VARCHAR(100) NOT NULL,
    `field_name` VARCHAR(100) NULL,
    `area_hectares` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `planting_date` DATE NULL,
    `expected_harvest_date` DATE NULL,
    `actual_harvest_date` DATE NULL,
    `status` ENUM('planned', 'growing', 'harvested', 'failed') NOT NULL DEFAULT 'planned',
    `expected_yield_kg` DECIMAL(12,2) DEFAULT 0.00,
    `actual_yield_kg` DECIMAL(12,2) DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_crops_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Livestock table
CREATE TABLE IF NOT EXISTS `livestock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `animal_type` VARCHAR(100) NOT NULL,
    `tag_id` VARCHAR(60) NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `acquisition_date` DATE NULL,
    `health_status` ENUM('healthy', 'sick', 'under_treatment', 'deceased') NOT NULL DEFAULT 'healthy',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_livestock_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Activity Log table
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `activity_type` ENUM('planting', 'harvest', 'feeding', 'treatment', 'irrigation', 'scouting', 'note') NOT NULL DEFAULT 'note',
    `description` TEXT NOT NULL,
    `activity_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Weather Cache table (30-minute TTL for OpenWeatherMap free tier)
CREATE TABLE IF NOT EXISTS `weather_cache` (
    `farm_id` INT PRIMARY KEY,
    `temp_c` DECIMAL(5,2) NULL,
    `feels_like_c` DECIMAL(5,2) NULL,
    `description` VARCHAR(120) NULL,
    `humidity` INT NULL,
    `wind_speed` DECIMAL(5,2) NULL,
    `rain_1h` DECIMAL(5,2) DEFAULT 0.00,
    `city_query` VARCHAR(100) NULL,
    `fetched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_weather_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Input Purchases table (MTN MoMo & Airtel Money sandbox integration)
CREATE TABLE IF NOT EXISTS `input_purchases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `category` ENUM('fertilizer', 'seed', 'chemicals', 'feed', 'equipment', 'fuel', 'labor', 'other') NOT NULL DEFAULT 'other',
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'units',
    `cost_zmw` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `purchase_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'mtn_momo', 'airtel_money') NOT NULL DEFAULT 'cash',
    `phone_number` VARCHAR(30) NULL,
    `payment_status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed',
    `provider_ref` VARCHAR(120) NULL,
    `provider_response` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inputs_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Workers table (Recognition & Points system)
CREATE TABLE IF NOT EXISTS `workers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `farm_id` INT NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `role` VARCHAR(100) NOT NULL DEFAULT 'General Hand',
    `phone` VARCHAR(30) NULL,
    `points_balance` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_workers_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Worker Points Log table (Full non-monetary recognition audit trail)
CREATE TABLE IF NOT EXISTS `worker_points_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `worker_id` INT NOT NULL,
    `points_delta` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `log_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_points_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Community Posts table (Global Peer Forum)
CREATE TABLE IF NOT EXISTS `community_posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category` ENUM('crops', 'livestock', 'equipment', 'market_prices', 'pests_disease', 'general') NOT NULL DEFAULT 'general',
    `title` VARCHAR(200) NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_community_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Community Replies table
CREATE TABLE IF NOT EXISTS `community_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `reply_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_replies_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA (Zambian Agricultural Demonstration Context)
-- ============================================================================

-- Primary Demo User (Password is: password123)
-- Hash generated via password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `location_district`)
VALUES 
(1, 'Dalitso Mwansa', 'dalitso@fieldledger.zm', '+260 977 672770', '$2y$10$tZ2E71sM8lP8Ksp1z1y0wOiP6aX7a39CkyV6Bf/vBqPZzWjSsqgqm', 'Lusaka'),
(2, 'Chileshe Bwalya', 'chileshe@fieldledger.zm', '+260 966 123456', '$2y$10$tZ2E71sM8lP8Ksp1z1y0wOiP6aX7a39CkyV6Bf/vBqPZzWjSsqgqm', 'Mkushi')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `location_district` = VALUES(`location_district`);

-- Demo Farms
INSERT INTO `farms` (`id`, `user_id`, `farm_name`, `location`, `weather_city`, `size_hectares`, `farm_type`, `notes`)
VALUES
(1, 1, 'Maverick Plains Estate', 'Lusaka East, Chongwe District', 'Lusaka', 200.00, 'mixed', 'Commercial mixed farming holding with irrigated winter wheat and rainy season maize & soya, plus breeding Boran beef herd.'),
(2, 1, 'Kafue Basin Outgrower Plot', 'Mazabuka, Southern Province', 'Mazabuka', 45.50, 'crop', 'River-adjacent smallholder crop block producing sweet corn, sugar beans, and seed sunflower.'),
(3, 2, 'Mkushi Golden Ridge', 'Mkushi Farm Block, Central Province', 'Mkushi', 320.00, 'mixed', 'Large commercial grain and cattle enterprise in Mkushi.')
ON DUPLICATE KEY UPDATE `farm_name` = VALUES(`farm_name`), `weather_city` = VALUES(`weather_city`);

-- Demo Crops
INSERT INTO `crops` (`id`, `farm_id`, `crop_name`, `field_name`, `area_hectares`, `planting_date`, `expected_harvest_date`, `actual_harvest_date`, `status`, `expected_yield_kg`, `actual_yield_kg`, `notes`)
VALUES
(1, 1, 'White Maize (Seed Co SC647)', 'Block A - North Pivot', 50.00, '2026-11-15', '2026-04-20', NULL, 'growing', 350000.00, 0.00, 'Targeting 7 tonnes per hectare with basal D-Compound and two split Urea applications.'),
(2, 1, 'Soya Beans (MRI Dina)', 'Block B - Rainfed South', 35.00, '2026-12-01', '2026-04-10', NULL, 'growing', 87500.00, 0.00, 'Inoculated with Rhizobium at planting; good nodulation observed during flowering.'),
(3, 1, 'Winter Wheat', 'Block C - Center Pivot', 40.00, '2026-05-10', '2026-09-30', '2026-10-02', 'harvested', 280000.00, 292400.00, 'Exceptional season. Average yield 7.31 t/ha delivered to National Milling Lusaka depot.'),
(4, 2, 'Sunflower (Pannar PAN 7033)', 'River Bend Section', 20.00, '2026-12-10', '2026-04-25', NULL, 'growing', 32000.00, 0.00, 'Planted with minimum tillage; pest scouting active for cutworms.'),
(5, 2, 'Sugar Beans (Kabulangeti)', 'Plot 4 Lower Meadow', 10.00, '2026-01-15', '2026-04-30', NULL, 'planned', 14000.00, 0.00, 'Soil prep complete; awaiting moisture window.')
ON DUPLICATE KEY UPDATE `crop_name` = VALUES(`crop_name`);

-- Demo Livestock
INSERT INTO `livestock` (`id`, `farm_id`, `animal_type`, `tag_id`, `quantity`, `acquisition_date`, `health_status`, `notes`)
VALUES
(1, 1, 'Boran Beef Cattle (Breeding Cows)', 'TAG-BOR-2024/01-45', 45, '2024-03-15', 'healthy', 'Purebred Boran brood cows. Rotationally grazed on Rhodes grass pastures.'),
(2, 1, 'Boran Stud Bull', 'TAG-BULL-09', 1, '2023-08-20', 'healthy', 'Registered stud sire, vaccinated against Anthrax and Quarter Evil.'),
(3, 1, 'Boer Goats (Does & Kids)', 'TAG-BOER-KAFUE-20', 28, '2025-05-10', 'healthy', 'Kid crop thriving; dewormed with Albendazole last month.'),
(4, 1, 'Broiler Chickens (Cobb 500)', 'BATCH-BR-2026-04', 500, '2026-08-25', 'under_treatment', 'Day 22. Mild respiratory rattle observed in House 2; oxytetracycline administered in drinking water.'),
(5, 2, 'Dorper Sheep Flock', 'TAG-DORPER-MBZ', 18, '2025-09-12', 'healthy', 'Grazing river levee grass; hooves trimmed and dipped.')
ON DUPLICATE KEY UPDATE `animal_type` = VALUES(`animal_type`);

-- Demo Activity Log
INSERT INTO `activity_log` (`id`, `farm_id`, `activity_type`, `description`, `activity_date`)
VALUES
(1, 1, 'irrigation', 'Started North Pivot irrigation cycle on Block A maize; applied 25mm over 18 hours.', '2026-09-10'),
(2, 1, 'treatment', 'Routine plunge dip of Boran cattle herd using Triatix pour-on against bout ticks.', '2026-09-12'),
(3, 1, 'feeding', 'Distributed 15 bags of broiler finisher mash to Poultry Unit 1 and 2.', '2026-09-14'),
(4, 1, 'scouting', 'Field scouting Block B soya beans: zero fall armyworm detected; leaf canopy clean.', '2026-09-15'),
(5, 2, 'planting', 'Completed harrowing and ridging on Sugar Beans plot; soil moisture favorable.', '2026-09-14')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Demo Input Purchases (With Cash, MTN MoMo, Airtel Money records)
INSERT INTO `input_purchases` (`id`, `farm_id`, `item_name`, `category`, `quantity`, `unit`, `cost_zmw`, `purchase_date`, `payment_method`, `phone_number`, `payment_status`, `provider_ref`, `provider_response`, `notes`)
VALUES
(1, 1, 'D-Compound Basal Fertilizer (50kg)', 'fertilizer', 60.00, '50kg bags', 49200.00, '2026-09-02', 'mtn_momo', '0966778899', 'completed', 'MTN-ZM-MOMO-8839214', 'Sandbox Payment Approved. Financial Transaction ID: 9021882', 'Purchased from Omnia Fertilizer depot Lusaka for summer planting.'),
(2, 1, 'Seed Co SC647 Hybrid Maize Seed', 'seed', 12.00, '25kg pockets', 11400.00, '2026-09-05', 'airtel_money', '0977112233', 'completed', 'AIRTEL-ZM-PAY-449120', 'Sandbox UAT Approved. TxnRef: AT-991203', 'Certified seed pockets with fungicidal dressing.'),
(3, 1, 'Veterinary Dewormer (Albendazole 10%)', 'chemicals', 5.00, '1L bottles', 1750.00, '2026-09-08', 'cash', NULL, 'completed', 'CASH-REC-0089', 'Paid in cash at AgroVet Chisamba.', 'Routine spring herd protocol.'),
(4, 1, 'Broiler Starter Mash (Tiger Animal Feeds)', 'feed', 20.00, '50kg bags', 9800.00, '2026-09-12', 'mtn_momo', '0966778899', 'completed', 'MTN-ZM-MOMO-9938102', 'Sandbox Payment Approved. Financial Transaction ID: 9022019', 'Delivered to farm stores by Tiger Lusaka branch.'),
(5, 2, 'Sunflower Seed (Pannar 7033)', 'seed', 6.00, '5kg bags', 2850.00, '2026-09-10', 'airtel_money', '0977554433', 'completed', 'AIRTEL-ZM-PAY-551029', 'Sandbox UAT Approved. TxnRef: AT-991340', 'Seed for Mazabuka river bend block.')
ON DUPLICATE KEY UPDATE `item_name` = VALUES(`item_name`);

-- Demo Workers & Points
INSERT INTO `workers` (`id`, `farm_id`, `name`, `role`, `phone`, `points_balance`, `is_active`)
VALUES
(1, 1, 'Musonda Kapembwa', 'Farm Foreman & Pivot Tech', '+260 971 200100', 380, 1),
(2, 1, 'Mulenga Tembo', 'Senior Herdsman', '+260 962 300200', 290, 1),
(3, 1, 'Bwalya Phiri', 'Tractor Operator', '+260 978 400300', 220, 1),
(4, 1, 'Chanda Lungu', 'Poultry Lead Hand', '+260 965 500400', 175, 1),
(5, 2, 'Kondwani Banda', 'Field Supervisor', '+260 976 600500', 140, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Demo Worker Points Audit Log
INSERT INTO `worker_points_log` (`id`, `worker_id`, `points_delta`, `reason`, `log_date`)
VALUES
(1, 1, 50, 'Preventative maintenance on North Pivot gearbox avoiding breakdown during dry spell', '2026-08-15'),
(2, 1, 30, 'Perfect weekly irrigation logging and water conservation score', '2026-08-30'),
(3, 2, 40, 'Calf delivery assistance at night; 100% calf survival rate this cycle', '2026-09-02'),
(4, 3, 25, 'Zero fuel spill and clean tractor maintenance checklist completion', '2026-09-08'),
(5, 4, 35, 'Early detection and isolation of flock coughing symptoms in House 2', '2026-09-13')
ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`);

-- Demo Community Posts
INSERT INTO `community_posts` (`id`, `user_id`, `category`, `title`, `body`)
VALUES
(1, 1, 'crops', 'Early planting dates for Maize in Lusaka/Central: Waiting for 25mm rain vs dry planting?', 'Fellow farmers, with the latest meteorological advisory for the upcoming season, are you planning to dry-plant your maize or wait strictly for the first consistent 25-30mm rainfall event? Last year early dry planting caught an armyworm wave in late November. What is your strategy this year?'),
(2, 2, 'market_prices', 'Soya Bean Farm-gate prices around Mkushi and Kapiri Mposhi', 'Current quotes from commercial off-takers are ranging between K7.50 to K8.80 per kg depending on moisture content (<11%). Anyone getting better contracts directly with crushers in Lusaka or Copperbelt?'),
(3, 1, 'livestock', 'Preventing Heartwater and Bout Tick resistance in cattle during wet season', 'We rotate our dip chemicals between organophosphates and synthetic pyrethroids every 6 months to avoid tick resistance. How often do you strip your plunge dips, and what protocols do you follow during the peak rainy season?')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Demo Community Replies
INSERT INTO `community_replies` (`id`, `post_id`, `user_id`, `reply_text`)
VALUES
(1, 1, 2, 'In Mkushi we strongly recommend waiting for at least 35mm cumulative moisture before placing SC647 or Pioneer seed. Dry planting risks patchy germination if the first showers are followed by a 10-day dry spell.'),
(2, 2, 1, 'Mt Meru and Seba Foods were offering closer to K9.10/kg for delivered bulk lots (>30 tonnes) with clean grade A certification last week. Worth consolidating transport if you have neighbors with tonnage.'),
(3, 3, 2, 'Great point on dip rotation. In Central Province we also do hand-dressing with grease around ears and tail-switch weekly during peak bont tick season. Keeps tick-borne gall sickness down significantly.')
ON DUPLICATE KEY UPDATE `reply_text` = VALUES(`reply_text`);
