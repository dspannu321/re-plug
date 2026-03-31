-- Payouts table for recycler revenue share (run once)
CREATE TABLE IF NOT EXISTS `payouts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `recycler_user_id` INT NOT NULL,
  `order_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'unpaid',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_order` (`order_id`),
  INDEX `idx_payout_recycler` (`recycler_user_id`),
  CONSTRAINT `fk_payout_recycler` FOREIGN KEY (`recycler_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payout_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
);
