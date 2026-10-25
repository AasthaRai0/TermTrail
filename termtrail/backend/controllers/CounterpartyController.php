<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response, Validator};
use TermTrail\middleware\Auth;

final class CounterpartyController
{
    private static function scope(array $u): array
    {
        if ($u['role'] === 'admin') { return ['1=1', []]; }
        return ['(cp.owner_id = ? OR EXISTS (SELECT 1 FROM contracts rc WHERE rc.counterparty_id=cp.id AND rc.reviewer_id = ?))', [(int)$u['id'], (int)$u['id']]];
    }

    private static function find(int $id, array $u): ?array
    {
        [$w, $p] = self::scope($u);
        $st = Database::pdo()->prepare("SELECT cp.* FROM counterparties cp WHERE cp.id=? AND $w");
        $st->execute(array_merge([$id], $p));
        return $st->fetch() ?: null;
    }

    private static function canWrite(array $cp, array $u): bool
    {
        return $u['role'] === 'admin' || ($u['role'] === 'manager' && (int)$cp['owner_id'] === (int)$u['id']);
    }

    /** Used by contracts: may this user attach this counterparty? (write=true requires ownership, non-archived) */
    public static function accessible(int $id, array $u, bool $forLink = false): bool
    {
        $cp = self::find($id, $u);
        if (!$cp) { return false; }
        return !$forLink || ($u['role'] === 'admin' || (int)$cp['owner_id'] === (int)$u['id']);
    }

    private static function load(int $id, array $u, bool $write = false): array
    {
        $cp = $id > 0 ? self::find($id, $u) : null;
        if (!$cp) { Response::error('Counterparty not found.', 404); }
        if ($write && !self::canWrite($cp, $u)) { Response::error('You do not have permission to modify this counterparty.', 403); }
        return $cp;
    }

    private static function clean(array $in): array
    {
        $s = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $d = ['name' => $s('name', 190), 'contact_person' => $s('contact_person', 150) ?: null, 'email' => strtolower($s('email', 190)) ?: null,
              'phone' => $s('phone', 40) ?: null, 'address' => $s('address', 2000) ?: null, 'notes' => $s('notes', 2000) ?: null];
        $e = [];
        if ($d['name'] === '') { $e['name'] = 'Name is required.'; }
        if ($d['email'] !== null && !Validator::email($d['email'])) { $e['email'] = 'Enter a valid email address.'; }
        if ($d['phone'] !== null && !preg_match('/^[0-9+()\-.\s]{5,40}$/', $d['phone'])) { $e['phone'] = 'Enter a valid phone number.'; }
        return [$d, $e];
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        [$w, $p] = self::scope($u);
        $where = [$w, !empty($_GET['archived']) ? 'cp.is_archived=1' : 'cp.is_archived=0'];
        if (($q = trim((string)($_GET['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(cp.name LIKE ? OR cp.contact_person LIKE ? OR cp.email LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $from = 'FROM counterparties cp WHERE ' . implode(' AND ', $where);
        $pdo = Database::pdo();
        $cnt = $pdo->prepare("SELECT COUNT(*) $from"); $cnt->execute($p);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $off = ($page - 1) * $perPage;
        $st = $pdo->prepare("SELECT cp.id,cp.name,cp.contact_person,cp.email,cp.phone,cp.is_archived,cp.owner_id,
            (SELECT COUNT(*) FROM contracts c WHERE c.counterparty_id=cp.id) AS contract_count $from ORDER BY cp.name ASC LIMIT $perPage OFFSET $off");
        $st->execute($p);
        $items = $st->fetchAll();
        foreach ($items as &$it) {
            $it['can_edit'] = $u['role'] === 'admin' || ($u['role'] === 'manager' && (int)$it['owner_id'] === (int)$u['id']);
            unset($it['owner_id']);
        }
        unset($it);
        Response::ok(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage]);
    }

    public static function options(): never
    {
        $u = Auth::requireApi();
        [$w, $p] = self::scope($u);
        $own = $u['role'] === 'admin' ? '1=1' : 'cp.owner_id = ?';
        if ($u['role'] !== 'admin') { $p = [(int)$u['id']]; $w = '1=1'; }
        $st = Database::pdo()->prepare("SELECT cp.id, cp.name FROM counterparties cp WHERE $w AND $own AND cp.is_archived=0 ORDER BY cp.name LIMIT 500");
        $st->execute($p);
        Response::ok(['items' => $st->fetchAll()]);
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        $cp = self::load((int)($_GET['id'] ?? 0), $u);
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM contracts WHERE counterparty_id=?');
        $st->execute([$cp['id']]);
        $cp['contract_count'] = (int)$st->fetchColumn();
        Response::ok(['counterparty' => $cp, 'can_write' => self::canWrite($cp, $u)]);
    }

    public static function create(array $in): never
    {
        $u = Auth::requireApi('manager');
        [$d, $e] = self::clean($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO counterparties (owner_id,name,contact_person,email,phone,address,notes) VALUES (?,?,?,?,?,?,?)')
            ->execute([$u['id'], $d['name'], $d['contact_person'], $d['email'], $d['phone'], $d['address'], $d['notes']]);
        $id = (int)$pdo->lastInsertId();
        Audit::log((int)$u['id'], 'counterparty.create', 'counterparty', $id, ['name' => $d['name']]);
        Response::ok(['id' => $id, 'message' => 'Counterparty added.'], 201);
    }

    public static function update(array $in): never
    {
        $u = Auth::requireApi('manager');
        $cp = self::load((int)($in['id'] ?? 0), $u, true);
        [$d, $e] = self::clean($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        Database::pdo()->prepare('UPDATE counterparties SET name=?,contact_person=?,email=?,phone=?,address=?,notes=? WHERE id=?')
            ->execute([$d['name'], $d['contact_person'], $d['email'], $d['phone'], $d['address'], $d['notes'], $cp['id']]);
        Audit::log((int)$u['id'], 'counterparty.update', 'counterparty', (int)$cp['id']);
        Response::ok(['message' => 'Counterparty updated.']);
    }

    public static function setArchived(array $in, bool $archived): never
    {
        $u = Auth::requireApi('manager');
        $cp = self::load((int)($in['id'] ?? 0), $u, true);
        Database::pdo()->prepare('UPDATE counterparties SET is_archived=? WHERE id=?')->execute([$archived ? 1 : 0, $cp['id']]);
        Audit::log((int)$u['id'], $archived ? 'counterparty.archive' : 'counterparty.restore', 'counterparty', (int)$cp['id']);
        Response::ok(['message' => $archived ? 'Counterparty archived.' : 'Counterparty restored.']);
    }

    public static function delete(array $in): never
    {
        $u = Auth::requireApi('manager');
        $cp = self::load((int)($in['id'] ?? 0), $u, true);
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM contracts WHERE counterparty_id=?');
        $st->execute([$cp['id']]);
        if ((int)$st->fetchColumn() > 0) {
            Response::error('This counterparty has linked contracts and cannot be deleted. Archive it instead, or reassign its contracts first.', 409);
        }
        Database::pdo()->prepare('DELETE FROM counterparties WHERE id=?')->execute([$cp['id']]);
        Audit::log((int)$u['id'], 'counterparty.delete', 'counterparty', (int)$cp['id'], ['name' => $cp['name']]);
        Response::ok(['message' => 'Counterparty deleted.']);
    }
}
