<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;

/**
 * Creates in-app notifications (and optional emails) for upcoming/overdue items.
 * The `reminders` table has a unique key (user, entity, window) so each reminder fires once.
 */
final class ReminderService
{
    private static array $emails = [];

    public static function run(): array
    {
        Lifecycle::sync();
        $c = ['notifications' => 0, 'emails' => 0, 'email_configured' => Mailer::configured()];
        self::contractExpiry($c);
        self::renewalNotice($c);
        self::payments($c);
        self::obligations($c);
        Database::pdo()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('reminders_last_run', NOW()) ON DUPLICATE KEY UPDATE setting_value=NOW()")->execute();
        return $c;
    }

    public static function lastRun(): ?string
    {
        $v = Database::pdo()->query("SELECT setting_value FROM settings WHERE setting_key='reminders_last_run'")->fetchColumn();
        return $v ?: null;
    }

    /** Smallest window that still covers $days (e.g. 5 days left with [30,14,7,1] -> 7). */
    public static function window(int $days, array $windows): ?int
    {
        sort($windows);
        foreach ($windows as $w) { if ($days <= $w) { return $w; } }
        return null;
    }

    private static function email(int $uid): ?string
    {
        if (!array_key_exists($uid, self::$emails)) {
            $st = Database::pdo()->prepare('SELECT email FROM users WHERE id=? AND is_active=1');
            $st->execute([$uid]);
            self::$emails[$uid] = $st->fetchColumn() ?: null;
        }
        return self::$emails[$uid];
    }

    private static function dispatch(array $userIds, string $entityType, int $entityId, int $window, string $type, string $title, string $msg, string $link, array &$c): void
    {
        $pdo = Database::pdo();
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $uid) {
            $st = $pdo->prepare('INSERT IGNORE INTO reminders (user_id,entity_type,entity_id,window_days) VALUES (?,?,?,?)');
            $st->execute([$uid, $entityType, $entityId, $window]);
            if ($st->rowCount() !== 1) { continue; }
            NotificationService::create($uid, $type, $title, $msg, $link);
            $c['notifications']++;
            $to = self::email($uid);
            if ($to && Mailer::send($to, '[TermTrail] ' . $title, $msg . "\n\n" . APP_URL . '/' . $link . "\n\nThis is an automated reminder from TermTrail AI.")) {
                $pdo->prepare('UPDATE reminders SET email_sent=1 WHERE user_id=? AND entity_type=? AND entity_id=? AND window_days=?')->execute([$uid, $entityType, $entityId, $window]);
                $c['emails']++;
            }
        }
    }

    private static function rows(string $sql): array { return Database::pdo()->query($sql)->fetchAll(); }
    private static function days(int $d): string { return $d <= 0 ? 'today' : ($d === 1 ? 'tomorrow' : "in $d days"); }

    private static function contractExpiry(array &$c): void
    {
        foreach (self::rows("SELECT id,title,owner_id,reviewer_id,expiry_date,DATEDIFF(expiry_date,CURDATE()) d FROM contracts
            WHERE is_archived=0 AND status IN ('active','under_review') AND expiry_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)") as $r) {
            $w = self::window((int)$r['d'], [30, 14, 7, 1]);
            if ($w === null) { continue; }
            self::dispatch([$r['owner_id'], $r['reviewer_id']], 'contract_expiry', (int)$r['id'], $w, 'contract_expiry',
                'Contract expiring ' . self::days((int)$r['d']), '"' . $r['title'] . '" expires on ' . $r['expiry_date'] . '.', 'index.php?page=contract_view&id=' . $r['id'], $c);
        }
    }

    private static function renewalNotice(array &$c): void
    {
        foreach (self::rows("SELECT id,title,owner_id,reviewer_id, DATE_SUB(COALESCE(renewal_date,expiry_date), INTERVAL notice_period_days DAY) nd,
            DATEDIFF(DATE_SUB(COALESCE(renewal_date,expiry_date), INTERVAL notice_period_days DAY), CURDATE()) d FROM contracts
            WHERE is_archived=0 AND status IN ('active','under_review') AND renewal_decision='pending' AND notice_period_days IS NOT NULL
            AND COALESCE(renewal_date,expiry_date) IS NOT NULL
            AND DATE_SUB(COALESCE(renewal_date,expiry_date), INTERVAL notice_period_days DAY) BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)") as $r) {
            $w = self::window((int)$r['d'], [30, 14, 7, 1]);
            if ($w === null) { continue; }
            self::dispatch([$r['owner_id'], $r['reviewer_id']], 'contract_renewal', (int)$r['id'], $w, 'renewal_notice',
                'Renewal notice deadline ' . self::days((int)$r['d']), 'Decide on renewal of "' . $r['title'] . '" by ' . $r['nd'] . '.', 'index.php?page=renewals', $c);
        }
    }

    private static function payments(array &$c): void
    {
        $base = 'SELECT p.id,p.amount,p.currency,p.due_date,c.title,c.owner_id,c.reviewer_id,DATEDIFF(p.due_date,CURDATE()) d FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE c.is_archived=0 AND ';
        foreach (self::rows($base . "p.status IN ('planned','due') AND p.due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)") as $r) {
            $w = self::window((int)$r['d'], [7, 3, 1]);
            if ($w === null) { continue; }
            self::dispatch([$r['owner_id'], $r['reviewer_id']], 'payment', (int)$r['id'], $w, 'payment_due', 'Payment due ' . self::days((int)$r['d']),
                $r['currency'] . ' ' . $r['amount'] . ' for "' . $r['title'] . '" is due on ' . $r['due_date'] . '.', 'index.php?page=payments&id=' . $r['id'], $c);
        }
        foreach (self::rows($base . "p.status='overdue'") as $r) {
            self::dispatch([$r['owner_id'], $r['reviewer_id']], 'payment', (int)$r['id'], 0, 'payment_overdue', 'Payment overdue',
                $r['currency'] . ' ' . $r['amount'] . ' for "' . $r['title'] . '" was due on ' . $r['due_date'] . '.', 'index.php?page=payments&id=' . $r['id'], $c);
        }
    }

    private static function obligations(array &$c): void
    {
        $base = 'SELECT o.id,o.title,o.due_date,o.owner_id oid,c.title ctitle,c.owner_id,DATEDIFF(o.due_date,CURDATE()) d FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE c.is_archived=0 AND ';
        foreach (self::rows($base . "o.status='open' AND o.due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)") as $r) {
            $w = self::window((int)$r['d'], [7, 1]);
            if ($w === null) { continue; }
            self::dispatch([$r['oid'], $r['owner_id']], 'obligation', (int)$r['id'], $w, 'obligation_due', 'Obligation due ' . self::days((int)$r['d']),
                '"' . $r['title'] . '" (' . $r['ctitle'] . ') is due on ' . $r['due_date'] . '.', 'index.php?page=obligation_view&id=' . $r['id'], $c);
        }
        foreach (self::rows($base . "o.status='overdue'") as $r) {
            self::dispatch([$r['oid'], $r['owner_id']], 'obligation', (int)$r['id'], 0, 'obligation_overdue', 'Obligation overdue',
                '"' . $r['title'] . '" (' . $r['ctitle'] . ') was due on ' . $r['due_date'] . '.', 'index.php?page=obligation_view&id=' . $r['id'], $c);
        }
    }
}
