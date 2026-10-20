<?php
declare(strict_types=1);
namespace TermTrail\helpers;

use TermTrail\config\Database;

final class Audit
{
    private const FORBIDDEN = ['password', 'pass', 'api_key', 'token', 'secret', 'csrf'];

    public static function log(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
    {
        foreach (array_keys($meta) as $k) {
            foreach (self::FORBIDDEN as $bad) {
                if (stripos((string)$k, $bad) !== false) { unset($meta[$k]); }
            }
        }
        try {
            $st = Database::pdo()->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, metadata) VALUES (?,?,?,?,?,?)');
            $st->execute([$userId, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($meta)]);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
