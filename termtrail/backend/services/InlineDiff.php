<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Word-level diff of two short strings (LCS). Returns segment lists: [['t'=>'eq|del|ins','s'=>text], ...] for old and new. */
final class InlineDiff
{
    private const MAX_TOKENS = 320;

    /** @return array{0:array,1:array} */
    public static function words(string $old, string $new): array
    {
        $a = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($a); $m = count($b);
        if ($n > self::MAX_TOKENS || $m > self::MAX_TOKENS) { return [[['t' => 'del', 's' => $old]], [['t' => 'ins', 's' => $new]]]; }
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $oldSegs = []; $newSegs = [];
        $i = 0; $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) { $oldSegs[] = ['t' => 'eq', 's' => $a[$i]]; $newSegs[] = ['t' => 'eq', 's' => $b[$j]]; $i++; $j++; }
            elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) { $oldSegs[] = ['t' => 'del', 's' => $a[$i++]]; }
            else { $newSegs[] = ['t' => 'ins', 's' => $b[$j++]]; }
        }
        while ($i < $n) { $oldSegs[] = ['t' => 'del', 's' => $a[$i++]]; }
        while ($j < $m) { $newSegs[] = ['t' => 'ins', 's' => $b[$j++]]; }
        return [self::merge($oldSegs), self::merge($newSegs)];
    }

    private static function merge(array $segs): array
    {
        $out = [];
        foreach ($segs as $s) {
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['t'] === $s['t']) { $out[$last]['s'] .= $s['s']; } else { $out[] = $s; }
        }
        return $out;
    }
}
