<?php
/**
 * RePlug — User dashboard: Profile (password, avatar) and My listings.
 */
session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/app/config/db.php';

/** Human-readable labels for item statuses */
function item_status_label($status) {
    $labels = [
        'draft' => 'Draft',
        'pickup_requested' => 'Pickup requested',
        'scheduled' => 'Scheduled',
        'picked_up' => 'Picked up',
        'inspected' => 'Inspected',
        'recycled' => 'Recycled',
        'repair_in_progress' => 'Repair in progress',
        'approved_for_sale' => 'Approved for sale',
        'listed_for_sale' => 'Listed for sale',
        'sold' => 'Sold',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

/** Human-readable labels for pickup statuses */
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

// Load fresh user row (for avatar)
$stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($userRow) {
    $user['avatar'] = $userRow['avatar'] ?? null;
}

$section = isset($_GET['section']) ? $_GET['section'] : 'listings';
if (!in_array($section, ['profile', 'listings', 'pickups'], true)) {
    $section = 'listings';
}

$uploadDir = __DIR__ . '/public/storage/uploads';
$avatarsDir = $uploadDir . '/avatars';
$itemsDir = $uploadDir . '/items';
$profileMsg = '';
$profileError = '';
$listingError = '';
$listingSuccess = '';
$pickupError = '';
$pickupSuccess = '';
if ($section === 'listings' && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $listingSuccess = 'Listing updated.';
    if ($_GET['msg'] === 'deleted') $listingSuccess = 'Listing deleted.';
}
if ($section === 'pickups' && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $pickupSuccess = 'Pickup updated.';
    if ($_GET['msg'] === 'cancelled') $pickupSuccess = 'Pickup cancelled.';
}

// Ensure upload dirs exist
if (!is_dir($avatarsDir)) {
    @mkdir($avatarsDir, 0755, true);
}
if (!is_dir($itemsDir)) {
    @mkdir($itemsDir, 0755, true);
}

// ---------- Profile: avatar upload ----------
if ($section === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_avatar'])) {
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
        $profileError = $profileError ? $profileError . ' ' : '';
        $profileError .= 'Fill all password fields.';
    } elseif (strlen($new) < 8) {
        $profileError = $profileError ? $profileError . ' ' : '';
        $profileError .= 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $profileError = $profileError ? $profileError . ' ' : '';
        $profileError .= 'New passwords do not match.';
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
            $profileError = $profileError ? $profileError . ' ' : '';
            $profileError .= 'Current password is wrong.';
        }
    }
}

// ---------- Listings: create new item ----------
if ($section === 'listings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_listing'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $condition_notes = trim($_POST['condition_notes'] ?? '');
    if ($title === '' || $category === '') {
        $listingError = 'Title and category are required.';
    } else {
        $photos = [];
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['photo']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($mime, $allowed, true) && $_FILES['photo']['size'] <= 3 * 1024 * 1024) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
                $filename = 'item_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $itemsDir . '/' . $filename)) {
                    $photos[] = 'items/' . $filename;
                }
            }
        }
        $photosJson = $photos ? json_encode($photos) : null;
        try {
            $stmt = $pdo->prepare("INSERT INTO items (recycler_user_id, category, title, description, condition_notes, photos_json, status) VALUES (?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$userId, $category, $title, $description, $condition_notes, $photosJson]);
            $listingSuccess = 'Listing created.';
        } catch (PDOException $e) {
            $listingError = 'Could not create listing. Make sure the items table exists.';
        }
    }
}

// ---------- Listings: edit item ----------
if ($section === 'listings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    if ($itemId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM items WHERE id = ? AND recycler_user_id = ?");
        $stmt->execute([$itemId, $userId]);
        if ($stmt->fetch()) {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $condition_notes = trim($_POST['condition_notes'] ?? '');
            if ($title !== '' && $category !== '') {
                $photos = [];
                $stmt = $pdo->prepare("SELECT photos_json FROM items WHERE id = ?");
                $stmt->execute([$itemId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['photos_json']) {
                    $photos = json_decode($row['photos_json'], true) ?: [];
                }
                if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['photo']['tmp_name']);
                    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($mime, $allowed, true) && $_FILES['photo']['size'] <= 3 * 1024 * 1024) {
                        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
                        $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
                        $filename = 'item_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $itemsDir . '/' . $filename)) {
                            $photos = ['items/' . $filename];
                        }
                    }
                }
                $photosJson = $photos ? json_encode($photos) : null;
                $stmt = $pdo->prepare("UPDATE items SET title = ?, category = ?, description = ?, condition_notes = ?, photos_json = ? WHERE id = ? AND recycler_user_id = ?");
                $stmt->execute([$title, $category, $description, $condition_notes, $photosJson, $itemId, $userId]);
                header('Location: dashboard.php?section=listings&msg=updated');
                exit;
            } else {
                $listingError = 'Title and category are required.';
            }
        }
    }
}

// ---------- Listings: delete item ----------
if ($section === 'listings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    if ($itemId > 0) {
        $stmt = $pdo->prepare("SELECT id, status FROM items WHERE id = ? AND recycler_user_id = ?");
        $stmt->execute([$itemId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['status'] ?? '') === 'draft') {
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ? AND recycler_user_id = ?");
            $stmt->execute([$itemId, $userId]);
            header('Location: dashboard.php?section=listings&msg=deleted');
            exit;
        }
    }
}

// Fetch user's items (check table exists)
$items = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, category, description, condition_notes, photos_json, status, created_at FROM items WHERE recycler_user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // items table may not exist yet
}

// ---------- Pickups: request new pickup ----------
if ($section === 'pickups' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_pickup'])) {
    $itemIds = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
    $address = trim($_POST['address_text'] ?? '');
    $windowStart = trim($_POST['pickup_window_start'] ?? '');
    $windowEnd = trim($_POST['pickup_window_end'] ?? '');
    if (empty($itemIds)) {
        $pickupError = 'Select at least one item.';
    } elseif ($address === '') {
        $pickupError = 'Please enter the pickup address.';
    } elseif ($windowStart === '' || $windowEnd === '') {
        $pickupError = 'Please select pickup window start and end.';
    } elseif (strtotime($windowEnd) <= strtotime($windowStart)) {
        $pickupError = 'Window end must be after window start.';
    } else {
        $itemIds = array_unique(array_filter($itemIds, function ($id) { return $id > 0; }));
        $stmt = $pdo->prepare("SELECT id FROM items WHERE id = ? AND recycler_user_id = ? AND status = 'draft'");
        $validIds = [];
        foreach ($itemIds as $itemId) {
            $stmt->execute([$itemId, $userId]);
            if ($stmt->fetch()) {
                $validIds[] = $itemId;
            }
        }
        if (empty($validIds)) {
            $pickupError = 'Selected items are invalid or not in draft.';
        } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO pickups (recycler_user_id, pickup_window_start, pickup_window_end, address_text, status) VALUES (?, ?, ?, ?, 'requested')");
            $stmt->execute([$userId, $windowStart, $windowEnd, $address]);
            $pickupId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO pickup_items (pickup_id, item_id) VALUES (?, ?)");
            foreach ($validIds as $itemId) {
                $stmt->execute([$pickupId, $itemId]);
            }
            $stmt = $pdo->prepare("UPDATE items SET status = 'pickup_requested' WHERE id = ? AND recycler_user_id = ?");
            foreach ($validIds as $itemId) {
                $stmt->execute([$itemId, $userId]);
            }
            $pdo->commit();
            $pickupSuccess = 'Pickup requested. We will assign a driver and confirm the schedule.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $pickupError = 'Could not create pickup. Make sure the pickups table exists.';
        }
        }
    }
}

// ---------- Pickups: edit pickup (only if not picked_up) ----------
if ($section === 'pickups' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pickup'])) {
    $pickupId = (int) ($_POST['pickup_id'] ?? 0);
    if ($pickupId > 0) {
        $stmt = $pdo->prepare("SELECT id, status FROM pickups WHERE id = ? AND recycler_user_id = ?");
        $stmt->execute([$pickupId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && in_array($row['status'], ['requested', 'scheduled'], true)) {
            $address = trim($_POST['address_text'] ?? '');
            $windowStart = trim($_POST['pickup_window_start'] ?? '');
            $windowEnd = trim($_POST['pickup_window_end'] ?? '');
            $itemIds = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('intval', array_filter($_POST['item_ids'])) : [];
            if ($address !== '' && $windowStart !== '' && $windowEnd !== '' && strtotime($windowEnd) > strtotime($windowStart)) {
                if (empty($itemIds)) {
                    $pickupError = 'Select at least one item for the pickup.';
                } else {
                    $stmt = $pdo->prepare("SELECT item_id FROM pickup_items WHERE pickup_id = ?");
                    $stmt->execute([$pickupId]);
                    $oldItemIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id');
                    $stmt = $pdo->prepare("SELECT id FROM items WHERE id = ? AND recycler_user_id = ? AND (status = 'draft' OR status = 'pickup_requested')");
                    $validIds = [];
                    foreach (array_unique($itemIds) as $iid) {
                        if ($iid <= 0) continue;
                        $stmt->execute([$iid, $userId]);
                        if ($stmt->fetch()) $validIds[] = $iid;
                    }
                    if (empty($validIds)) {
                        $pickupError = 'Selected items are invalid.';
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare("UPDATE pickups SET address_text = ?, pickup_window_start = ?, pickup_window_end = ? WHERE id = ? AND recycler_user_id = ?");
                            $stmt->execute([$address, $windowStart, $windowEnd, $pickupId, $userId]);
                            $stmt = $pdo->prepare("DELETE FROM pickup_items WHERE pickup_id = ?");
                            $stmt->execute([$pickupId]);
                            $stmt = $pdo->prepare("INSERT INTO pickup_items (pickup_id, item_id) VALUES (?, ?)");
                            foreach ($validIds as $iid) {
                                $stmt->execute([$pickupId, $iid]);
                            }
                            foreach ($oldItemIds as $oid) {
                                if (!in_array($oid, $validIds, true)) {
                                    $pdo->prepare("UPDATE items SET status = 'draft' WHERE id = ? AND recycler_user_id = ?")->execute([$oid, $userId]);
                                }
                            }
                            foreach ($validIds as $iid) {
                                if (!in_array($iid, $oldItemIds, true)) {
                                    $pdo->prepare("UPDATE items SET status = 'pickup_requested' WHERE id = ? AND recycler_user_id = ?")->execute([$iid, $userId]);
                                }
                            }
                            $pdo->commit();
                            header('Location: dashboard.php?section=pickups&msg=updated');
                            exit;
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $pickupError = 'Could not update pickup.';
                        }
                    }
                }
            } else {
                $pickupError = 'Please fill address and a valid time window.';
            }
        }
    }
}

// ---------- Pickups: cancel pickup (only if not picked_up); release items back to draft ----------
if ($section === 'pickups' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_pickup'])) {
    $pickupId = (int) ($_POST['pickup_id'] ?? 0);
    if ($pickupId > 0) {
        $stmt = $pdo->prepare("SELECT id, status FROM pickups WHERE id = ? AND recycler_user_id = ?");
        $stmt->execute([$pickupId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['status'] !== 'picked_up') {
            $stmt = $pdo->prepare("SELECT item_id FROM pickup_items WHERE pickup_id = ?");
            $stmt->execute([$pickupId]);
            $itemIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_id');
            $stmt = $pdo->prepare("UPDATE pickups SET status = 'cancelled' WHERE id = ? AND recycler_user_id = ?");
            $stmt->execute([$pickupId, $userId]);
            foreach ($itemIds as $iid) {
                $pdo->prepare("UPDATE items SET status = 'draft' WHERE id = ? AND recycler_user_id = ?")->execute([$iid, $userId]);
            }
            header('Location: dashboard.php?section=pickups&msg=cancelled');
            exit;
        }
    }
}

// Fetch user's pickups (with item count and item titles)
$pickups = [];
$pickupItemTitles = [];
try {
    $stmt = $pdo->prepare("SELECT p.id, p.pickup_window_start, p.pickup_window_end, p.address_text, p.status, p.created_at,
        (SELECT COUNT(*) FROM pickup_items WHERE pickup_id = p.id) AS item_count
        FROM pickups p WHERE p.recycler_user_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$userId]);
    $pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pickupIds = array_column($pickups, 'id');
    $pickupItemIds = [];
    if (!empty($pickupIds)) {
        $placeholders = implode(',', array_fill(0, count($pickupIds), '?'));
        $stmt = $pdo->prepare("SELECT pi.pickup_id, pi.item_id, i.title FROM pickup_items pi JOIN items i ON i.id = pi.item_id WHERE pi.pickup_id IN ($placeholders)");
        $stmt->execute($pickupIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pickupItemTitles[$row['pickup_id']][] = $row['title'];
            $pickupItemIds[$row['pickup_id']][] = (int) $row['item_id'];
        }
    }
} catch (PDOException $e) {
    // pickups table may not exist
}
foreach ($pickups as &$pu) {
    $pu['item_titles'] = $pickupItemTitles[$pu['id']] ?? [];
    $pu['item_ids'] = $pickupItemIds[$pu['id']] ?? [];
    $pu['available_items'] = [];
    foreach ($items as $it) {
        $inThisPickup = in_array((int)$it['id'], $pu['item_ids'], true);
        $isDraft = ($it['status'] ?? '') === 'draft';
        if ($inThisPickup || $isDraft) {
            $pu['available_items'][] = ['id' => (int)$it['id'], 'title' => $it['title'], 'category' => $it['category'] ?? ''];
        }
    }
}
unset($pu);

// Draft items only (for request pickup form) — items not yet in a requested/scheduled pickup
$draftItems = array_filter($items, function ($i) {
    return ($i['status'] ?? '') === 'draft';
});

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
    <title>Dashboard — RePlug</title>
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
        .header-logo img { display: block; height: 36px; width: auto; max-width: 120px; max-height: 36px; object-fit: contain; }
        .header-logo span { font-size: 1.25rem; font-weight: 700; color: #1F2933; letter-spacing: -0.02em; }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .header-nav .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .header-nav .user-avatar {
            display: block;
            width: 36px;
            height: 36px;
            max-width: 36px;
            max-height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #E5E7EB;
        }
        .header-nav .user-avatar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #E8F4FD;
            color: #1E88E5;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-nav .user-name { font-size: 14px; font-weight: 500; color: #1F2933; }
        .header-nav .btn {
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            font-family: inherit;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
            background: #F7F9FB;
            color: #5F6C7B;
            border: none;
            text-decoration: none;
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
        .sidebar {
            flex-shrink: 0;
            width: 200px;
        }
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
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #1F2933;
            margin-bottom: 0.375rem;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            font-family: inherit;
            color: #1F2933;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1E88E5;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group .hint { font-size: 12px; color: #5F6C7B; margin-top: 0.25rem; }

        .btn-primary {
            display: inline-block;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            color: #FFFFFF;
            background: #1E88E5;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background: #1565C0; }
        .btn-secondary {
            display: inline-block;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            color: #1F2933;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .btn-secondary:hover { border-color: #1E88E5; color: #1E88E5; }
        .btn-danger {
            display: inline-block;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            color: #FFFFFF;
            background: #E53935;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-danger:hover { background: #C62828; color: #FFFFFF; }

        .msg { padding: 10px 14px; margin-bottom: 1rem; font-size: 14px; border-radius: 8px; }
        .msg.success { color: #2FAE66; background: #E8F5EE; }
        .msg.error { color: #E53935; background: #FFEBEE; }

        .avatar-wrap { margin-bottom: 1.5rem; }
        .avatar-wrap img { display: block; width: 96px; height: 96px; max-width: 96px; max-height: 96px; object-fit: cover; border-radius: 50%; border: 2px solid #E5E7EB; }
        .avatar-placeholder {
            width: 96px; height: 96px; border-radius: 50%;
            background: #E5E7EB;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: #5F6C7B;
        }

        .listings-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem; }
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .listing-card {
            background: #FFFFFF;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(31,41,51,0.06);
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s;
            border: 1px solid transparent;
        }
        .listing-card:hover {
            box-shadow: 0 4px 12px rgba(31,41,51,0.08);
            transform: translateY(-2px);
        }
        .listing-card .thumb {
            display: block;
            width: 100%;
            height: 140px;
            max-width: 100%;
            background: #F7F9FB;
            object-fit: cover;
        }
        .listing-card .thumb-none {
            width: 100%;
            height: 140px;
            background: #EEF1F5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5F6C7B;
            font-size: 13px;
        }
        .listing-card .body { padding: 1rem 1.1rem; }
        .listing-card .title { font-size: 15px; font-weight: 600; color: #1F2933; margin-bottom: 0.25rem; }
        .listing-card .meta { font-size: 12px; color: #5F6C7B; margin-bottom: 0.5rem; }
        .listing-card .status {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 500;
            border-radius: 6px;
            text-transform: capitalize;
            background: #F0F2F5;
            color: #5F6C7B;
        }

        .empty-listings {
            text-align: center;
            padding: 3rem 2rem;
            background: #FFFFFF;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(31,41,51,0.06);
        }
        .empty-listings p { color: #5F6C7B; margin-bottom: 1.5rem; }

        .pickup-card {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(31,41,51,0.06);
        }
        .pickup-card .pickup-meta { font-size: 13px; color: #5F6C7B; margin-bottom: 0.25rem; }
        .pickup-card .pickup-address { font-size: 14px; color: #1F2933; margin-bottom: 0.5rem; }
        .pickup-card .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 4px;
        }
        .pickup-card .status-requested { background: #E3F2FD; color: #1E88E5; }
        .pickup-card .status-scheduled { background: #E3F2FD; color: #1565C0; }
        .pickup-card .status-picked_up { background: #E8F5EE; color: #2FAE66; }
        .pickup-card .status-failed, .pickup-card .status-cancelled { background: #FFEBEE; color: #5F6C7B; }
        .pickup-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }
        .pickup-card-clickable {
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .pickup-card-clickable:hover {
            box-shadow: 0 4px 12px rgba(31,41,51,0.08);
            transform: translateY(-2px);
        }
        .item-checkbox { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; }
        .item-checkbox input { width: auto; }
        .pickup-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 600px) { .pickup-form-row { grid-template-columns: 1fr; } }

        /* Create pickup modal (from item modal) */
        .create-pickup-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(31,41,51,0.5); z-index: 1001; align-items: center; justify-content: center; padding: 1rem; }
        .create-pickup-modal-overlay.is-open { display: flex; }
        .create-pickup-modal { background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(31,41,51,0.15); max-width: 480px; width: 100%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        .create-pickup-modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #E8ECF0; }
        .create-pickup-modal__title { margin: 0; font-size: 1.125rem; font-weight: 600; color: #1F2933; }
        .create-pickup-modal__close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #5F6C7B; padding: 0 0.25rem; line-height: 1; }
        .create-pickup-modal__close:hover { color: #1F2933; }
        .create-pickup-modal__body { padding: 1.25rem; overflow-y: auto; }
        .create-pickup-modal__form-section { margin-bottom: 1rem; }
        .create-pickup-modal__form-section-title { font-weight: 600; color: #1F2933; margin-bottom: 0.5rem; }
        .create-pickup-modal .item-checkbox { padding: 0.4rem 0; }
        .create-pickup-modal__actions { display: flex; gap: 0.75rem; flex-wrap: wrap; padding: 1rem 1.25rem; border-top: 1px solid #E8ECF0; }
        .create-pickup-modal__no-drafts { color: #5F6C7B; font-size: 14px; }

        /* Pickup modal - improved layout, wider edit form */
        .pickup-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(31,41,51,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .pickup-modal-overlay.is-open { display: flex; }
        .pickup-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 520px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 24px 48px rgba(0,0,0,0.18); }
        .pickup-modal__header { flex-shrink: 0; padding: 1rem 1.5rem; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .pickup-modal__title { font-size: 1.15rem; font-weight: 600; color: #1F2933; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pickup-modal__close { flex-shrink: 0; width: 36px; height: 36px; border: none; background: #F7F9FB; border-radius: 8px; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #5F6C7B; }
        .pickup-modal__close:hover { background: #E5E7EB; color: #1F2933; }
        .pickup-modal__body { flex: 1; min-height: 0; overflow: auto; padding: 1.5rem; }
        .pickup-modal__panel { display: none; }
        .pickup-modal__panel.is-active { display: block; }
        .pickup-modal__view-detail { margin-bottom: 1.25rem; }
        .pickup-modal__view-detail:last-of-type { margin-bottom: 0; }
        .pickup-modal__view-label { font-size: 11px; font-weight: 600; color: #5F6C7B; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.35rem; }
        .pickup-modal__view-value { font-size: 14px; color: #1F2933; word-break: break-word; line-height: 1.55; }
        .pickup-modal__view-items { margin: 0.25rem 0 0; padding-left: 1.25rem; }
        .pickup-modal__view-items li { margin-bottom: 0.25rem; }
        .pickup-modal__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #E5E7EB; }
        .pickup-modal__btn-cancel { display: none; }
        .pickup-modal__btn-cancel.is-visible { display: inline-block; }
        .pickup-modal__cancel-note { margin-bottom: 1rem; padding: 1rem; background: #FFEBEE; border-radius: 8px; font-size: 14px; color: #5F6C7B; }
        .pickup-modal__form-section { margin-bottom: 1.5rem; }
        .pickup-modal__form-section:last-of-type { margin-bottom: 0; }
        .pickup-modal__form-section-title { font-size: 13px; font-weight: 600; color: #1F2933; margin-bottom: 0.75rem; padding-bottom: 0.35rem; border-bottom: 1px solid #E5E7EB; }
        .pickup-modal__item-list { max-height: 180px; overflow-y: auto; border: 1px solid #E5E7EB; border-radius: 8px; padding: 0.75rem; background: #F7F9FB; }
        .pickup-modal__item-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #E5E7EB; }
        .pickup-modal__item-row:last-child { border-bottom: none; padding-bottom: 0; }
        .pickup-modal__item-row input { width: auto; margin: 0; }
        .pickup-modal__item-row label { margin: 0; font-weight: 400; cursor: pointer; flex: 1; font-size: 14px; }
        .pickup-modal__edit-form .form-group { margin-bottom: 1rem; }
        .pickup-modal__edit-form .form-group label { font-size: 13px; font-weight: 500; color: #1F2933; margin-bottom: 0.35rem; display: block; }
        .pickup-modal__edit-form .form-group input,
        .pickup-modal__edit-form .form-group textarea { width: 100%; padding: 10px 12px; font-size: 14px; border: 1px solid #E5E7EB; border-radius: 8px; }
        .pickup-modal__edit-form .form-group textarea { min-height: 88px; resize: vertical; }
        .pickup-modal__edit-form .pickup-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 520px) { .pickup-modal__edit-form .pickup-form-row { grid-template-columns: 1fr; } }

        /* Item modal - all styles in CSS, no inline; BEM-style classes */
        .item-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(31,41,51,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .item-modal-overlay.is-open { display: flex; }
        .item-modal {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0,0,0,0.18);
        }
        .item-modal__header {
            flex-shrink: 0;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .item-modal__title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1F2933;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .item-modal__close {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border: none;
            background: #F7F9FB;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
            color: #5F6C7B;
        }
        .item-modal__close:hover { background: #E5E7EB; color: #1F2933; }
        .item-modal__body {
            flex: 1;
            min-height: 0;
            overflow: auto;
            padding: 1.25rem;
        }
        .item-modal__panel { display: none; }
        .item-modal__panel.is-active { display: block; }
        .item-modal__view-photo {
            width: 100%;
            max-height: 180px;
            background: #F0F2F5;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item-modal__view-photo img {
            display: block;
            max-width: 100%;
            max-height: 180px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .item-modal__view-photo-empty {
            width: 100%;
            height: 120px;
            background: #F0F2F5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5F6C7B;
            font-size: 13px;
            margin-bottom: 1.25rem;
        }
        .item-modal__view-detail { margin-bottom: 1rem; }
        .item-modal__view-detail:last-of-type { margin-bottom: 0; }
        .item-modal__view-label {
            font-size: 11px;
            font-weight: 600;
            color: #5F6C7B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.25rem;
        }
        .item-modal__view-value {
            font-size: 14px;
            color: #1F2933;
            word-break: break-word;
            line-height: 1.5;
        }
        .item-modal__actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #E5E7EB;
        }
        .item-modal__btn-delete { display: none; }
        .item-modal__btn-delete.is-visible { display: inline-block; }
        .item-modal__btn-request-pickup { display: none; }
        .item-modal__btn-request-pickup.is-visible { display: inline-block; }
        .item-modal__delete-note {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #FFEBEE;
            border-radius: 8px;
            font-size: 14px;
            color: #5F6C7B;
        }

        @media (max-width: 640px) {
            .main-wrap { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="dashboard.php" class="header-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
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
                <a href="login.php?logout=1" class="btn">Log out</a>
            </nav>
        </div>
    </header>

    <div class="main-wrap">
        <aside class="sidebar">
            <nav>
                <a href="dashboard.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">Profile</a>
                <a href="dashboard.php?section=listings" class="<?php echo $section === 'listings' ? 'active' : ''; ?>">My listings</a>
                <a href="dashboard.php?section=pickups" class="<?php echo $section === 'pickups' ? 'active' : ''; ?>">My Pickups</a>
            </nav>
        </aside>

        <main class="content">
            <?php if ($section === 'profile'): ?>
                <h1>Profile</h1>
                <p class="page-desc">Update your avatar and password.</p>
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
                    <form method="post" action="dashboard.php?section=profile" enctype="multipart/form-data">
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
                    <form method="post" action="dashboard.php?section=profile">
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

            <?php if ($section === 'listings'): ?>
                <h1>My listings</h1>
                <p class="page-desc">Click an item to view, edit, or delete it.</p>
                <?php if ($listingSuccess): ?><p class="msg success"><?php echo htmlspecialchars($listingSuccess); ?></p><?php endif; ?>
                <?php if ($listingError): ?><p class="msg error"><?php echo htmlspecialchars($listingError); ?></p><?php endif; ?>

                <?php if (count($items) === 0): ?>
                    <div class="empty-listings">
                        <p>You don’t have any listings yet. Create your first one to request a pickup.</p>
                        <form method="post" action="dashboard.php?section=listings" enctype="multipart/form-data" style="max-width: 420px; margin: 0 auto; text-align: left;">
                            <input type="hidden" name="create_listing" value="1">
                            <div class="form-group">
                                <label for="title">Title *</label>
                                <input type="text" id="title" name="title" required placeholder="e.g. Laptop, Microwave">
                            </div>
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <input type="text" id="category" name="category" required placeholder="e.g. Electronics, Small appliance">
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" placeholder="Brief description of the item"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="condition_notes">Condition notes</label>
                                <textarea id="condition_notes" name="condition_notes" placeholder="Working, for parts, etc."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="photo">Photo (optional)</label>
                                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                                <p class="hint">Max 3 MB.</p>
                            </div>
                            <button type="submit" class="btn-primary">Create listing</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="listings-header">
                        <span></span>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('new-listing-form').style.display = document.getElementById('new-listing-form').style.display === 'none' ? 'block' : 'none'">+ New listing</button>
                    </div>
                    <div id="new-listing-form" class="card" style="display: none;">
                        <h2>New listing</h2>
                        <form method="post" action="dashboard.php?section=listings" enctype="multipart/form-data">
                            <input type="hidden" name="create_listing" value="1">
                            <div class="form-group">
                                <label for="title2">Title *</label>
                                <input type="text" id="title2" name="title" required placeholder="e.g. Laptop, Microwave">
                            </div>
                            <div class="form-group">
                                <label for="category2">Category *</label>
                                <input type="text" id="category2" name="category" required placeholder="e.g. Electronics, Small appliance">
                            </div>
                            <div class="form-group">
                                <label for="description2">Description</label>
                                <textarea id="description2" name="description" placeholder="Brief description"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="condition_notes2">Condition notes</label>
                                <textarea id="condition_notes2" name="condition_notes" placeholder="Working, for parts, etc."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="photo2">Photo (optional)</label>
                                <input type="file" id="photo2" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            </div>
                            <button type="submit" class="btn-primary">Create listing</button>
                        </form>
                    </div>
                    <div class="listings-grid" id="listings-grid">
                        <?php foreach ($items as $item):
                            $photos = $item['photos_json'] ? json_decode($item['photos_json'], true) : [];
                            $thumb = null;
                            $photoUrl = '';
                            if (!empty($photos[0])) {
                                $thumbPath = __DIR__ . '/public/storage/uploads/' . $photos[0];
                                if (file_exists($thumbPath)) {
                                    $thumb = 'public/storage/uploads/' . $photos[0];
                                    $photoUrl = $thumb;
                                }
                            }
                            $itemData = [
                                'id' => (int)$item['id'],
                                'title' => $item['title'],
                                'category' => $item['category'],
                                'description' => $item['description'] ?? '',
                                'condition_notes' => $item['condition_notes'] ?? '',
                                'status' => $item['status'] ?? 'draft',
                                'status_label' => item_status_label($item['status'] ?? 'draft'),
                                'photo' => $photoUrl,
                            ];
                        ?>
                            <div class="listing-card" role="button" tabindex="0" data-item="<?php echo htmlspecialchars(json_encode($itemData), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($thumb): ?>
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="" class="thumb">
                                <?php else: ?>
                                    <div class="thumb-none">No photo</div>
                                <?php endif; ?>
                                <div class="body">
                                    <div class="title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="meta"><?php echo htmlspecialchars($item['category']); ?> · <?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
                                    <span class="status"><?php echo htmlspecialchars(item_status_label($item['status'] ?? 'draft')); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($section === 'pickups'): ?>
                <h1>My pickups</h1>
                <p class="page-desc">View and manage your pickups, or request a new one.</p>
                <?php if ($pickupSuccess): ?><p class="msg success"><?php echo htmlspecialchars($pickupSuccess); ?></p><?php endif; ?>
                <?php if ($pickupError): ?><p class="msg error"><?php echo htmlspecialchars($pickupError); ?></p><?php endif; ?>

                <?php if (count($pickups) > 0): ?>
                    <div class="pickup-cards-grid" id="pickup-cards-grid">
                        <?php foreach ($pickups as $pu):
                            $windowStartForm = date('Y-m-d\TH:i', strtotime($pu['pickup_window_start']));
                            $windowEndForm = date('Y-m-d\TH:i', strtotime($pu['pickup_window_end']));
                            $pickupData = [
                                'id' => (int)$pu['id'],
                                'address_text' => $pu['address_text'],
                                'pickup_window_start' => $windowStartForm,
                                'pickup_window_end' => $windowEndForm,
                                'status' => $pu['status'],
                                'status_label' => pickup_status_label($pu['status']),
                                'item_count' => (int)$pu['item_count'],
                                'item_titles' => $pu['item_titles'],
                                'item_ids' => $pu['item_ids'],
                                'available_items' => $pu['available_items'],
                                'can_edit' => in_array($pu['status'], ['requested', 'scheduled'], true),
                            ];
                        ?>
                            <div class="pickup-card pickup-card-clickable" role="button" tabindex="0" data-pickup="<?php echo htmlspecialchars(json_encode($pickupData), ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="pickup-meta">
                                    <?php echo date('M j, Y', strtotime($pu['pickup_window_start'])); ?>
                                    – <?php echo date('M j, g:i A', strtotime($pu['pickup_window_end'])); ?>
                                </div>
                                <div class="pickup-address"><?php echo htmlspecialchars(mb_strimwidth($pu['address_text'], 0, 60, '…')); ?></div>
                                <div class="pickup-meta"><?php echo (int) $pu['item_count']; ?> item(s)</div>
                                <span class="status-badge status-<?php echo htmlspecialchars($pu['status']); ?>"><?php echo htmlspecialchars(pickup_status_label($pu['status'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="msg">You don’t have any pickups yet. Request one below.</p>
                <?php endif; ?>

                <div class="card" style="margin-top: 1.5rem;">
                    <h2>Request new pickup</h2>
                    <p style="font-size: 14px; color: #5F6C7B; margin-bottom: 1rem;">Select items you want picked up, enter your address, and choose a time window.</p>
                    <?php if (count($draftItems) === 0): ?>
                        <p class="msg error">You have no draft listings. Add items in <a href="dashboard.php?section=listings">My listings</a> first, then request a pickup.</p>
                    <?php else: ?>
                        <form method="post" action="dashboard.php?section=pickups">
                            <input type="hidden" name="request_pickup" value="1">
                            <div class="form-group">
                                <label>Select items to pick up</label>
                                <?php foreach ($draftItems as $di): ?>
                                    <label class="item-checkbox">
                                        <input type="checkbox" name="item_ids[]" value="<?php echo (int) $di['id']; ?>">
                                        <?php echo htmlspecialchars($di['title']); ?> (<?php echo htmlspecialchars($di['category']); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-group">
                                <label for="address_text">Pickup address *</label>
                                <textarea id="address_text" name="address_text" rows="3" required placeholder="Street, city, province, postal code"><?php echo htmlspecialchars($_POST['address_text'] ?? ''); ?></textarea>
                            </div>
                            <div class="pickup-form-row">
                                <div class="form-group">
                                    <label for="pickup_window_start">Window start *</label>
                                    <input type="datetime-local" id="pickup_window_start" name="pickup_window_start" required value="<?php echo htmlspecialchars($_POST['pickup_window_start'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="pickup_window_end">Window end *</label>
                                    <input type="datetime-local" id="pickup_window_end" name="pickup_window_end" required value="<?php echo htmlspecialchars($_POST['pickup_window_end'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary">Request pickup</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Item modal (listings) - structure and classes only, no inline styles -->
    <div class="item-modal-overlay" id="item-modal" aria-hidden="true">
        <div class="item-modal" role="dialog" aria-labelledby="item-modal-title">
            <div class="item-modal__header">
                <h2 class="item-modal__title" id="item-modal-title">Item</h2>
                <button type="button" class="item-modal__close" id="item-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="item-modal__body">
                <div class="item-modal__panel is-active" id="item-modal-panel-view">
                    <div id="item-modal-view-content"></div>
                    <div class="item-modal__actions">
                        <button type="button" class="btn-primary" id="item-modal-btn-edit">Edit</button>
                        <button type="button" class="btn-secondary item-modal__btn-request-pickup" id="item-modal-btn-request-pickup">Request pickup</button>
                        <button type="button" class="btn-danger item-modal__btn-delete" id="item-modal-btn-delete">Delete</button>
                    </div>
                </div>
                <div class="item-modal__panel" id="item-modal-panel-edit">
                    <form id="item-edit-form" method="post" action="dashboard.php?section=listings" enctype="multipart/form-data">
                        <input type="hidden" name="edit_item" value="1">
                        <input type="hidden" name="item_id" id="edit-item-id" value="">
                        <div class="form-group">
                            <label for="edit-title">Title *</label>
                            <input type="text" id="edit-title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-category">Category *</label>
                            <input type="text" id="edit-category" name="category" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-description">Description</label>
                            <textarea id="edit-description" name="description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit-condition_notes">Condition notes</label>
                            <textarea id="edit-condition_notes" name="condition_notes"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit-photo">New photo (optional, replaces current)</label>
                            <input type="file" id="edit-photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                        <div class="item-modal__actions">
                            <button type="button" class="btn-secondary" id="item-modal-edit-cancel">Cancel</button>
                            <button type="submit" class="btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
                <div class="item-modal__panel" id="item-modal-panel-delete">
                    <p class="item-modal__delete-note">This will permanently remove this listing. Only draft items can be deleted.</p>
                    <form id="item-delete-form" method="post" action="dashboard.php?section=listings">
                        <input type="hidden" name="delete_item" value="1">
                        <input type="hidden" name="item_id" id="delete-item-id" value="">
                        <div class="item-modal__actions">
                            <button type="button" class="btn-secondary" id="item-modal-delete-cancel">Cancel</button>
                            <button type="submit" class="btn-danger">Delete listing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Pickup modal (same structure as item modal) -->
    <div class="pickup-modal-overlay" id="pickup-modal" aria-hidden="true">
        <div class="pickup-modal" role="dialog" aria-labelledby="pickup-modal-title">
            <div class="pickup-modal__header">
                <h2 class="pickup-modal__title" id="pickup-modal-title">Pickup</h2>
                <button type="button" class="pickup-modal__close" id="pickup-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="pickup-modal__body">
                <div class="pickup-modal__panel is-active" id="pickup-modal-panel-view">
                    <div id="pickup-modal-view-content"></div>
                    <div class="pickup-modal__actions">
                        <button type="button" class="btn-primary" id="pickup-modal-btn-edit">Edit</button>
                        <button type="button" class="btn-danger pickup-modal__btn-cancel" id="pickup-modal-btn-cancel">Cancel pickup</button>
                    </div>
                </div>
                <div class="pickup-modal__panel" id="pickup-modal-panel-edit">
                    <form id="pickup-edit-form" class="pickup-modal__edit-form" method="post" action="dashboard.php?section=pickups">
                        <input type="hidden" name="edit_pickup" value="1">
                        <input type="hidden" name="pickup_id" id="pickup-edit-id" value="">
                        <div class="pickup-modal__form-section">
                            <div class="pickup-modal__form-section-title">Items to pick up</div>
                            <p style="font-size: 13px; color: #5F6C7B; margin-bottom: 0.5rem;">Select at least one item. You can add draft items or remove items from this pickup.</p>
                            <div class="pickup-modal__item-list" id="pickup-edit-items-container"></div>
                        </div>
                        <div class="pickup-modal__form-section">
                            <div class="pickup-modal__form-section-title">Address</div>
                            <div class="form-group">
                                <label for="pickup-edit-address">Pickup address *</label>
                                <textarea id="pickup-edit-address" name="address_text" rows="3" required placeholder="Street, city, province, postal code"></textarea>
                            </div>
                        </div>
                        <div class="pickup-modal__form-section">
                            <div class="pickup-modal__form-section-title">Time window</div>
                            <div class="pickup-form-row">
                                <div class="form-group">
                                    <label for="pickup-edit-window-start">Start *</label>
                                    <input type="datetime-local" id="pickup-edit-window-start" name="pickup_window_start" required>
                                </div>
                                <div class="form-group">
                                    <label for="pickup-edit-window-end">End *</label>
                                    <input type="datetime-local" id="pickup-edit-window-end" name="pickup_window_end" required>
                                </div>
                            </div>
                        </div>
                        <div class="pickup-modal__actions">
                            <button type="button" class="btn-secondary" id="pickup-modal-edit-cancel">Cancel</button>
                            <button type="submit" class="btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
                <div class="pickup-modal__panel" id="pickup-modal-panel-cancel">
                    <p class="pickup-modal__cancel-note">This will cancel the pickup. You can request a new one later.</p>
                    <form id="pickup-cancel-form" method="post" action="dashboard.php?section=pickups">
                        <input type="hidden" name="cancel_pickup" value="1">
                        <input type="hidden" name="pickup_id" id="pickup-cancel-id" value="">
                        <div class="pickup-modal__actions">
                            <button type="button" class="btn-secondary" id="pickup-modal-cancel-back">Back</button>
                            <button type="submit" class="btn-danger">Cancel pickup</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create pickup modal (opened from item modal) -->
    <div class="create-pickup-modal-overlay" id="create-pickup-modal" aria-hidden="true">
        <div class="create-pickup-modal" role="dialog" aria-labelledby="create-pickup-modal-title">
            <div class="create-pickup-modal__header">
                <h2 class="create-pickup-modal__title" id="create-pickup-modal-title">Request pickup</h2>
                <button type="button" class="create-pickup-modal__close" id="create-pickup-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="create-pickup-modal__body">
                <div id="create-pickup-no-drafts" class="create-pickup-modal__no-drafts" style="display: none;">You have no draft items. Add items in My listings first.</div>
                <form id="create-pickup-form" method="post" action="dashboard.php?section=pickups" style="display: none;">
                    <input type="hidden" name="request_pickup" value="1">
                    <div class="create-pickup-modal__form-section">
                        <div class="create-pickup-modal__form-section-title">Select items to pick up</div>
                        <div id="create-pickup-items-container"></div>
                    </div>
                    <div class="create-pickup-modal__form-section">
                        <div class="create-pickup-modal__form-section-title">Pickup address *</div>
                        <div class="form-group">
                            <textarea id="create-pickup-address" name="address_text" rows="3" required placeholder="Street, city, province, postal code"></textarea>
                        </div>
                    </div>
                    <div class="create-pickup-modal__form-section">
                        <div class="create-pickup-modal__form-section-title">Time window</div>
                        <div class="pickup-form-row">
                            <div class="form-group">
                                <label for="create-pickup-window-start">Start *</label>
                                <input type="datetime-local" id="create-pickup-window-start" name="pickup_window_start" required>
                            </div>
                            <div class="form-group">
                                <label for="create-pickup-window-end">End *</label>
                                <input type="datetime-local" id="create-pickup-window-end" name="pickup_window_end" required>
                            </div>
                        </div>
                    </div>
                    <div class="create-pickup-modal__actions">
                        <button type="button" class="btn-secondary" id="create-pickup-modal-cancel">Cancel</button>
                        <button type="submit" class="btn-primary">Request pickup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    window.draftItemsForPickup = <?php echo json_encode(array_values(array_map(function($i) { return ['id' => (int)$i['id'], 'title' => $i['title'], 'category' => $i['category'] ?? '']; }, $draftItems))); ?>;

    (function() {
        var overlay = document.getElementById('create-pickup-modal');
        var form = document.getElementById('create-pickup-form');
        var noDrafts = document.getElementById('create-pickup-no-drafts');
        var itemsContainer = document.getElementById('create-pickup-items-container');
        var closeBtn = document.getElementById('create-pickup-modal-close');
        var cancelBtn = document.getElementById('create-pickup-modal-cancel');

        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function openCreatePickupModal(preSelectItemId) {
            if (!overlay) return;
            var drafts = window.draftItemsForPickup || [];
            if (noDrafts) noDrafts.style.display = drafts.length === 0 ? 'block' : 'none';
            if (form) form.style.display = drafts.length === 0 ? 'none' : 'block';
            if (itemsContainer && drafts.length > 0) {
                itemsContainer.innerHTML = '';
                for (var i = 0; i < drafts.length; i++) {
                    var it = drafts[i];
                    var checked = preSelectItemId && (it.id === preSelectItemId || it.id === parseInt(preSelectItemId, 10));
                    var label = document.createElement('label');
                    label.className = 'item-checkbox';
                    var id = 'create-pickup-item-' + it.id;
                    label.innerHTML = '<input type="checkbox" name="item_ids[]" value="' + it.id + '" id="' + id + '" ' + (checked ? 'checked' : '') + '> ' + escapeHtml(it.title) + (it.category ? ' (' + escapeHtml(it.category) + ')' : '');
                    itemsContainer.appendChild(label);
                }
                if (form) {
                    var addr = form.querySelector('#create-pickup-address');
                    var start = form.querySelector('#create-pickup-window-start');
                    var end = form.querySelector('#create-pickup-window-end');
                    if (addr) addr.value = '';
                    if (start) start.value = '';
                    if (end) end.value = '';
                }
            }
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
        }

        function closeCreatePickupModal() {
            if (overlay) {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }

        window.openCreatePickupModal = openCreatePickupModal;

        if (closeBtn) closeBtn.addEventListener('click', closeCreatePickupModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeCreatePickupModal);
        if (overlay) overlay.addEventListener('click', function(e) { if (e.target === overlay) closeCreatePickupModal(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay && overlay.classList.contains('is-open')) closeCreatePickupModal();
        });
    })();

    (function() {
        var grid = document.getElementById('listings-grid');
        if (!grid) return;
        var overlay = document.getElementById('item-modal');
        var closeBtn = document.getElementById('item-modal-close');
        var viewContent = document.getElementById('item-modal-view-content');
        var btnEdit = document.getElementById('item-modal-btn-edit');
        var btnDelete = document.getElementById('item-modal-btn-delete');
        var btnRequestPickup = document.getElementById('item-modal-btn-request-pickup');
        var panelView = document.getElementById('item-modal-panel-view');
        var panelEdit = document.getElementById('item-modal-panel-edit');
        var panelDelete = document.getElementById('item-modal-panel-delete');
        var editItemId = document.getElementById('edit-item-id');
        var deleteItemId = document.getElementById('delete-item-id');
        var currentItem = null;

        function openModal(item) {
            currentItem = item;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.getElementById('item-modal-title').textContent = item.title;
            showPanel('view');
            renderView(item);
            btnDelete.classList.toggle('is-visible', item.status === 'draft');
            btnRequestPickup.classList.toggle('is-visible', item.status === 'draft');
        }
        function closeModal() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            currentItem = null;
        }
        function showPanel(name) {
            panelView.classList.toggle('is-active', name === 'view');
            panelEdit.classList.toggle('is-active', name === 'edit');
            panelDelete.classList.toggle('is-active', name === 'delete');
            if (name === 'edit' && currentItem) {
                editItemId.value = currentItem.id;
                document.getElementById('edit-title').value = currentItem.title;
                document.getElementById('edit-category').value = currentItem.category;
                document.getElementById('edit-description').value = currentItem.description || '';
                document.getElementById('edit-condition_notes').value = currentItem.condition_notes || '';
                document.getElementById('edit-photo').value = '';
            }
            if (name === 'delete' && currentItem) {
                deleteItemId.value = currentItem.id;
            }
        }
        function renderView(item) {
            var html = '';
            if (item.photo) {
                html += '<div class="item-modal__view-photo"><img src="' + escapeHtml(item.photo) + '" alt=""></div>';
            } else {
                html += '<div class="item-modal__view-photo-empty">No photo</div>';
            }
            html += '<div class="item-modal__view-detail"><div class="item-modal__view-label">Title</div><div class="item-modal__view-value">' + escapeHtml(item.title) + '</div></div>';
            html += '<div class="item-modal__view-detail"><div class="item-modal__view-label">Category</div><div class="item-modal__view-value">' + escapeHtml(item.category) + '</div></div>';
            html += '<div class="item-modal__view-detail"><div class="item-modal__view-label">Status</div><div class="item-modal__view-value">' + escapeHtml(item.status_label || item.status) + '</div></div>';
            if (item.description) html += '<div class="item-modal__view-detail"><div class="item-modal__view-label">Description</div><div class="item-modal__view-value">' + escapeHtml(item.description) + '</div></div>';
            if (item.condition_notes) html += '<div class="item-modal__view-detail"><div class="item-modal__view-label">Condition notes</div><div class="item-modal__view-value">' + escapeHtml(item.condition_notes) + '</div></div>';
            viewContent.innerHTML = html;
        }
        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        grid.addEventListener('click', function(e) {
            var card = e.target.closest('.listing-card');
            if (!card) return;
            try {
                var item = JSON.parse(card.getAttribute('data-item'));
                openModal(item);
            } catch (err) {}
        });
        grid.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var card = e.target.closest('.listing-card');
            if (!card) return;
            e.preventDefault();
            try {
                var item = JSON.parse(card.getAttribute('data-item'));
                openModal(item);
            } catch (err) {}
        });
        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
        btnEdit.addEventListener('click', function() { showPanel('edit'); });
        btnDelete.addEventListener('click', function() { showPanel('delete'); });
        btnRequestPickup.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!currentItem) return;
            var itemId = currentItem.id;
            // Open create-pickup modal first so it's visible, then close item modal (defer so modal is shown)
            if (typeof window.openCreatePickupModal === 'function') {
                window.openCreatePickupModal(itemId);
                setTimeout(function() { closeModal(); }, 10);
            } else {
                closeModal();
                window.location.href = 'dashboard.php?section=pickups';
            }
        });
        document.getElementById('item-modal-edit-cancel').addEventListener('click', function() { showPanel('view'); });
        document.getElementById('item-modal-delete-cancel').addEventListener('click', function() { showPanel('view'); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
        });
    })();

    (function() {
        var grid = document.getElementById('pickup-cards-grid');
        if (!grid) return;
        var overlay = document.getElementById('pickup-modal');
        var closeBtn = document.getElementById('pickup-modal-close');
        var viewContent = document.getElementById('pickup-modal-view-content');
        var btnEdit = document.getElementById('pickup-modal-btn-edit');
        var btnCancel = document.getElementById('pickup-modal-btn-cancel');
        var panelView = document.getElementById('pickup-modal-panel-view');
        var panelEdit = document.getElementById('pickup-modal-panel-edit');
        var panelCancel = document.getElementById('pickup-modal-panel-cancel');
        var editId = document.getElementById('pickup-edit-id');
        var cancelId = document.getElementById('pickup-cancel-id');
        var currentPickup = null;

        function openPickupModal(pickup) {
            currentPickup = pickup;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.getElementById('pickup-modal-title').textContent = 'Pickup #' + pickup.id;
            showPanel('view');
            renderPickupView(pickup);
            btnEdit.classList.toggle('is-visible', pickup.can_edit);
            btnCancel.classList.toggle('is-visible', pickup.can_edit);
        }
        function closePickupModal() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            currentPickup = null;
        }
        function showPanel(name) {
            panelView.classList.toggle('is-active', name === 'view');
            panelEdit.classList.toggle('is-active', name === 'edit');
            panelCancel.classList.toggle('is-active', name === 'cancel');
            if (name === 'edit' && currentPickup) {
                editId.value = currentPickup.id;
                document.getElementById('pickup-edit-address').value = currentPickup.address_text;
                document.getElementById('pickup-edit-window-start').value = currentPickup.pickup_window_start;
                document.getElementById('pickup-edit-window-end').value = currentPickup.pickup_window_end;
                var container = document.getElementById('pickup-edit-items-container');
                container.innerHTML = '';
                var itemIds = currentPickup.item_ids || [];
                var available = currentPickup.available_items || [];
                for (var i = 0; i < available.length; i++) {
                    var it = available[i];
                    var checked = itemIds.indexOf(it.id) !== -1;
                    var row = document.createElement('div');
                    row.className = 'pickup-modal__item-row';
                    var id = 'pickup-edit-item-' + it.id;
                    row.innerHTML = '<input type="checkbox" name="item_ids[]" value="' + it.id + '" id="' + id + '" ' + (checked ? 'checked' : '') + '><label for="' + id + '">' + escapeHtml(it.title) + (it.category ? ' <span style="color:#5F6C7B">(' + escapeHtml(it.category) + ')</span>' : '') + '</label>';
                    container.appendChild(row);
                }
                if (available.length === 0) {
                    container.innerHTML = '<p style="font-size:13px;color:#5F6C7B;margin:0;">No items available to add. Create draft listings first.</p>';
                }
            }
            if (name === 'cancel' && currentPickup) {
                cancelId.value = currentPickup.id;
            }
        }
        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }
        function renderPickupView(pickup) {
            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
            var html = '';
            html += '<div class="pickup-modal__view-detail"><div class="pickup-modal__view-label">Status</div><div class="pickup-modal__view-value">' + esc(pickup.status_label || pickup.status) + '</div></div>';
            html += '<div class="pickup-modal__view-detail"><div class="pickup-modal__view-label">Address</div><div class="pickup-modal__view-value">' + esc(pickup.address_text) + '</div></div>';
            html += '<div class="pickup-modal__view-detail"><div class="pickup-modal__view-label">Window start</div><div class="pickup-modal__view-value">' + esc(formatDateTime(pickup.pickup_window_start)) + '</div></div>';
            html += '<div class="pickup-modal__view-detail"><div class="pickup-modal__view-label">Window end</div><div class="pickup-modal__view-value">' + esc(formatDateTime(pickup.pickup_window_end)) + '</div></div>';
            html += '<div class="pickup-modal__view-detail"><div class="pickup-modal__view-label">Items (' + pickup.item_count + ')</div><div class="pickup-modal__view-value">';
            if (pickup.item_titles && pickup.item_titles.length) {
                html += '<ul class="pickup-modal__view-items">';
                for (var i = 0; i < pickup.item_titles.length; i++) {
                    html += '<li>' + esc(pickup.item_titles[i]) + '</li>';
                }
                html += '</ul>';
            } else {
                html += pickup.item_count + ' item(s)';
            }
            html += '</div></div>';
            viewContent.innerHTML = html;
        }
        function formatDateTime(iso) {
            if (!iso) return '';
            var d = new Date(iso);
            var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return m[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2,'0');
        }

        grid.addEventListener('click', function(e) {
            var card = e.target.closest('.pickup-card-clickable');
            if (!card) return;
            try {
                var pickup = JSON.parse(card.getAttribute('data-pickup'));
                openPickupModal(pickup);
            } catch (err) {}
        });
        grid.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var card = e.target.closest('.pickup-card-clickable');
            if (!card) return;
            e.preventDefault();
            try {
                var pickup = JSON.parse(card.getAttribute('data-pickup'));
                openPickupModal(pickup);
            } catch (err) {}
        });
        closeBtn.addEventListener('click', closePickupModal);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closePickupModal(); });
        btnEdit.addEventListener('click', function() { showPanel('edit'); });
        btnCancel.addEventListener('click', function() { showPanel('cancel'); });
        document.getElementById('pickup-modal-edit-cancel').addEventListener('click', function() { showPanel('view'); });
        document.getElementById('pickup-modal-cancel-back').addEventListener('click', function() { showPanel('view'); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closePickupModal();
        });
    })();
    </script>
</body>
</html>
