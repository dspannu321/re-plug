-- Audit logs table (run once)
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT NOT NULL,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_id` INT NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `meta_json` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_actor` (`actor_user_id`),
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
