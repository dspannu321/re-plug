<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/app/config/db.php';

$buyerUserId = (int) $_SESSION['user']['id'];
$orders = [];
$error = '';

try {
    $stmt = $pdo->prepare("SELECT o.id, o.amount, o.status, o.created_at,
        ml.id AS listing_id, ml.title AS listing_title
        FROM orders o
        LEFT JOIN marketplace_listings ml ON ml.id = o.listing_id
        WHERE o.buyer_user_id = ?
        ORDER BY o.created_at DESC, o.id DESC");
    $stmt->execute([$buyerUserId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Orders are not available yet. Run orders_table.sql first.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — RePlug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #F7F9FB; color: #1F2933; }
        .header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 0 1.5rem; }
        .header-inner { max-width: 1100px; margin: 0 auto; min-height: 68px; display: flex; justify-content: space-between; align-items: center; }
        .logo { text-decoration: none; color: inherit; font-weight: 700; font-size: 1.2rem; display: inline-flex; align-items: center; gap: .5rem; }
        .logo img { height: 38px; width: auto; }
        .btn { display: inline-block; padding: 9px 13px; border-radius: 7px; border: 1px solid #E5E7EB; color: #1F2933; text-decoration: none; font-size: 14px; }
        .btn:hover { border-color: #1E88E5; color: #1E88E5; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        h1 { font-size: 1.6rem; margin-bottom: .25rem; }
        .desc { color: #5F6C7B; margin-bottom: 1rem; font-size: 14px; }
        .msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 1rem; font-size: 14px; background: #FFEBEE; color: #E53935; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #F0F2F5; }
        th { color: #5F6C7B; text-transform: uppercase; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; background: #E8F5EE; color: #2FAE66; font-size: 11px; font-weight: 600; }
        .empty { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 1rem; color: #5F6C7B; font-size: 14px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a class="logo" href="index.php"><img src="public/assets/images/logo.png" alt="RePlug">RePlug</a>
            <div style="display:flex; gap:.5rem;">
                <a class="btn" href="marketplace.php">Marketplace</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>
    </header>
    <main class="wrap">
        <h1>My Orders</h1>
        <p class="desc">Your completed marketplace purchases.</p>
        <?php if ($error): ?>
            <p class="msg"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (count($orders) === 0): ?>
            <div class="empty">You don't have any orders yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Listing</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td>#<?php echo (int)$o['id']; ?></td>
                                <td>
                                    <?php if (!empty($o['listing_id'])): ?>
                                        <a href="marketplace.php?listing_id=<?php echo (int)$o['listing_id']; ?>"><?php echo htmlspecialchars($o['listing_title'] ?: ('Listing #' . (int)$o['listing_id'])); ?></a>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>$<?php echo number_format((float)$o['amount'], 2); ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars(ucfirst((string)$o['status'])); ?></span></td>
                                <td><?php echo !empty($o['created_at']) ? date('M j, Y g:i A', strtotime($o['created_at'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
