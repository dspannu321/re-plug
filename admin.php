<?php
/**
 * RePlug — Admin dashboard: Pickups, Users, Marketplace, Inventory, Profile.
 */
session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/app/config/db.php';

$userId = (int) $_SESSION['user']['id'];
$user = $_SESSION['user'];

// Load fresh user row (for avatar)
$stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($userRow) {
    $user['avatar'] = $userRow['avatar'] ?? null;
}

$section = isset($_GET['section']) ? $_GET['section'] : 'pickups';
$validSections = ['pickups', 'users', 'marketplace', 'inventory', 'profile'];
if (!in_array($section, $validSections, true)) {
    $section = 'pickups';
}

$uploadDir = __DIR__ . '/public/storage/uploads';
$avatarsDir = $uploadDir . '/avatars';
$profileMsg = '';
$profileError = '';

// ---------- Profile: avatar upload ----------
if ($section === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_avatar'])) {
    if (!is_dir($avatarsDir)) {
        @mkdir($avatarsDir, 0755, true);
    }
    if (!empty($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['avatar']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $profileError = 'Please upload a JPEG, PNG, GIF or WebP image.';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $profileError = 'Image must be under 2 MB.';
        } else {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
            $filename = bin2hex(random_bytes(8)) . '.' . strtolower($ext);
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarsDir . '/' . $filename)) {
                $avatarPath = 'avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatarPath, $userId]);
                $user['avatar'] = $avatarPath;
                $profileMsg = 'Avatar updated.';
            } else {
                $profileError = 'Could not save upload.';
            }
        }
    } else {
        $profileError = 'Please choose an image file.';
    }
}

// ---------- Profile: change password ----------
if ($section === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirm'] ?? '';
    if ($current === '' || $new === '' || $confirm === '') {
        $profileError = ($profileError ? $profileError . ' ' : '') . 'Fill all password fields.';
    } elseif (strlen($new) < 8) {
        $profileError = ($profileError ? $profileError . ' ' : '') . 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $profileError = ($profileError ? $profileError . ' ' : '') . 'New passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($current, $row['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            $profileMsg = $profileMsg ? $profileMsg . ' Password updated.' : 'Password updated.';
        } else {
            $profileError = ($profileError ? $profileError . ' ' : '') . 'Current password is wrong.';
        }
    }
}

$avatarUrl = null;
if (!empty($user['avatar'])) {
    $avatarUrl = 'public/storage/uploads/' . $user['avatar'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — RePlug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #EEF1F5;
            color: #1F2933;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }
        a { color: #1E88E5; text-decoration: none; }
        a:hover { color: #1565C0; }

        .header {
            background: #FFFFFF;
            border-bottom: 1px solid #E5E7EB;
            padding: 0 1.5rem;
            box-shadow: 0 1px 2px rgba(31,41,51,0.04);
        }
        .header-inner {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 64px;
            gap: 1rem;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: inherit;
        }
        .header-logo:hover { color: inherit; }
        .header-logo img { display: block; height: 36px; width: auto; max-width: 120px; object-fit: contain; }
        .header-logo span { font-size: 1.25rem; font-weight: 700; color: #1F2933; letter-spacing: -0.02em; }
        .header-logo .badge { font-size: 11px; font-weight: 600; color: #FFF; background: #1E88E5; padding: 2px 6px; border-radius: 4px; margin-left: 4px; }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .header-nav .user-info { display: flex; align-items: center; gap: 0.5rem; }
        .header-nav .user-avatar {
            display: block; width: 36px; height: 36px; max-width: 36px; max-height: 36px;
            border-radius: 50%; object-fit: cover; border: 1px solid #E5E7EB;
        }
        .header-nav .user-avatar-placeholder {
            width: 36px; height: 36px; border-radius: 50%;
            background: #E8F4FD; color: #1E88E5; font-size: 14px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
        }
        .header-nav .user-name { font-size: 14px; font-weight: 500; color: #1F2933; }
        .header-nav .btn {
            padding: 8px 14px; font-size: 13px; font-weight: 500; font-family: inherit;
            border-radius: 6px; cursor: pointer; transition: background-color 0.2s, color 0.2s;
            background: #F7F9FB; color: #5F6C7B; border: none; text-decoration: none;
        }
        .header-nav .btn:hover { background: #E5E7EB; color: #1F2933; }

        .main-wrap {
            flex: 1;
            max-width: 1120px;
            margin: 0 auto;
            width: 100%;
            padding: 1.75rem 1.5rem;
            display: flex;
            gap: 1.75rem;
        }
        .sidebar { flex-shrink: 0; width: 200px; }
        .sidebar nav {
            background: #FFFFFF;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(31,41,51,0.06);
        }
        .sidebar a {
            display: block;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #5F6C7B;
            border-bottom: 1px solid #F0F2F5;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar a:last-child { border-bottom: none; }
        .sidebar a:hover { background: #F7F9FB; color: #1E88E5; }
        .sidebar a.active { background: #E8F4FD; color: #1E88E5; }

        .content { flex: 1; min-width: 0; }
        .content h1 { font-size: 1.5rem; font-weight: 600; color: #1F2933; margin-bottom: 0.25rem; letter-spacing: -0.01em; }
        .content .page-desc { font-size: 14px; color: #5F6C7B; margin-bottom: 1.5rem; }

        .card {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(31,41,51,0.06);
        }
        .card h2 { font-size: 1rem; font-weight: 600; color: #1F2933; margin-bottom: 1rem; }

        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block; font-size: 13px; font-weight: 500; color: #1F2933; margin-bottom: 0.375rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; font-size: 14px; font-family: inherit; color: #1F2933;
            background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #1E88E5;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group .hint { font-size: 12px; color: #5F6C7B; margin-top: 0.25rem; }

        .btn-primary {
            display: inline-block; padding: 10px 18px; font-size: 14px; font-weight: 500; font-family: inherit;
            color: #FFFFFF; background: #1E88E5; border: none; border-radius: 8px; cursor: pointer; transition: background-color 0.2s;
        }
        .btn-primary:hover { background: #1565C0; }

        .msg { padding: 10px 14px; margin-bottom: 1rem; font-size: 14px; border-radius: 8px; }
        .msg.success { color: #2FAE66; background: #E8F5EE; }
        .msg.error { color: #E53935; background: #FFEBEE; }

        .avatar-wrap { margin-bottom: 1.5rem; }
        .avatar-wrap img { display: block; width: 96px; height: 96px; max-width: 96px; max-height: 96px; object-fit: cover; border-radius: 50%; border: 2px solid #E5E7EB; }
        .avatar-placeholder {
            width: 96px; height: 96px; border-radius: 50%;
            background: #E5E7EB; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #5F6C7B;
        }

        .placeholder-desc { font-size: 14px; color: #5F6C7B; line-height: 1.6; }
        .placeholder-desc + .card { margin-top: 1rem; }

        @media (max-width: 640px) {
            .main-wrap { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="admin.php" class="header-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
                <span class="badge">Admin</span>
            </a>
            <nav class="header-nav">
                <div class="user-info">
                    <?php if ($avatarUrl && is_file(__DIR__ . '/' . $avatarUrl)): ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="" class="user-avatar">
                    <?php else: ?>
                        <div class="user-avatar-placeholder"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <?php endif; ?>
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                </div>
                <a href="dashboard.php" class="btn">User site</a>
                <a href="login.php?logout=1" class="btn">Log out</a>
            </nav>
        </div>
    </header>

    <div class="main-wrap">
        <aside class="sidebar">
            <nav>
                <a href="admin.php?section=pickups" class="<?php echo $section === 'pickups' ? 'active' : ''; ?>">Pickups</a>
                <a href="admin.php?section=users" class="<?php echo $section === 'users' ? 'active' : ''; ?>">Users</a>
                <a href="admin.php?section=marketplace" class="<?php echo $section === 'marketplace' ? 'active' : ''; ?>">Marketplace</a>
                <a href="admin.php?section=inventory" class="<?php echo $section === 'inventory' ? 'active' : ''; ?>">Inventory</a>
                <a href="admin.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">My profile</a>
            </nav>
        </aside>

        <main class="content">
            <?php if ($section === 'pickups'): ?>
                <h1>Pickups</h1>
                <p class="page-desc">Manage all pickup requests: view status, assign drivers, and update schedules.</p>
                <p class="placeholder-desc">Pickup management will be built here (list, assign driver, update status).</p>
                <div class="card">
                    <h2>Coming soon</h2>
                    <p style="font-size: 14px; color: #5F6C7B;">Tables and actions for pickups will appear in this section.</p>
                </div>

            <?php elseif ($section === 'users'): ?>
                <h1>Users</h1>
                <p class="page-desc">View and manage user accounts (recyclers, technicians, drivers).</p>
                <p class="placeholder-desc">User list and management will be built here.</p>
                <div class="card">
                    <h2>Coming soon</h2>
                    <p style="font-size: 14px; color: #5F6C7B;">User listing and role management will appear in this section.</p>
                </div>

            <?php elseif ($section === 'marketplace'): ?>
                <h1>Marketplace</h1>
                <p class="page-desc">Approve items for listing when the technician gives the green light. Control what goes on sale.</p>
                <p class="placeholder-desc">Items approved by technicians can be approved here for marketplace listing.</p>
                <div class="card">
                    <h2>Coming soon</h2>
                    <p style="font-size: 14px; color: #5F6C7B;">Queue of items ready for approval and listing will appear here.</p>
                </div>

            <?php elseif ($section === 'inventory'): ?>
                <h1>Inventory</h1>
                <p class="page-desc">What comes in from pickups: received items at the facility.</p>
                <p class="placeholder-desc">Track items received from completed pickups and their current location/status.</p>
                <div class="card">
                    <h2>Coming soon</h2>
                    <p style="font-size: 14px; color: #5F6C7B;">Inventory received from pickups will be listed here.</p>
                </div>

            <?php elseif ($section === 'profile'): ?>
                <h1>My profile</h1>
                <p class="page-desc">Update your admin account avatar and password.</p>
                <?php if ($profileMsg): ?><p class="msg success"><?php echo htmlspecialchars($profileMsg); ?></p><?php endif; ?>
                <?php if ($profileError): ?><p class="msg error"><?php echo htmlspecialchars($profileError); ?></p><?php endif; ?>

                <div class="card">
                    <h2>Avatar</h2>
                    <div class="avatar-wrap">
                        <?php if ($avatarUrl && is_file(__DIR__ . '/' . $avatarUrl)): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>?v=<?php echo time(); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">?</div>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="admin.php?section=profile" enctype="multipart/form-data">
                        <input type="hidden" name="profile_avatar" value="1">
                        <div class="form-group">
                            <label for="avatar">Upload new avatar</label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                            <p class="hint">JPEG, PNG, GIF or WebP. Max 2 MB.</p>
                        </div>
                        <button type="submit" class="btn-primary">Update avatar</button>
                    </form>
                </div>

                <div class="card">
                    <h2>Change password</h2>
                    <form method="post" action="admin.php?section=profile">
                        <input type="hidden" name="profile_password" value="1">
                        <div class="form-group">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                            <p class="hint">At least 8 characters.</p>
                        </div>
                        <div class="form-group">
                            <label for="new_password_confirm">Confirm new password</label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn-primary">Change password</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
