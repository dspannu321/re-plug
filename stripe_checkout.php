<?php
session_start();
require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/config/csrf.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Require verified email before checkout
$stmt = $pdo->prepare("SELECT email_verified_at FROM users WHERE id = ?");
$stmt->execute([(int)$_SESSION['user']['id']]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($u['email_verified_at'])) {
    header('Location: marketplace.php?checkout=error&msg=' . urlencode('Please verify your email before purchasing.'));
    exit;
}

if (empty(STRIPE_SECRET_KEY)) {
    header('Location: marketplace.php?checkout=error&msg=' . urlencode('Stripe is not configured yet.'));
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    header('Location: marketplace.php?checkout=error&msg=' . urlencode('Stripe SDK missing. Run composer install.'));
    exit;
}
require_once $autoload;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
}

$listingId = (int) ($_POST['listing_id'] ?? 0);
if ($listingId <= 0) {
    header('Location: marketplace.php?checkout=error&msg=' . urlencode('Invalid listing.'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, description, price, is_active FROM marketplace_listings WHERE id = ? LIMIT 1");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing || (int)$listing['is_active'] !== 1) {
        header('Location: marketplace.php?checkout=error&msg=' . urlencode('Listing is not available.'));
        exit;
    }

    $stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    $successUrl = APP_URL . '/marketplace.php?checkout=success&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = APP_URL . '/marketplace.php?listing_id=' . $listingId . '&checkout=cancel';

    $session = $stripe->checkout->sessions->create([
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'cad',
                'unit_amount' => (int) round(((float)$listing['price']) * 100),
                'product_data' => [
                    'name' => (string)$listing['title'],
                    'description' => (string)($listing['description'] ?? ''),
                ],
            ],
        ]],
        'metadata' => [
            'listing_id' => (string)$listingId,
            'buyer_user_id' => (string)((int)$_SESSION['user']['id']),
        ],
    ]);

    header('Location: ' . $session->url);
    exit;
} catch (Exception $e) {
    header('Location: marketplace.php?checkout=error&msg=' . urlencode('Checkout failed. ' . $e->getMessage()));
    exit;
}

