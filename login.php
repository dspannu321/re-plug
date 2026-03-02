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
    $role = $_SESSION['user']['role'] ?? 'user';
    header('Location: ' . ($role === 'admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}

require_once __DIR__ . '/app/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            $role = $user['role'] ?? 'user';
            header('Location: ' . ($role === 'admin' ? 'admin.php' : 'dashboard.php'));
            exit;
        }
        $error = 'Invalid email or password.';
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
        .header-nav .btn-primary { background: #1E88E5; color: #FFFFFF; border: none; }
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
        .form-group input::placeholder { color: #5F6C7B; }

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
            <h1>Log in</h1>
            <p class="subtitle">Don’t have an account? <a href="register.php">Register</a></p>

            <?php if ($error): ?>
                <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Your password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit">Log in</button>
            </form>
        </div>
    </main>
</body>
</html>
