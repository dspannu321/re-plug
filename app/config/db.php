<?php
/**
 * RePlug — PDO database connection.
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    if (APP_ENV === 'local') {
        die('Database connection failed: ' . $e->getMessage());
    }
    die('Database connection failed. Please try again later.');
}
