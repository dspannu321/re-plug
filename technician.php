<?php
/**
 * RePlug — Technician dashboard.
 */
session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'technician') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/audit.php';
require_once __DIR__ . '/app/config/csrf.php';
require_once __DIR__ . '/app/config/item_workflow.php';

$techId = (int) $_SESSION['user']['id'];
$techName = $_SESSION['user']['name'] ?? 'Technician';
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_item'])) {
    require_valid_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $result = trim($_POST['result'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $estimatedRepairCostRaw = trim($_POST['estimated_repair_cost'] ?? '');
    $estimatedRepairCost = ($estimatedRepairCostRaw === '') ? null : (float) $estimatedRepairCostRaw;
    $salvagedPartName = trim($_POST['salvaged_part_name'] ?? '');
    $salvagedCondition = trim($_POST['salvaged_condition'] ?? '');

    if ($itemId <= 0 || !inspection_result_is_valid($result)) {
        $error = 'Invalid inspection input.';
    } elseif (in_array($result, ['recyclable', 'not_repairable'], true) && $salvagedPartName === '') {
        $error = 'Please record at least a salvaged part name for recyclable / not repairable items.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, status, technician_user_id FROM items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $error = 'Item not found.';
                $pdo->rollBack();
            } elseif ((int) ($item['technician_user_id'] ?? 0) !== $techId) {
                $error = 'This item is not assigned to you.';
                $pdo->rollBack();
            } elseif (($item['status'] ?? '') !== 'assigned_to_technician') {
                $error = 'This item is not awaiting your inspection.';
                $pdo->rollBack();
            } else {
                $statusAfter = inspection_result_sets_status($result);
                $stmt = $pdo->prepare(
                    "INSERT INTO inspections (item_id, technician_user_id, result, notes, estimated_repair_cost, status_after)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$itemId, $techId, $result, $notes, $estimatedRepairCost, $statusAfter]);
                $inspectionId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE items SET status = ? WHERE id = ?");
                $stmt->execute([$statusAfter, $itemId]);

                if (in_array($result, ['recyclable', 'not_repairable'], true)) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO salvaged_parts (item_id, technician_user_id, part_name, condition_notes, status)
                         VALUES (?, ?, ?, ?, 'pending_review')"
                    );
                    $stmt->execute([$itemId, $techId, $salvagedPartName, $salvagedCondition]);
                }

                log_audit($pdo, $techId, 'item', $itemId, 'inspect_item', [
                    'inspection_id' => $inspectionId,
                    'result' => $result,
                    'status_after' => $statusAfter,
                ]);
                $pdo->commit();
                $msg = 'Inspection submitted. Waiting for admin approval.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not save inspection.';
            if (defined('APP_ENV') && APP_ENV === 'local') {
                $error .= ' ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item_status'])) {
    require_valid_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($itemId <= 0 || $newStatus === '') {
        $error = 'Invalid status update.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, status, technician_user_id FROM items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $error = 'Item not found.';
                $pdo->rollBack();
            } elseif ((int) ($item['technician_user_id'] ?? 0) !== $techId) {
                $error = 'This item is not assigned to you.';
                $pdo->rollBack();
            } elseif (!technician_can_set_status($item['status'] ?? '', $newStatus)) {
                $error = 'That status change is not allowed.';
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare("UPDATE items SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $itemId]);
                log_audit($pdo, $techId, 'item', $itemId, 'technician_status_update', [
                    'from' => $item['status'],
                    'to' => $newStatus,
                ]);
                $pdo->commit();
                $msg = 'Item status updated to ' . item_status_label($newStatus) . '.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not update status.';
        }
    }
}

$activeItems = [];
$pendingAdminItems = [];
try {
    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.category, i.description, i.condition_notes, i.status, i.created_at,
                u.name AS recycler_name, u.email AS recycler_email
         FROM items i
         JOIN users u ON u.id = i.recycler_user_id
         WHERE i.technician_user_id = ?
           AND i.status IN ('assigned_to_technician', 'repair_approved_by_admin', 'repair_in_progress', 'repaired')
         ORDER BY i.updated_at DESC, i.id DESC"
    );
    $stmt->execute([$techId]);
    $activeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT i.id, i.title, i.status, i.updated_at,
                (SELECT result FROM inspections WHERE item_id = i.id ORDER BY id DESC LIMIT 1) AS last_result
         FROM items i
         WHERE i.technician_user_id = ? AND i.status = 'waiting_for_admin_approval'
         ORDER BY i.updated_at DESC"
    );
    $stmt->execute([$techId]);
    $pendingAdminItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: 'Could not load assigned items.';
}

$recentInspections = [];
try {
    $stmt = $pdo->prepare(
        "SELECT ins.id, ins.item_id, ins.result, ins.status_after, ins.estimated_repair_cost, ins.created_at, i.title
         FROM inspections ins
         JOIN items i ON i.id = ins.item_id
         WHERE ins.technician_user_id = ?
         ORDER BY ins.created_at DESC, ins.id DESC
         LIMIT 20"
    );
    $stmt->execute([$techId]);
    $recentInspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // optional
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard — RePlug</title>
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
        .nav { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 8px 13px; border-radius: 7px; font-size: 13px; font-weight: 500; border: 1px solid #E5E7EB; text-decoration: none; color: #1F2933; background: #fff; cursor: pointer; }
        .btn:hover { border-color: #1E88E5; color: #1E88E5; }
        .btn-marketplace { font-weight: 600; }
        .btn-primary { background: #1E88E5; color: #fff; border-color: #1E88E5; }
        .btn-primary:hover { background: #1565C0; border-color: #1565C0; color: #fff; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 1.6rem 1.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: .3rem; }
        h2 { font-size: 1rem; margin-bottom: .75rem; }
        .desc { color: #5F6C7B; font-size: 14px; margin-bottom: 1rem; }
        .msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .msg.success { background: #E8F5EE; color: #2FAE66; }
        .msg.error { background: #FFEBEE; color: #E53935; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(31,41,51,.06); padding: 1rem 1.1rem; margin-bottom: 1rem; }
        .small { color: #5F6C7B; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .item-title { font-size: 15px; font-weight: 600; }
        .status-pill { display: inline-block; margin-top: .35rem; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #E3F2FD; color: #1565C0; }
        .form-group { margin-bottom: .75rem; }
        label { display: block; font-size: 12px; font-weight: 600; color: #425466; margin-bottom: .25rem; }
        input, select, textarea { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 10px; font-size: 13px; font-family: inherit; }
        textarea { min-height: 70px; resize: vertical; }
        .salvaged-fields { display: none; margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed #E5E7EB; }
        .salvaged-fields.is-visible { display: block; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #F0F2F5; }
        th { font-size: 12px; color: #5F6C7B; text-transform: uppercase; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #E8F5E9; color: #2E7D32; }
        @media (max-width: 760px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="app-bg-page">
    <header class="header">
        <div class="header-inner">
            <a class="logo" href="technician.php"><img src="public/assets/images/logo.png" alt="RePlug">RePlug Technician</a>
            <nav class="nav">
                <?php require __DIR__ . '/app/includes/nav_marketplace.php'; ?>
                <span class="small"><?php echo htmlspecialchars($techName); ?></span>
                <a href="login.php?logout=1" class="btn">Log out</a>
            </nav>
        </div>
    </header>
    <main class="wrap">
        <h1>Technician workspace</h1>
        <p class="desc">Inspect assigned items, request admin approval for repairs, and update repair progress.</p>
        <?php if ($msg): ?><p class="msg success"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="msg error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if (count($pendingAdminItems) > 0): ?>
            <div class="card">
                <h2>Waiting for admin approval</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Your decision</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($pendingAdminItems as $pa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pa['title']); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $pa['last_result']))); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars(item_status_label($pa['status'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($activeItems) === 0): ?>
            <p class="desc">No items assigned to you right now.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($activeItems as $it): ?>
                    <?php
                    $itemStatus = (string) ($it['status'] ?? '');
                    $statusOptions = technician_status_options($itemStatus);
                    ?>
                    <div class="card">
                        <div class="item-title"><?php echo htmlspecialchars($it['title']); ?>
                            <span class="small">(<?php echo htmlspecialchars($it['category'] ?: 'General'); ?>)</span>
                        </div>
                        <span class="status-pill"><?php echo htmlspecialchars(item_status_label($itemStatus)); ?></span>
                        <p class="small" style="margin-top:.35rem;">Recycler: <?php echo htmlspecialchars($it['recycler_name']); ?></p>

                        <?php if ($itemStatus === 'assigned_to_technician'): ?>
                            <form method="post" action="technician.php" style="margin-top:.75rem;" class="inspect-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="inspect_item" value="1">
                                <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                <div class="form-group">
                                    <label>Inspection result *</label>
                                    <select name="result" required class="inspect-result">
                                        <option value="">Select result</option>
                                        <option value="working">Working</option>
                                        <option value="repairable">Repairable</option>
                                        <option value="recyclable">Recyclable</option>
                                        <option value="not_repairable">Not repairable</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Repair notes</label>
                                    <textarea name="notes" placeholder="Inspection / repair notes..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Estimated repair cost (optional)</label>
                                    <input type="number" name="estimated_repair_cost" step="0.01" min="0">
                                </div>
                                <div class="salvaged-fields" data-salvaged-fields>
                                    <div class="form-group">
                                        <label>Salvaged part name *</label>
                                        <input type="text" name="salvaged_part_name" placeholder="e.g. Power supply, Motor">
                                    </div>
                                    <div class="form-group">
                                        <label>Part condition</label>
                                        <textarea name="salvaged_condition" placeholder="Condition of salvaged part(s)"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit for admin approval</button>
                            </form>
                        <?php elseif (count($statusOptions) > 0): ?>
                            <form method="post" action="technician.php" style="margin-top:.75rem;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="update_item_status" value="1">
                                <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                <div class="form-group">
                                    <label>Update status</label>
                                    <select name="new_status" required>
                                        <option value="">Select next status</option>
                                        <?php foreach ($statusOptions as $val => $label): ?>
                                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Save status</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Recent inspections</h2>
            <?php if (count($recentInspections) === 0): ?>
                <p class="small">No inspections yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Item</th><th>Result</th><th>Status after</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInspections as $ri): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ri['title'] ?: ('Item #' . (int) $ri['item_id'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $ri['result']))); ?></td>
                                    <td><?php echo htmlspecialchars(item_status_label((string) $ri['status_after'])); ?></td>
                                    <td><?php echo !empty($ri['created_at']) ? date('M j, Y g:i A', strtotime($ri['created_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
    document.querySelectorAll('.inspect-result').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var block = sel.closest('form').querySelector('[data-salvaged-fields]');
            if (!block) return;
            var show = sel.value === 'recyclable' || sel.value === 'not_repairable';
            block.classList.toggle('is-visible', show);
            block.querySelector('[name="salvaged_part_name"]').required = show;
        });
    });
    </script>
</body>
</html>
