<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\{AiException, AnalysisService, GeminiClient, RiskScore};

final class AnalysisController
{
    private const STARTS_PER_HOUR = 20;

    private static function contract(int $id, array $u, bool $write): array
    {
        $c = $id > 0 ? ContractModel::find($id, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404); }
        if ($write && !ContractModel::canWrite($c, $u)) { Response::error('You do not have permission to analyze this contract.', 403); }
        return $c;
    }

    public static function aiStatus(): never
    {
        Auth::requireApi();
        Response::ok(['configured' => GeminiClient::configured(), 'embeddings' => GeminiClient::embeddingsConfigured(), 'model' => GeminiClient::configured() ? GeminiClient::model() : null]);
    }

    public static function start(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::contract((int)($in['contract_id'] ?? 0), $u, true);
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=? AND action IN ('analysis.start','analysis.resume') AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $st->execute([$u['id']]);
        if ((int)$st->fetchColumn() >= self::STARTS_PER_HOUR) { Response::error('Analysis limit reached for this hour. Please try again later.', 429); }
        try {
            $job = AnalysisService::start($c, $u, !empty($in['restart']));
        } catch (AiException $e) { Response::error($e->getMessage(), 422); }
        Response::ok(['job' => $job, 'mode' => GeminiClient::configured() ? 'ai' : 'fallback']);
    }

    public static function step(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::contract((int)($in['contract_id'] ?? 0), $u, true);
        try { $job = AnalysisService::step($c, $u); }
        catch (AiException $e) { Response::error($e->getMessage(), 409); }
        Response::ok(['job' => $job]);
    }

    public static function status(): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($_GET['contract_id'] ?? 0), $u, false);
        Response::ok(['job' => AnalysisService::publicJob(AnalysisService::job((int)$c['id']))]);
    }

    public static function get(): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($_GET['contract_id'] ?? 0), $u, false);
        $cid = (int)$c['id'];
        $pdo = Database::pdo();
        $q = function (string $sql) use ($pdo, $cid): array { $s = $pdo->prepare($sql); $s->execute([$cid]); return $s->fetchAll(); };

        $ar = $q('SELECT mode, result_json, risk_score, model_name, created_at FROM analysis_results WHERE contract_id=?')[0] ?? null;
        $result = $ar ? (json_decode((string)$ar['result_json'], true) ?: []) : [];
        $findings = $q("SELECT f.id,f.title,f.description,f.severity,f.reason,f.source_quote,f.quote_verified,f.is_inferred,f.suggested_action,f.confidence,f.review_status,f.reviewer_comment,f.reviewed_at,
              cl.clause_type AS clause_type, ru.name AS reviewed_by_name FROM risk_findings f LEFT JOIN clauses cl ON cl.id=f.clause_id LEFT JOIN users ru ON ru.id=f.reviewed_by
              WHERE f.contract_id=? ORDER BY FIELD(f.severity,'high','medium','low'), f.id");
        $risk = null;
        if ($ar && $ar['mode'] === 'ai') {
            $risk = RiskScore::calculate($findings) + ['formula' => RiskScore::FORMULA];
        }
        Response::ok([
            'ai' => ['configured' => GeminiClient::configured(), 'embeddings' => GeminiClient::embeddingsConfigured()],
            'job' => AnalysisService::publicJob(AnalysisService::job($cid)),
            'analysis' => $ar ? ['mode' => $ar['mode'], 'model' => $ar['model_name'], 'created_at' => $ar['created_at'], 'summary' => $result['summary'] ?? null, 'notes' => $result['notes'] ?? null] : null,
            'date_suggestions' => $result['date_suggestions'] ?? [],
            'risk' => $risk,
            'findings' => $findings,
            'clauses' => $q('SELECT id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence FROM clauses WHERE contract_id=? ORDER BY clause_type,id'),
            'suggestions' => $q("SELECT s.id,s.kind,s.title,s.description,s.responsible_party,s.due_date,s.amount,s.currency,s.recurrence,s.source_quote,s.quote_verified,s.confidence,s.status,s.decided_at,s.reject_reason
              FROM obligation_suggestions s WHERE s.contract_id=? ORDER BY FIELD(s.status,'pending','approved','rejected'), s.id"),
            'stale' => $ar ? (bool)$pdo->query("SELECT COUNT(*) FROM contract_versions v WHERE v.contract_id=" . $cid . " AND v.created_at > (SELECT created_at FROM analysis_results WHERE contract_id=" . $cid . ")")->fetchColumn() : false,
            'has_text' => !empty($c['extracted_text_path']), 'extraction_error' => $c['extraction_error'],
            'can_write' => ContractModel::canWrite($c, $u), 'can_review' => ContractModel::canNote($c, $u),
        ]);
    }

    /** Copy a suggested key date into the contract. Only values the analysis actually suggested (and verified) are accepted. */
    public static function applyDate(array $in): never
    {
        $u = Auth::requireApi('manager');
        $c = self::contract((int)($in['contract_id'] ?? 0), $u, true);
        $field = (string)($in['field'] ?? '');
        if (!in_array($field, ['effective_date', 'start_date', 'expiry_date', 'renewal_date', 'notice_period_days'], true)) { Response::error('Invalid field.', 422); }
        $st = Database::pdo()->prepare('SELECT result_json FROM analysis_results WHERE contract_id=?');
        $st->execute([$c['id']]);
        $res = json_decode((string)$st->fetchColumn(), true) ?: [];
        $hit = null;
        foreach ($res['date_suggestions'] ?? [] as $d) { if ($d['field'] === $field && !empty($d['verified'])) { $hit = $d; } }
        if (!$hit) { Response::error('There is no verified suggestion for this field.', 422); }
        $val = $field === 'notice_period_days' ? (int)$hit['value'] : $hit['value'];
        ContractModel::update((int)$c['id'], [$field => $val]);
        if ($field === 'expiry_date') { \TermTrail\services\Lifecycle::clearReminders('contract_expiry', (int)$c['id']); }
        if (in_array($field, ['expiry_date', 'renewal_date', 'notice_period_days'], true)) { \TermTrail\services\Lifecycle::clearReminders('contract_renewal', (int)$c['id']); }
        Audit::log((int)$u['id'], 'analysis.apply_date', 'contract', (int)$c['id'], ['field' => $field, 'value' => (string)$val, 'previous' => (string)($c[$field] ?? '')]);
        Response::ok(['message' => 'Contract updated.']);
    }
}
