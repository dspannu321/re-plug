<?php
/**
 * RePlug — Public marketplace listings.
 */
session_start();
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/audit.php';
require_once __DIR__ . '/app/config/csrf.php';

$listingId = (int) ($_GET['listing_id'] ?? 0);
$listingError = '';
$checkoutMsg = '';
$listing = null;
$listings = [];
$isLoggedIn = false;
$currentUserRole = '';

if (!empty($_SESSION['user'])) {
    $sessionUserId = (int) ($_SESSION['user']['id'] ?? 0);
    if ($sessionUserId > 0) {
        $stmt = $pdo->prepare("SELECT role, email_verified_at FROM users WHERE id = ?");
        $stmt->execute([$sessionUserId]);
        $authUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($authUser && !empty($authUser['email_verified_at'])) {
            $isLoggedIn = true;
            $currentUserRole = (string) ($authUser['role'] ?? '');
        } else {
            $_SESSION = [];
        }
    } else {
        $_SESSION = [];
    }
}

try {
    if (isset($_GET['checkout']) && $_GET['checkout'] === 'cancel') {
        $checkoutMsg = 'Checkout was cancelled.';
    } elseif (isset($_GET['checkout']) && $_GET['checkout'] === 'error') {
        $checkoutMsg = trim($_GET['msg'] ?? 'Checkout failed.');
    } elseif (isset($_GET['checkout']) && $_GET['checkout'] === 'success') {
        if (!$isLoggedIn) {
            $listingError = 'Please log in first.';
        } elseif (empty(STRIPE_SECRET_KEY)) {
            $listingError = 'Stripe is not configured.';
        } else {
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                $listingError = 'Stripe SDK missing. Run composer install.';
            } else {
                require_once $autoload;
                $sessionId = trim($_GET['session_id'] ?? '');
                if ($sessionId === '') {
                    $listingError = 'Invalid Stripe session.';
                } else {
                    $stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
                    $checkoutSession = $stripe->checkout->sessions->retrieve($sessionId);
                    if (($checkoutSession->payment_status ?? '') !== 'paid') {
                        $listingError = 'Payment is not completed yet.';
                    } else {
                        $buyerUserId = (int) ($_SESSION['user']['id'] ?? 0);
                        $listingIdFromMeta = (int) ($checkoutSession->metadata->listing_id ?? 0);
                        if ($listingIdFromMeta <= 0 || $buyerUserId <= 0) {
                            $listingError = 'Invalid payment metadata.';
                        } else {
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ? LIMIT 1");
                            $stmt->execute([$sessionId]);
                            if (!$stmt->fetch()) {
                                $stmt = $pdo->prepare("SELECT ml.id, ml.item_id, ml.price, ml.is_active, i.recycler_user_id
                                    FROM marketplace_listings ml
                                    JOIN items i ON i.id = ml.item_id
                                    WHERE ml.id = ?
                                    FOR UPDATE");
                                $stmt->execute([$listingIdFromMeta]);
                                $dbListing = $stmt->fetch(PDO::FETCH_ASSOC);
                                if (!$dbListing) {
                                    $pdo->rollBack();
                                    $listingError = 'Listing no longer exists.';
                                } else {
                                    $amount = (float) $dbListing['price'];
                                    $stmt = $pdo->prepare("INSERT INTO orders (buyer_user_id, listing_id, amount, status, stripe_session_id) VALUES (?, ?, ?, 'paid', ?)");
                                    $stmt->execute([$buyerUserId, $listingIdFromMeta, $amount, $sessionId]);
                                    $orderId = (int) $pdo->lastInsertId();

                                    // Revenue split and payout record (one payout per paid order).
                                    $recyclerShare = round($amount * (RECYCLER_SHARE_PERCENT / 100), 2);
                                    $recyclerUserId = (int) ($dbListing['recycler_user_id'] ?? 0);
                                    if ($recyclerUserId > 0 && $recyclerShare > 0) {
                                        $stmt = $pdo->prepare("INSERT INTO payouts (recycler_user_id, order_id, amount, status) VALUES (?, ?, ?, 'unpaid')");
                                        $stmt->execute([$recyclerUserId, $orderId, $recyclerShare]);
                                        $payoutId = (int) $pdo->lastInsertId();
                                        log_audit($pdo, $buyerUserId, 'payout', $payoutId, 'create_payout', [
                                            'order_id' => $orderId,
                                            'recycler_user_id' => $recyclerUserId,
                                            'amount' => $recyclerShare,
                                        ]);
                                    }

                                    $stmt = $pdo->prepare("UPDATE marketplace_listings SET is_active = 0 WHERE id = ?");
                                    $stmt->execute([$listingIdFromMeta]);
                                    $stmt = $pdo->prepare("UPDATE items SET status = 'sold' WHERE id = (SELECT item_id FROM marketplace_listings WHERE id = ?)");
                                    $stmt->execute([$listingIdFromMeta]);
                                    log_audit($pdo, $buyerUserId, 'order', $orderId, 'paid_order', [
                                        'listing_id' => $listingIdFromMeta,
                                        'amount' => $amount,
                                        'stripe_session_id' => $sessionId,
                                    ]);
                                    $pdo->commit();
                                    $checkoutMsg = 'Payment successful. Your order has been placed.';
                                }
                            } else {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                $checkoutMsg = 'Payment already recorded. Thank you.';
                            }
                            $listingId = $listingIdFromMeta;
                        }
                    }
                }
            }
        }
    }

    if ($listingId > 0) {
        $stmt = $pdo->prepare("SELECT ml.id, ml.item_id, ml.price, ml.title, ml.description, ml.is_active, ml.created_at,
            i.photos_json, i.category
            FROM marketplace_listings ml
            JOIN items i ON i.id = ml.item_id
            WHERE ml.id = ? AND ml.is_active = 1
            LIMIT 1");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$listing) {
            $listingError = 'Listing not found.';
        }
    }

    $stmt = $pdo->query("SELECT ml.id, ml.item_id, ml.price, ml.title, ml.description, ml.created_at,
        i.photos_json, i.category
        FROM marketplace_listings ml
        JOIN items i ON i.id = ml.item_id
        WHERE ml.is_active = 1
        ORDER BY ml.created_at DESC, ml.id DESC");
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $listingError = 'Marketplace is not ready yet. Please set up marketplace tables.';
}

function listing_photo_url($photosJson) {
    $arr = json_decode((string)$photosJson, true);
    if (is_array($arr) && !empty($arr[0])) {
        return 'public/storage/uploads/' . ltrim((string)$arr[0], '/');
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace — RePlug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #F7F9FB; color: #1F2933; line-height: 1.5; }
        a { color: #1E88E5; text-decoration: none; }
        a:hover { color: #1565C0; }
        .header { background: #fff; border-bottom: 1px solid #E5E7EB; padding: 0 1.5rem; }
        .header-inner { max-width: 1120px; margin: 0 auto; min-height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .header-logo { display: flex; align-items: center; gap: .75rem; color: inherit; text-decoration: none; }
        .header-logo img { height: 42px; width: auto; display: block; }
        .header-logo span { font-size: 1.2rem; font-weight: 700; color: #1F2933; }
        .header-nav { display: flex; gap: .5rem; align-items: center; }
        .btn { display: inline-block; padding: 9px 14px; border-radius: 7px; font-size: 14px; font-weight: 500; }
        .btn-secondary { border: 1px solid #E5E7EB; color: #1F2933; background: #fff; }
        .btn-secondary:hover { border-color: #1E88E5; color: #1E88E5; }
        .btn-primary { border: 1px solid #1E88E5; color: #fff; background: #1E88E5; }
        .btn-primary:hover { background: #1565C0; border-color: #1565C0; color: #fff; }

        .wrap { max-width: 1120px; margin: 0 auto; padding: 1.5rem; }
        .title { font-size: 1.7rem; font-weight: 700; margin-bottom: .25rem; }
        .desc { color: #5F6C7B; font-size: 14px; margin-bottom: 1.25rem; }
        .msg { padding: 10px 14px; margin-bottom: 1rem; font-size: 14px; border-radius: 8px; background: #FFEBEE; color: #E53935; }
        .msg-success { background: #E8F5EE; color: #2FAE66; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
        .card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(31,41,51,.05); }
        .thumb { width: 100%; height: 160px; object-fit: cover; display: block; background: #EEF1F5; }
        .thumb-none { width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; color: #5F6C7B; background: #EEF1F5; font-size: 13px; }
        .body { padding: .9rem 1rem 1rem; }
        .name { font-size: 15px; font-weight: 600; margin-bottom: .25rem; }
        .meta { color: #5F6C7B; font-size: 12px; margin-bottom: .6rem; }
        .price { font-size: 18px; font-weight: 700; color: #2FAE66; margin-bottom: .65rem; }

        .detail { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 1rem; margin-bottom: 1.2rem; display: grid; grid-template-columns: minmax(280px, 360px) 1fr; gap: 1rem; }
        .detail-photo img { width: 100%; border-radius: 8px; display: block; background: #EEF1F5; }
        .detail-photo .thumb-none { border-radius: 8px; }
        .detail h2 { font-size: 1.2rem; margin-bottom: .5rem; }
        .detail .detail-price { font-size: 20px; font-weight: 700; color: #2FAE66; margin: .5rem 0; }
        .detail .detail-p { font-size: 14px; color: #425466; white-space: pre-wrap; }
        @media (max-width: 780px) { .detail { grid-template-columns: 1fr; } }
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
                <a href="index.php" class="btn btn-secondary">Home</a>
                <?php if ($isLoggedIn): ?>
                    <a href="my_orders.php" class="btn btn-secondary">My orders</a>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="login.php?logout=1" class="btn btn-primary">Log out</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                    <a href="login.php" class="btn btn-primary">Log in</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="wrap">
        <h1 class="title">Marketplace</h1>
        <p class="desc">Refurbished and approved items available for reuse.</p>

        <?php if ($listingError): ?>
            <p class="msg"><?php echo htmlspecialchars($listingError); ?></p>
        <?php endif; ?>
        <?php if ($checkoutMsg): ?>
            <p class="msg msg-success"><?php echo htmlspecialchars($checkoutMsg); ?></p>
        <?php endif; ?>

        <?php if ($listing): ?>
            <?php $detailPhoto = listing_photo_url($listing['photos_json']); ?>
            <section class="detail">
                <div class="detail-photo">
                    <?php if ($detailPhoto && is_file(__DIR__ . '/' . $detailPhoto)): ?>
                        <img src="<?php echo htmlspecialchars($detailPhoto); ?>" alt="">
                    <?php else: ?>
                        <div class="thumb-none">No photo</div>
                    <?php endif; ?>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
                    <p class="meta">Category: <?php echo htmlspecialchars($listing['category'] ?: 'General'); ?> • Listed <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></p>
                    <p class="detail-price">$<?php echo number_format((float)$listing['price'], 2); ?></p>
                    <p class="detail-p"><?php echo nl2br(htmlspecialchars($listing['description'] ?: 'No description provided.')); ?></p>
                    <div style="margin-top: 1rem;">
                        <?php if ($isLoggedIn): ?>
                            <?php if ($currentUserRole === 'admin'): ?>
                                <span class="desc">Admins cannot purchase listings.</span>
                            <?php else: ?>
                                <form method="post" action="stripe_checkout.php">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="listing_id" value="<?php echo (int)$listing['id']; ?>">
                                    <button type="submit" class="btn btn-primary">Buy with Stripe</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Log in to buy</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (count($listings) === 0): ?>
            <p class="desc">No active marketplace listings yet.</p>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($listings as $row): ?>
                    <?php $photo = listing_photo_url($row['photos_json']); ?>
                    <article class="card">
                        <?php if ($photo && is_file(__DIR__ . '/' . $photo)): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="" class="thumb">
                        <?php else: ?>
                            <div class="thumb-none">No photo</div>
                        <?php endif; ?>
                        <div class="body">
                            <div class="name"><?php echo htmlspecialchars($row['title']); ?></div>
                            <div class="meta"><?php echo htmlspecialchars($row['category'] ?: 'General'); ?> • <?php echo date('M j, Y', strtotime($row['created_at'])); ?></div>
                            <div class="price">$<?php echo number_format((float)$row['price'], 2); ?></div>
                            <a class="btn btn-secondary" href="marketplace.php?listing_id=<?php echo (int)$row['id']; ?>">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
