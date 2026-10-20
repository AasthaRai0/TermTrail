<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\Response;
use TermTrail\middleware\Auth;

final class DashboardController
{
    /** Admin sees everything; others see contracts they own or review. */
    public static function scope(array $u, string $alias = 'c'): array
    {
        if ($u['role'] === 'admin') { return ['1=1', []]; }
        return ["($alias.owner_id = ? OR $alias.reviewer_id = ?)", [$u['id'], $u['id']]];
    }

    public static function stats(): never
    {
        $u = Auth::requireApi();
        $pdo = Database::pdo();
        [$w, $p] = self::scope($u);
        $q = function (string $sql, array $extra = []) use ($pdo, $p): int {
            $st = $pdo->prepare($sql);
            $st->execute(array_merge($p, $extra));
            return (int)$st->fetchColumn();
        };
        $base = "FROM contracts c WHERE $w AND c.is_archived=0";
        $cards = [
            'total_contracts'    => $q("SELECT COUNT(*) $base"),
            'active_contracts'   => $q("SELECT COUNT(*) $base AND c.status='active'"),
            'expiring_soon'      => $q("SELECT COUNT(*) $base AND c.status='active' AND c.expiry_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY"),
            'expired_contracts'  => $q("SELECT COUNT(*) $base AND (c.status='expired' OR (c.expiry_date < CURDATE() AND c.status='active'))"),
            'awaiting_review'    => $q("SELECT COUNT(*) $base AND c.status='under_review'"),
            'high_risk'          => $q("SELECT COUNT(*) $base AND c.risk_level='high'"),
            'open_obligations'   => $q("SELECT COUNT(*) FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE $w AND o.status IN ('open','overdue')"),
            'overdue_obligations'=> $q("SELECT COUNT(*) FROM obligations o JOIN contracts c ON c.id=o.contract_id WHERE $w AND o.status IN ('open','overdue') AND o.due_date < CURDATE()"),
            'upcoming_payments'  => $q("SELECT COUNT(*) FROM payments y JOIN contracts c ON c.id=y.contract_id WHERE $w AND y.status IN ('planned','due') AND y.due_date >= CURDATE()"),
        ];

        $rows = function (string $sql) use ($pdo, $p): array {
            $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll();
        };
        $status = $rows("SELECT c.status AS label, COUNT(*) AS value $base GROUP BY c.status");
        $risk = $rows("SELECT COALESCE(c.risk_level,'unassessed') AS label, COUNT(*) AS value $base GROUP BY label");
        $monthly = $rows("SELECT DATE_FORMAT(c.created_at,'%Y-%m') AS label, COUNT(*) AS value $base AND c.created_at >= (CURDATE() - INTERVAL 11 MONTH) GROUP BY label ORDER BY label");
        $recent = $rows("SELECT c.id,c.title,c.status,c.risk_level,c.expiry_date,cp.name AS counterparty FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id WHERE $w AND c.is_archived=0 ORDER BY c.created_at DESC LIMIT 6");
        $deadlines = $rows("SELECT * FROM (
            SELECT 'Contract expiry' AS kind, c.title AS title, c.expiry_date AS due, c.id AS contract_id FROM contracts c WHERE $w AND c.is_archived=0 AND c.expiry_date >= CURDATE()
            ) d ORDER BY due ASC LIMIT 6");

        $st = $pdo->prepare('SELECT al.action, al.entity_type, al.created_at, u.name FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.user_id=? ORDER BY al.id DESC LIMIT 8');
        $st->execute([$u['id']]);

        Response::ok(['cards' => $cards, 'charts' => ['status' => $status, 'risk' => $risk, 'monthly' => $monthly],
            'recent_contracts' => $recent, 'deadlines' => $deadlines, 'activity' => $st->fetchAll()]);
    }
}
