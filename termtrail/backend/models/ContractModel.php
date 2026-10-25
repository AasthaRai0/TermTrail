<?php
declare(strict_types=1);
namespace TermTrail\models;

use PDO;
use TermTrail\config\Database;

final class ContractModel
{
    public const STATUSES = ['draft', 'under_review', 'active', 'expired', 'terminated'];
    public const SORTS = [
        'title' => 'c.title', 'expiry_date' => 'c.expiry_date', 'created_at' => 'c.created_at',
        'contract_value' => 'c.contract_value', 'status' => 'c.status', 'risk' => 'c.risk_score',
    ];
    private const COLS = ['owner_id','title','contract_type','counterparty_id','reviewer_id','effective_date','start_date','expiry_date',
        'renewal_date','notice_period_days','contract_value','currency','status','tags','description',
        'original_filename','stored_filename','mime_type','file_size','extracted_text_path','extraction_error','analysis_status'];

    /** Admin: everything. Others: contracts they own or are assigned to review. */
    public static function scope(array $u, string $a = 'c'): array
    {
        if ($u['role'] === 'admin') { return ['1=1', []]; }
        return ["($a.owner_id = ? OR $a.reviewer_id = ?)", [(int)$u['id'], (int)$u['id']]];
    }

    public static function find(int $id, array $u): ?array
    {
        [$w, $p] = self::scope($u);
        $st = Database::pdo()->prepare(
            "SELECT c.*, cp.name AS counterparty_name, rv.name AS reviewer_name, ow.name AS owner_name
             FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id
             LEFT JOIN users rv ON rv.id=c.reviewer_id LEFT JOIN users ow ON ow.id=c.owner_id
             WHERE c.id=? AND $w");
        $st->execute(array_merge([$id], $p));
        return $st->fetch() ?: null;
    }

    public static function canWrite(array $c, array $u): bool
    {
        return $u['role'] === 'admin' || ($u['role'] === 'manager' && (int)$c['owner_id'] === (int)$u['id']);
    }

    public static function canNote(array $c, array $u): bool
    {
        if (!in_array($u['role'], ['admin', 'manager'], true)) { return false; }
        return $u['role'] === 'admin' || (int)$c['owner_id'] === (int)$u['id'] || (int)$c['reviewer_id'] === (int)$u['id'];
    }

    /** Remove internal storage details before sending to the browser. */
    public static function publicRow(array $c): array
    {
        $c['has_file'] = !empty($c['stored_filename']);
        $c['has_text'] = !empty($c['extracted_text_path']);
        unset($c['stored_filename'], $c['extracted_text_path']);
        return $c;
    }

    public static function insert(array $data): int
    {
        $cols = array_values(array_intersect(array_keys($data), self::COLS));
        $sql = 'INSERT INTO contracts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $pdo = Database::pdo();
        $pdo->prepare($sql)->execute(array_map(fn($c) => $data[$c], $cols));
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $cols = array_values(array_intersect(array_keys($data), self::COLS));
        if (!$cols) { return; }
        $sql = 'UPDATE contracts SET ' . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE id=?';
        Database::pdo()->prepare($sql)->execute(array_merge(array_map(fn($c) => $data[$c], $cols), [$id]));
    }

    public static function saveChunks(int $contractId, array $chunks): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM document_chunks WHERE contract_id=?')->execute([$contractId]);
        $st = $pdo->prepare('INSERT INTO document_chunks (contract_id,chunk_index,content,word_count) VALUES (?,?,?,?)');
        foreach ($chunks as $i => $c) { $st->execute([$contractId, $i, $c, str_word_count($c)]); }
    }

    /** Turns free text into a safe boolean-mode FULLTEXT expression (or null). */
    public static function ftExpression(string $q): ?string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_slice(array_filter($tokens, fn($t) => mb_strlen($t) >= 3), 0, 6);
        return $tokens ? implode(' ', array_map(fn($t) => '+' . $t . '*', $tokens)) : null;
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function search(array $q, array $u): array
    {
        [$w, $params] = self::scope($u);
        $where = [$w];
        $where[] = !empty($q['archived']) ? 'c.is_archived=1' : 'c.is_archived=0';

        $term = trim((string)($q['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . addcslashes(mb_substr($term, 0, 100), '%_\\') . '%';
            $parts = ['c.title LIKE ?', 'c.description LIKE ?', 'c.tags LIKE ?', 'cp.name LIKE ?', 'c.contract_type LIKE ?'];
            $params = array_merge($params, array_fill(0, 5, $like));
            if ($ft = self::ftExpression($term)) {
                $parts[] = 'EXISTS (SELECT 1 FROM document_chunks d WHERE d.contract_id=c.id AND MATCH(d.content) AGAINST (? IN BOOLEAN MODE))';
                $params[] = $ft;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (in_array($q['status'] ?? '', self::STATUSES, true)) { $where[] = 'c.status=?'; $params[] = $q['status']; }
        $risk = $q['risk'] ?? '';
        if (in_array($risk, ['low', 'medium', 'high'], true)) { $where[] = 'c.risk_level=?'; $params[] = $risk; }
        elseif ($risk === 'unassessed') { $where[] = 'c.risk_level IS NULL'; }
        if (($t = trim((string)($q['type'] ?? ''))) !== '') { $where[] = 'c.contract_type=?'; $params[] = $t; }
        if ((int)($q['counterparty_id'] ?? 0) > 0) { $where[] = 'c.counterparty_id=?'; $params[] = (int)$q['counterparty_id']; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) {
            $v = (string)($q[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) { $where[] = "c.expiry_date $op ?"; $params[] = $v; }
        }
        if (($q['quick'] ?? '') === 'expiring') {
            $where[] = "c.status='active' AND c.expiry_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY";
        } elseif (($q['quick'] ?? '') === 'expired') {
            $where[] = "(c.status='expired' OR (c.expiry_date < CURDATE() AND c.status='active'))";
        }

        $sortCol = self::SORTS[$q['sort'] ?? ''] ?? 'c.created_at';
        $dir = strtolower((string)($q['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $perPage = min(50, max(5, (int)($q['per_page'] ?? 10)));
        $page = max(1, (int)($q['page'] ?? 1));
        $from = 'FROM contracts c LEFT JOIN counterparties cp ON cp.id=c.counterparty_id WHERE ' . implode(' AND ', $where);

        $pdo = Database::pdo();
        $cnt = $pdo->prepare("SELECT COUNT(*) $from");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare("SELECT c.id,c.title,c.contract_type,c.status,c.risk_level,c.risk_score,c.expiry_date,c.contract_value,c.currency,
              c.is_archived,c.analysis_status,c.created_at,c.counterparty_id,cp.name AS counterparty_name,(c.stored_filename IS NOT NULL) AS has_file
              $from ORDER BY $sortCol $dir, c.id DESC LIMIT $perPage OFFSET $offset");
        $st->execute($params);
        return ['items' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }
}
