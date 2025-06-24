-- Agregar columna profile_picture a la tabla users
ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL AFTER `aboutme`; 