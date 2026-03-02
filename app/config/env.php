<?php
/**
 * Load .env file into $_ENV (and putenv for getenv()).
 * .env should be in project root (parent of app/).
 */
$envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envFile)) {
    return;
}
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);
    if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }
    $_ENV[$name] = $value;
    putenv("$name=$value");
}
