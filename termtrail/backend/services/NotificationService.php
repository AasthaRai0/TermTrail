<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;

final class NotificationService
{
    /** @param string|null $link relative link such as "index.php?page=contract_view&id=3" */
    public static function create(int $userId, string $type, string $title, ?string $message = null, ?string $link = null): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,link) VALUES (?,?,?,?,?)')
            ->execute([$userId, $type, mb_substr($title, 0, 190), $message !== null ? mb_substr($message, 0, 500) : null, $link]);
        return (int)$pdo->lastInsertId();
    }
}
