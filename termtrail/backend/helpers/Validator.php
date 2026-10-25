<?php
declare(strict_types=1);
namespace TermTrail\helpers;

final class Validator
{
    public static function email(string $v): bool { return (bool)filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) <= 190; }

    public static function passwordErrors(string $p): array
    {
        $e = [];
        if (strlen($p) < 8) { $e[] = 'Password must be at least 8 characters.'; }
        if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) { $e[] = 'Password must contain letters and numbers.'; }
        return $e;
    }

    public static function name(string $v): bool
    {
        $len = mb_strlen(trim($v));
        return $len >= 2 && $len <= 120;
    }

    public static function date(?string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$v, $m)) { return false; }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** Positive decimal with up to 2 places (commas tolerated). Returns normalized string or null. */
    public static function amount(string $v, bool $allowZero = false): ?string
    {
        $v = str_replace(',', '', trim($v));
        if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v)) { return null; }
        if (!$allowZero && (float)$v <= 0) { return null; }
        return $v;
    }
}
