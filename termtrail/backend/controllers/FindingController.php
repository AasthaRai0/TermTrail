<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\AnalysisService;

final class FindingController
{
    /** Mark a risk finding reviewed/dismissed (or back to pending) with an optional comment. Recalculates the risk score. */
    public static function review(array $in): never
    {
        $u = Auth::requireApi('manager');
        [$w, $p] = ContractModel::scope($u);
        $st = Database::pdo()->prepare("SELECT f.*, c.owner_id, c.reviewer_id FROM risk_findings f JOIN contracts c ON c.id=f.contract_id WHERE f.id=? AND $w");
        $st->execute(array_merge([(int)($in['id'] ?? 0)], $p));
        $f = $st->fetch();
        if (!$f) { Response::error('Finding not found.', 404); }
        if (!ContractModel::canNote($f, $u)) { Response::error('You do not have permission to review this finding.', 403); }
        $status = (string)($in['status'] ?? '');
        if (!in_array($status, ['reviewed', 'dismissed', 'pending'], true)) { Response::error('Invalid review status.', 422); }
        $comment = mb_substr(trim((string)($in['comment'] ?? '')), 0, 1000) ?: null;
        if ($status === 'pending') {
            Database::pdo()->prepare("UPDATE risk_findings SET review_status='pending', reviewed_by=NULL, reviewed_at=NULL, reviewer_comment=? WHERE id=?")->execute([$comment, $f['id']]);
        } else {
            Database::pdo()->prepare('UPDATE risk_findings SET review_status=?, reviewed_by=?, reviewed_at=NOW(), reviewer_comment=? WHERE id=?')->execute([$status, $u['id'], $comment, $f['id']]);
        }
        $risk = AnalysisService::recalcRisk((int)$f['contract_id']);
        Audit::log((int)$u['id'], 'finding.review', 'finding', (int)$f['id'], ['status' => $status]);
        Response::ok(['message' => $status === 'dismissed' ? 'Finding dismissed.' : ($status === 'reviewed' ? 'Finding marked as reviewed.' : 'Finding reopened.'), 'risk' => $risk]);
    }
}
