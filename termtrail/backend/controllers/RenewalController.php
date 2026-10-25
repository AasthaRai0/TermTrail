<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;

final class RenewalController
{
    /** Contracts whose renewal (or expiry) date falls between 30 days ago and N days ahead. */
    public static function list(): never
    {
        $u = Auth::requireApi();
        [$w, $p] = ContractModel::scope($u);
        $days = min(365, max(7, (int)($_GET['days'] ?? 180)));
        $where = [$w, 'c.is_archived=0', "c.status IN ('active','under_review')", 'COALESCE(c.renewal_date,c.expiry_date) IS NOT NULL',
            'COALESCE(c.renewal_date,c.expiry_date) BETWEEN (CURDATE() - INTERVAL 30 DAY) AND (CURDATE() + INTERVAL ' . $days . ' DAY)'];
        if (in_array($_GET['decision'] ?? '', ['pending', 'renew', 'not_renew', 'renegotiate'], true)) { $where[] = 'c.renewal_decision=?'; $p[] = $_GET['decision']; }
        $st = Database::pdo()->prepare(
            "SELECT c.id,c.title,c.status,c.owner_id,c.renewal_decision,c.notice_period_days,cp.name AS counterparty_name,
              COALESCE(c.renewal_date,c.expiry_date) AS key_date, IF(c.renewal_date IS NOT NULL,'renewal','expiry') AS key_type,
              DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY) AS notice_deadline,
              DATEDIFF(COALESCE(c.renewal_date,c.expiry_date), CURDATE()) AS days_to_key,
              DATEDIFF(DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY), CURDATE()) AS days_to_notice
             FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id WHERE " . implode(' AND ', $where) .
            ' ORDER BY COALESCE(DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY), COALESCE(c.renewal_date,c.expiry_date)) ASC LIMIT 200');
        $st->execute($p);
        $items = $st->fetchAll();
        foreach ($items as &$i) { $i['can_edit'] = ContractModel::canWrite($i, $u); unset($i['owner_id']); }
        unset($i);
        Response::ok(['items' => $items, 'days' => $days]);
    }

    public static function decide(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = ContractModel::find((int)($in['id'] ?? 0), $u);
        if (!$c) { Response::error('Contract not found.', 404); }
        if (!ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to change this contract.', 403); }
        $dec = (string)($in['decision'] ?? '');
        if (!in_array($dec, ['pending', 'renew', 'not_renew', 'renegotiate'], true)) { Response::error('Invalid decision.', 422); }
        Database::pdo()->prepare('UPDATE contracts SET renewal_decision=? WHERE id=?')->execute([$dec, $c['id']]);
        Audit::log((int)$u['id'], 'contract.renewal_decision', 'contract', (int)$c['id'], ['from' => $c['renewal_decision'], 'to' => $dec]);
        Response::ok(['message' => 'Renewal decision saved.']);
    }
}
