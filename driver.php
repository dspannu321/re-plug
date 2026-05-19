<?php
/**
 * RePlug — Driver dashboard.
 */
session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'driver') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/audit.php';
require_once __DIR__ . '/app/config/csrf.php';
require_once __DIR__ . '/app/config/item_workflow.php';

$driverId = (int) $_SESSION['user']['id'];
$driverName = $_SESSION['user']['name'] ?? 'Driver';
$msg = '';
$error = '';

// Ensure driver email is verified
$stmt = $pdo->prepare("SELECT email_verified_at FROM users WHERE id = ?");
$stmt->execute([$driverId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['email_verified_at'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pickup_action'])) {
    require_valid_csrf();
    $pickupId = (int) ($_POST['pickup_id'] ?? 0);
    $action = trim($_POST['pickup_action'] ?? '');

    if ($pickupId <= 0 || !in_array($action, ['complete', 'fail'], true)) {
        $error = 'Invalid action.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM pickups WHERE id = ? AND driver_user_id = ?");
            $stmt->execute([$pickupId, $driverId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pickup) {
                $error = 'Pickup not found or not assigned to you.';
            } elseif (($pickup['status'] ?? '') !== 'scheduled') {
                $error = 'Only scheduled pickups can be updated by driver.';
            } else {
                $pdo->beginTransaction();
                if ($action === 'complete') {
                    $stmt = $pdo->prepare("UPDATE pickups SET status = 'picked_up' WHERE id = ? AND driver_user_id = ?");
                    $stmt->execute([$pickupId, $driverId]);

                    $stmt = $pdo->prepare("SELECT item_id FROM pickup_items WHERE pickup_id = ?");
                    $stmt->execute([$pickupId]);
                    $itemIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id');
                    $stmt = $pdo->prepare("UPDATE items SET status = 'picked_up' WHERE id = ?");
                    foreach ($itemIds as $itemId) {
                        $stmt->execute([(int) $itemId]);
                    }
                    log_audit($pdo, $driverId, 'pickup', $pickupId, 'complete_pickup', ['status' => 'picked_up', 'item_count' => count($itemIds)]);
                    $msg = 'Pickup marked as completed.';
                } else {
                    $stmt = $pdo->prepare("UPDATE pickups SET status = 'failed' WHERE id = ? AND driver_user_id = ?");
                    $stmt->execute([$pickupId, $driverId]);

                    // Release items back to draft so recycler can request again.
                    $stmt = $pdo->prepare("SELECT item_id FROM pickup_items WHERE pickup_id = ?");
                    $stmt->execute([$pickupId]);
                    $itemIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id');
                    $stmt = $pdo->prepare("UPDATE items SET status = 'draft' WHERE id = ?");
                    foreach ($itemIds as $itemId) {
                        $stmt->execute([(int) $itemId]);
                    }
                    log_audit($pdo, $driverId, 'pickup', $pickupId, 'fail_pickup', ['status' => 'failed', 'released_item_count' => count($itemIds)]);
                    $msg = 'Pickup marked as failed.';
                }
                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not update pickup.';
        }
    }
}

$assignedPickups = [];
try {
    $sql = "SELECT p.id, p.pickup_window_start, p.pickup_window_end, p.address_text, p.status, p.created_at,
                u.name AS recycler_name, u.email AS recycler_email,
                (SELECT COUNT(*) FROM pickup_items pi WHERE pi.pickup_id = p.id) AS item_count
            FROM pickups p
            JOIN users u ON u.id = p.recycler_user_id
            WHERE p.driver_user_id = ?
            ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$driverId]);
    $assignedPickups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: 'Could not load pickups.';
}

function driver_pickup_status_label($status) {
    $labels = [
        'requested' => 'Requested',
        'scheduled' => 'Scheduled',
        'picked_up' => 'Picked up',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard — RePlug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php require_once __DIR__ . '/app/includes/app_bg.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #1F2933; min-height: 100vh; display: flex; flex-direction: column; }
        .header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 0 1.5rem; }
        .header-inner { max-width: 1120px; margin: 0 auto; min-height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: .7rem; color: inherit; text-decoration: none; font-weight: 700; }
        .logo img { height: 36px; width: auto; }
        .nav { display: flex; align-items: center; gap: .5rem; }
        .btn { display: inline-block; padding: 8px 13px; border-radius: 7px; font-size: 13px; font-weight: 500; border: 1px solid #E5E7EB; text-decoration: none; color: #1F2933; background: #fff; }
        .btn-marketplace { font-weight: 600; }
        .address-link { color: #1E88E5; word-break: break-word; }
        .btn:hover { border-color: #1E88E5; color: #1E88E5; }
        .btn-primary { background: #1E88E5; color: #fff; border-color: #1E88E5; }
        .btn-primary:hover { background: #1565C0; border-color: #1565C0; color: #fff; }
        .btn-danger { background: #E53935; border-color: #E53935; color: #fff; }
        .btn-danger:hover { background: #C62828; border-color: #C62828; color: #fff; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 1.6rem 1.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: .3rem; }
        .desc { color: #5F6C7B; font-size: 14px; margin-bottom: 1rem; }
        .msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .msg.success { background: #E8F5EE; color: #2FAE66; }
        .msg.error { background: #FFEBEE; color: #E53935; }
        .table-wrap { overflow-x: auto; background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(31,41,51,.06); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #F0F2F5; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; color: #5F6C7B; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .status-requested, .status-scheduled { background: #E3F2FD; color: #1565C0; }
        .status-picked_up { background: #E8F5EE; color: #2FAE66; }
        .status-failed, .status-cancelled { background: #FFEBEE; color: #E53935; }
        .actions { display: flex; gap: .4rem; flex-wrap: wrap; }
        form { margin: 0; }
        .small { color: #5F6C7B; font-size: 12px; }
    </style>
</head>
<body class="app-bg-page">
    <header class="header">
        <div class="header-inner">
            <a class="logo" href="driver.php"><img src="public/assets/images/logo.png" alt="RePlug">RePlug Driver</a>
            <nav class="nav">
                <?php require __DIR__ . '/app/includes/nav_marketplace.php'; ?>
                <span class="small"><?php echo htmlspecialchars($driverName); ?></span>
                <a href="login.php?logout=1" class="btn">Log out</a>
            </nav>
        </div>
    </header>
    <main class="wrap">
        <h1>Assigned Pickups</h1>
        <p class="desc">View your assigned pickups and mark them as completed or failed.</p>
        <?php if ($msg): ?><p class="msg success"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="msg error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if (count($assignedPickups) === 0): ?>
            <p class="desc">No pickups assigned yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Recycler</th>
                            <th>Window</th>
                            <th>Address</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignedPickups as $pu): ?>
                            <tr>
                                <td>#<?php echo (int) $pu['id']; ?></td>
                                <td><?php echo htmlspecialchars($pu['recycler_name']); ?><br><span class="small"><?php echo htmlspecialchars($pu['recycler_email']); ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($pu['pickup_window_start'])); ?><br><span class="small">to <?php echo date('M j, Y g:i A', strtotime($pu['pickup_window_end'])); ?></span></td>
                                <td>
                                    <?php $mapUrl = replug_maps_search_url((string) $pu['address_text']); ?>
                                    <a href="<?php echo htmlspecialchars($mapUrl); ?>" target="_blank" rel="noopener noreferrer" style="font-weight:600;">
                                        <?php echo htmlspecialchars($pu['address_text']); ?>
                                    </a>
                                </td>
                                <td><?php echo (int) $pu['item_count']; ?></td>
                                <td><span class="badge status-<?php echo htmlspecialchars((string)$pu['status']); ?>"><?php echo htmlspecialchars(driver_pickup_status_label($pu['status'])); ?></span></td>
                                <td>
                                    <?php if (($pu['status'] ?? '') === 'scheduled'): ?>
                                        <div class="actions">
                                            <form method="post" action="driver.php">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="pickup_id" value="<?php echo (int)$pu['id']; ?>">
                                                <input type="hidden" name="pickup_action" value="complete">
                                                <button type="submit" class="btn btn-primary">Mark completed</button>
                                            </form>
                                            <form method="post" action="driver.php">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="pickup_id" value="<?php echo (int)$pu['id']; ?>">
                                                <input type="hidden" name="pickup_action" value="fail">
                                                <button type="submit" class="btn btn-danger">Mark failed</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="small">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

