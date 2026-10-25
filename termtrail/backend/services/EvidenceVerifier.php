<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Confirms that a quotation really appears in the source text (whitespace/quote-style tolerant). */
final class EvidenceVerifier
{
    public static function normalize(string $s): string
    {
        $s = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{00A0}", "\u{200B}", "\u{FEFF}"], ["'", "'", '"', '"', '-', '-', ' ', '', ''], $s);
        $s = mb_strtolower($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        return trim($s);
    }

    /** Quotes may use "..." / "…" to skip text; every fragment (>= 8 chars) must appear, in order. */
    public static function verify(?string $quote, string $normalizedText): bool
    {
        if ($quote === null) { return false; }
        $q = self::normalize($quote);
        $q = trim($q, " \"'`");
        if (mb_strlen($q) < 8) { return false; }
        $parts = preg_split('/\s*(?:\.\.\.|\x{2026})\s*/u', $q) ?: [];
        $parts = array_values(array_filter(array_map(fn($p) => trim($p, " \"'`"), $parts), fn($p) => $p !== ''));
        if (!$parts) { return false; }
        $pos = 0;
        foreach ($parts as $p) {
            if (mb_strlen($p) < 8) { return false; }
            $i = mb_strpos($normalizedText, $p, $pos);
            if ($i === false) { return false; }
            $pos = $i + mb_strlen($p);
        }
        return true;
    }

    /** Index of the chunk (500-word windows) that contains the quote, or null. */
    public static function chunkIndex(string $quote, array $normalizedChunks): ?int
    {
        $q = trim(self::normalize($quote), " \"'`");
        $first = preg_split('/\s*(?:\.\.\.|\x{2026})\s*/u', $q)[0] ?? $q;
        foreach ($normalizedChunks as $i => $c) { if ($first !== '' && mb_strpos($c, $first) !== false) { return (int)$i; } }
        return null;
    }
}
