-- Safe migration: technician assignment + salvaged parts (run once on replug_db)
-- Preserves existing data and status values.

ALTER TABLE `items`
  ADD COLUMN IF NOT EXISTS `technician_user_id` INT NULL DEFAULT NULL AFTER `recycler_user_id`;

-- MariaDB 10.4 may not support IF NOT EXISTS on ADD COLUMN; ignore duplicate errors if re-run.

CREATE INDEX IF NOT EXISTS `idx_items_technician_user_id` ON `items` (`technician_user_id`);

ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_technician`
  FOREIGN KEY (`technician_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `salvaged_parts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `item_id` INT NOT NULL,
  `technician_user_id` INT NOT NULL,
  `part_name` VARCHAR(255) NOT NULL,
  `condition_notes` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending_review',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_salvaged_item` (`item_id`),
  KEY `idx_salvaged_tech` (`technician_user_id`),
  CONSTRAINT `fk_salvaged_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_salvaged_technician` FOREIGN KEY (`technician_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
