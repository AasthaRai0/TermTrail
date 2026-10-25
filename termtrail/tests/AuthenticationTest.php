<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use TermTrail\config\Database;
use TermTrail\helpers\Validator;
use TermTrail\models\UserModel;

final class AuthenticationTest extends DbTestCase
{
    public function testPasswordIsHashedNotStored(): void
    {
        $id = UserModel::create('Asha Rao', 'Asha@Example.test', 'Secret123', 'manager');
        $row = UserModel::findByEmail('asha@example.test');
        $this->assertSame($id, (int)$row['id']);
        $this->assertNotSame('Secret123', $row['password_hash']);
        $this->assertStringStartsWith('$', $row['password_hash']);
        $this->assertSame('asha@example.test', $row['email'], 'emails are stored lower-case');
    }

    public function testPasswordVerification(): void
    {
        UserModel::create('Asha Rao', 'asha@example.test', 'Secret123', 'viewer');
        $hash = UserModel::findByEmail('asha@example.test')['password_hash'];
        $this->assertTrue(password_verify('Secret123', $hash));
        $this->assertFalse(password_verify('secret123', $hash));
        $this->assertFalse(password_verify('', $hash));
    }

    public function testPasswordChangeReplacesHash(): void
    {
        $id = UserModel::create('Asha Rao', 'asha@example.test', 'Secret123', 'viewer');
        UserModel::updatePassword($id, 'NewSecret456');
        $hash = UserModel::findByEmail('asha@example.test')['password_hash'];
        $this->assertFalse(password_verify('Secret123', $hash));
        $this->assertTrue(password_verify('NewSecret456', $hash));
    }

    public function testEmailsAreUnique(): void
    {
        UserModel::create('A One', 'dup@example.test', 'Secret123', 'viewer');
        $this->expectException(\PDOException::class);
        UserModel::create('A Two', 'DUP@example.test', 'Secret123', 'viewer');
    }

    public function testUnknownEmailIsNotFound(): void
    {
        $this->assertNull(UserModel::findByEmail('nobody@example.test'));
    }

    public function testPasswordPolicy(): void
    {
        $this->assertSame([], Validator::passwordErrors('Passw0rd1'));
        $this->assertNotEmpty(Validator::passwordErrors('short1'));
        $this->assertNotEmpty(Validator::passwordErrors('onlyletters'));
        $this->assertNotEmpty(Validator::passwordErrors('12345678'));
    }

    public function testEmailAndNameValidation(): void
    {
        $this->assertTrue(Validator::email('a@b.co'));
        foreach (['bad', 'a@', '@b.co', 'a b@c.co', str_repeat('a', 200) . '@x.co'] as $bad) { $this->assertFalse(Validator::email($bad), $bad); }
        $this->assertTrue(Validator::name('Al'));
        $this->assertFalse(Validator::name('A'));
        $this->assertFalse(Validator::name(str_repeat('x', 121)));
    }

    public function testSchemaStoresRolesFromAllowedSet(): void
    {
        foreach (['admin', 'manager', 'viewer'] as $r) { $this->assertSame($r, $this->user($r)['role']); }
        $this->expectException(\PDOException::class);
        Database::pdo()->exec("INSERT INTO users (name,email,password_hash,role) VALUES ('x','x@x.test','h','superuser')");
    }
}
