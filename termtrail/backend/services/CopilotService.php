<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;

/** Evidence-grounded Q&A: retrieve the most relevant chunks, send ONLY those to the LLM, verify every quotation it returns. */
final class CopilotService
{
    private const STOP = ['the', 'and', 'for', 'are', 'does', 'what', 'when', 'this', 'that', 'with', 'from', 'have', 'has', 'any', 'how', 'who', 'which', 'will', 'shall', 'can', 'was', 'were',
        'been', 'into', 'about', 'contract', 'agreement', 'there', 'their', 'they', 'you', 'your', 'our', 'not', 'but', 'its', 'also', 'than', 'then', 'should', 'would', 'could', 'say', 'says', 'specify'];

    public static function tokens(string $q): array
    {
        $t = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($t, fn($x) => mb_strlen($x) >= 3 && !in_array($x, self::STOP, true))));
    }

    /** @return array{passages:array[],method:string} */
    public static function retrieve(int $cid, string $question, int $k = 5): array
    {
        $pdo = Database::pdo();
        $tokens = self::tokens($question);

        if (GeminiClient::embeddingsConfigured()) {
            try {
                $st = $pdo->prepare('SELECT chunk_index, content, embedding FROM document_chunks WHERE contract_id=? AND embedding IS NOT NULL LIMIT 400');
                $st->execute([$cid]);
                $rows = $st->fetchAll();
                if ($rows) {
                    $qv = VectorMath::validate(GeminiClient::embed([$question])[0] ?? null);
                    if ($qv !== null) {
                        $scored = [];
                        foreach ($rows as $r) {
                            $cv = VectorMath::validate(json_decode((string)$r['embedding'], true));
                            $s = $cv ? VectorMath::cosine($qv, $cv) : null;
                            if ($s !== null) { $scored[] = ['chunk_index' => (int)$r['chunk_index'], 'content' => $r['content'], 'score' => $s]; }
                        }
                        if ($scored) {
                            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
                            return ['passages' => array_slice($scored, 0, $k), 'method' => 'embeddings'];
                        }
                    }
                }
            } catch (AiException $e) { error_log('Copilot embedding retrieval failed: ' . $e->getMessage()); }
        }
        if (!$tokens) { return ['passages' => [], 'method' => 'none']; }

        $st = $pdo->prepare('SELECT chunk_index, content, MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE) AS score FROM document_chunks
            WHERE contract_id=? AND MATCH(content) AGAINST (? IN NATURAL LANGUAGE MODE) ORDER BY score DESC LIMIT ' . (int)$k);
        $expr = implode(' ', $tokens);
        $st->execute([$expr, $cid, $expr]);
        $rows = $st->fetchAll();
        if ($rows) {
            return ['passages' => array_map(fn($r) => ['chunk_index' => (int)$r['chunk_index'], 'content' => $r['content'], 'score' => (float)$r['score']], $rows), 'method' => 'fulltext'];
        }
        // Keyword scoring (handles short words, stop-words and FULLTEXT quirks)
        $st = $pdo->prepare('SELECT chunk_index, content FROM document_chunks WHERE contract_id=? ORDER BY chunk_index LIMIT 400');
        $st->execute([$cid]);
        $scored = [];
        foreach ($st->fetchAll() as $r) {
            $low = mb_strtolower($r['content']); $s = 0;
            foreach ($tokens as $t) { $s += mb_substr_count($low, $t); }
            if ($s > 0) { $scored[] = ['chunk_index' => (int)$r['chunk_index'], 'content' => $r['content'], 'score' => (float)$s]; }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return ['passages' => array_slice($scored, 0, min($k, 3)), 'method' => $scored ? 'keywords' : 'none'];
    }

    private static function excerpt(string $content, int $max = 320): string
    {
        $s = preg_replace('/\s+/u', ' ', $content) ?? $content;
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    public static function ask(int $cid, string $question): array
    {
        $total = (int)Database::pdo()->query('SELECT COUNT(*) FROM document_chunks WHERE contract_id=' . (int)$cid)->fetchColumn();
        $r = self::retrieve($cid, $question);
        $passages = $r['passages'];
        if (!$passages) {
            return ['answer' => 'I could not find any passage in this contract that relates to your question, so I cannot answer it from the document.', 'sources' => [], 'mode' => 'none', 'method' => $r['method'], 'warning' => null];
        }
        $label = fn(array $p) => 'Section ' . ($p['chunk_index'] + 1) . ' of ' . $total;

        if (!GeminiClient::configured()) {
            return ['answer' => 'AI answers are unavailable (no AI key configured). Here are the passages of the contract most relevant to your question — please read them directly.',
                'sources' => array_map(fn($p) => ['section' => $label($p), 'chunk_index' => $p['chunk_index'], 'excerpt' => self::excerpt($p['content']), 'verified_quote' => false], $passages),
                'mode' => 'excerpts', 'method' => $r['method'], 'warning' => null];
        }
        $user = "<question>\n" . mb_substr($question, 0, 500) . "\n</question>\n";
        foreach ($passages as $i => $p) { $user .= '<passage id="P' . ($i + 1) . "\">\n" . mb_substr($p['content'], 0, 3000) . "\n</passage>\n"; }
        try {
            $raw = GeminiClient::generate(Prompts::COPILOT_SYSTEM, $user, 1024);
            $a = AiJson::answer(AiJson::parse($raw));
        } catch (AiException $e) {
            return ['answer' => 'The AI could not answer right now (' . $e->getMessage() . ') Here are the most relevant passages instead.',
                'sources' => array_map(fn($p) => ['section' => $label($p), 'chunk_index' => $p['chunk_index'], 'excerpt' => self::excerpt($p['content']), 'verified_quote' => false], $passages),
                'mode' => 'excerpts', 'method' => $r['method'], 'warning' => null];
        }
        $sources = []; $unverified = 0;
        if ($a['sufficient']) {
            foreach ($a['evidence'] as $e) {
                $idx = (int)ltrim($e['passage'], 'P') - 1;
                if (!isset($passages[$idx])) { continue; }
                $p = $passages[$idx];
                if ($e['quote'] !== null && EvidenceVerifier::verify($e['quote'], EvidenceVerifier::normalize($p['content']))) {
                    $sources[] = ['section' => $label($p), 'chunk_index' => $p['chunk_index'], 'excerpt' => $e['quote'], 'verified_quote' => true];
                } else { $unverified++; }
            }
            if (!$sources) {
                foreach (array_slice($passages, 0, 3) as $p) { $sources[] = ['section' => $label($p), 'chunk_index' => $p['chunk_index'], 'excerpt' => self::excerpt($p['content']), 'verified_quote' => false]; }
            }
        }
        $warning = null;
        if ($a['sufficient'] && !array_filter($sources, fn($s) => $s['verified_quote'])) {
            $warning = 'The supporting quotation from the AI could not be verified in the contract. Check the excerpts below before relying on this answer.';
        } elseif ($unverified > 0) { $warning = $unverified . ' quotation(s) from the AI could not be verified and were omitted.'; }
        return ['answer' => $a['answer'], 'sources' => $sources, 'mode' => $a['sufficient'] ? 'ai' : 'ai_insufficient', 'method' => $r['method'], 'warning' => $warning];
    }
}
