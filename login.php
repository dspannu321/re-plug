<?php
/**
 * RePlug — Login. Redirects to dashboard on success.
 */
session_start();

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// Already logged in
if (!empty($_SESSION['user'])) {
    require_once __DIR__ . '/app/config/db.php';
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid > 0) {
        $stmt = $pdo->prepare("SELECT email_verified_at, role FROM users WHERE id = ?");
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
    // If we get here, user is not verified or not found; clear session and show login form
    $_SESSION = [];
}

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/csrf.php';

$error = '';
$info = '';
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $info = 'Check your email to verify your account, then log in.';
}
if (isset($_GET['reset']) && $_GET['reset'] === 'sent') {
    $info = 'If an account exists for that email, we have sent a link to reset your password.';
}
if (isset($_GET['reset']) && $_GET['reset'] === 'done') {
    $info = 'Your password was updated. You can log in with your new password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, email_verified_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            if (empty($user['email_verified_at'])) {
                $error = 'Please verify your email before logging in. Check your inbox for the verification link.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'    => (int) $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ];
                $role = $user['role'] ?? 'user';
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
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — RePlug</title>
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
            <h1>Log in</h1>
            <p class="subtitle">Don’t have an account? <a href="register.php">Register</a></p>

            <?php if ($info): ?>
                <p class="auth-info-msg"><?php echo htmlspecialchars($info); ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="auth-error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post" action="login.php">
                <?php echo csrf_field(); ?>
                <div class="auth-form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="auth-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
                </div>
                <button type="submit" class="auth-btn-submit">Log in</button>
            </form>
            <p class="auth-form-footer-link"><a href="forgot_password.php">Forgot password?</a></p>
        </div>
    </main>
</body>
</html>
