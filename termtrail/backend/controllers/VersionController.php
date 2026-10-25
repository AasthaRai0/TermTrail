<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\{AiException, AiJson, FileStorage, FileValidator, GeminiClient, Prompts, VersionDiffService, VersionException, VersionService};

final class VersionController
{
    private const COMPARES_PER_HOUR = 30;

    private static function contract(int $id, array $u, bool $write): array
    {
        $c = $id > 0 ? ContractModel::find($id, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404); }
        if ($write && !ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to add versions to this contract.', 403); }
        return $c;
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($_GET['contract_id'] ?? 0), $u, false);
        VersionService::ensureBase($c);
        $st = Database::pdo()->prepare('SELECT vc.id, vc.created_at, vc.added_count, vc.removed_count, fv.version_number AS from_version, tv.version_number AS to_version, us.name AS compared_by_name
            FROM version_comparisons vc JOIN contract_versions fv ON fv.id=vc.from_version_id JOIN contract_versions tv ON tv.id=vc.to_version_id JOIN users us ON us.id=vc.compared_by
            WHERE vc.contract_id=? ORDER BY vc.id DESC LIMIT 30');
        $st->execute([$c['id']]);
        Response::ok(['versions' => VersionService::list((int)$c['id']), 'comparisons' => $st->fetchAll(), 'can_write' => ContractModel::canWrite($c, $u)]);
    }

    public static function upload(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::contract((int)($in['contract_id'] ?? 0), $u, true);
        try { $file = FileValidator::validate($_FILES['file'] ?? []); }
        catch (\InvalidArgumentException $e) { Response::error($e->getMessage(), 422, ['file' => $e->getMessage()]); }
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=? AND action IN ('contract.upload','version.upload') AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $st->execute([$u['id']]);
        if ((int)$st->fetchColumn() >= 30) { Response::error('Upload limit reached. Please try again later.', 429); }
        $r = VersionService::add($c, $u, $file, $_FILES['file']['tmp_name'], trim((string)($in['notes'] ?? '')) ?: null);
        Audit::log((int)$u['id'], 'version.upload', 'contract', (int)$c['id'], ['version' => $r['version_number'], 'ext' => $file['ext'], 'size' => $file['size'], 'extracted' => $r['extraction_error'] === null]);
        Response::ok(['message' => 'Version ' . $r['version_number'] . ' saved' . ($r['extraction_error'] ? ', but its text could not be extracted.' : '.'), 'version_number' => $r['version_number'], 'extraction_error' => $r['extraction_error']], 201);
    }

    public static function download(): never
    {
        $u = Auth::requireApi();
        $cid = (int)Database::pdo()->query('SELECT contract_id FROM contract_versions WHERE id=' . (int)($_GET['id'] ?? 0))->fetchColumn();
        $c = self::contract($cid, $u, false);
        $v = VersionService::find((int)$_GET['id'], (int)$c['id']);
        if (!$v) { Response::error('Version not found.', 404); }
        $path = FileStorage::contractPath($v['stored_filename']);
        if (!is_file($path)) { Response::error('The file is missing from storage.', 404); }
        $ext = strtolower(pathinfo($v['stored_filename'], PATHINFO_EXTENSION));
        $types = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'txt' => 'text/plain; charset=utf-8'];
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$v['original_filename']) ?: ('version.' . $ext);
        Audit::log((int)$u['id'], 'version.download', 'contract', (int)$c['id'], ['version' => (int)$v['version_number']]);
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function aiSummary(array $diff): array
    {
        if (!GeminiClient::configured() || $diff['identical']) { return [null, null]; }
        try {
            $raw = GeminiClient::generate(Prompts::VERSION_SYSTEM, "<changes>\n" . VersionDiffService::changedText($diff) . "</changes>", 1500);
            return [AiJson::versionSummary(AiJson::parse($raw)), null];
        } catch (AiException $e) { return [null, $e->getMessage()]; }
    }

    public static function compare(array $in): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($in['contract_id'] ?? 0), $u, false);
        $from = VersionService::find((int)($in['from_version_id'] ?? 0), (int)$c['id']);
        $to = VersionService::find((int)($in['to_version_id'] ?? 0), (int)$c['id']);
        if (!$from || !$to) { Response::error('Choose two versions of this contract.', 422); }
        if ((int)$from['id'] === (int)$to['id']) { Response::error('Choose two different versions.', 422); }
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=? AND action='version.compare' AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $st->execute([$u['id']]);
        if ((int)$st->fetchColumn() >= self::COMPARES_PER_HOUR) { Response::error('Comparison limit reached for this hour.', 429); }
        try { $diff = VersionDiffService::compare($from, $to); }
        catch (VersionException $e) { Response::error($e->getMessage(), 422); }
        [$ai, $aiError] = self::aiSummary($diff);
        $summary = ['stats' => $diff['stats'], 'flags' => $diff['flags'], 'ai' => $ai, 'ai_error' => $aiError, 'truncated' => $diff['truncated']];
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO version_comparisons (contract_id,from_version_id,to_version_id,added_count,removed_count,summary_json,compared_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$c['id'], $from['id'], $to['id'], $diff['stats']['added_lines'], $diff['stats']['removed_lines'], json_encode($summary, JSON_UNESCAPED_UNICODE), $u['id']]);
        $id = (int)$pdo->lastInsertId();
        Audit::log((int)$u['id'], 'version.compare', 'contract', (int)$c['id'], ['from' => (int)$from['version_number'], 'to' => (int)$to['version_number'], 'added' => $diff['stats']['added_lines'], 'removed' => $diff['stats']['removed_lines']]);
        Response::ok(['comparison_id' => $id, 'from_version' => (int)$from['version_number'], 'to_version' => (int)$to['version_number'], 'summary' => $summary, 'diff' => $diff]);
    }

    /** Re-open a saved comparison: the diff is recomputed from the stored version texts (versions are immutable). */
    public static function comparison(): never
    {
        $u = Auth::requireApi();
        $st = Database::pdo()->prepare('SELECT * FROM version_comparisons WHERE id=?');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $vc = $st->fetch();
        if (!$vc) { Response::error('Comparison not found.', 404); }
        $c = self::contract((int)$vc['contract_id'], $u, false);
        $from = VersionService::find((int)$vc['from_version_id'], (int)$c['id']); $to = VersionService::find((int)$vc['to_version_id'], (int)$c['id']);
        try { $diff = VersionDiffService::compare($from, $to); } catch (VersionException $e) { Response::error($e->getMessage(), 422); }
        Response::ok(['comparison_id' => (int)$vc['id'], 'from_version' => (int)$from['version_number'], 'to_version' => (int)$to['version_number'],
            'summary' => json_decode((string)$vc['summary_json'], true), 'diff' => $diff, 'created_at' => $vc['created_at']]);
    }
}
