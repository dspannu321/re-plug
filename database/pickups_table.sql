-- Pickups and pickup_items (run after items table exists)
CREATE TABLE IF NOT EXISTS `pickups` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `recycler_user_id` INT NOT NULL,
  `driver_user_id` INT NULL DEFAULT NULL,
  `pickup_window_start` DATETIME NOT NULL,
  `pickup_window_end` DATETIME NOT NULL,
  `address_text` TEXT NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'requested',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pickups_recycler` (`recycler_user_id`),
  INDEX `idx_pickups_driver` (`driver_user_id`),
  CONSTRAINT `fk_pickups_recycler` FOREIGN KEY (`recycler_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pickups_driver` FOREIGN KEY (`driver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `pickup_items` (
  `pickup_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  PRIMARY KEY (`pickup_id`, `item_id`),
  CONSTRAINT `fk_pickup_items_pickup` FOREIGN KEY (`pickup_id`) REFERENCES `pickups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pickup_items_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
);
