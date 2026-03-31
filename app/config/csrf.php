<?php
// RePlug — CSRF helper

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_valid_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['csrf_token'] ?? '';
    $valid = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($valid) || $sent === '' || !hash_equals($valid, $sent)) {
        http_response_code(400);
        die('Invalid or missing CSRF token. Please go back and reload the page.');
    }
}

