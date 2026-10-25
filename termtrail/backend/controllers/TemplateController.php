<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\services\AiJson;

/** Shared clause library: everyone signed in can read; managers add their own; admins manage all. Reference material only. */
final class TemplateController
{
    private static function canEdit(array $t, array $u): bool
    {
        return $u['role'] === 'admin' || ($u['role'] === 'manager' && (int)$t['created_by'] === (int)$u['id']);
    }

    private static function load(int $id, array $u, bool $write = false): array
    {
        $st = Database::pdo()->prepare('SELECT t.*, us.name AS creator_name FROM clause_templates t JOIN users us ON us.id=t.created_by WHERE t.id=?');
        $st->execute([$id]);
        $t = $id > 0 ? $st->fetch() : false;
        if (!$t) { Response::error('Template not found.', 404); }
        if ($write && !self::canEdit($t, $u)) { Response::error('You can only change templates you created.', 403); }
        return $t;
    }

    private static function clean(array $in): array
    {
        $s = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
        $d = ['name' => $s('name', 190), 'category' => (string)($in['category'] ?? ''), 'clause_text' => $s('clause_text', 20000),
            'plain_explanation' => $s('plain_explanation', 5000) ?: null, 'tags' => $s('tags', 255) ?: null, 'notes' => $s('notes', 2000) ?: null];
        $e = [];
        if ($d['name'] === '') { $e['name'] = 'Name is required.'; }
        if (!in_array($d['category'], AiJson::CLAUSE_TYPES, true)) { $e['category'] = 'Choose a category.'; }
        if ($d['clause_text'] === '') { $e['clause_text'] = 'Clause text is required.'; }
        return [$d, $e];
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        $where = [!empty($_GET['archived']) ? 't.is_archived=1' : 't.is_archived=0']; $p = [];
        if (in_array($_GET['category'] ?? '', AiJson::CLAUSE_TYPES, true)) { $where[] = 't.category=?'; $p[] = $_GET['category']; }
        if (($q = trim((string)($_GET['q'] ?? ''))) !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
            $where[] = '(t.name LIKE ? OR t.clause_text LIKE ? OR t.tags LIKE ? OR t.plain_explanation LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        $per = min(24, max(6, (int)($_GET['per_page'] ?? 12)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pdo = Database::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM clause_templates t WHERE ' . implode(' AND ', $where)); $cnt->execute($p);
        $total = (int)$cnt->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
        $st = $pdo->prepare('SELECT t.id,t.name,t.category,t.clause_text,t.plain_explanation,t.tags,t.notes,t.created_by,t.is_archived,t.created_at,t.updated_at,us.name AS creator_name
            FROM clause_templates t JOIN users us ON us.id=t.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY t.category, t.name LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
        $st->execute($p);
        $items = $st->fetchAll();
        foreach ($items as &$i) { $i['can_edit'] = self::canEdit($i, $u); }
        unset($i);
        Response::ok(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per, 'categories' => AiJson::CLAUSE_TYPES]);
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        $t = self::load((int)($_GET['id'] ?? 0), $u);
        $t['can_edit'] = self::canEdit($t, $u);
        Response::ok(['template' => $t]);
    }

    public static function create(array $in): never
    {
        $u = Auth::requireApi('manager');
        [$d, $e] = self::clean($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO clause_templates (name,category,clause_text,plain_explanation,tags,notes,created_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$d['name'], $d['category'], $d['clause_text'], $d['plain_explanation'], $d['tags'], $d['notes'], $u['id']]);
        $id = (int)$pdo->lastInsertId();
        Audit::log((int)$u['id'], 'template.create', 'template', $id, ['name' => $d['name']]);
        Response::ok(['id' => $id, 'message' => 'Template added.'], 201);
    }

    public static function update(array $in): never
    {
        $u = Auth::requireApi('manager');
        $t = self::load((int)($in['id'] ?? 0), $u, true);
        [$d, $e] = self::clean($in);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        Database::pdo()->prepare('UPDATE clause_templates SET name=?,category=?,clause_text=?,plain_explanation=?,tags=?,notes=? WHERE id=?')
            ->execute([$d['name'], $d['category'], $d['clause_text'], $d['plain_explanation'], $d['tags'], $d['notes'], $t['id']]);
        Audit::log((int)$u['id'], 'template.update', 'template', (int)$t['id']);
        Response::ok(['message' => 'Template updated.']);
    }

    public static function setArchived(array $in, bool $archived): never
    {
        $u = Auth::requireApi('manager');
        $t = self::load((int)($in['id'] ?? 0), $u, true);
        Database::pdo()->prepare('UPDATE clause_templates SET is_archived=? WHERE id=?')->execute([$archived ? 1 : 0, $t['id']]);
        Audit::log((int)$u['id'], $archived ? 'template.archive' : 'template.restore', 'template', (int)$t['id']);
        Response::ok(['message' => $archived ? 'Template archived.' : 'Template restored.']);
    }
}
