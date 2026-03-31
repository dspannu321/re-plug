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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #F7F9FB;
            color: #1F2933;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        a { color: #1E88E5; text-decoration: none; }
        a:hover { color: #1565C0; }

        /* Header */
        .header {
            background: #FFFFFF;
            border-bottom: 1px solid #E5E7EB;
            padding: 0 1.5rem;
        }
        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 72px;
            gap: 1rem;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            text-decoration: none;
            color: inherit;
        }
        .header-logo:hover { color: inherit; }
        .header-logo img { height: 44px; width: auto; display: block; }
        .header-logo span {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1F2933;
            letter-spacing: -0.02em;
        }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .header-nav .nav-link {
            padding: 0.5rem 1rem;
            font-size: 15px;
            font-weight: 500;
            color: #2FAE66;
            border-radius: 6px;
            transition: color 0.2s, background-color 0.2s;
        }
        .header-nav .nav-link:hover {
            color: #268F52;
            background: #E8F5EE;
        }
        .header-nav .btn {
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
            margin-left: 0.5rem;
        }
        .header-nav .btn-primary {
            background: #1E88E5;
            color: #FFFFFF;
            border: none;
        }
        .header-nav .btn-primary:hover { background: #1565C0; color: #FFFFFF; }

        /* Main */
        .main {
            flex: 1;
            padding: 2rem 1.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 2rem;
            width: 100%;
            max-width: 420px;
        }
        .card h1 {
            font-size: 22px;
            font-weight: 600;
            color: #1F2933;
            margin-bottom: 0.5rem;
        }
        .card .subtitle {
            font-size: 14px;
            color: #5F6C7B;
            margin-bottom: 1.5rem;
        }
        .card .subtitle a { font-weight: 500; }

        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1F2933;
            margin-bottom: 0.375rem;
        }
        .form-group input {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            font-size: 15px;
            font-family: inherit;
            color: #1F2933;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1E88E5;
        }
        .form-group input::placeholder {
            color: #5F6C7B;
        }
        .form-group .hint {
            font-size: 12px;
            color: #5F6C7B;
            margin-top: 0.25rem;
        }
        .btn-submit {
            width: 100%;
            height: 44px;
            margin-top: 0.5rem;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            color: #FFFFFF;
            background: #1E88E5;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-submit:hover { background: #1565C0; }
        .error-msg {
            padding: 10px 12px;
            margin-bottom: 1rem;
            font-size: 14px;
            color: #E53935;
            background: #FFEBEE;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="header-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
            </a>
            <nav class="header-nav">
                <a href="register.php" class="nav-link">Register</a>
                <a href="login.php" class="btn btn-primary">Log in</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="card">
            <h1>Create account</h1>
            <p class="subtitle">Register as a user to request pickups and shop the marketplace. <a href="login.php">Already have an account? Log in</a></p>

            <?php if ($error): ?>
                <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="post" action="register.php">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" placeholder="Your name" required autocomplete="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password" minlength="8">
                    <p class="hint">At least 8 characters.</p>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Same as above" required autocomplete="new-password" minlength="8">
                </div>
                <button type="submit" class="btn-submit">Register</button>
            </form>
        </div>
    </main>
</body>
</html>
