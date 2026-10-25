<?php
declare(strict_types=1);
namespace TermTrail\services;

final class VectorMath
{
    /** Valid embedding = list of 8..4096 finite numbers. Returns floats or null. */
    public static function validate(mixed $v): ?array
    {
        if (!is_array($v)) { return null; }
        $n = count($v);
        if ($n < 8 || $n > 4096) { return null; }
        $out = [];
        foreach ($v as $x) {
            if (!is_int($x) && !is_float($x)) { return null; }
            if (!is_finite((float)$x)) { return null; }
            $out[] = (float)$x;
        }
        return $out;
    }

    public static function cosine(array $a, array $b): ?float
    {
        if (count($a) !== count($b) || !$a) { return null; }
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        foreach ($a as $i => $x) { $y = $b[$i]; $dot += $x * $y; $na += $x * $x; $nb += $y * $y; }
        if ($na <= 0.0 || $nb <= 0.0) { return null; }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
