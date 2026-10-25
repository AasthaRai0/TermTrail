<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\config\Database;
use TermTrail\models\{ContractModel, UserModel};

/** Base class for tests that need the (throw-away) test database. Every test starts from empty tables with fictional data. */
abstract class DbTestCase extends TestCase
{
    private static int $seq = 0;

    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $t) { $pdo->exec('TRUNCATE TABLE `' . $t . '`'); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array{id:int,name:string,email:string,role:string,theme:string} same shape as Auth::user() */
    protected function user(string $role = 'manager'): array
    {
        $email = $role . (++self::$seq) . '@example.test';
        $id = UserModel::create(ucfirst($role) . ' ' . self::$seq, $email, 'Passw0rd1', $role);
        return ['id' => $id, 'name' => ucfirst($role) . ' ' . self::$seq, 'email' => $email, 'role' => $role, 'theme' => 'dark'];
    }

    protected function contract(array $owner, array $extra = []): int
    {
        return ContractModel::insert(array_merge(['owner_id' => $owner['id'], 'title' => 'Test contract ' . (++self::$seq), 'status' => 'active', 'currency' => 'INR'], $extra));
    }

    protected function tmp(string $content, string $suffix = '.txt'): string
    {
        $p = sys_get_temp_dir() . '/tt_' . bin2hex(random_bytes(6)) . $suffix;
        file_put_contents($p, $content);
        register_shutdown_function(fn() => @unlink($p));
        return $p;
    }
}
