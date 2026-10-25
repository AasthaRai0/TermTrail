<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;

/** Keeps derived statuses (overdue/due) in step with the calendar. Cheap, indexed UPDATEs. */
final class Lifecycle
{
    public static function sync(): void
    {
        $pdo = Database::pdo();
        $pdo->exec("UPDATE obligations SET status='overdue' WHERE status='open' AND due_date IS NOT NULL AND due_date < CURDATE()");
        $pdo->exec("UPDATE payments SET status='overdue' WHERE status IN ('planned','due') AND due_date < CURDATE()");
        $pdo->exec("UPDATE payments SET status='due' WHERE status='planned' AND due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)");
    }

    /** Allow a fresh reminder cycle after an entity's dates change. */
    public static function clearReminders(string $entityType, int $entityId): void
    {
        Database::pdo()->prepare('DELETE FROM reminders WHERE entity_type=? AND entity_id=?')->execute([$entityType, $entityId]);
    }

    /** An internal owner must be an active user who can already see the contract (owner, reviewer or admin). */
    public static function validAssignee(int $contractId, int $userId): bool
    {
        $st = Database::pdo()->prepare(
            "SELECT 1 FROM users u JOIN contracts c ON c.id=? WHERE u.id=? AND u.is_active=1
             AND (u.role='admin' OR c.owner_id=u.id OR c.reviewer_id=u.id)");
        $st->execute([$contractId, $userId]);
        return (bool)$st->fetchColumn();
    }

    /** Next due date for a recurring obligation, clamping month-end (31 Jan + 1 month = 28/29 Feb). */
    public static function nextDate(string $date, string $recurrence): ?string
    {
        $d = new \DateTimeImmutable($date);
        $months = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12][$recurrence] ?? null;
        if ($recurrence === 'weekly') { return $d->modify('+7 days')->format('Y-m-d'); }
        if ($months === null) { return null; }
        $day = (int)$d->format('j');
        $first = $d->modify('first day of +' . $months . ' months');
        return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min($day, (int)$first->format('t')))->format('Y-m-d');
    }
}
