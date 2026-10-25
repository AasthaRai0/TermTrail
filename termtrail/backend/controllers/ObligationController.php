<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response, Validator};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\{Lifecycle, NotificationService};

final class ObligationController
{
    private const PRIORITIES = ['low', 'medium', 'high'];
    private const RECURRENCE = ['none', 'weekly', 'monthly', 'quarterly', 'yearly'];
    private const SORTS = ['due_date' => 'o.due_date', 'title' => 'o.title', 'status' => 'o.status', 'priority' => "FIELD(o.priority,'high','medium','low')"];

    private static function load(int $id, array $u): array
    {
        [$w, $p] = ContractModel::scope($u);
        $st = Database::pdo()->prepare(
            "SELECT o.*, c.title AS contract_title, c.owner_id AS contract_owner_id, ow.name AS owner_name, ap.name AS approver_name
             FROM obligations o JOIN contracts c ON c.id=o.contract_id LEFT JOIN users ow ON ow.id=o.owner_id LEFT JOIN users ap ON ap.id=o.approved_by
             WHERE o.id=? AND $w");
        $st->execute(array_merge([$id], $p));
        $o = $id > 0 ? $st->fetch() : false;
        if (!$o) { Response::error('Obligation not found.', 404); }
        return $o;
    }

    private static function canEdit(array $o, array $u): bool { return ContractModel::canWrite(['owner_id' => $o['contract_owner_id']], $u); }
    private static function canStatus(array $o, array $u): bool
    {
        return self::canEdit($o, $u) || ($u['role'] === 'manager' && (int)$o['owner_id'] === (int)$u['id']);
    }

    private static function writableContract(int $contractId, array $u): array
    {
        $c = $contractId > 0 ? ContractModel::find($contractId, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404, ['contract_id' => 'Choose a valid contract.']); }
        if (!ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to add obligations to this contract.', 403); }
        return $c;
    }

    public static function fields(array $in, int $contractId): array
    {
        $s = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $d = [];
        $e = [];
        $d['title'] = $s('title', 255);
        if ($d['title'] === '') { $e['title'] = 'Title is required.'; }
        $d['description'] = $s('description', 5000) ?: null;
        $d['responsible_party'] = $s('responsible_party', 190) ?: null;
        $d['source_quote'] = $s('source_quote', 2000) ?: null;
        $due = trim((string)($in['due_date'] ?? ''));
        if ($due === '') { $d['due_date'] = null; } elseif (!Validator::date($due)) { $e['due_date'] = 'Enter a valid date.'; } else { $d['due_date'] = $due; }
        $rec = (string)($in['recurrence'] ?? 'none') ?: 'none';
        if (!in_array($rec, self::RECURRENCE, true)) { $e['recurrence'] = 'Choose a valid recurrence.'; }
        elseif ($rec !== 'none' && empty($d['due_date']) && empty($e['due_date'])) { $e['due_date'] = 'A due date is required for recurring obligations.'; }
        $d['recurrence'] = $rec === 'none' ? null : $rec;
        $pri = (string)($in['priority'] ?? 'medium');
        if (!in_array($pri, self::PRIORITIES, true)) { $e['priority'] = 'Choose a valid priority.'; }
        $d['priority'] = $pri;
        $own = (int)($in['owner_id'] ?? 0);
        $d['owner_id'] = null;
        if ($own > 0) {
            if (Lifecycle::validAssignee($contractId, $own)) { $d['owner_id'] = $own; }
            else { $e['owner_id'] = 'The owner must be an active user with access to this contract.'; }
        }
        return [$d, $e];
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        [$w, $p] = ContractModel::scope($u);
        $where = [$w, 'c.is_archived=0'];
        if (($q = trim((string)($_GET['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(o.title LIKE ? OR o.description LIKE ? OR c.title LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        if ((int)($_GET['contract_id'] ?? 0) > 0) { $where[] = 'o.contract_id=?'; $p[] = (int)$_GET['contract_id']; }
        $st = (string)($_GET['status'] ?? '');
        if (in_array($st, ['open', 'completed', 'overdue', 'cancelled'], true)) { $where[] = 'o.status=?'; $p[] = $st; }
        elseif ($st === 'active') { $where[] = "o.status IN ('open','overdue')"; }
        if (in_array($_GET['priority'] ?? '', self::PRIORITIES, true)) { $where[] = 'o.priority=?'; $p[] = $_GET['priority']; }
        $own = $_GET['owner_id'] ?? '';
        if ($own === 'me') { $where[] = 'o.owner_id=?'; $p[] = (int)$u['id']; }
        elseif ((int)$own > 0) { $where[] = 'o.owner_id=?'; $p[] = (int)$own; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
            if (Validator::date((string)($_GET[$k] ?? ''))) { $where[] = "o.due_date $op ?"; $p[] = $_GET[$k]; }
        }
        $sort = self::SORTS[$_GET['sort'] ?? ''] ?? 'o.due_date';
        $dir = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $per = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $from = 'FROM obligations o JOIN contracts c ON c.id=o.contract_id LEFT JOIN users ow ON ow.id=o.owner_id WHERE ' . implode(' AND ', $where);
        $pdo = Database::pdo();
        $cnt = $pdo->prepare("SELECT COUNT(*) $from"); $cnt->execute($p);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $per));
        $page = min($page, $pages);
        $off = ($page - 1) * $per;
        $q2 = $pdo->prepare("SELECT o.id,o.title,o.due_date,o.priority,o.status,o.recurrence,o.contract_id,c.title AS contract_title,c.owner_id AS contract_owner_id,o.owner_id,ow.name AS owner_name
            $from ORDER BY (o.due_date IS NULL), $sort $dir, o.id DESC LIMIT $per OFFSET $off");
        $q2->execute($p);
        $items = $q2->fetchAll();
        foreach ($items as &$i) { $i['can_edit'] = ContractModel::canWrite(['owner_id' => $i['contract_owner_id']], $u); unset($i['contract_owner_id']); }
        unset($i);
        Response::ok(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per]);
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        Lifecycle::sync();
        $o = self::load((int)($_GET['id'] ?? 0), $u);
        $o['can_edit'] = self::canEdit($o, $u);
        $o['can_status'] = self::canStatus($o, $u);
        unset($o['contract_owner_id']);
        Response::ok(['obligation' => $o]);
    }

    public static function create(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::writableContract((int)($in['contract_id'] ?? 0), $u);
        [$d, $e] = self::fields($in, (int)$c['id']);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,recurrence,priority,source_quote) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$c['id'], $d['title'], $d['description'], $d['responsible_party'], $d['owner_id'], $d['due_date'], $d['recurrence'], $d['priority'], $d['source_quote']]);
        $id = (int)$pdo->lastInsertId();
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'obligation.create', 'obligation', $id, ['contract_id' => (int)$c['id']]);
        if ($d['owner_id'] && (int)$d['owner_id'] !== (int)$u['id']) {
            NotificationService::create((int)$d['owner_id'], 'assignment', 'Obligation assigned to you', '"' . $d['title'] . '" on "' . $c['title'] . '".', 'index.php?page=obligation_view&id=' . $id);
        }
        Response::ok(['id' => $id, 'redirect' => APP_URL . '/index.php?page=obligation_view&id=' . $id], 201);
    }

    public static function update(array $in): never
    {
        $u = Auth::requireApi('manager');
        $o = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canEdit($o, $u)) { Response::error('You do not have permission to edit this obligation.', 403); }
        [$d, $e] = self::fields($in, (int)$o['contract_id']);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        Database::pdo()->prepare('UPDATE obligations SET title=?,description=?,responsible_party=?,owner_id=?,due_date=?,recurrence=?,priority=?,source_quote=? WHERE id=?')
            ->execute([$d['title'], $d['description'], $d['responsible_party'], $d['owner_id'], $d['due_date'], $d['recurrence'], $d['priority'], $d['source_quote'], $o['id']]);
        if (($o['due_date'] ?? null) !== $d['due_date']) {
            Lifecycle::clearReminders('obligation', (int)$o['id']);
            if (in_array($o['status'], ['overdue'], true) && $d['due_date'] && $d['due_date'] >= date('Y-m-d')) {
                Database::pdo()->prepare("UPDATE obligations SET status='open' WHERE id=?")->execute([$o['id']]);
            }
        }
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'obligation.update', 'obligation', (int)$o['id']);
        if ($d['owner_id'] && (int)$d['owner_id'] !== (int)$o['owner_id'] && (int)$d['owner_id'] !== (int)$u['id']) {
            NotificationService::create((int)$d['owner_id'], 'assignment', 'Obligation assigned to you', '"' . $d['title'] . '" on "' . $o['contract_title'] . '".', 'index.php?page=obligation_view&id=' . $o['id']);
        }
        Response::ok(['message' => 'Obligation updated.', 'redirect' => APP_URL . '/index.php?page=obligation_view&id=' . $o['id']]);
    }

    public static function setStatus(array $in): never
    {
        $u = Auth::requireApi('manager');
        $o = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canStatus($o, $u)) { Response::error('You do not have permission to change this obligation.', 403); }
        $to = (string)($in['status'] ?? '');
        $from = (string)$o['status'];
        $allowed = ['completed' => ['open', 'overdue'], 'cancelled' => ['open', 'overdue'], 'open' => ['completed', 'cancelled']];
        if (!isset($allowed[$to]) || !in_array($from, $allowed[$to], true)) { Response::error("Cannot change status from $from to $to.", 422); }
        $notes = mb_substr(trim((string)($in['notes'] ?? '')), 0, 2000) ?: null;

        $pdo = Database::pdo();
        $nextId = null;
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE obligations SET status=?, completion_notes=? WHERE id=?')->execute([$to, $to === 'open' ? null : $notes, $o['id']]);
            if ($to === 'completed' && $o['recurrence'] && $o['due_date']) {
                $next = Lifecycle::nextDate((string)$o['due_date'], (string)$o['recurrence']);
                if ($next) {
                    $pdo->prepare('INSERT INTO obligations (contract_id,title,description,responsible_party,owner_id,due_date,recurrence,priority,source_quote) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$o['contract_id'], $o['title'], $o['description'], $o['responsible_party'], $o['owner_id'], $next, $o['recurrence'], $o['priority'], $o['source_quote']]);
                    $nextId = (int)$pdo->lastInsertId();
                }
            }
            $pdo->commit();
        } catch (\Throwable $ex) { $pdo->rollBack(); throw $ex; }
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'obligation.status', 'obligation', (int)$o['id'], ['from' => $from, 'to' => $to, 'next_id' => $nextId]);
        $msg = ['completed' => 'Marked as completed.', 'cancelled' => 'Obligation cancelled.', 'open' => 'Obligation reopened.'][$to];
        if ($nextId) { $msg .= ' The next recurrence was scheduled.'; }
        Response::ok(['message' => $msg, 'next_id' => $nextId]);
    }

    public static function delete(array $in): never
    {
        $u = Auth::requireApi('manager');
        $o = self::load((int)($in['id'] ?? 0), $u);
        if (!self::canEdit($o, $u)) { Response::error('You do not have permission to delete this obligation.', 403); }
        Database::pdo()->prepare('DELETE FROM obligations WHERE id=?')->execute([$o['id']]);
        Lifecycle::clearReminders('obligation', (int)$o['id']);
        Audit::log((int)$u['id'], 'obligation.delete', 'obligation', (int)$o['id'], ['title' => $o['title']]);
        Response::ok(['message' => 'Obligation deleted.']);
    }
}
