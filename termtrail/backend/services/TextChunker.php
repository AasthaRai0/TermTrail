<?php
declare(strict_types=1);
namespace TermTrail\services;

final class TextChunker
{
    /** Overlapping word windows (~500 words, 50-word overlap). @return string[] */
    public static function chunk(string $text, int $size = 500, int $overlap = 50): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($words);
        if ($n === 0) { return []; }
        if ($n <= $size) { return [implode(' ', $words)]; }
        $step = max(1, $size - $overlap);
        $chunks = [];
        for ($i = 0; $i < $n; $i += $step) {
            $chunks[] = implode(' ', array_slice($words, $i, $size));
            if ($i + $size >= $n) { break; }
        }
        return $chunks;
    }
}
