<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response, Validator};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\Lifecycle;

final class PaymentController
{
    public const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'JPY'];
    private const SORTS = ['due_date' => 'p.due_date', 'amount' => 'p.amount', 'status' => 'p.status', 'category' => 'p.category'];

    private static function load(int $id, array $u): array
    {
        [$w, $p] = ContractModel::scope($u);
        $st = Database::pdo()->prepare("SELECT p.*, c.title AS contract_title, c.owner_id AS contract_owner_id FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE p.id=? AND $w");
        $st->execute(array_merge([$id], $p));
        $r = $id > 0 ? $st->fetch() : false;
        if (!$r) { Response::error('Payment not found.', 404); }
        return $r;
    }

    private static function canEdit(array $p, array $u): bool { return ContractModel::canWrite(['owner_id' => $p['contract_owner_id']], $u); }

    public static function fields(array $in): array
    {
        $d = []; $e = [];
        $amt = Validator::amount((string)($in['amount'] ?? ''));
        if ($amt === null) { $e['amount'] = 'Enter an amount greater than zero (up to 2 decimals).'; }
        $d['amount'] = $amt;
        $cur = strtoupper(trim((string)($in['currency'] ?? 'INR')));
        if (!in_array($cur, self::CURRENCIES, true)) { $e['currency'] = 'Unsupported currency.'; }
        $d['currency'] = $cur;
        $due = trim((string)($in['due_date'] ?? ''));
        if (!Validator::date($due)) { $e['due_date'] = 'Enter a valid due date.'; }
        $d['due_date'] = $due;
        $d['category'] = mb_substr(trim((string)($in['category'] ?? '')), 0, 80) ?: null;
        $d['reference_number'] = mb_substr(trim((string)($in['reference_number'] ?? '')), 0, 100) ?: null;
        $d['notes'] = mb_substr(trim((string)($in['notes'] ?? '')), 0, 2000) ?: null;
        return [$d, $e];
    }

    private static function filters(array $u, array $q): array
    {
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0'];
        if ((int)($q['contract_id'] ?? 0) > 0) { $where[] = 'p.contract_id=?'; $p[] = (int)$q['contract_id']; }
        $st = (string)($q['status'] ?? '');
        if (in_array($st, ['planned', 'due', 'paid', 'overdue', 'cancelled'], true)) { $where[] = 'p.status=?'; $p[] = $st; }
        elseif ($st === 'upcoming') { $where[] = "p.status IN ('planned','due') AND p.due_date >= CURDATE()"; }
        if (($cat = trim((string)($q['category'] ?? ''))) !== '') { $where[] = 'p.category=?'; $p[] = $cat; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
            if (Validator::date((string)($q[$k] ?? ''))) { $where[] = "p.due_date $op ?"; $p[] = $q[$k]; }
        }
        if (($s = trim((string)($q['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes(mb_substr($s, 0, 100), '%_\\') . '%';
            $where[] = '(p.reference_number LIKE ? OR p.notes LIKE ? OR c.title LIKE ? OR p.category LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $p];
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        [$where, $p] = self::filters($u, $_GET);
        $sort = self::SORTS[$_GET['sort'] ?? ''] ?? 'p.due_date';
        $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $per = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $from = "FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE $where";
        $pdo = Database::pdo();
        $cnt = $pdo->prepare("SELECT COUNT(*) $from"); $cnt->execute($p);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $per));
        $page = min($page, $pages);
        $off = ($page - 1) * $per;
        $st = $pdo->prepare("SELECT p.id,p.amount,p.currency,p.category,p.due_date,p.status,p.paid_date,p.reference_number,p.contract_id,c.title AS contract_title,c.owner_id AS contract_owner_id
            $from ORDER BY $sort $dir, p.id DESC LIMIT $per OFFSET $off");
        $st->execute($p);
        $items = $st->fetchAll();
        foreach ($items as &$i) { $i['can_edit'] = ContractModel::canWrite(['owner_id' => $i['contract_owner_id']], $u); unset($i['contract_owner_id']); }
        unset($i);
        Response::ok(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per]);
    }

    /** Totals per currency — never adds different currencies together. */
    public static function summary(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        $q = array_intersect_key($_GET, ['contract_id' => 1]);
        [$where, $p] = self::filters($u, $q);
        $st = Database::pdo()->prepare(
            "SELECT p.currency,
              COALESCE(SUM(CASE WHEN p.status IN ('planned','due') AND p.due_date >= CURDATE() THEN p.amount END),0) AS upcoming,
              COALESCE(SUM(CASE WHEN p.status='overdue' THEN p.amount END),0) AS overdue,
              COALESCE(SUM(CASE WHEN p.status='paid' THEN p.amount END),0) AS paid,
              COALESCE(SUM(CASE WHEN p.status<>'cancelled' THEN p.amount END),0) AS total
             FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE $where GROUP BY p.currency ORDER BY p.currency");
        $st->execute($p);
        Response::ok(['currencies' => $st->fetchAll(), 'categories' => self::categories($u)]);
    }

    private static function categories(array $u): array
    {
        [$w, $p] = ContractModel::scope($u);
        $st = Database::pdo()->prepare("SELECT DISTINCT p.category FROM payments p JOIN contracts c ON c.id=p.contract_id WHERE $w AND p.category IS NOT NULL ORDER BY p.category LIMIT 50");
        $st->execute($p);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        $r = self::load((int)($_GET['id'] ?? 0), $u);
        $r['can_edit'] = self::canEdit($r, $u);
        unset($r['contract_owner_id']);
        Response::ok(['payment' => $r]);
    }

    public static function create(array $in): never
    {
        $u = Auth::requireApi('manager');
        $cid = (int)($in['contract_id'] ?? 0);
        $c = $cid > 0 ? ContractModel::find($cid, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404, ['contract_id' => 'Choose a valid contract.']); }
        if (!ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to add payments to this contract.', 403); }
        [$d, $e] = self::fields($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO payments (contract_id,amount,currency,category,due_date,reference_number,notes) VALUES (?,?,?,?,?,?,?)')
            ->execute([$c['id'], $d['amount'], $d['currency'], $d['category'], $d['due_date'], $d['reference_number'], $d['notes']]);
        $id = (int)$pdo->lastInsertId();
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'payment.create', 'payment', $id, ['contract_id' => (int)$c['id'], 'amount' => $d['amount'], 'currency' => $d['currency']]);
        Response::ok(['id' => $id, 'message' => 'Payment added.'], 201);
    }

    public static function update(array $in): never
    {
        $u = Auth::requireApi('manager');
        $r = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canEdit($r, $u)) { Response::error('You do not have permission to edit this payment.', 403); }
        [$d, $e] = self::fields($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE payments SET amount=?,currency=?,category=?,due_date=?,reference_number=?,notes=? WHERE id=?')
            ->execute([$d['amount'], $d['currency'], $d['category'], $d['due_date'], $d['reference_number'], $d['notes'], $r['id']]);
        if ($r['due_date'] !== $d['due_date']) {
            Lifecycle::clearReminders('payment', (int)$r['id']);
            if (in_array($r['status'], ['due', 'overdue'], true)) { $pdo->prepare("UPDATE payments SET status='planned' WHERE id=?")->execute([$r['id']]); }
        }
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'payment.update', 'payment', (int)$r['id']);
        Response::ok(['message' => 'Payment updated.']);
    }

    public static function setStatus(array $in): never
    {
        $u = Auth::requireApi('manager');
        $r = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canEdit($r, $u)) { Response::error('You do not have permission to change this payment.', 403); }
        $to = (string)($in['status'] ?? '');
        if (!in_array($to, ['paid', 'cancelled', 'planned'], true)) { Response::error('Invalid status.', 422); }
        if ($to === 'paid' && $r['status'] === 'paid') { Response::error('This payment is already marked as paid.', 422); }
        $paid = null;
        if ($to === 'paid') {
            $paid = trim((string)($in['paid_date'] ?? '')) ?: date('Y-m-d');
            if (!Validator::date($paid)) { Response::error('Enter a valid payment date.', 422, ['paid_date' => 'Enter a valid payment date.']); }
        }
        $ref = mb_substr(trim((string)($in['reference_number'] ?? '')), 0, 100) ?: $r['reference_number'];
        Database::pdo()->prepare('UPDATE payments SET status=?, paid_date=?, reference_number=? WHERE id=?')->execute([$to, $paid, $ref, $r['id']]);
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'payment.status', 'payment', (int)$r['id'], ['from' => $r['status'], 'to' => $to]);
        Response::ok(['message' => ['paid' => 'Payment marked as paid.', 'cancelled' => 'Payment cancelled.', 'planned' => 'Payment reopened.'][$to]]);
    }

    public static function delete(array $in): never
    {
        $u = Auth::requireApi('manager');
        $r = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canEdit($r, $u)) { Response::error('You do not have permission to delete this payment.', 403); }
        Database::pdo()->prepare('DELETE FROM payments WHERE id=?')->execute([$r['id']]);
        Lifecycle::clearReminders('payment', (int)$r['id']);
        Audit::log((int)$u['id'], 'payment.delete', 'payment', (int)$r['id']);
        Response::ok(['message' => 'Payment deleted.']);
    }
}
