<?php
declare(strict_types=1);
namespace TermTrail\helpers;

final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_readable($file)) { return; }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) { continue; }
            [$k, $v] = explode('=', $line, 2);
            self::$vars[trim($k)] = trim(trim($v), "\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') { return self::$vars[$key]; }
        $e = getenv($key);
        if ($e !== false && $e !== '') { return $e; }
        return self::$vars[$key] ?? $default;
    }
}
