<?php
declare(strict_types=1);
namespace TermTrail\models;

use TermTrail\config\Database;

final class UserModel
{
    public static function findByEmail(string $email): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([strtolower($email)]);
        return $st->fetch() ?: null;
    }

    public static function count(): int
    {
        return (int)Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function create(string $name, string $email, string $password, string $role): int
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
        $st->execute([trim($name), strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int)$pdo->lastInsertId();
    }

    public static function updatePassword(int $id, string $password): void
    {
        $st = Database::pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $st->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
