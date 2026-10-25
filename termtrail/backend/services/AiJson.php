<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Parses and validates LLM output. Everything returned by the model is untrusted. */
final class AiJson
{
    public const CLAUSE_TYPES = ['payment_terms', 'termination', 'confidentiality', 'intellectual_property', 'indemnification', 'limitation_of_liability',
        'dispute_resolution', 'governing_law', 'automatic_renewal', 'data_protection', 'non_compete_exclusivity', 'penalties_late_fees',
        'force_majeure', 'service_levels', 'other'];
    public const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'JPY'];
    private const DATE_FIELDS = ['effective_date', 'start_date', 'expiry_date', 'renewal_date', 'notice_period_days'];

    /** @throws AiException */
    public static function parse(string $raw): array
    {
        $t = trim($raw);
        $t = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $t) ?? $t;
        $d = json_decode($t, true);
        if (!is_array($d) && ($s = strpos($t, '{')) !== false && ($e = strrpos($t, '}')) !== false && $e > $s) {
            $d = json_decode(substr($t, $s, $e - $s + 1), true);
        }
        if (!is_array($d) || array_is_list($d) && $d !== []) { throw new AiException('The AI returned a response that was not valid JSON.'); }
        return $d;
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (!is_string($v) && !is_numeric($v)) { return null; }
        $s = trim((string)$v);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private static function conf(mixed $v): ?float
    {
        return (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && (float)$v >= 0 && (float)$v <= 1 ? round((float)$v, 3) : null;
    }

    private static function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function enum(mixed $v, array $allowed, string $default): string
    {
        $s = is_string($v) ? strtolower(trim(str_replace([' ', '-'], '_', $v))) : '';
        return in_array($s, $allowed, true) ? $s : $default;
    }

    private static function list(mixed $v, int $max, int $len): array
    {
        if (!is_array($v)) { return []; }
        $out = [];
        foreach (array_slice(array_values($v), 0, $max) as $x) { if (($s = self::str($x, $len)) !== null) { $out[] = $s; } }
        return $out;
    }

    /** One document section's extraction. */
    public static function section(array $d): array
    {
        $r = ['clauses' => [], 'risks' => [], 'obligations' => [], 'payments' => [], 'dates' => []];
        foreach (array_slice(is_array($d['clauses'] ?? null) ? $d['clauses'] : [], 0, 15) as $c) {
            if (!is_array($c) || ($sum = self::str($c['summary'] ?? null, 600)) === null) { continue; }
            $r['clauses'][] = ['type' => self::enum($c['type'] ?? '', self::CLAUSE_TYPES, 'other'), 'summary' => $sum, 'quote' => self::str($c['quote'] ?? null, 400), 'confidence' => self::conf($c['confidence'] ?? null)];
        }
        foreach (array_slice(is_array($d['risks'] ?? null) ? $d['risks'] : [], 0, 10) as $c) {
            if (!is_array($c) || ($t = self::str($c['title'] ?? null, 200)) === null || ($desc = self::str($c['description'] ?? null, 1200)) === null) { continue; }
            $r['risks'][] = ['title' => $t, 'description' => $desc, 'severity' => self::enum($c['severity'] ?? '', ['low', 'medium', 'high'], 'low'),
                'reason' => self::str($c['reason'] ?? null, 1000), 'quote' => self::str($c['quote'] ?? null, 400),
                'kind' => self::enum($c['kind'] ?? '', ['detected', 'inferred', 'missing'], 'inferred'),
                'suggested_action' => self::str($c['suggested_action'] ?? null, 600), 'clause_type' => self::enum($c['clause_type'] ?? '', self::CLAUSE_TYPES, 'other'),
                'confidence' => self::conf($c['confidence'] ?? null)];
        }
        foreach (array_slice(is_array($d['obligations'] ?? null) ? $d['obligations'] : [], 0, 10) as $c) {
            if (!is_array($c) || ($t = self::str($c['title'] ?? null, 255)) === null) { continue; }
            $r['obligations'][] = ['title' => $t, 'description' => self::str($c['description'] ?? null, 1500), 'responsible_party' => self::str($c['responsible_party'] ?? null, 190),
                'due_date' => self::date($c['due_date'] ?? null), 'recurrence' => self::enum($c['recurrence'] ?? '', ['none', 'weekly', 'monthly', 'quarterly', 'yearly'], 'none'),
                'quote' => self::str($c['quote'] ?? null, 400), 'confidence' => self::conf($c['confidence'] ?? null)];
        }
        foreach (array_slice(is_array($d['payments'] ?? null) ? $d['payments'] : [], 0, 10) as $c) {
            if (!is_array($c)) { continue; }
            $amt = $c['amount'] ?? null;
            if (is_string($amt)) { $amt = str_replace(',', '', trim($amt)); }
            $amt = (is_numeric($amt) && (float)$amt > 0 && (float)$amt < 1e13) ? number_format((float)$amt, 2, '.', '') : null;
            $title = self::str($c['title'] ?? null, 255);
            if ($title === null && $amt === null) { continue; }
            $cur = is_string($c['currency'] ?? null) ? strtoupper(trim($c['currency'])) : null;
            $r['payments'][] = ['title' => $title ?? 'Payment', 'amount' => $amt, 'currency' => in_array($cur, self::CURRENCIES, true) ? $cur : null,
                'due_date' => self::date($c['due_date'] ?? null), 'schedule' => self::str($c['schedule'] ?? null, 300),
                'quote' => self::str($c['quote'] ?? null, 400), 'confidence' => self::conf($c['confidence'] ?? null)];
        }
        foreach (array_slice(is_array($d['dates'] ?? null) ? $d['dates'] : [], 0, 12) as $c) {
            if (!is_array($c)) { continue; }
            $f = self::enum($c['field'] ?? '', self::DATE_FIELDS, '');
            if ($f === '') { continue; }
            $v = $c['value'] ?? null;
            if ($f === 'notice_period_days') { $v = (is_numeric($v) && (int)$v >= 0 && (int)$v <= 3650) ? (string)(int)$v : null; } else { $v = self::date($v); }
            if ($v === null) { continue; }
            $r['dates'][] = ['field' => $f, 'value' => $v, 'quote' => self::str($c['quote'] ?? null, 400)];
        }
        return $r;
    }

    public static function summary(array $d): array
    {
        $kd = [];
        foreach (array_slice(is_array($d['key_dates'] ?? null) ? $d['key_dates'] : [], 0, 15) as $x) {
            if (is_array($x) && ($l = self::str($x['label'] ?? null, 150)) !== null) { $kd[] = ['label' => $l, 'date' => self::date($x['date'] ?? null), 'note' => self::str($x['note'] ?? null, 300)]; }
        }
        $s = self::str($d['executive_summary'] ?? null, 1500);
        if ($s === null) { throw new AiException('The AI summary was incomplete. Please retry.'); }
        return ['executive_summary' => $s, 'detailed_explanation' => self::str($d['detailed_explanation'] ?? null, 8000), 'parties' => self::list($d['parties'] ?? null, 10, 200),
            'purpose' => self::str($d['purpose'] ?? null, 800), 'key_dates' => $kd, 'financial_terms' => self::list($d['financial_terms'] ?? null, 15, 400),
            'main_commitments' => self::list($d['main_commitments'] ?? null, 15, 400), 'termination_conditions' => self::list($d['termination_conditions'] ?? null, 10, 400),
            'renewal_terms' => self::list($d['renewal_terms'] ?? null, 10, 400), 'important_limitations' => self::list($d['important_limitations'] ?? null, 10, 400)];
    }

    public static function answer(array $d): array
    {
        $ev = [];
        foreach (array_slice(is_array($d['evidence'] ?? null) ? $d['evidence'] : [], 0, 6) as $e) {
            if (is_array($e) && ($p = self::str($e['passage'] ?? null, 10)) !== null) { $ev[] = ['passage' => strtoupper($p), 'quote' => self::str($e['quote'] ?? null, 500)]; }
        }
        $a = self::str($d['answer'] ?? null, 3000);
        if ($a === null) { throw new AiException('The AI returned an empty answer.'); }
        return ['answer' => $a, 'sufficient' => ($d['sufficient'] ?? true) !== false, 'evidence' => $ev];
    }

    public static function versionSummary(array $d): array
    {
        $sum = self::str($d['summary'] ?? null, 1500);
        if ($sum === null) { throw new AiException('The AI change summary was incomplete.'); }
        $changes = [];
        foreach (array_slice(is_array($d['changes'] ?? null) ? $d['changes'] : [], 0, 12) as $c) {
            if (!is_array($c) || ($t = self::str($c['change'] ?? null, 500)) === null) { continue; }
            $changes[] = ['area' => self::enum($c['area'] ?? '', ['payment', 'renewal', 'termination', 'liability', 'other'], 'other'), 'change' => $t,
                'impact' => self::enum($c['impact'] ?? '', ['favourable', 'unfavourable', 'neutral', 'unclear'], 'unclear')];
        }
        return ['summary' => $sum, 'changes' => $changes];
    }
}
