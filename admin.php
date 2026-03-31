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
require_once __DIR__ . '/app/config/csrf.php';
require_once __DIR__ . '/app/config/audit.php';

function pickup_status_label($status) {
    $labels = [
        'requested' => 'Requested',
        'scheduled' => 'Scheduled',
        'picked_up' => 'Picked up',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

$userId = (int) $_SESSION['user']['id'];
$user = $_SESSION['user'];

// Load fresh user row (for avatar + verification)
$stmt = $pdo->prepare("SELECT id, name, email, avatar, email_verified_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($userRow) {
    $user['avatar'] = $userRow['avatar'] ?? null;
}
if (!$userRow || empty($userRow['email_verified_at'])) {
    header('Location: login.php');
    exit;
}

$section = isset($_GET['section']) ? $_GET['section'] : 'pickups';
$validSections = ['pickups', 'users', 'marketplace', 'inventory', 'audit', 'profile'];
if (!in_array($section, $validSections, true)) {
    $section = 'pickups';
}

$uploadDir = __DIR__ . '/public/storage/uploads';
$avatarsDir = $uploadDir . '/avatars';
$profileMsg = '';
$profileError = '';
$pickupMsg = '';
$pickupError = '';
$userMsg = '';
$userError = '';
$marketplaceMsg = '';
$marketplaceError = '';
$payoutMsg = '';
$payoutError = '';

// ---------- Admin Pickups: assign driver ----------
if ($section === 'pickups' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver'])) {
    require_valid_csrf();
    $pickupId = (int) ($_POST['pickup_id'] ?? 0);
    $driverId = (int) ($_POST['driver_user_id'] ?? 0);
    if ($pickupId <= 0 || $driverId <= 0) {
        $pickupError = 'Invalid pickup or driver.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'driver'");
        $stmt->execute([$driverId]);
        if (!$stmt->fetch()) {
            $pickupError = 'Selected driver is invalid.';
        } else {
            $stmt = $pdo->prepare("SELECT id, status FROM pickups WHERE id = ?");
            $stmt->execute([$pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pickup) {
                $pickupError = 'Pickup not found.';
            } elseif (!in_array($pickup['status'], ['requested', 'scheduled'], true)) {
                $pickupError = 'Only requested or scheduled pickups can be assigned.';
            } else {
                $stmt = $pdo->prepare("UPDATE pickups SET driver_user_id = ?, status = 'scheduled' WHERE id = ?");
                $stmt->execute([$driverId, $pickupId]);
                log_audit($pdo, $userId, 'pickup', $pickupId, 'assign_driver', ['driver_user_id' => $driverId, 'status' => 'scheduled']);
                $pickupMsg = 'Driver assigned.';
            }
        }
    }
}

// ---------- Admin Pickups: update pickup status ----------
if ($section === 'pickups' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pickup_status'])) {
    require_valid_csrf();
    $pickupId = (int) ($_POST['pickup_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $allowedStatuses = ['scheduled', 'picked_up', 'failed', 'cancelled'];
    if ($pickupId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        $pickupError = 'Invalid pickup status update.';
    } else {
        $stmt = $pdo->prepare("SELECT id, status FROM pickups WHERE id = ?");
        $stmt->execute([$pickupId]);
        $pickup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pickup) {
            $pickupError = 'Pickup not found.';
        } else {
            $currentStatus = $pickup['status'];
            $allowedTransitions = [
                'requested' => ['scheduled', 'cancelled', 'failed'],
                'scheduled' => ['picked_up', 'failed', 'cancelled'],
                'failed' => [],
                'cancelled' => [],
                'picked_up' => [],
            ];
            $canTransition = in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true);
            if (!$canTransition) {
                $pickupError = 'Invalid status transition.';
            } else {
                $stmt = $pdo->prepare("UPDATE pickups SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $pickupId]);
                log_audit($pdo, $userId, 'pickup', $pickupId, 'status_update', ['from' => $currentStatus, 'to' => $newStatus]);
                $pickupMsg = 'Pickup status updated.';
            }
        }
    }
}

$drivers = [];
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'driver' ORDER BY name ASC, id ASC");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // drivers might not exist yet
}

$pickups = [];
try {
    $sql = "SELECT
                p.id,
                p.recycler_user_id,
                p.driver_user_id,
                p.pickup_window_start,
                p.pickup_window_end,
                p.address_text,
                p.status,
                p.created_at,
                ru.name AS recycler_name,
                ru.email AS recycler_email,
                du.name AS driver_name,
                du.email AS driver_email,
                (SELECT COUNT(*) FROM pickup_items pi WHERE pi.pickup_id = p.id) AS item_count
            FROM pickups p
            JOIN users ru ON ru.id = p.recycler_user_id
            LEFT JOIN users du ON du.id = p.driver_user_id
            ORDER BY p.created_at DESC";
    $stmt = $pdo->query($sql);
    $pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // pickups table might not exist yet
}

// ---------- Admin Users: update role ----------
if ($section === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_role'])) {
    require_valid_csrf();
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $newRole = trim($_POST['role'] ?? '');
    $allowedRoles = ['user', 'driver', 'technician', 'admin'];
    if ($targetUserId <= 0 || !in_array($newRole, $allowedRoles, true)) {
        $userError = 'Invalid user or role.';
    } elseif ($targetUserId === $userId) {
        $userError = 'You cannot change your own role.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $targetUserId]);
            log_audit($pdo, $userId, 'user', $targetUserId, 'role_update', ['role' => $newRole]);
            $userMsg = 'User role updated.';
        } catch (PDOException $e) {
            $userError = 'Could not update role.';
        }
    }
}

$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // users table should exist, but fail gracefully
}

$inventoryItems = [];
try {
    $sql = "SELECT
                i.id,
                i.title,
                i.category,
                i.status,
                i.created_at,
                u.name AS recycler_name,
                u.email AS recycler_email,
                p.id AS pickup_id,
                p.pickup_window_start,
                p.pickup_window_end,
                p.status AS pickup_status
            FROM items i
            JOIN users u ON u.id = i.recycler_user_id
            LEFT JOIN pickup_items pi ON pi.item_id = i.id
            LEFT JOIN pickups p ON p.id = pi.pickup_id
            WHERE i.status IN ('picked_up', 'inspected', 'repair_in_progress', 'approved_for_sale', 'listed_for_sale', 'sold', 'recycled')
            ORDER BY i.updated_at DESC, i.id DESC";
    $stmt = $pdo->query($sql);
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // inventory source tables may not exist yet
}

// ---------- Admin Marketplace: create listing from approved item ----------
if ($section === 'marketplace' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_marketplace_listing'])) {
    require_valid_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $priceRaw = trim($_POST['price'] ?? '');
    $price = is_numeric($priceRaw) ? (float) $priceRaw : -1;

    if ($itemId <= 0 || $price <= 0) {
        $marketplaceError = 'Please select a valid item and price.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, title, description, status FROM items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $marketplaceError = 'Item not found.';
                $pdo->rollBack();
            } elseif (($item['status'] ?? '') !== 'approved_for_sale') {
                $marketplaceError = 'Only items approved for sale can be listed.';
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare("SELECT id FROM marketplace_listings WHERE item_id = ? LIMIT 1");
                $stmt->execute([$itemId]);
                if ($stmt->fetch()) {
                    $marketplaceError = 'This item already has a marketplace listing.';
                    $pdo->rollBack();
                } else {
                    $stmt = $pdo->prepare("INSERT INTO marketplace_listings (item_id, admin_user_id, price, title, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$itemId, $userId, $price, $item['title'], $item['description']]);
                    $listingId = (int) $pdo->lastInsertId();
                    $stmt = $pdo->prepare("UPDATE items SET status = 'listed_for_sale' WHERE id = ?");
                    $stmt->execute([$itemId]);
                    log_audit($pdo, $userId, 'listing', $listingId, 'create_listing', ['item_id' => $itemId, 'price' => $price]);
                    $pdo->commit();
                    $marketplaceMsg = 'Marketplace listing created.';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $marketplaceError = 'Could not create listing. Make sure marketplace_listings table exists.';
        }
    }
}

// ---------- Admin Payouts: mark payout paid ----------
if ($section === 'marketplace' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_payout_paid'])) {
    require_valid_csrf();
    $payoutId = (int) ($_POST['payout_id'] ?? 0);
    if ($payoutId <= 0) {
        $payoutError = 'Invalid payout.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE payouts SET status = 'paid' WHERE id = ? AND status = 'unpaid'");
            $stmt->execute([$payoutId]);
            if ($stmt->rowCount() > 0) {
                log_audit($pdo, $userId, 'payout', $payoutId, 'mark_paid');
                $payoutMsg = 'Payout marked as paid.';
            } else {
                $payoutError = 'Payout not found or already paid.';
            }
        } catch (PDOException $e) {
            $payoutError = 'Could not update payout. Make sure payouts table exists.';
        }
    }
}

$marketplaceQueue = [];
try {
    $stmt = $pdo->query("SELECT i.id, i.title, i.category, i.description, i.created_at, u.name AS recycler_name
        FROM items i
        JOIN users u ON u.id = i.recycler_user_id
        WHERE i.status = 'approved_for_sale'
        ORDER BY i.updated_at DESC, i.id DESC");
    $marketplaceQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // items/users table may not exist
}

$marketplaceListings = [];
try {
    $stmt = $pdo->query("SELECT ml.id, ml.item_id, ml.price, ml.title, ml.is_active, ml.created_at,
        a.name AS admin_name, i.status AS item_status
        FROM marketplace_listings ml
        LEFT JOIN users a ON a.id = ml.admin_user_id
        LEFT JOIN items i ON i.id = ml.item_id
        ORDER BY ml.created_at DESC, ml.id DESC");
    $marketplaceListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // marketplace_listings table may not exist yet
}

$payouts = [];
try {
    $stmt = $pdo->query("SELECT p.id, p.recycler_user_id, p.order_id, p.amount, p.status, p.created_at,
        u.name AS recycler_name, o.amount AS order_amount
        FROM payouts p
        JOIN users u ON u.id = p.recycler_user_id
        JOIN orders o ON o.id = p.order_id
        ORDER BY p.created_at DESC, p.id DESC");
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // payouts/orders tables may not exist yet
}

$auditLogs = [];
try {
    $stmt = $pdo->query("SELECT a.id, a.actor_user_id, a.entity_type, a.entity_id, a.action, a.meta_json, a.created_at,
        u.name AS actor_name, u.role AS actor_role
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.actor_user_id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 100");
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // audit_logs may not exist yet
}

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
        .btn-secondary {
            display: inline-block;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
            font-family: inherit;
            color: #1F2933;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-secondary:hover { border-color: #1E88E5; color: #1E88E5; }

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
        .table-wrap { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .data-table th, .data-table td {
            border-bottom: 1px solid #F0F2F5;
            padding: 10px 8px;
            vertical-align: top;
            text-align: left;
        }
        .data-table th { color: #5F6C7B; font-weight: 600; font-size: 12px; letter-spacing: 0.01em; text-transform: uppercase; }
        .status-badge {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-requested { background: #E3F2FD; color: #1E88E5; }
        .status-scheduled { background: #E3F2FD; color: #1565C0; }
        .status-picked_up { background: #E8F5EE; color: #2FAE66; }
        .status-failed, .status-cancelled { background: #FFEBEE; color: #E53935; }
        .status-inspected { background: #FFF8E1; color: #F57F17; }
        .status-repair_in_progress { background: #EDE7F6; color: #5E35B1; }
        .status-approved_for_sale, .status-listed_for_sale { background: #E8F5E9; color: #2E7D32; }
        .status-sold { background: #E8F5E9; color: #1B5E20; }
        .status-recycled { background: #F1F8E9; color: #33691E; }
        .pickup-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .pickup-actions form { margin: 0; }
        .pickup-actions select { min-width: 130px; padding: 8px 10px; font-size: 13px; border-radius: 8px; border: 1px solid #E5E7EB; }
        .small-note { color: #5F6C7B; font-size: 12px; }

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
                <a href="admin.php?section=audit" class="<?php echo $section === 'audit' ? 'active' : ''; ?>">Audit logs</a>
                <a href="admin.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">My profile</a>
            </nav>
        </aside>

        <main class="content">
            <?php if ($section === 'pickups'): ?>
                <h1>Pickups</h1>
                <p class="page-desc">Manage all pickup requests: view status, assign drivers, and update schedules.</p>
                <div class="card">
                    <h2>All pickups</h2>
                    <?php if ($pickupMsg): ?><p class="msg success"><?php echo htmlspecialchars($pickupMsg); ?></p><?php endif; ?>
                    <?php if ($pickupError): ?><p class="msg error"><?php echo htmlspecialchars($pickupError); ?></p><?php endif; ?>
                    <?php if (count($pickups) === 0): ?>
                        <p class="placeholder-desc">No pickups found yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recycler</th>
                                        <th>Driver</th>
                                        <th>Window</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pickups as $pu): ?>
                                        <tr>
                                            <td>#<?php echo (int) $pu['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($pu['recycler_name'] ?: ('User #' . (int) $pu['recycler_user_id'])); ?><br>
                                                <span class="small-note"><?php echo htmlspecialchars($pu['recycler_email'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($pu['driver_user_id'])): ?>
                                                    <?php echo htmlspecialchars($pu['driver_name'] ?: ('Driver #' . (int) $pu['driver_user_id'])); ?><br>
                                                    <span class="small-note"><?php echo htmlspecialchars($pu['driver_email'] ?? ''); ?></span>
                                                <?php else: ?>
                                                    <span class="small-note">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($pu['pickup_window_start'])); ?><br>
                                                <span class="small-note">to <?php echo date('M j, Y g:i A', strtotime($pu['pickup_window_end'])); ?></span>
                                            </td>
                                            <td><?php echo (int) $pu['item_count']; ?></td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars($pu['status']); ?>"><?php echo htmlspecialchars(pickup_status_label($pu['status'])); ?></span></td>
                                            <td><?php echo htmlspecialchars(mb_strimwidth((string) $pu['address_text'], 0, 70, '...')); ?></td>
                                            <td>
                                                <div class="pickup-actions">
                                                    <?php if (in_array($pu['status'], ['requested', 'scheduled'], true)): ?>
                                                        <form method="post" action="admin.php?section=pickups">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="assign_driver" value="1">
                                                            <input type="hidden" name="pickup_id" value="<?php echo (int) $pu['id']; ?>">
                                                            <select name="driver_user_id" required>
                                                                <option value="">Assign driver</option>
                                                                <?php foreach ($drivers as $dr): ?>
                                                                    <option value="<?php echo (int) $dr['id']; ?>" <?php echo ((int)$pu['driver_user_id'] === (int)$dr['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($dr['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn-secondary">Save</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (in_array($pu['status'], ['requested', 'scheduled'], true)): ?>
                                                        <form method="post" action="admin.php?section=pickups">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="update_pickup_status" value="1">
                                                            <input type="hidden" name="pickup_id" value="<?php echo (int) $pu['id']; ?>">
                                                            <select name="status" required>
                                                                <option value="">Set status</option>
                                                                <?php if ($pu['status'] === 'requested'): ?>
                                                                    <option value="scheduled">Scheduled</option>
                                                                <?php endif; ?>
                                                                <?php if ($pu['status'] === 'scheduled'): ?>
                                                                    <option value="picked_up">Picked up</option>
                                                                <?php endif; ?>
                                                                <option value="failed">Failed</option>
                                                                <option value="cancelled">Cancelled</option>
                                                            </select>
                                                            <button type="submit" class="btn-secondary">Update</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'users'): ?>
                <h1>Users</h1>
                <p class="page-desc">View and manage user accounts (recyclers, technicians, drivers).</p>
                <div class="card">
                    <h2>All users</h2>
                    <?php if ($userMsg): ?><p class="msg success"><?php echo htmlspecialchars($userMsg); ?></p><?php endif; ?>
                    <?php if ($userError): ?><p class="msg error"><?php echo htmlspecialchars($userError); ?></p><?php endif; ?>
                    <?php if (count($users) === 0): ?>
                        <p class="placeholder-desc">No users found.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>#<?php echo (int) $u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['name'] ?: 'Unnamed'); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars($u['role'] === 'admin' ? 'scheduled' : 'requested'); ?>"><?php echo htmlspecialchars(ucfirst((string) $u['role'])); ?></span></td>
                                            <td>
                                                <?php if (!empty($u['created_at'])): ?>
                                                    <?php echo date('M j, Y g:i A', strtotime($u['created_at'])); ?>
                                                <?php else: ?>
                                                    <span class="small-note">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$u['id'] === $userId): ?>
                                                    <span class="small-note">Current admin</span>
                                                <?php else: ?>
                                                    <form method="post" action="admin.php?section=users" class="pickup-actions">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="update_user_role" value="1">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                                        <select name="role" required>
                                                            <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                            <option value="driver" <?php echo $u['role'] === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                                            <option value="technician" <?php echo $u['role'] === 'technician' ? 'selected' : ''; ?>>Technician</option>
                                                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                        </select>
                                                        <button type="submit" class="btn-secondary">Update role</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'marketplace'): ?>
                <h1>Marketplace</h1>
                <p class="page-desc">Approve items for listing when the technician gives the green light. Control what goes on sale.</p>
                <div class="card">
                    <h2>Create listing from approved item</h2>
                    <?php if ($marketplaceMsg): ?><p class="msg success"><?php echo htmlspecialchars($marketplaceMsg); ?></p><?php endif; ?>
                    <?php if ($marketplaceError): ?><p class="msg error"><?php echo htmlspecialchars($marketplaceError); ?></p><?php endif; ?>

                    <?php if (count($marketplaceQueue) === 0): ?>
                        <p class="placeholder-desc">No items are currently approved for sale.</p>
                    <?php else: ?>
                        <form method="post" action="admin.php?section=marketplace">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="create_marketplace_listing" value="1">
                            <div class="form-group">
                                <label for="marketplace-item-id">Approved item</label>
                                <select id="marketplace-item-id" name="item_id" required>
                                    <option value="">Select item</option>
                                    <?php foreach ($marketplaceQueue as $qi): ?>
                                        <option value="<?php echo (int) $qi['id']; ?>">
                                            #<?php echo (int) $qi['id']; ?> — <?php echo htmlspecialchars($qi['title']); ?> (<?php echo htmlspecialchars($qi['recycler_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="marketplace-price">Price</label>
                                <input type="number" step="0.01" min="0.01" id="marketplace-price" name="price" required placeholder="e.g. 49.99">
                            </div>
                            <button type="submit" class="btn-primary">Create listing</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Marketplace listings</h2>
                    <?php if (count($marketplaceListings) === 0): ?>
                        <p class="placeholder-desc">No marketplace listings yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Listing</th>
                                        <th>Item ID</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Item status</th>
                                        <th>Created by</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketplaceListings as $ml): ?>
                                        <tr>
                                            <td>#<?php echo (int) $ml['id']; ?> — <?php echo htmlspecialchars($ml['title']); ?></td>
                                            <td>#<?php echo (int) $ml['item_id']; ?></td>
                                            <td>PHP <?php echo number_format((float) $ml['price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $ml['is_active'] ? 'scheduled' : 'cancelled'; ?>">
                                                    <?php echo $ml['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($ml['item_status'])): ?>
                                                    <span class="status-badge status-<?php echo htmlspecialchars((string) $ml['item_status']); ?>">
                                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $ml['item_status']))); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="small-note">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ml['admin_name'] ?: 'Admin'); ?></td>
                                            <td><?php echo !empty($ml['created_at']) ? date('M j, Y g:i A', strtotime($ml['created_at'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Payouts</h2>
                    <p class="small-note" style="margin-bottom: 0.75rem;">Recycler share is calculated at <?php echo number_format(RECYCLER_SHARE_PERCENT, 2); ?>% of paid order amount.</p>
                    <?php if ($payoutMsg): ?><p class="msg success"><?php echo htmlspecialchars($payoutMsg); ?></p><?php endif; ?>
                    <?php if ($payoutError): ?><p class="msg error"><?php echo htmlspecialchars($payoutError); ?></p><?php endif; ?>
                    <?php if (count($payouts) === 0): ?>
                        <p class="placeholder-desc">No payouts found yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Payout</th>
                                        <th>Recycler</th>
                                        <th>Order</th>
                                        <th>Order amount</th>
                                        <th>Payout amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payouts as $po): ?>
                                        <tr>
                                            <td>#<?php echo (int) $po['id']; ?></td>
                                            <td><?php echo htmlspecialchars($po['recycler_name']); ?></td>
                                            <td>#<?php echo (int) $po['order_id']; ?></td>
                                            <td>PHP <?php echo number_format((float) $po['order_amount'], 2); ?></td>
                                            <td>PHP <?php echo number_format((float) $po['amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $po['status'] === 'paid' ? 'picked_up' : 'requested'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst((string) $po['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (($po['status'] ?? '') === 'unpaid'): ?>
                                                    <form method="post" action="admin.php?section=marketplace">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="mark_payout_paid" value="1">
                                                        <input type="hidden" name="payout_id" value="<?php echo (int) $po['id']; ?>">
                                                        <button type="submit" class="btn-secondary">Mark paid</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="small-note">Done</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'inventory'): ?>
                <h1>Inventory</h1>
                <p class="page-desc">What comes in from pickups: received items at the facility.</p>
                <div class="card">
                    <h2>Received items</h2>
                    <p class="small-note" style="margin-bottom: 0.75rem;">Includes items with status: picked up, inspected, repair in progress, approved for sale, listed for sale, sold, or recycled.</p>
                    <?php if (count($inventoryItems) === 0): ?>
                        <p class="placeholder-desc">No received inventory yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Recycler</th>
                                        <th>Pickup</th>
                                        <th>Item status</th>
                                        <th>Pickup status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventoryItems as $ii): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ii['title'] ?: ('Item #' . (int) $ii['id'])); ?></strong><br>
                                                <span class="small-note"><?php echo htmlspecialchars($ii['category'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($ii['recycler_name'] ?: 'Unknown recycler'); ?><br>
                                                <span class="small-note"><?php echo htmlspecialchars($ii['recycler_email'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($ii['pickup_id'])): ?>
                                                    #<?php echo (int) $ii['pickup_id']; ?><br>
                                                    <span class="small-note"><?php echo date('M j, Y g:i A', strtotime($ii['pickup_window_start'])); ?> to <?php echo date('M j, Y g:i A', strtotime($ii['pickup_window_end'])); ?></span>
                                                <?php else: ?>
                                                    <span class="small-note">No pickup record</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars((string) $ii['status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $ii['status']))); ?></span></td>
                                            <td>
                                                <?php if (!empty($ii['pickup_status'])): ?>
                                                    <span class="status-badge status-<?php echo htmlspecialchars((string) $ii['pickup_status']); ?>"><?php echo htmlspecialchars(pickup_status_label($ii['pickup_status'])); ?></span>
                                                <?php else: ?>
                                                    <span class="small-note">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ii['created_at'])): ?>
                                                    <?php echo date('M j, Y g:i A', strtotime($ii['created_at'])); ?>
                                                <?php else: ?>
                                                    <span class="small-note">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'audit'): ?>
                <h1>Audit logs</h1>
                <p class="page-desc">Recent system activity (last 100 events).</p>
                <div class="card">
                    <h2>Events</h2>
                    <?php if (count($auditLogs) === 0): ?>
                        <p class="placeholder-desc">No audit logs yet or audit table not created.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Actor</th>
                                        <th>Entity</th>
                                        <th>Action</th>
                                        <th>Meta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $al): ?>
                                        <tr>
                                            <td><?php echo !empty($al['created_at']) ? date('M j, Y g:i A', strtotime($al['created_at'])) : '-'; ?></td>
                                            <td>
                                                <?php if (!empty($al['actor_name'])): ?>
                                                    <?php echo htmlspecialchars($al['actor_name']); ?>
                                                    <span class="small-note">(<?php echo htmlspecialchars($al['actor_role'] ?? ''); ?>)</span>
                                                <?php else: ?>
                                                    User #<?php echo (int) $al['actor_user_id']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($al['entity_type']); ?> #<?php echo (int) $al['entity_id']; ?></td>
                                            <td><?php echo htmlspecialchars($al['action']); ?></td>
                                            <td>
                                                <?php
                                                if (!empty($al['meta_json'])) {
                                                    $meta = json_decode($al['meta_json'], true);
                                                    if (is_array($meta)) {
                                                        $pairs = [];
                                                        foreach ($meta as $k => $v) {
                                                            $pairs[] = htmlspecialchars((string)$k) . ': ' . htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v));
                                                        }
                                                        echo '<span class="small-note">' . implode(', ', $pairs) . '</span>';
                                                    } else {
                                                        echo '<span class="small-note">' . htmlspecialchars($al['meta_json']) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="small-note">-</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                        <?php echo csrf_field(); ?>
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
                        <?php echo csrf_field(); ?>
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
