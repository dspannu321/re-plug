-- Add email verification timestamp to users table (run once if missing)
ALTER TABLE `users`
ADD COLUMN `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `role`;
