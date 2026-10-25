<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Response, Validator};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\Lifecycle;

final class CalendarController
{
    public static function events(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        $start = substr((string)($_GET['start'] ?? ''), 0, 10);
        $end = substr((string)($_GET['end'] ?? ''), 0, 10);
        if (!Validator::date($start) || !Validator::date($end)) { $start = date('Y-m-01'); $end = date('Y-m-t', strtotime('+1 month')); }
        [$w, $sp] = ContractModel::scope($u);
        $pdo = Database::pdo();
        $ev = [];
        $q = function (string $sql, array $extra = []) use ($pdo, $sp): array { $st = $pdo->prepare($sql); $st->execute(array_merge($sp, $extra)); return $st->fetchAll(); };
        $url = fn(string $page, $id) => APP_URL . '/index.php?page=' . $page . '&id=' . $id;

        foreach ($q("SELECT o.id,o.title,o.due_date,o.status,o.priority,c.title ctitle FROM obligations o JOIN contracts c ON c.id=o.contract_id
                     WHERE $w AND c.is_archived=0 AND o.status<>'cancelled' AND o.due_date BETWEEN ? AND ? LIMIT 400", [$start, $end]) as $r) {
            $ev[] = ['id' => 'o' . $r['id'], 'title' => 'Obligation: ' . $r['title'], 'start' => $r['due_date'], 'allDay' => true, 'url' => $url('obligation_view', $r['id']),
                'classNames' => ['ev-obligation', 'ev-' . $r['status'], 'pri-' . $r['priority']], 'extendedProps' => ['type' => 'obligation', 'contract' => $r['ctitle'], 'status' => $r['status']]];
        }
        foreach ($q("SELECT p.id,p.amount,p.currency,p.due_date,p.status,c.title ctitle FROM payments p JOIN contracts c ON c.id=p.contract_id
                     WHERE $w AND c.is_archived=0 AND p.status<>'cancelled' AND p.due_date BETWEEN ? AND ? LIMIT 400", [$start, $end]) as $r) {
            $ev[] = ['id' => 'p' . $r['id'], 'title' => 'Payment: ' . $r['currency'] . ' ' . $r['amount'], 'start' => $r['due_date'], 'allDay' => true, 'url' => $url('payments', $r['id']),
                'classNames' => ['ev-payment', 'ev-' . $r['status']], 'extendedProps' => ['type' => 'payment', 'contract' => $r['ctitle'], 'status' => $r['status']]];
        }
        $base = "FROM contracts c WHERE $w AND c.is_archived=0 AND c.status IN ('active','under_review')";
        foreach ($q("SELECT c.id,c.title,c.expiry_date d $base AND c.expiry_date BETWEEN ? AND ? LIMIT 300", [$start, $end]) as $r) {
            $ev[] = ['id' => 'x' . $r['id'], 'title' => 'Expiry: ' . $r['title'], 'start' => $r['d'], 'allDay' => true, 'url' => $url('contract_view', $r['id']),
                'classNames' => ['ev-expiry'], 'extendedProps' => ['type' => 'expiry', 'contract' => $r['title']]];
        }
        foreach ($q("SELECT c.id,c.title,c.renewal_date d $base AND c.renewal_date BETWEEN ? AND ? LIMIT 300", [$start, $end]) as $r) {
            $ev[] = ['id' => 'r' . $r['id'], 'title' => 'Renewal: ' . $r['title'], 'start' => $r['d'], 'allDay' => true, 'url' => APP_URL . '/index.php?page=renewals',
                'classNames' => ['ev-renewal'], 'extendedProps' => ['type' => 'renewal', 'contract' => $r['title']]];
        }
        foreach ($q("SELECT c.id,c.title,DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY) d $base AND c.notice_period_days IS NOT NULL AND c.renewal_decision='pending'
                     AND COALESCE(c.renewal_date,c.expiry_date) IS NOT NULL
                     AND DATE_SUB(COALESCE(c.renewal_date,c.expiry_date), INTERVAL c.notice_period_days DAY) BETWEEN ? AND ? LIMIT 300", [$start, $end]) as $r) {
            $ev[] = ['id' => 'n' . $r['id'], 'title' => 'Notice deadline: ' . $r['title'], 'start' => $r['d'], 'allDay' => true, 'url' => APP_URL . '/index.php?page=renewals',
                'classNames' => ['ev-notice'], 'extendedProps' => ['type' => 'notice', 'contract' => $r['title']]];
        }
        Response::json($ev);
    }
}
