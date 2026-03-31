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

$techId = (int) $_SESSION['user']['id'];
$techName = $_SESSION['user']['name'] ?? 'Technician';
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_item'])) {
    require_valid_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $result = trim($_POST['result'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $statusAfter = trim($_POST['status_after'] ?? '');
    $estimatedRepairCostRaw = trim($_POST['estimated_repair_cost'] ?? '');
    $estimatedRepairCost = ($estimatedRepairCostRaw === '') ? null : (float) $estimatedRepairCostRaw;

    $allowedResults = ['working', 'repairable', 'not_repairable'];
    $allowedStatuses = ['approved_for_sale', 'repair_in_progress', 'recycled'];
    $validPair = (
        ($result === 'working' && $statusAfter === 'approved_for_sale') ||
        ($result === 'repairable' && in_array($statusAfter, ['repair_in_progress', 'approved_for_sale'], true)) ||
        ($result === 'not_repairable' && $statusAfter === 'recycled')
    );

    if ($itemId <= 0 || !in_array($result, $allowedResults, true) || !in_array($statusAfter, $allowedStatuses, true)) {
        $error = 'Invalid inspection input.';
    } elseif (!$validPair) {
        $error = 'Invalid result and status combination.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, status FROM items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $error = 'Item not found.';
                $pdo->rollBack();
            } elseif (($item['status'] ?? '') !== 'picked_up') {
                $error = 'Only picked up items can be inspected.';
                $pdo->rollBack();
            } else {
                $stmt = $pdo->prepare("INSERT INTO inspections (item_id, technician_user_id, result, notes, estimated_repair_cost, status_after) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$itemId, $techId, $result, $notes, $estimatedRepairCost, $statusAfter]);
                $inspectionId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE items SET status = ? WHERE id = ?");
                $stmt->execute([$statusAfter, $itemId]);
                log_audit($pdo, $techId, 'item', $itemId, 'inspect_item', [
                    'inspection_id' => $inspectionId,
                    'result' => $result,
                    'status_after' => $statusAfter,
                    'estimated_repair_cost' => $estimatedRepairCost,
                ]);

                $pdo->commit();
                $msg = 'Inspection saved.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not save inspection. Make sure inspections table exists.';
        }
    }
}

$queueItems = [];
try {
    $sql = "SELECT i.id, i.title, i.category, i.description, i.condition_notes, i.created_at,
                u.name AS recycler_name, u.email AS recycler_email
            FROM items i
            JOIN users u ON u.id = i.recycler_user_id
            WHERE i.status = 'picked_up'
            ORDER BY i.updated_at DESC, i.id DESC";
    $stmt = $pdo->query($sql);
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $error ?: 'Could not load inspection queue.';
}

$recentInspections = [];
try {
    $sql = "SELECT ins.id, ins.item_id, ins.result, ins.status_after, ins.estimated_repair_cost, ins.created_at,
                i.title
            FROM inspections ins
            JOIN items i ON i.id = ins.item_id
            WHERE ins.technician_user_id = ?
            ORDER BY ins.created_at DESC, ins.id DESC
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$techId]);
    $recentInspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // inspections table may not exist
}

function item_status_label_tech($status) {
    $labels = [
        'approved_for_sale' => 'Approved for sale',
        'repair_in_progress' => 'Repair in progress',
        'recycled' => 'Recycled',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #EEF1F5; color: #1F2933; }
        .header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 0 1.5rem; }
        .header-inner { max-width: 1120px; margin: 0 auto; min-height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .logo { display: flex; align-items: center; gap: .7rem; color: inherit; text-decoration: none; font-weight: 700; }
        .logo img { height: 36px; width: auto; }
        .nav { display: flex; align-items: center; gap: .5rem; }
        .btn { display: inline-block; padding: 8px 13px; border-radius: 7px; font-size: 13px; font-weight: 500; border: 1px solid #E5E7EB; text-decoration: none; color: #1F2933; background: #fff; cursor: pointer; }
        .btn:hover { border-color: #1E88E5; color: #1E88E5; }
        .btn-primary { background: #1E88E5; color: #fff; border-color: #1E88E5; }
        .btn-primary:hover { background: #1565C0; border-color: #1565C0; color: #fff; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 1.6rem 1.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: .3rem; }
        .desc { color: #5F6C7B; font-size: 14px; margin-bottom: 1rem; }
        .msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; }
        .msg.success { background: #E8F5EE; color: #2FAE66; }
        .msg.error { background: #FFEBEE; color: #E53935; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(31,41,51,.06); padding: 1rem 1.1rem; margin-bottom: 1rem; }
        .card h2 { font-size: 1rem; margin-bottom: .75rem; }
        .small { color: #5F6C7B; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .item-title { font-size: 15px; font-weight: 600; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .form-group { margin-bottom: .75rem; }
        label { display: block; font-size: 12px; font-weight: 600; color: #425466; margin-bottom: .25rem; }
        input, select, textarea { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 10px; font-size: 13px; font-family: inherit; }
        textarea { min-height: 70px; resize: vertical; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #F0F2F5; }
        th { font-size: 12px; color: #5F6C7B; text-transform: uppercase; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #E8F5E9; color: #2E7D32; }
        @media (max-width: 760px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a class="logo" href="technician.php"><img src="public/assets/images/logo.png" alt="RePlug">RePlug Technician</a>
            <nav class="nav">
                <span class="small"><?php echo htmlspecialchars($techName); ?></span>
                <a href="login.php?logout=1" class="btn">Log out</a>
            </nav>
        </div>
    </header>
    <main class="wrap">
        <h1>Inspection Queue</h1>
        <p class="desc">Inspect picked-up items and set post-inspection status.</p>
        <?php if ($msg): ?><p class="msg success"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="msg error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if (count($queueItems) === 0): ?>
            <p class="desc">No picked-up items waiting for inspection.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($queueItems as $it): ?>
                    <div class="card">
                        <div class="item-title"><?php echo htmlspecialchars($it['title']); ?> <span class="small">(<?php echo htmlspecialchars($it['category'] ?: 'General'); ?>)</span></div>
                        <p class="small" style="margin-top:.2rem;">Recycler: <?php echo htmlspecialchars($it['recycler_name']); ?> (<?php echo htmlspecialchars($it['recycler_email']); ?>)</p>
                        <?php if (!empty($it['description'])): ?><p class="small" style="margin-top:.25rem;">Description: <?php echo htmlspecialchars($it['description']); ?></p><?php endif; ?>
                        <?php if (!empty($it['condition_notes'])): ?><p class="small" style="margin-top:.25rem;">Condition notes: <?php echo htmlspecialchars($it['condition_notes']); ?></p><?php endif; ?>

                        <form method="post" action="technician.php" style="margin-top:.75rem;">
                            <input type="hidden" name="inspect_item" value="1">
                            <input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>">
                            <div class="row">
                                <div class="form-group">
                                    <label>Result</label>
                                    <select name="result" required>
                                        <option value="">Select result</option>
                                        <option value="working">Working</option>
                                        <option value="repairable">Repairable</option>
                                        <option value="not_repairable">Not repairable</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status after inspection</label>
                                    <select name="status_after" required>
                                        <option value="">Select status</option>
                                        <option value="approved_for_sale">Approved for sale</option>
                                        <option value="repair_in_progress">Repair in progress</option>
                                        <option value="recycled">Recycled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group">
                                    <label>Estimated repair cost (optional)</label>
                                    <input type="number" name="estimated_repair_cost" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" placeholder="Inspection notes..."></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save inspection</button>
                        </form>
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
                            <tr>
                                <th>Item</th>
                                <th>Result</th>
                                <th>Status after</th>
                                <th>Estimated cost</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInspections as $ri): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ri['title'] ?: ('Item #' . (int)$ri['item_id'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$ri['result']))); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars(item_status_label_tech($ri['status_after'])); ?></span></td>
                                    <td><?php echo $ri['estimated_repair_cost'] !== null ? ('PHP ' . number_format((float)$ri['estimated_repair_cost'], 2)) : '-'; ?></td>
                                    <td><?php echo !empty($ri['created_at']) ? date('M j, Y g:i A', strtotime($ri['created_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

