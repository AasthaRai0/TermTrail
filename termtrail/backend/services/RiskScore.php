<?php
declare(strict_types=1);
namespace TermTrail\services;

/**
 * Heuristic 0-100 risk indicator. NOT a legally validated score.
 *   score = min(100, SUM(weight[severity] * evidence_factor))
 *   weight: high 25, medium 10, low 3
 *   evidence_factor: 1.0 if the finding is detected AND its quotation was verified in the document, else 0.6
 *   findings dismissed by a reviewer are excluded
 *   level: 0-29 low, 30-59 medium, 60-100 high
 */
final class RiskScore
{
    public const WEIGHTS = ['high' => 25, 'medium' => 10, 'low' => 3];
    public const FORMULA = 'Score = min(100, sum of severity weights). Weights: High 25, Medium 10, Low 3. A finding counts fully only when it is stated in the document and its quotation was verified; inferred, missing-clause or unverified findings count at 60%. Findings dismissed by a reviewer are excluded. Levels: 0-29 Low, 30-59 Medium, 60-100 High. This is a heuristic indicator, not a legally validated score.';

    /** @param array[] $findings rows with severity, quote_verified, is_inferred, review_status */
    public static function calculate(array $findings): array
    {
        $total = 0.0;
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];
        $items = [];
        foreach ($findings as $f) {
            if (($f['review_status'] ?? 'pending') === 'dismissed') { continue; }
            $sev = $f['severity'] ?? 'low';
            if (!isset(self::WEIGHTS[$sev])) { continue; }
            $factor = (!empty($f['quote_verified']) && empty($f['is_inferred'])) ? 1.0 : 0.6;
            $pts = self::WEIGHTS[$sev] * $factor;
            $total += $pts;
            $counts[$sev]++;
            $items[] = ['title' => (string)($f['title'] ?? ''), 'severity' => $sev, 'points' => round($pts, 1)];
        }
        $score = (int)min(100, round($total));
        return ['score' => $score, 'level' => $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low'), 'counts' => $counts, 'breakdown' => $items];
    }
}
