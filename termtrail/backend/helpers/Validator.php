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
}
