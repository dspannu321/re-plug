-- Orders table for marketplace purchases (run once)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `buyer_user_id` INT NOT NULL,
  `listing_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'paid',
  `stripe_session_id` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_stripe_session` (`stripe_session_id`),
  INDEX `idx_orders_buyer` (`buyer_user_id`),
  INDEX `idx_orders_listing` (`listing_id`),
  CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_listing` FOREIGN KEY (`listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
);
