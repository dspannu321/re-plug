-- Add avatar column to users (run once)
ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) NULL DEFAULT NULL AFTER `password`;
