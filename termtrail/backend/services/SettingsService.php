<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\config\Database;

final class SettingsService
{
    public static function get(string $key, string $default = ''): string
    {
        $st = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? $default : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        Database::pdo()->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key, $value]);
    }

    /** Self-registration is open by default; the very first account is always allowed. */
    public static function registrationOpen(): bool
    {
        return self::get('registration_open', '1') === '1' || (int)Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    }
}
