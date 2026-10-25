<?php
declare(strict_types=1);
namespace TermTrail\services;

/** No-AI fallback: conservative regex suggestions for dates, notice periods and amounts. Every suggestion carries a snippet copied from the text. */
final class RegexExtractor
{
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    public static function extract(string $text): array
    {
        return ['date_suggestions' => self::dates($text), 'payments' => self::payments($text)];
    }

    private static function snippet(string $text, int $pos, int $len): string
    {
        $from = max(0, $pos - 90);
        $s = substr($text, $from, min(strlen($text) - $from, $len + 90 + 50));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        if (!mb_check_encoding($s, 'UTF-8')) { $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8'); }
        return trim($s);
    }

    private static function fieldFor(string $before): ?string
    {
        $b = strtolower($before);
        $pos = ['expiry_date' => 0, 'renewal_date' => 0, 'start_date' => 0, 'effective_date' => 0];
        $kw = ['expiry_date' => '/(expir|terminat|end(?:s|ing)?\b|until|through)/', 'renewal_date' => '/renew/', 'start_date' => '/(commenc|start|begin)/', 'effective_date' => '/effective/'];
        foreach ($kw as $f => $re) {
            if (preg_match_all($re, $b, $m, PREG_OFFSET_CAPTURE) && $m[0]) { $pos[$f] = end($m[0])[1] + 1; }
        }
        arsort($pos);
        $top = array_key_first($pos);
        return $pos[$top] > 0 ? $top : null;
    }

    private static function dates(string $text): array
    {
        $found = [];
        $add = function (int $y, int $m, int $d, int $off, int $len, ?string $note) use (&$found, $text) {
            if (!checkdate($m, $d, $y) || $y < 1990 || $y > 2100) { return; }
            $field = self::fieldFor(substr($text, max(0, $off - 70), min($off, 70)));
            if ($field === null || isset($found[$field])) { return; }
            $found[$field] = ['field' => $field, 'value' => sprintf('%04d-%02d-%02d', $y, $m, $d), 'quote' => self::snippet($text, $off, $len), 'verified' => true, 'note' => $note];
        };
        $re = [
            '/\b(\d{4})-(\d{2})-(\d{2})\b/' => fn($m) => [(int)$m[1], (int)$m[2], (int)$m[3], null],
            '/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/' => fn($m) => [(int)$m[3], (int)$m[2], (int)$m[1], ((int)$m[1] <= 12 && (int)$m[2] <= 12) ? 'Day/month order assumed (DD/MM/YYYY).' : null],
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?,?\s+(\d{4})\b/i' => fn($m) => [(int)$m[3], self::MONTHS[strtolower($m[2])], (int)$m[1], null],
            '/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i' => fn($m) => [(int)$m[3], self::MONTHS[strtolower($m[1])], (int)$m[2], null],
        ];
        foreach ($re as $pattern => $conv) {
            if (!preg_match_all($pattern, $text, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) { continue; }
            foreach ($ms as $m) {
                $plain = array_map(fn($x) => $x[0], $m);
                [$y, $mo, $d, $note] = $conv($plain);
                $add($y, $mo, $d, $m[0][1], strlen($m[0][0]), $note);
            }
        }
        if (preg_match('/\(?(\d{1,3})\)?\s*(?:calendar\s+|business\s+|working\s+)?days?[\'’]?\s*(?:\W\s*)?(?:prior\s+|advance\s+|written\s+)*notice/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $found['notice_period_days'] = ['field' => 'notice_period_days', 'value' => (string)(int)$m[1][0], 'quote' => self::snippet($text, $m[0][1], strlen($m[0][0])), 'verified' => true, 'note' => null];
        }
        return array_values($found);
    }

    private static function payments(string $text): array
    {
        $cur = ['inr' => 'INR', 'rs' => 'INR', 'rs.' => 'INR', '₹' => 'INR', 'rupees' => 'INR', 'usd' => 'USD', 'us$' => 'USD', '$' => 'USD', 'dollars' => 'USD', 'eur' => 'EUR', '€' => 'EUR', 'gbp' => 'GBP', '£' => 'GBP'];
        $out = []; $seen = [];
        $push = function (string $c, string $num, int $off, int $len) use (&$out, &$seen, $text, $cur) {
            $code = $cur[strtolower($c)] ?? null;
            $amount = str_replace(',', '', $num);
            if ($code === null || !is_numeric($amount) || (float)$amount <= 0 || (float)$amount >= 1e13) { return; }
            $amount = number_format((float)$amount, 2, '.', '');
            $key = $code . $amount;
            if (isset($seen[$key]) || count($out) >= 15) { return; }
            $seen[$key] = true;
            $out[] = ['title' => 'Payment of ' . $code . ' ' . number_format((float)$amount, 2), 'amount' => $amount, 'currency' => $code, 'due_date' => null, 'quote' => self::snippet($text, $off, $len)];
        };
        if (preg_match_all('/(INR|Rs\.?|₹|USD|US\$|\$|EUR|€|GBP|£)\s?(\d{1,3}(?:,\d{2,3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)(?![\d,])/iu', $text, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($ms as $m) { $push($m[1][0], $m[2][0], $m[0][1], strlen($m[0][0])); }
        }
        if (preg_match_all('/(\d{1,3}(?:,\d{2,3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)\s*(INR|USD|EUR|GBP|rupees|dollars)\b/i', $text, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($ms as $m) { $push($m[2][0], $m[1][0], $m[0][1], strlen($m[0][0])); }
        }
        return $out;
    }
}
