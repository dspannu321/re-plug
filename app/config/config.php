<?php
/**
 * RePlug — Application config.
 * Loads .env and defines constants for use across the app.
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost/re-plug', '/'));

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'replug_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
