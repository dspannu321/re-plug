<?php
/**
 * RePlug — Set a new password using a one-time link from email.
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

$error = '';
$tokenFromUrl = trim($_GET['token'] ?? '');
$tokenFromPost = trim($_POST['token'] ?? '');
$activeToken = $tokenFromUrl !== '' ? $tokenFromUrl : $tokenFromPost;

$resetRow = null;
if ($activeToken !== '') {
    try {
        $hash = hash('sha256', $activeToken);
        $stmt = $pdo->prepare(
            'SELECT pr.id, pr.user_id, pr.expires_at
            FROM password_resets pr
            WHERE pr.token_hash = ?
            LIMIT 1'
        );
        $stmt->execute([$hash]);
        $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($resetRow && strtotime($resetRow['expires_at']) < time()) {
            $resetRow = null;
            $error = 'This reset link has expired. Please request a new one.';
        }
    } catch (PDOException $e) {
        $error = 'Password reset is not available. Please contact support.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeToken === '') {
    $error = 'Invalid reset link.';
}

if ($activeToken !== '' && !$resetRow && $error === '') {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && $resetRow) {
    require_valid_csrf();
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, (int) $resetRow['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $resetRow['user_id']]);
            $pdo->commit();
            header('Location: login.php?reset=done');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not update password. Please try again.';
        }
    }
}

$showForm = (bool) $resetRow;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password — RePlug</title>
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
            <h1>Reset password</h1>
            <?php if ($showForm): ?>
                <p class="subtitle">Choose a new password for your account.</p>
                <?php if ($error): ?>
                    <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <form method="post" action="reset_password.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($activeToken); ?>">
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm new password</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-submit">Save new password</button>
                </form>
            <?php else: ?>
                <p class="subtitle"><?php echo htmlspecialchars($error !== '' ? $error : 'Something went wrong.'); ?></p>
                <p class="subtitle"><a href="forgot_password.php">Request a new link</a> or <a href="login.php">log in</a>.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
