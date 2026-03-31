<?php
/**
 * RePlug — lightweight audit logger.
 */
function log_audit(PDO $pdo, int $actorUserId, string $entityType, int $entityId, string $action, array $meta = []): void
{
    if ($actorUserId <= 0 || $entityId <= 0 || $entityType === '' || $action === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, entity_type, entity_id, action, meta_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $actorUserId,
            $entityType,
            $entityId,
            $action,
            $meta ? json_encode($meta) : null,
        ]);
    } catch (Throwable $e) {
        // Do not block business flow if audit table is missing.
    }
}

