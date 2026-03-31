<?php
session_start();
require_once __DIR__ . '/app/config/db.php';

$token = trim($_GET['token'] ?? '');
$success = false;
$message = '';

if ($token === '') {
    $message = 'Invalid verification link.';
} else {
    $stmt = $pdo->prepare("SELECT ev.id, ev.user_id, ev.expires_at, u.email_verified_at
        FROM email_verifications ev
        JOIN users u ON u.id = ev.user_id
        WHERE ev.token = ?
        LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $message = 'Verification link is invalid or already used.';
    } elseif (!empty($row['email_verified_at'])) {
        $success = true;
        $message = 'Your email is already verified. You can log in.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $message = 'Verification link has expired.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")
                ->execute([(int)$row['user_id']]);
            $pdo->prepare("DELETE FROM email_verifications WHERE id = ?")
                ->execute([(int)$row['id']]);
            $pdo->commit();
            $success = true;
            $message = 'Your email has been verified. You can now use all features.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Could not verify email. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email verification — RePlug</title>
</head>
<body>
    <h1><?php echo $success ? 'Email verified' : 'Verification problem'; ?></h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <p><a href="login.php">Go to login</a></p>
</body>
</html>

