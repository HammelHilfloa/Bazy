<?php
class AuditLog
{
    /**
     * Speichert einen Eintrag im Audit-Log.
     *
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function record(PDO $pdo, string $entityType, string $entityId, string $action, ?int $userId, ?array $before = null, ?array $after = null): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (entity_type, entity_id, action, user_id, before_json, after_json, created_at)
             VALUES (:entity_type, :entity_id, :action, :user_id, :before_json, :after_json, NOW())'
        );

        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => $userId,
            'before_json' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
