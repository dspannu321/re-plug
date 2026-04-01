<?php
/**
 * RePlug — Request a password reset link by email.
 */
session_start();

if (!empty($_SESSION['user'])) {
    require_once __DIR__ . '/app/config/db.php';
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if ($uid > 0) {
        $stmt = $pdo->prepare('SELECT email_verified_at, role FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email_verified_at'])) {
            $role = $row['role'] ?? 'user';
            if ($role === 'admin') {
                header('Location: admin.php');
            } elseif ($role === 'driver') {
                header('Location: driver.php');
            } elseif ($role === 'technician') {
                header('Location: technician.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }
    $_SESSION = [];
}

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/csrf.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/app/config/tokens.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['id']]);
                $rawToken = generate_token(24);
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);
                $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
                $ins->execute([(int) $user['id'], $tokenHash, $expiresAt]);

                $resetLink = APP_URL . '/reset_password.php?token=' . urlencode($rawToken);
                $subject = 'Reset your password — RePlug';
                $name = (string) ($user['name'] ?? '');
                $html = '<p>Hi ' . htmlspecialchars($name !== '' ? $name : 'there') . ',</p>'
                    . '<p>We received a request to reset your RePlug password. Click the button below to choose a new password. This link expires in one hour.</p>'
                    . '<p><a href="' . htmlspecialchars($resetLink) . '" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#1E88E5;color:#fff;text-decoration:none;">Reset password</a></p>'
                    . '<p>If you did not ask for this, you can ignore this email.</p>'
                    . '<p style="font-size:13px;color:#5F6C7B;">Or copy this link: ' . htmlspecialchars($resetLink) . '</p>';
                replug_send_mail($user['email'], $name, $subject, $html);
            }
        } catch (PDOException $e) {
            // Table missing or DB error — still redirect to avoid leaking details
        }
        header('Location: login.php?reset=sent');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password — RePlug</title>
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
        .header-logo span { font-size: 1.35rem; font-weight: 700; color: #1F2933; letter-spacing: -0.02em; }
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
        .header-nav .nav-link:hover { color: #268F52; background: #E8F5EE; }
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
        .header-nav .btn-primary { background: #1E88E5; color: #FFFFFF; border: none; text-decoration: none; display: inline-block; }
        .header-nav .btn-primary:hover { background: #1565C0; color: #FFFFFF; }

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
        .card h1 { font-size: 22px; font-weight: 600; color: #1F2933; margin-bottom: 0.5rem; }
        .card .subtitle { font-size: 14px; color: #5F6C7B; margin-bottom: 1.5rem; }
        .card .subtitle a { font-weight: 500; }

        .form-group { margin-bottom: 1.25rem; }
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
        .form-group input:focus { outline: none; border-color: #1E88E5; }

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
            <h1>Forgot password</h1>
            <p class="subtitle">Enter the email for your account. If it exists, we will send a reset link. <a href="login.php">Back to log in</a></p>

            <?php if ($error): ?>
                <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post" action="forgot_password.php">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn-submit">Send reset link</button>
            </form>
        </div>
    </main>
</body>
</html>
