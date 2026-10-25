<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response, Validator};
use TermTrail\middleware\Auth;
use TermTrail\models\UserModel;
use TermTrail\services\SettingsService;

/** Administrator-only: user management, application settings and the audit log. Every action is enforced server-side. */
final class AdminController
{
    private static function tempPassword(): string
    {
        $letters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; $digits = '23456789';
        $chars = $letters . $digits;
        $p = $letters[random_int(0, strlen($letters) - 1)] . $digits[random_int(0, strlen($digits) - 1)];
        for ($i = 0; $i < 10; $i++) { $p .= $chars[random_int(0, strlen($chars) - 1)]; }
        return str_shuffle($p);
    }

    private static function target(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT id,name,email,role,is_active FROM users WHERE id=?');
        $st->execute([$id]);
        $t = $id > 0 ? $st->fetch() : false;
        if (!$t) { Response::error('User not found.', 404); }
        return $t;
    }

    private static function otherActiveAdmins(int $exceptId): int
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1 AND id<>?");
        $st->execute([$exceptId]);
        return (int)$st->fetchColumn();
    }

    public static function users(): never
    {
        Auth::requireApi('admin');
        $rows = Database::pdo()->query("SELECT u.id,u.name,u.email,u.role,u.is_active,u.last_login_at,u.created_at,
            (SELECT COUNT(*) FROM contracts c WHERE c.owner_id=u.id) AS contracts FROM users u ORDER BY u.created_at, u.id LIMIT 500")->fetchAll();
        Response::ok(['items' => $rows, 'registration_open' => SettingsService::get('registration_open', '1') === '1']);
    }

    public static function createUser(array $in): never
    {
        $u = Auth::requireApi('admin');
        $name = trim((string)($in['name'] ?? '')); $email = strtolower(trim((string)($in['email'] ?? ''))); $role = (string)($in['role'] ?? 'viewer');
        $e = [];
        if (!Validator::name($name)) { $e['name'] = 'Enter a name (2-120 characters).'; }
        if (!Validator::email($email)) { $e['email'] = 'Enter a valid email address.'; }
        elseif (UserModel::findByEmail($email)) { $e['email'] = 'An account with this email already exists.'; }
        if (!in_array($role, ['admin', 'manager', 'viewer'], true)) { $e['role'] = 'Choose a role.'; }
        if ($e) { Response::error('Please fix the highlighted fields.', 422, $e); }
        $temp = self::tempPassword();
        $id = UserModel::create($name, $email, $temp, $role);
        Audit::log((int)$u['id'], 'user.created', 'user', $id, ['role' => $role]);
        Response::ok(['id' => $id, 'temporary_password' => $temp, 'message' => 'User created. Share the temporary password securely; it is shown only once.'], 201);
    }

    public static function setRole(array $in): never
    {
        $u = Auth::requireApi('admin');
        $t = self::target((int)($in['id'] ?? 0));
        $role = (string)($in['role'] ?? '');
        if (!in_array($role, ['admin', 'manager', 'viewer'], true)) { Response::error('Invalid role.', 422); }
        if ((int)$t['id'] === (int)$u['id'] && $role !== 'admin') { Response::error('You cannot remove your own admin role.', 409); }
        if ($t['role'] === 'admin' && $role !== 'admin' && (int)$t['is_active'] === 1 && self::otherActiveAdmins((int)$t['id']) === 0) { Response::error('At least one active administrator is required.', 409); }
        Database::pdo()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $t['id']]);
        Audit::log((int)$u['id'], 'user.role_changed', 'user', (int)$t['id'], ['from' => $t['role'], 'to' => $role]);
        Response::ok(['message' => 'Role updated.']);
    }

    public static function setActive(array $in): never
    {
        $u = Auth::requireApi('admin');
        $t = self::target((int)($in['id'] ?? 0));
        $active = !empty($in['active']) && $in['active'] !== '0';
        if ((int)$t['id'] === (int)$u['id'] && !$active) { Response::error('You cannot deactivate your own account.', 409); }
        if (!$active && $t['role'] === 'admin' && self::otherActiveAdmins((int)$t['id']) === 0) { Response::error('At least one active administrator is required.', 409); }
        Database::pdo()->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$active ? 1 : 0, $t['id']]);
        Audit::log((int)$u['id'], $active ? 'user.activated' : 'user.deactivated', 'user', (int)$t['id']);
        Response::ok(['message' => $active ? 'User activated.' : 'User deactivated. They can no longer sign in.']);
    }

    public static function resetPassword(array $in): never
    {
        $u = Auth::requireApi('admin');
        $t = self::target((int)($in['id'] ?? 0));
        $temp = self::tempPassword();
        UserModel::updatePassword((int)$t['id'], $temp);
        Audit::log((int)$u['id'], 'user.password_reset', 'user', (int)$t['id']);
        Response::ok(['temporary_password' => $temp, 'message' => 'Password reset. Share the temporary password securely; it is shown only once. Ask the user to change it in Settings.']);
    }

    public static function setSettings(array $in): never
    {
        $u = Auth::requireApi('admin');
        if (array_key_exists('registration_open', $in)) {
            $v = !empty($in['registration_open']) && $in['registration_open'] !== '0' ? '1' : '0';
            SettingsService::set('registration_open', $v);
            Audit::log((int)$u['id'], 'settings.changed', null, null, ['registration_open' => $v]);
        }
        Response::ok(['message' => 'Settings saved.', 'registration_open' => SettingsService::get('registration_open', '1') === '1']);
    }

    public static function audit(): never
    {
        Auth::requireApi('admin');
        $where = ['1=1']; $p = [];
        if ((int)($_GET['user_id'] ?? 0) > 0) { $where[] = 'al.user_id=?'; $p[] = (int)$_GET['user_id']; }
        if (preg_match('/^[a-z_]+(\.[a-z_]+)?$/', (string)($_GET['action'] ?? ''))) { $where[] = 'al.action LIKE ?'; $p[] = $_GET['action'] . '%'; }
        if (preg_match('/^[a-z_]+$/', (string)($_GET['entity_type'] ?? ''))) { $where[] = 'al.entity_type=?'; $p[] = $_GET['entity_type']; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $k => $op) { if (Validator::date((string)($_GET[$k] ?? ''))) { $where[] = "DATE(al.created_at) $op ?"; $p[] = $_GET[$k]; } }
        $per = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pdo = Database::pdo();
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs al WHERE ' . implode(' AND ', $where)); $cnt->execute($p);
        $total = (int)$cnt->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
        $st = $pdo->prepare('SELECT al.id, al.created_at, al.action, al.entity_type, al.entity_id, al.ip_address, al.metadata, al.user_id, us.name AS user_name
            FROM activity_logs al LEFT JOIN users us ON us.id=al.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY al.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
        $st->execute($p);
        $items = $st->fetchAll();
        foreach ($items as &$i) { $i['metadata'] = $i['metadata'] ? (json_decode($i['metadata'], true) ?? null) : null; }
        unset($i);
        $actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action LIMIT 200')->fetchAll(\PDO::FETCH_COLUMN);
        $users = $pdo->query('SELECT id, name FROM users ORDER BY name LIMIT 500')->fetchAll();
        Response::ok(['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per, 'actions' => $actions, 'users' => $users]);
    }
}
