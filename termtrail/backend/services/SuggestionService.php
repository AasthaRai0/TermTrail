<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;
use TermTrail\controllers\{ObligationController, PaymentController};
use TermTrail\helpers\Audit;
use TermTrail\models\ContractModel;

/** Human-in-the-loop: only an approved suggestion becomes a real obligation/payment. Everything happens in one transaction. */
final class SuggestionService
{
    private static function load(int $id, array $u): array
    {
        [$w, $p] = ContractModel::scope($u);
        $st = Database::pdo()->prepare('SELECT s.*, c.owner_id AS contract_owner_id FROM obligation_suggestions s JOIN contracts c ON c.id=s.contract_id WHERE s.id=? AND ' . $w . ' FOR UPDATE');
        $st->execute(array_merge([$id], $p));
        $s = $id > 0 ? $st->fetch() : false;
        if (!$s) { throw new SuggestionException('Suggestion not found.', 404); }
        if (!ContractModel::canWrite(['owner_id' => $s['contract_owner_id']], $u)) { throw new SuggestionException('You do not have permission to review suggestions for this contract.', 403); }
        return $s;
    }

    /** @return array{kind:string,created_id:int} @throws SuggestionException */
    public static function approve(int $id, array $u, array $ov): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $s = self::load($id, $u);
            if ($s['status'] !== 'pending') { throw new SuggestionException('This suggestion has already been ' . $s['status'] . '.', 409); }
            $merge = fn(string $k) => array_key_exists($k, $ov) ? $ov[$k] : $s[$k];
            $now = date('Y-m-d H:i:s');
            if ($s['kind'] === 'obligation') {
                $data = ['title' => $merge('title'), 'description' => $merge('description'), 'responsible_party' => $merge('responsible_party'), 'due_date' => $merge('due_date'),
                    'recurrence' => $merge('recurrence') ?: 'none', 'priority' => $ov['priority'] ?? 'medium', 'owner_id' => $ov['owner_id'] ?? '', 'source_quote' => $s['source_quote']];
                [$d, $e] = ObligationController::fields($data, (int)$s['contract_id']);
                if ($e) { throw new SuggestionException('Please fix the highlighted fields.', 422, $e); }
                $pdo->prepare('INSERT INTO obligations (contract_id,suggestion_id,title,description,responsible_party,owner_id,due_date,recurrence,priority,source_quote,approved_by,approved_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$s['contract_id'], $s['id'], $d['title'], $d['description'], $d['responsible_party'], $d['owner_id'], $d['due_date'], $d['recurrence'], $d['priority'], $d['source_quote'], $u['id'], $now]);
                $kind = 'obligation';
            } else {
                $data = ['amount' => $merge('amount'), 'currency' => $merge('currency') ?: 'INR', 'due_date' => $merge('due_date'), 'category' => $ov['category'] ?? '',
                    'reference_number' => $ov['reference_number'] ?? '', 'notes' => $ov['notes'] ?? ($s['source_quote'] ? 'Source: "' . mb_substr((string)$s['source_quote'], 0, 400) . '"' : '')];
                [$d, $e] = PaymentController::fields($data);
                if ($e) { throw new SuggestionException('Please fix the highlighted fields. A payment needs an amount and a due date.', 422, $e); }
                $pdo->prepare('INSERT INTO payments (contract_id,suggestion_id,amount,currency,category,due_date,reference_number,notes) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$s['contract_id'], $s['id'], $d['amount'], $d['currency'], $d['category'] ?? ($merge('title') ? mb_substr((string)$merge('title'), 0, 80) : null), $d['due_date'], $d['reference_number'], $d['notes']]);
                $kind = 'payment';
            }
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE obligation_suggestions SET status='approved', decided_by=?, decided_at=? WHERE id=?")->execute([$u['id'], $now, $s['id']]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        Lifecycle::sync();
        Audit::log((int)$u['id'], 'suggestion.approve', 'suggestion', (int)$s['id'], ['kind' => $kind, 'created_id' => $newId, 'edited' => $ov !== []]);
        return ['kind' => $kind, 'created_id' => $newId];
    }

    /** @throws SuggestionException */
    public static function reject(int $id, array $u, ?string $reason): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $s = self::load($id, $u);
            if ($s['status'] !== 'pending') { throw new SuggestionException('This suggestion has already been ' . $s['status'] . '.', 409); }
            $reason = $reason !== null ? (mb_substr(trim($reason), 0, 250) ?: null) : null;
            $pdo->prepare("UPDATE obligation_suggestions SET status='rejected', decided_by=?, decided_at=NOW(), reject_reason=? WHERE id=?")->execute([$u['id'], $reason, $s['id']]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        Audit::log((int)$u['id'], 'suggestion.reject', 'suggestion', (int)$s['id'], ['reason' => $reason]);
    }
}
