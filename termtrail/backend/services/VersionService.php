<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;
use TermTrail\models\ContractModel;

/** Contract document versions. The newest version is always the contract's current file; older files are kept for history and comparison. */
final class VersionService
{
    /** Version 1 = the original upload. Backfills a row for contracts created before versioning existed. */
    public static function ensureBase(array $c): void
    {
        if (empty($c['stored_filename'])) { return; }
        $pdo = Database::pdo();
        $n = $pdo->prepare('SELECT COUNT(*) FROM contract_versions WHERE contract_id=?');
        $n->execute([$c['id']]);
        if ((int)$n->fetchColumn() > 0) { return; }
        $pdo->prepare("INSERT INTO contract_versions (contract_id,version_number,original_filename,stored_filename,mime_type,extracted_text_path,notes,uploaded_by,created_at) VALUES (?,1,?,?,?,?,'Original upload',?,?)")
            ->execute([$c['id'], $c['original_filename'], $c['stored_filename'], $c['mime_type'], $c['extracted_text_path'], $c['owner_id'], $c['created_at']]);
    }

    public static function insertBase(int $contractId, array $c, int $userId): void
    {
        Database::pdo()->prepare("INSERT INTO contract_versions (contract_id,version_number,original_filename,stored_filename,mime_type,extracted_text_path,notes,uploaded_by) VALUES (?,1,?,?,?,?,'Original upload',?)")
            ->execute([$contractId, $c['original_filename'], $c['stored_filename'], $c['mime_type'], $c['extracted_text_path'], $userId]);
    }

    public static function list(int $contractId): array
    {
        $st = Database::pdo()->prepare('SELECT v.id,v.version_number,v.original_filename,v.mime_type,v.notes,v.created_at,v.uploaded_by,u.name AS uploaded_by_name,
            (v.extracted_text_path IS NOT NULL) AS has_text, v.stored_filename FROM contract_versions v JOIN users u ON u.id=v.uploaded_by WHERE v.contract_id=? ORDER BY v.version_number DESC');
        $st->execute([$contractId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $p = FileStorage::contractPath($r['stored_filename']);
            $r['file_size'] = is_file($p) ? (int)filesize($p) : null;
            unset($r['stored_filename']);
        }
        return $rows;
    }

    public static function find(int $id, int $contractId): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM contract_versions WHERE id=? AND contract_id=?');
        $st->execute([$id, $contractId]);
        return $st->fetch() ?: null;
    }

    /** Stores a new version and makes it the contract's current document. */
    public static function add(array $c, array $u, array $file, string $tmp, ?string $notes): array
    {
        $pdo = Database::pdo();
        $cid = (int)$c['id'];
        self::ensureBase($c);
        $proc = ContractFileService::process($file, $tmp);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('SELECT id FROM contracts WHERE id=? FOR UPDATE')->execute([$cid]);
            $q = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM contract_versions WHERE contract_id=?');
            $q->execute([$cid]);
            $next = (int)$q->fetchColumn();
            $pdo->prepare('INSERT INTO contract_versions (contract_id,version_number,original_filename,stored_filename,mime_type,extracted_text_path,notes,uploaded_by) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$cid, $next, $file['original'], $proc['stored'], $file['mime'], $proc['text_file'], $notes ? mb_substr($notes, 0, 1000) : null, $u['id']]);
            ContractModel::update($cid, ['original_filename' => $file['original'], 'stored_filename' => $proc['stored'], 'mime_type' => $file['mime'], 'file_size' => $file['size'],
                'extracted_text_path' => $proc['text_file'], 'extraction_error' => $proc['error'] ? mb_substr($proc['error'], 0, 255) : null,
                'analysis_status' => ($c['analysis_status'] ?? 'none') === 'none' ? 'uploaded' : $c['analysis_status']]);
            ContractModel::saveChunks($cid, $proc['text'] ? TextChunker::chunk($proc['text']) : []);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            ContractFileService::discard($proc);
            throw $e;
        }
        return ['version_number' => $next, 'extraction_error' => $proc['error']];
    }

    /** All file names (originals + extracted text) that belong to a contract, for cleanup on delete. */
    public static function allFiles(array $c): array
    {
        $st = Database::pdo()->prepare('SELECT stored_filename, extracted_text_path FROM contract_versions WHERE contract_id=?');
        $st->execute([$c['id']]);
        $files = ['stored' => [$c['stored_filename'] ?? null], 'text' => [$c['extracted_text_path'] ?? null]];
        foreach ($st->fetchAll() as $r) { $files['stored'][] = $r['stored_filename']; $files['text'][] = $r['extracted_text_path']; }
        return ['stored' => array_values(array_unique(array_filter($files['stored']))), 'text' => array_values(array_unique(array_filter($files['text'])))];
    }
}
