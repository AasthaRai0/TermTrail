<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;
use TermTrail\helpers\Audit;

/**
 * Bounded, resumable analysis pipeline. Each call to step() performs ONE unit of work (one LLM call, one embedding batch, or the final save),
 * so no request is ever long-running. State lives in processing_jobs (stage, progress, next_chunk, partial_json).
 * Stages: uploaded -> extracting -> analyzing -> saving_results -> completed | failed.
 */
final class AnalysisService
{
    public const SECTION_WORDS = 1800;
    public const SECTION_OVERLAP = 100;
    public const MAX_SECTIONS = 12;
    public const EMBED_BATCH = 16;
    public const STALE_SECONDS = 120;
    private const MAX_CLAUSES = 80, MAX_FINDINGS = 40, MAX_SUGGESTIONS = 40;

    public static function job(int $cid): ?array
    {
        $st = Database::pdo()->prepare('SELECT *, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age FROM processing_jobs WHERE contract_id=?');
        $st->execute([$cid]);
        return $st->fetch() ?: null;
    }

    public static function publicJob(?array $j): ?array
    {
        if (!$j) { return null; }
        $p = $j['partial_json'] ? (json_decode((string)$j['partial_json'], true) ?: []) : [];
        return ['stage' => $j['stage'], 'progress' => (int)$j['progress'], 'next_chunk' => (int)$j['next_chunk'], 'total_chunks' => (int)$j['total_chunks'],
            'attempts' => (int)$j['attempts'], 'error_message' => $j['error_message'], 'age' => (int)($j['age'] ?? 0),
            'phase' => $p['phase'] ?? null, 'truncated' => !empty($p['truncated']), 'resumable' => $j['stage'] === 'failed' && !empty($j['partial_json'])];
    }

    public static function loadText(array $c): string
    {
        if (empty($c['extracted_text_path'])) { throw new AiException('This contract has no extracted text to analyze.'); }
        $path = FileStorage::textPath($c['extracted_text_path']);
        $t = is_file($path) ? (string)file_get_contents($path) : '';
        if (trim($t) === '') { throw new AiException('The extracted text is missing. Re-upload the document.'); }
        return $t;
    }

    /** Start (or safely resume) an analysis. Idempotent for a job that is already running. */
    public static function start(array $c, array $u, bool $restart): array
    {
        $pdo = Database::pdo();
        $cid = (int)$c['id'];
        $text = self::loadText($c);
        $job = self::job($cid);

        if (!GeminiClient::configured()) {
            Audit::log((int)$u['id'], 'analysis.start', 'contract', $cid, ['mode' => 'fallback']);
            return self::runFallback($c, $u, $text);
        }
        if ($job && !$restart && in_array($job['stage'], ['extracting', 'analyzing', 'saving_results'], true) && (int)$job['age'] < self::STALE_SECONDS) {
            return self::publicJob($job);
        }
        if ($job && !$restart && $job['stage'] === 'failed' && $job['partial_json']) {
            $pdo->prepare("UPDATE processing_jobs SET stage='analyzing', error_message=NULL WHERE contract_id=?")->execute([$cid]);
            $pdo->prepare("UPDATE contracts SET analysis_status='analyzing', analysis_error=NULL WHERE id=?")->execute([$cid]);
            Audit::log((int)$u['id'], 'analysis.resume', 'contract', $cid);
            return self::publicJob(self::job($cid));
        }
        $pdo->prepare("INSERT INTO processing_jobs (contract_id,stage,progress,next_chunk,total_chunks,attempts,error_message,partial_json) VALUES (?,'extracting',0,0,0,0,NULL,NULL)
            ON DUPLICATE KEY UPDATE stage='extracting',progress=0,next_chunk=0,total_chunks=0,attempts=0,error_message=NULL,partial_json=NULL")->execute([$cid]);
        $pdo->prepare("UPDATE contracts SET analysis_status='extracting', analysis_error=NULL WHERE id=?")->execute([$cid]);
        Audit::log((int)$u['id'], 'analysis.start', 'contract', $cid, ['mode' => 'ai', 'restart' => $restart]);
        return self::publicJob(self::job($cid));
    }

    public static function step(array $c, array $u): array
    {
        $pdo = Database::pdo();
        $name = $pdo->quote('tt_analysis_' . (int)$c['id']);
        if ((int)$pdo->query("SELECT GET_LOCK($name, 0)")->fetchColumn() !== 1) {
            return ['busy' => true] + (self::publicJob(self::job((int)$c['id'])) ?? []);
        }
        try { return self::stepLocked($c, $u); }
        finally { $pdo->query("SELECT RELEASE_LOCK($name)")->fetchColumn(); }
    }

    private static function stepLocked(array $c, array $u): array
    {
        $cid = (int)$c['id'];
        $job = self::job($cid);
        if (!$job) { throw new AiException('No analysis is running for this contract. Start one first.'); }
        if (in_array($job['stage'], ['completed', 'failed'], true)) { return self::publicJob($job); }
        try {
            $p = $job['partial_json'] ? json_decode((string)$job['partial_json'], true) : null;
            if (!is_array($p)) {
                $p = self::prepare($c);
                self::saveJob($cid, 'analyzing', $p, 0, (int)$p['plan']['sections']);
                return self::publicJob(self::job($cid));
            }
            switch ($p['phase']) {
                case 'sections': self::doSection($c, $job, $p); break;
                case 'summary':  self::doSummary($c, $job, $p); break;
                case 'embeddings': self::doEmbeddings($c, $job, $p); break;
                case 'save':
                    self::saveJob($cid, 'saving_results', $p, (int)$job['next_chunk'], (int)$job['total_chunks']);
                    self::doSave($c, $u, $p);
                    return self::publicJob(self::job($cid));
                default: throw new AiException('The analysis state is invalid. Restart the analysis.');
            }
            return self::publicJob(self::job($cid));
        } catch (AiException $e) {
            return self::fail($c, $u, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Analysis step failed: ' . $e->getMessage());
            return self::fail($c, $u, 'An unexpected error occurred during analysis. Please retry.');
        }
    }

    private static function sections(string $text): array
    {
        $all = TextChunker::chunk($text, self::SECTION_WORDS, self::SECTION_OVERLAP);
        return ['list' => array_slice($all, 0, self::MAX_SECTIONS), 'truncated' => count($all) > self::MAX_SECTIONS];
    }

    private static function prepare(array $c): array
    {
        $text = self::loadText($c);
        $s = self::sections($text);
        $pdo = Database::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM document_chunks WHERE contract_id=?');
        $cnt->execute([$c['id']]);
        $chunks = (int)$cnt->fetchColumn();
        if ($chunks === 0) {
            \TermTrail\models\ContractModel::saveChunks((int)$c['id'], TextChunker::chunk($text));
            $cnt->execute([$c['id']]);
            $chunks = (int)$cnt->fetchColumn();
        }
        $batches = GeminiClient::embeddingsConfigured() ? (int)ceil($chunks / self::EMBED_BATCH) : 0;
        return ['phase' => 'sections', 'sections' => [], 'summary' => null, 'embed_next' => 0, 'embeddings' => $batches > 0 ? 'pending' : 'off',
            'truncated' => $s['truncated'], 'plan' => ['sections' => count($s['list']), 'embed_batches' => $batches]];
    }

    private static function saveJob(int $cid, string $stage, array $p, int $next, int $total): void
    {
        $plan = $p['plan'] ?? ['sections' => 0, 'embed_batches' => 0];
        $n = (int)$plan['sections']; $m = (int)$plan['embed_batches'];
        $done = count($p['sections'] ?? []) + (!empty($p['summary']) ? 1 : 0) + min($m, (int)($p['embed_next'] ?? 0));
        $progress = $stage === 'completed' ? 100 : min(99, (int)floor($done / max(1, $n + 1 + $m + 1) * 100));
        Database::pdo()->prepare('UPDATE processing_jobs SET stage=?, progress=?, next_chunk=?, total_chunks=?, partial_json=? WHERE contract_id=?')
            ->execute([$stage, $progress, $next, $total, json_encode($p, JSON_UNESCAPED_UNICODE), $cid]);
        Database::pdo()->prepare('UPDATE contracts SET analysis_status=? WHERE id=?')->execute([in_array($stage, ['analyzing', 'saving_results'], true) ? $stage : 'analyzing', $cid]);
    }

    /** One LLM call that must return a JSON object; one retry with a stricter reminder if the JSON is unreadable. */
    private static function callJson(string $system, string $user, int $max): array
    {
        try { return AiJson::parse(GeminiClient::generate($system, $user, $max)); }
        catch (AiException $e) {
            if (!str_contains($e->getMessage(), 'not valid JSON')) { throw $e; }
            return AiJson::parse(GeminiClient::generate($system, $user . "\n\nReminder: respond with ONLY a single valid JSON object, no prose or code fences.", $max));
        }
    }

    private static function doSection(array $c, array $job, array &$p): void
    {
        $idx = (int)$job['next_chunk'];
        $s = self::sections(self::loadText($c))['list'];
        $n = count($s);
        if (!isset($s[$idx])) { $p['phase'] = 'summary'; self::saveJob((int)$c['id'], 'analyzing', $p, $idx, $n); return; }
        $d = self::callJson(Prompts::SECTION_SYSTEM, 'Section ' . ($idx + 1) . ' of ' . $n . ".\n<contract_section>\n" . $s[$idx] . "\n</contract_section>", 4096);
        $p['sections'][] = AiJson::section($d);
        if ($idx + 1 >= $n) { $p['phase'] = 'summary'; }
        self::saveJob((int)$c['id'], 'analyzing', $p, $idx + 1, $n);
    }

    private static function merged(array $p): array
    {
        $m = ['clauses' => [], 'risks' => [], 'obligations' => [], 'payments' => [], 'dates' => []];
        foreach ($p['sections'] as $s) { foreach ($m as $k => $_) { $m[$k] = array_merge($m[$k], $s[$k] ?? []); } }
        return $m;
    }

    private static function doSummary(array $c, array $job, array &$p): void
    {
        $text = self::loadText($c);
        $m = self::merged($p);
        $digest = [
            'clauses' => array_map(fn($x) => ['type' => $x['type'], 'summary' => $x['summary']], array_slice($m['clauses'], 0, 50)),
            'risks' => array_map(fn($x) => ['title' => $x['title'], 'severity' => $x['severity']], array_slice($m['risks'], 0, 20)),
            'obligations' => array_map(fn($x) => $x['title'], array_slice($m['obligations'], 0, 20)),
            'payments' => array_map(fn($x) => ['title' => $x['title'], 'amount' => $x['amount'], 'currency' => $x['currency'], 'schedule' => $x['schedule']], array_slice($m['payments'], 0, 20)),
            'dates' => array_slice($m['dates'], 0, 12),
            'sections_analyzed' => count($p['sections']), 'document_truncated' => !empty($p['truncated']),
        ];
        $user = "<contract_opening>\n" . mb_substr($text, 0, 2500) . "\n</contract_opening>\n<extracted_data>\n" . json_encode($digest, JSON_UNESCAPED_UNICODE) . "\n</extracted_data>";
        $p['summary'] = AiJson::summary(self::callJson(Prompts::SUMMARY_SYSTEM, $user, 4096));
        $p['phase'] = ((int)$p['plan']['embed_batches'] > 0) ? 'embeddings' : 'save';
        self::saveJob((int)$c['id'], 'analyzing', $p, (int)$job['next_chunk'], (int)$job['total_chunks']);
    }

    /** Embeddings are optional: a failure here never fails the analysis (retrieval falls back to FULLTEXT). */
    private static function doEmbeddings(array $c, array $job, array &$p): void
    {
        $pdo = Database::pdo();
        $i = (int)$p['embed_next'];
        $st = $pdo->prepare('SELECT id, content FROM document_chunks WHERE contract_id=? ORDER BY chunk_index LIMIT ' . self::EMBED_BATCH . ' OFFSET ' . ($i * self::EMBED_BATCH));
        $st->execute([$c['id']]);
        $rows = $st->fetchAll();
        try {
            if ($rows) {
                $vecs = GeminiClient::embed(array_column($rows, 'content'));
                $up = $pdo->prepare('UPDATE document_chunks SET embedding=? WHERE id=?');
                foreach ($rows as $k => $r) {
                    $v = VectorMath::validate($vecs[$k] ?? null);
                    if ($v === null) { throw new AiException('The embedding provider returned an invalid vector.'); }
                    $up->execute([json_encode($v), $r['id']]);
                }
            }
            $p['embed_next'] = $i + 1;
            if ($p['embed_next'] >= (int)$p['plan']['embed_batches']) { $p['phase'] = 'save'; $p['embeddings'] = 'ok'; }
        } catch (AiException $e) {
            error_log('Embedding step skipped: ' . $e->getMessage());
            $pdo->prepare('UPDATE document_chunks SET embedding=NULL WHERE contract_id=?')->execute([$c['id']]);
            $p['phase'] = 'save'; $p['embeddings'] = 'skipped'; $p['embed_next'] = (int)$p['plan']['embed_batches'];
        }
        self::saveJob((int)$c['id'], 'analyzing', $p, (int)$job['next_chunk'], (int)$job['total_chunks']);
    }

    private static function doSave(array $c, array $u, array $p): void
    {
        $text = self::loadText($c);
        $m = self::merged($p);
        $norm = EvidenceVerifier::normalize($text);
        $v = fn(?string $q) => $q !== null && EvidenceVerifier::verify($q, $norm);

        $normChunks = [];
        $st = Database::pdo()->prepare('SELECT chunk_index, content FROM document_chunks WHERE contract_id=? ORDER BY chunk_index');
        $st->execute([$c['id']]);
        foreach ($st->fetchAll() as $r) { $normChunks[(int)$r['chunk_index']] = EvidenceVerifier::normalize($r['content']); }
        $where = fn(?string $q) => ($q !== null && $normChunks) ? EvidenceVerifier::chunkIndex($q, $normChunks) : null;

        $clauses = []; $seen = [];
        foreach ($m['clauses'] as $x) {
            $key = $x['type'] . '|' . EvidenceVerifier::normalize((string)($x['quote'] ?? $x['summary']));
            if (isset($seen[$key]) || count($clauses) >= self::MAX_CLAUSES) { continue; }
            $seen[$key] = true;
            $clauses[] = $x + ['verified' => $v($x['quote']), 'chunk' => $where($x['quote'])];
        }
        $findings = []; $seen = [];
        foreach ($m['risks'] as $x) {
            $key = EvidenceVerifier::normalize($x['title']) . '|' . $x['severity'];
            if (isset($seen[$key]) || count($findings) >= self::MAX_FINDINGS) { continue; }
            $seen[$key] = true;
            $findings[] = $x + ['verified' => $v($x['quote']), 'inferred' => ($x['kind'] !== 'detected' || $x['quote'] === null)];
        }
        $sugg = []; $seen = [];
        foreach ($m['obligations'] as $x) {
            $key = 'o|' . EvidenceVerifier::normalize($x['title']) . '|' . ($x['due_date'] ?? '');
            if (isset($seen[$key]) || count($sugg) >= self::MAX_SUGGESTIONS) { continue; }
            $seen[$key] = true;
            $sugg[] = ['kind' => 'obligation', 'title' => $x['title'], 'description' => $x['description'], 'responsible_party' => $x['responsible_party'], 'due_date' => $x['due_date'],
                'amount' => null, 'currency' => null, 'recurrence' => $x['recurrence'] === 'none' ? null : $x['recurrence'], 'quote' => $x['quote'], 'verified' => $v($x['quote']), 'confidence' => $x['confidence']];
        }
        foreach ($m['payments'] as $x) {
            $key = 'p|' . EvidenceVerifier::normalize($x['title']) . '|' . ($x['amount'] ?? '') . '|' . ($x['due_date'] ?? '');
            if (isset($seen[$key]) || count($sugg) >= self::MAX_SUGGESTIONS) { continue; }
            $seen[$key] = true;
            $sugg[] = ['kind' => 'payment', 'title' => $x['title'], 'description' => $x['schedule'], 'responsible_party' => null, 'due_date' => $x['due_date'],
                'amount' => $x['amount'], 'currency' => $x['currency'], 'recurrence' => null, 'quote' => $x['quote'], 'verified' => $v($x['quote']), 'confidence' => $x['confidence']];
        }
        $dates = []; 
        foreach ($m['dates'] as $x) {
            $ok = $v($x['quote']);
            if (!isset($dates[$x['field']]) || ($ok && !$dates[$x['field']]['verified'])) { $dates[$x['field']] = ['field' => $x['field'], 'value' => $x['value'], 'quote' => $x['quote'], 'verified' => $ok, 'note' => null]; }
        }
        self::persist($c, $u, 'ai', [
            'clauses' => $clauses, 'findings' => $findings, 'suggestions' => $sugg, 'dates' => array_values($dates), 'summary' => $p['summary'],
            'notes' => ['truncated' => !empty($p['truncated']), 'sections' => count($p['sections']), 'embeddings' => $p['embeddings'] ?? 'off', 'model' => GeminiClient::model()],
        ]);
    }

    private static function runFallback(array $c, array $u, string $text): array
    {
        $r = RegexExtractor::extract($text);
        $sugg = array_map(fn($x) => ['kind' => 'payment', 'title' => $x['title'], 'description' => null, 'responsible_party' => null, 'due_date' => null, 'amount' => $x['amount'],
            'currency' => $x['currency'], 'recurrence' => null, 'quote' => $x['quote'], 'verified' => true, 'confidence' => null], $r['payments']);
        $pdo = Database::pdo();
        $pdo->prepare("INSERT INTO processing_jobs (contract_id,stage,progress) VALUES (?,'saving_results',50) ON DUPLICATE KEY UPDATE stage='saving_results',progress=50,error_message=NULL,partial_json=NULL,next_chunk=0,total_chunks=0,attempts=0")->execute([$c['id']]);
        self::persist($c, $u, 'fallback', ['clauses' => [], 'findings' => [], 'suggestions' => $sugg, 'dates' => $r['date_suggestions'], 'summary' => null, 'notes' => ['mode' => 'fallback']]);
        return self::publicJob(self::job((int)$c['id']));
    }

    private static function persist(array $c, array $u, string $mode, array $r): void
    {
        $pdo = Database::pdo();
        $cid = (int)$c['id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM risk_findings WHERE contract_id=?')->execute([$cid]);
            $pdo->prepare('DELETE FROM clauses WHERE contract_id=?')->execute([$cid]);
            $pdo->prepare("DELETE FROM obligation_suggestions WHERE contract_id=? AND status='pending'")->execute([$cid]);

            $decided = [];
            $dq = $pdo->prepare("SELECT kind,title,due_date,amount FROM obligation_suggestions WHERE contract_id=? AND status<>'pending'");
            $dq->execute([$cid]);
            foreach ($dq->fetchAll() as $d) { $decided[$d['kind'] . '|' . mb_strtolower($d['title']) . '|' . $d['due_date'] . '|' . $d['amount']] = true; }

            $ins = $pdo->prepare('INSERT INTO clauses (contract_id,clause_type,summary,source_quote,quote_verified,chunk_index,confidence) VALUES (?,?,?,?,?,?,?)');
            $firstByType = [];
            foreach ($r['clauses'] as $x) {
                $ins->execute([$cid, $x['type'], $x['summary'], $x['quote'], $x['verified'] ? 1 : 0, $x['chunk'], $x['confidence']]);
                $firstByType[$x['type']] ??= (int)$pdo->lastInsertId();
            }
            $fi = $pdo->prepare('INSERT INTO risk_findings (contract_id,clause_id,title,description,severity,reason,source_quote,quote_verified,is_inferred,suggested_action,confidence) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $scoring = [];
            foreach ($r['findings'] as $x) {
                $fi->execute([$cid, $firstByType[$x['clause_type']] ?? null, $x['title'], $x['description'], $x['severity'], $x['reason'], $x['quote'], $x['verified'] ? 1 : 0, $x['inferred'] ? 1 : 0, $x['suggested_action'], $x['confidence']]);
                $scoring[] = ['title' => $x['title'], 'severity' => $x['severity'], 'quote_verified' => $x['verified'], 'is_inferred' => $x['inferred']];
            }
            $si = $pdo->prepare('INSERT INTO obligation_suggestions (contract_id,kind,title,description,responsible_party,due_date,amount,currency,recurrence,source_quote,quote_verified,confidence) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($r['suggestions'] as $x) {
                if (isset($decided[$x['kind'] . '|' . mb_strtolower($x['title']) . '|' . $x['due_date'] . '|' . $x['amount']])) { continue; }
                $si->execute([$cid, $x['kind'], $x['title'], $x['description'], $x['responsible_party'], $x['due_date'], $x['amount'], $x['currency'], $x['recurrence'], $x['quote'], $x['verified'] ? 1 : 0, $x['confidence']]);
            }
            $risk = $mode === 'ai' ? RiskScore::calculate($scoring) : null;
            $s = $r['summary'];
            $json = json_encode(['summary' => $s, 'date_suggestions' => $r['dates'], 'notes' => $r['notes']], JSON_UNESCAPED_UNICODE);
            $pdo->prepare('INSERT INTO analysis_results (contract_id,mode,executive_summary,detailed_explanation,result_json,risk_score,model_name) VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE mode=VALUES(mode),executive_summary=VALUES(executive_summary),detailed_explanation=VALUES(detailed_explanation),result_json=VALUES(result_json),risk_score=VALUES(risk_score),model_name=VALUES(model_name),created_at=NOW()')
                ->execute([$cid, $mode, $s['executive_summary'] ?? null, $s['detailed_explanation'] ?? null, $json, $risk['score'] ?? null, $mode === 'ai' ? GeminiClient::model() : null]);
            $pdo->prepare("UPDATE contracts SET risk_score=?, risk_level=?, analysis_status='completed', analysis_error=NULL WHERE id=?")->execute([$risk['score'] ?? null, $risk['level'] ?? null, $cid]);
            $pdo->prepare("UPDATE processing_jobs SET stage='completed', progress=100, error_message=NULL, partial_json=NULL WHERE contract_id=?")->execute([$cid]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
        Audit::log((int)$u['id'], 'analysis.completed', 'contract', $cid, ['mode' => $mode, 'findings' => count($r['findings']), 'suggestions' => count($r['suggestions'])]);
        NotificationService::create((int)$c['owner_id'], 'analysis_completed', $mode === 'ai' ? 'AI analysis complete' : 'Basic analysis complete',
            '"' . $c['title'] . '": ' . count($r['findings']) . ' finding(s), ' . count($r['suggestions']) . ' suggestion(s) to review.', 'index.php?page=contract_view&id=' . $cid . '#analysis');
    }

    private static function fail(array $c, array $u, string $msg): array
    {
        $cid = (int)$c['id'];
        $pdo = Database::pdo();
        $pdo->prepare("UPDATE processing_jobs SET stage='failed', error_message=?, attempts=attempts+1 WHERE contract_id=?")->execute([mb_substr($msg, 0, 250), $cid]);
        $pdo->prepare("UPDATE contracts SET analysis_status='failed', analysis_error=? WHERE id=?")->execute([mb_substr($msg, 0, 250), $cid]);
        Audit::log((int)$u['id'], 'analysis.failed', 'contract', $cid, ['error' => mb_substr($msg, 0, 120)]);
        NotificationService::create((int)$c['owner_id'], 'analysis_failed', 'AI analysis failed', '"' . $c['title'] . '": ' . $msg, 'index.php?page=contract_view&id=' . $cid . '#analysis');
        return self::publicJob(self::job($cid));
    }

    /** Recompute the contract's risk score after a reviewer changes a finding. */
    public static function recalcRisk(int $cid): ?array
    {
        $pdo = Database::pdo();
        $mode = $pdo->prepare('SELECT mode FROM analysis_results WHERE contract_id=?');
        $mode->execute([$cid]);
        if ($mode->fetchColumn() !== 'ai') { return null; }
        $st = $pdo->prepare('SELECT title,severity,quote_verified,is_inferred,review_status FROM risk_findings WHERE contract_id=?');
        $st->execute([$cid]);
        $r = RiskScore::calculate($st->fetchAll());
        $pdo->prepare('UPDATE contracts SET risk_score=?, risk_level=? WHERE id=?')->execute([$r['score'], $r['level'], $cid]);
        $pdo->prepare('UPDATE analysis_results SET risk_score=? WHERE contract_id=?')->execute([$r['score'], $cid]);
        return $r;
    }
}
