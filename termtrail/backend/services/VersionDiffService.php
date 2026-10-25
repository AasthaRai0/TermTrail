<?php
declare(strict_types=1);
namespace TermTrail\services;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\SequenceMatcher;

/** Paragraph-level diff (jfcherng/php-diff) + word-level highlighting + keyword flags for sensitive clauses. */
final class VersionDiffService
{
    public const MAX_LINES = 6000;
    private const MAX_SHOWN_LINES = 800;

    private const AREAS = [
        'payment' => ['Payment terms', '/\b(pay(?:ment|ments|able|s)?|invoice\w*|fees?|price\w*|pricing|charges?|interest|penalt(?:y|ies)|late)\b|[₹$€£]|\b(?:INR|USD|EUR|GBP)\b/iu'],
        'renewal' => ['Renewal / term', '/\b(renew\w*|initial term|term of|expir\w*|extend\w*|extension)\b/iu'],
        'termination' => ['Termination / notice', '/\b(terminat\w*|notice|cancel\w*|breach|cure period)\b/iu'],
        'liability' => ['Liability / indemnity', '/\b(liabil\w*|indemnif\w*|damages|warrant\w*|consequential|limitation)\b/iu'],
    ];

    /** @return string[] non-empty trimmed paragraphs */
    public static function lines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $l) { $l = trim($l); if ($l !== '') { $out[] = $l; } }
        return $out;
    }

    private static function words(string $s): int { return count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []); }

    /** Pure function: diff two arrays of paragraphs. */
    public static function diff(array $old, array $new): array
    {
        if (count($old) > self::MAX_LINES || count($new) > self::MAX_LINES) { throw new VersionException('These documents are too large to compare (more than ' . self::MAX_LINES . ' paragraphs).'); }
        $differ = new Differ($old, $new, ['context' => 3, 'ignoreWhitespace' => true]);
        $groups = $differ->getGroupedOpcodes();

        $stats = ['added_lines' => 0, 'removed_lines' => 0, 'added_words' => 0, 'removed_words' => 0];
        $removed = []; $added = []; $pairs = [];
        $hunks = []; $shown = 0; $truncated = false; $prevEnd = 0;

        foreach ($groups as $g) {
            $lines = [];
            foreach ($g as [$tag, $i1, $i2, $j1, $j2]) {
                if ($tag === SequenceMatcher::OP_EQ) { for ($i = $i1; $i < $i2; $i++) { $lines[] = ['t' => 'eq', 's' => $old[$i]]; } continue; }
                $o = array_slice($old, $i1, $i2 - $i1); $n = array_slice($new, $j1, $j2 - $j1);
                foreach ($o as $x) { $stats['removed_lines']++; $stats['removed_words'] += self::words($x); }
                foreach ($n as $x) { $stats['added_lines']++; $stats['added_words'] += self::words($x); }
                $k = min(count($o), count($n));
                for ($x = 0; $x < $k; $x++) { $pairs[] = [$o[$x], $n[$x]]; [$os, $ns] = InlineDiff::words($o[$x], $n[$x]); $lines[] = ['t' => 'chg', 'old' => $os, 'new' => $ns]; }
                for ($x = $k; $x < count($o); $x++) { $removed[] = $o[$x]; $lines[] = ['t' => 'del', 's' => $o[$x]]; }
                for ($x = $k; $x < count($n); $x++) { $added[] = $n[$x]; $lines[] = ['t' => 'ins', 's' => $n[$x]]; }
            }
            $first = $g[0][1]; $last = end($g)[2];
            if ($shown + count($lines) <= self::MAX_SHOWN_LINES) {
                $hunks[] = ['gap_before' => max(0, $first - $prevEnd), 'lines' => $lines];
                $shown += count($lines);
            } else { $truncated = true; }
            $prevEnd = $last;
        }
        return ['identical' => !$groups, 'stats' => $stats, 'hunks' => $hunks, 'gap_after' => $groups ? max(0, count($old) - $prevEnd) : count($old), 'truncated' => $truncated,
            'flags' => self::flags($pairs, $removed, $added), 'old_lines' => count($old), 'new_lines' => count($new)];
    }

    private static function snip(string $s): string { return mb_strlen($s) > 220 ? mb_substr($s, 0, 220) . '…' : $s; }

    /** Which sensitive areas changed? Deterministic keyword matching (not AI). */
    private static function flags(array $pairs, array $removed, array $added): array
    {
        $out = [];
        foreach (self::AREAS as $key => [$label, $re]) {
            $items = [];
            foreach ($pairs as [$o, $n]) { if (preg_match($re, $o) || preg_match($re, $n)) { $items[] = ['kind' => 'changed', 'old' => self::snip($o), 'new' => self::snip($n)]; } }
            foreach ($removed as $o) { if (preg_match($re, $o)) { $items[] = ['kind' => 'removed', 'old' => self::snip($o), 'new' => null]; } }
            foreach ($added as $n) { if (preg_match($re, $n)) { $items[] = ['kind' => 'added', 'old' => null, 'new' => self::snip($n)]; } }
            if ($items) { $out[] = ['area' => $key, 'label' => $label, 'count' => count($items), 'examples' => array_slice($items, 0, 5)]; }
        }
        return $out;
    }

    /** Text of a version, or a clear error when it could not be extracted. */
    public static function textOf(array $v): string
    {
        if (empty($v['extracted_text_path'])) {
            throw new VersionException('Version ' . (int)$v['version_number'] . ' has no extracted text (it may be a scanned or unreadable file; OCR is not supported), so it cannot be compared.');
        }
        $p = FileStorage::textPath($v['extracted_text_path']);
        $t = is_file($p) ? (string)file_get_contents($p) : '';
        if (trim($t) === '') { throw new VersionException('The extracted text of version ' . (int)$v['version_number'] . ' is missing, so it cannot be compared.'); }
        return $t;
    }

    public static function compare(array $from, array $to): array
    {
        return self::diff(self::lines(self::textOf($from)), self::lines(self::textOf($to)));
    }

    /** Compact text of the changed paragraphs for the optional AI summary (bounded). */
    public static function changedText(array $diff, int $max = 6000): string
    {
        $out = '';
        foreach ($diff['hunks'] as $h) {
            foreach ($h['lines'] as $l) {
                if ($l['t'] === 'del') { $out .= "- " . $l['s'] . "\n"; }
                elseif ($l['t'] === 'ins') { $out .= "+ " . $l['s'] . "\n"; }
                elseif ($l['t'] === 'chg') { $out .= "- " . implode('', array_column(array_filter($l['old'], fn($s) => $s['t'] !== 'ins'), 's')) . "\n+ " . implode('', array_column(array_filter($l['new'], fn($s) => $s['t'] !== 'del'), 's')) . "\n"; }
                if (strlen($out) >= $max) { return mb_substr($out, 0, $max); }
            }
        }
        return $out;
    }
}
