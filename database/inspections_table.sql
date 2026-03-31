-- Inspections table (run once)
CREATE TABLE IF NOT EXISTS `inspections` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `item_id` INT NOT NULL,
  `technician_user_id` INT NOT NULL,
  `result` VARCHAR(32) NOT NULL,
  `notes` TEXT NULL,
  `estimated_repair_cost` DECIMAL(10,2) NULL,
  `status_after` VARCHAR(32) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_inspections_item` (`item_id`),
  INDEX `idx_inspections_technician` (`technician_user_id`),
  CONSTRAINT `fk_inspections_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspections_technician` FOREIGN KEY (`technician_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
