<?php
/**
 * RePlug — Register (user account only).
 * Users can request pickups and purchase from marketplace.
 * Admin, Technician, Driver are created manually by depot admin.
 */
session_start();
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/csrf.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/app/config/tokens.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $error = 'Please enter your name.';
    } elseif ($email === '') {
        $error = 'Please enter your email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
            $stmt->execute([$name, $email, $password_hash]);
            $userId = (int) $pdo->lastInsertId();

            // Create email verification token (24h expiry)
            $token = generate_token(16);
            $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24);
            $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $token, $expiresAt]);

            // Send verification email
            $verifyLink = APP_URL . '/verify_email.php?token=' . urlencode($token);
            $subject = 'Verify your email — RePlug';
            $html = '<p>Hi ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Thanks for registering with RePlug. Please verify your email by clicking the button below:</p>'
                  . '<p><a href="' . htmlspecialchars($verifyLink) . '" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#1E88E5;color:#fff;text-decoration:none;">Verify email</a></p>'
                  . '<p>Or open this link: ' . htmlspecialchars($verifyLink) . '</p>';
            replug_send_mail($email, $name, $subject, $html);

            // Do NOT log the user in yet; require email verification first.
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — RePlug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/assets/css/auth.css">
</head>
<body class="auth-page">
    <header class="auth-header">
        <div class="auth-header-inner">
            <a href="index.php" class="auth-header-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
            </a>
            <nav class="auth-header-nav">
                <a href="register.php" class="nav-link">Register</a>
                <a href="login.php" class="btn btn-primary">Log in</a>
            </nav>
        </div>
    </header>

    <main class="auth-main">
        <div class="auth-card">
            <h1>Create account</h1>
            <p class="subtitle">Register as a user to request pickups and shop the marketplace. <a href="login.php">Already have an account? Log in</a></p>

            <?php if ($error): ?>
                <p class="auth-error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="post" action="register.php">
                <?php echo csrf_field(); ?>
                <div class="auth-form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" placeholder="Your name" required autocomplete="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="auth-form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="auth-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password" minlength="8">
                    <p class="hint">At least 8 characters.</p>
                </div>
                <div class="auth-form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Same as above" required autocomplete="new-password" minlength="8">
                </div>
                <button type="submit" class="auth-btn-submit">Register</button>
            </form>
        </div>
    </main>
</body>
</html>
