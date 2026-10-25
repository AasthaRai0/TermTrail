<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\{ContractFileService, FileStorage, FileValidator, Lifecycle, NotificationService, TextChunker, VersionService};

final class ContractController
{
    private const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'JPY'];
    private const UPLOADS_PER_HOUR = 30;

    private static function load(int $id, array $u, bool $write = false): array
    {
        $c = $id > 0 ? ContractModel::find($id, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404); }
        if ($write && !ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to modify this contract.', 403); }
        return $c;
    }

    private static function validDate(?string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$v, $m)) { return false; }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** @return array{0:array,1:array} cleaned data + field errors */
    public static function fields(array $in, array $u): array
    {
        $pdo = Database::pdo();
        $d = []; $e = [];
        $str = fn(string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

        $d['title'] = $str('title', 255);
        if ($d['title'] === '') { $e['title'] = 'Title is required.'; }
        $d['contract_type'] = $str('contract_type', 80) ?: null;
        $d['tags'] = $str('tags', 255) ?: null;
        $d['description'] = $str('description', 5000) ?: null;

        $status = (string)($in['status'] ?? 'draft');
        if (!in_array($status, ContractModel::STATUSES, true)) { $e['status'] = 'Choose a valid status.'; }
        $d['status'] = $status;

        foreach (['effective_date', 'start_date', 'expiry_date', 'renewal_date'] as $k) {
            $v = trim((string)($in[$k] ?? ''));
            if ($v === '') { $d[$k] = null; continue; }
            if (!self::validDate($v)) { $e[$k] = 'Enter a valid date.'; continue; }
            $d[$k] = $v;
        }
        if (empty($e['start_date']) && empty($e['expiry_date']) && $d['start_date'] && $d['expiry_date'] && $d['expiry_date'] < $d['start_date']) {
            $e['expiry_date'] = 'Expiry date cannot be before the start date.';
        }

        $np = trim((string)($in['notice_period_days'] ?? ''));
        if ($np === '') { $d['notice_period_days'] = null; }
        elseif (!ctype_digit($np) || (int)$np > 3650) { $e['notice_period_days'] = 'Enter a number of days (0-3650).'; }
        else { $d['notice_period_days'] = (int)$np; }

        $val = str_replace(',', '', trim((string)($in['contract_value'] ?? '')));
        if ($val === '') { $d['contract_value'] = null; }
        elseif (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $val)) { $e['contract_value'] = 'Enter a valid amount (up to 2 decimals).'; }
        else { $d['contract_value'] = $val; }

        $cur = strtoupper(trim((string)($in['currency'] ?? 'INR')));
        if (!in_array($cur, self::CURRENCIES, true)) { $e['currency'] = 'Unsupported currency.'; }
        $d['currency'] = $cur;

        $cp = (int)($in['counterparty_id'] ?? 0);
        $d['counterparty_id'] = null;
        if ($cp > 0) {
            if (CounterpartyController::accessible($cp, $u, true)) { $d['counterparty_id'] = $cp; }
            else { $e['counterparty_id'] = 'Choose a valid counterparty.'; }
        }
        $rv = (int)($in['reviewer_id'] ?? 0);
        $d['reviewer_id'] = null;
        if ($rv > 0) {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE id=? AND is_active=1');
            $st->execute([$rv]);
            if ($st->fetchColumn()) { $d['reviewer_id'] = $rv; } else { $e['reviewer_id'] = 'Choose a valid reviewer.'; }
        }
        return [$d, $e];
    }

    private static function checkUploadRate(array $u): void
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=? AND action='contract.upload' AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $st->execute([$u['id']]);
        if ((int)$st->fetchColumn() >= self::UPLOADS_PER_HOUR) { Response::error('Upload limit reached. Please try again later.', 429); }
    }

    private static function hasUpload(): bool
    {
        return !empty($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private static function fileColumns(array $proc): array
    {
        return ['original_filename' => $proc['meta']['original'], 'stored_filename' => $proc['stored'], 'mime_type' => $proc['meta']['mime'],
            'file_size' => $proc['meta']['size'], 'extracted_text_path' => $proc['text_file'], 'extraction_error' => $proc['error'] ? mb_substr($proc['error'], 0, 255) : null,
            'analysis_status' => 'uploaded'];
    }

    public static function list(): never
    {
        $u = Auth::requireApi();
        Response::ok(ContractModel::search($_GET, $u));
    }

    /** Contracts the user may attach obligations/payments to (owner or admin, not archived). */
    public static function options(): never
    {
        $u = Auth::requireApi('manager');
        [$w, $p] = ContractModel::scope($u);
        if ($u['role'] !== 'admin') { $w = 'c.owner_id = ?'; $p = [(int)$u['id']]; }
        $st = Database::pdo()->prepare("SELECT c.id, c.title FROM contracts c WHERE $w AND c.is_archived=0 ORDER BY c.title LIMIT 500");
        $st->execute($p);
        Response::ok(['items' => $st->fetchAll()]);
    }

    private static function notifyReviewer(?int $reviewerId, array $u, string $title, int $contractId): void
    {
        if ($reviewerId && $reviewerId !== (int)$u['id']) {
            NotificationService::create($reviewerId, 'review_request', 'Review requested', 'You were assigned to review "' . $title . '".', 'index.php?page=contract_view&id=' . $contractId);
        }
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        $c = self::load((int)($_GET['id'] ?? 0), $u);
        $st = Database::pdo()->prepare('SELECT rn.id, rn.note, rn.created_at, us.name AS author FROM review_notes rn JOIN users us ON us.id=rn.user_id WHERE rn.contract_id=? ORDER BY rn.id DESC LIMIT 100');
        $st->execute([$c['id']]);
        Response::ok(['contract' => ContractModel::publicRow($c), 'notes' => $st->fetchAll(),
            'can_write' => ContractModel::canWrite($c, $u), 'can_note' => ContractModel::canNote($c, $u)]);
    }

    public static function create(array $in): never
    {
        $u = Auth::requireApi('manager');
        [$d, $e] = self::fields($in, $u);
        $file = null;
        if (self::hasUpload()) {
            try { $file = FileValidator::validate($_FILES['file']); } catch (\InvalidArgumentException $ex) { $e['file'] = $ex->getMessage(); }
        }
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        if ($file) { self::checkUploadRate($u); }

        $pdo = Database::pdo();
        $proc = null;
        try {
            if ($file) { $proc = ContractFileService::process($file, $_FILES['file']['tmp_name']); }
            $pdo->beginTransaction();
            $row = ['owner_id' => $u['id']] + $d + ($proc ? self::fileColumns($proc) : []);
            $id = ContractModel::insert($row);
            if ($proc && $proc['text']) { ContractModel::saveChunks($id, TextChunker::chunk($proc['text'])); }
            if ($proc) { VersionService::insertBase($id, ['original_filename' => $file['original'], 'stored_filename' => $proc['stored'], 'mime_type' => $file['mime'], 'extracted_text_path' => $proc['text_file']], (int)$u['id']); }
            $pdo->commit();
        } catch (\Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            ContractFileService::discard($proc);
            throw $ex;
        }
        Audit::log((int)$u['id'], 'contract.create', 'contract', $id, ['title' => $d['title']]);
        self::notifyReviewer($d['reviewer_id'], $u, $d['title'], $id);
        if ($proc) { Audit::log((int)$u['id'], 'contract.upload', 'contract', $id, ['ext' => $file['ext'], 'size' => $file['size'], 'extracted' => $proc['error'] === null]); }
        Response::ok(['id' => $id, 'extraction_error' => $proc['error'] ?? null,
            'redirect' => APP_URL . '/index.php?page=contract_view&id=' . $id], 201);
    }

    public static function update(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::load((int)($in['id'] ?? 0), $u, true);
        [$d, $e] = self::fields($in, $u);
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        ContractModel::update((int)$c['id'], $d);
        if (($c['expiry_date'] ?? null) !== $d['expiry_date']) { Lifecycle::clearReminders('contract_expiry', (int)$c['id']); }
        if (($c['renewal_date'] ?? null) !== $d['renewal_date'] || ($c['expiry_date'] ?? null) !== $d['expiry_date'] || ($c['notice_period_days'] ?? null) !== $d['notice_period_days']) {
            Lifecycle::clearReminders('contract_renewal', (int)$c['id']);
        }
        Audit::log((int)$u['id'], 'contract.update', 'contract', (int)$c['id']);
        if ((int)$d['reviewer_id'] !== (int)$c['reviewer_id']) { self::notifyReviewer($d['reviewer_id'], $u, $d['title'], (int)$c['id']); }
        Response::ok(['message' => 'Contract updated.', 'redirect' => APP_URL . '/index.php?page=contract_view&id=' . $c['id']]);
    }

    /** Uploading a new file creates a new version (older versions are kept for history/comparison). */
    public static function replaceFile(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::load((int)($in['id'] ?? 0), $u, true);
        try { $file = FileValidator::validate($_FILES['file'] ?? []); }
        catch (\InvalidArgumentException $ex) { Response::error($ex->getMessage(), 422, ['file' => $ex->getMessage()]); }
        self::checkUploadRate($u);
        $r = VersionService::add($c, $u, $file, $_FILES['file']['tmp_name'], trim((string)($in['notes'] ?? '')) ?: null);
        Audit::log((int)$u['id'], 'contract.upload', 'contract', (int)$c['id'], ['ext' => $file['ext'], 'size' => $file['size'], 'version' => $r['version_number']]);
        Response::ok(['message' => $r['extraction_error'] ? 'Version ' . $r['version_number'] . ' saved, but text could not be extracted.' : 'Version ' . $r['version_number'] . ' uploaded and text extracted.', 'extraction_error' => $r['extraction_error']]);
    }

    public static function setArchived(array $in, bool $archived): never
    {
        $u = Auth::requireApi('manager');
        $c = self::load((int)($in['id'] ?? 0), $u, true);
        Database::pdo()->prepare('UPDATE contracts SET is_archived=? WHERE id=?')->execute([$archived ? 1 : 0, $c['id']]);
        Audit::log((int)$u['id'], $archived ? 'contract.archive' : 'contract.restore', 'contract', (int)$c['id']);
        Response::ok(['message' => $archived ? 'Contract archived.' : 'Contract restored.']);
    }

    public static function delete(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::load((int)($in['id'] ?? 0), $u, true);
        $pdo = Database::pdo();
        $files = VersionService::allFiles($c);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM contracts WHERE id=?')->execute([$c['id']]);
            $pdo->commit();
        } catch (\Throwable $ex) { $pdo->rollBack(); throw $ex; }
        foreach ($files['stored'] as $f) { FileStorage::deleteContractFile($f); }
        foreach ($files['text'] as $f) { FileStorage::deleteText($f); }
        Audit::log((int)$u['id'], 'contract.delete', 'contract', (int)$c['id'], ['title' => $c['title']]);
        Response::ok(['message' => 'Contract deleted.']);
    }

    public static function download(): never
    {
        $u = Auth::requireApi();
        $c = self::load((int)($_GET['id'] ?? 0), $u);
        if (empty($c['stored_filename'])) { Response::error('This contract has no uploaded file.', 404); }
        $path = FileStorage::contractPath($c['stored_filename']);
        if (!is_file($path)) { Response::error('The file is missing from storage.', 404); }
        $ext = strtolower(pathinfo($c['stored_filename'], PATHINFO_EXTENSION));
        $types = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'txt' => 'text/plain; charset=utf-8'];
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$c['original_filename']) ?: ('contract.' . $ext);
        Audit::log((int)$u['id'], 'contract.download', 'contract', (int)$c['id']);
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode((string)$c['original_filename']));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public static function text(): never
    {
        $u = Auth::requireApi();
        $c = self::load((int)($_GET['id'] ?? 0), $u);
        if (empty($c['extracted_text_path'])) { Response::error($c['extraction_error'] ?: 'No extracted text is available.', 404); }
        $p = FileStorage::textPath($c['extracted_text_path']);
        $t = is_file($p) ? (string)file_get_contents($p) : '';
        $truncated = mb_strlen($t) > 100000;
        Response::ok(['text' => $truncated ? mb_substr($t, 0, 100000) : $t, 'truncated' => $truncated]);
    }

    public static function addNote(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::load((int)($in['contract_id'] ?? 0), $u);
        if (!ContractModel::canNote($c, $u)) { Response::error('You cannot add notes to this contract.', 403); }
        $note = trim((string)($in['note'] ?? ''));
        if ($note === '' || mb_strlen($note) > 2000) { Response::error('Enter a note (up to 2000 characters).', 422, ['note' => 'Enter a note (up to 2000 characters).']); }
        Database::pdo()->prepare('INSERT INTO review_notes (contract_id,user_id,note) VALUES (?,?,?)')->execute([$c['id'], $u['id'], $note]);
        Audit::log((int)$u['id'], 'contract.note_added', 'contract', (int)$c['id']);
        Response::ok(['message' => 'Note added.'], 201);
    }
}
