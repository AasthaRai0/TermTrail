<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response, Validator};
use TermTrail\middleware\Auth;
use TermTrail\models\UserModel;

final class AuthController
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MIN = 15;

    private static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

    private static function tooManyAttempts(string $email): bool
    {
        $st = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE (email=? OR ip_address=?) AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE)');
        $st->execute([$email, self::ip()]);
        return (int)$st->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function register(array $in): never
    {
        Auth::requireCsrf();
        $name = trim((string)($in['name'] ?? ''));
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $pass = (string)($in['password'] ?? '');
        $errors = [];
        if (!Validator::name($name)) { $errors['name'] = 'Enter your name (2-120 characters).'; }
        if (!Validator::email($email)) { $errors['email'] = 'Enter a valid email address.'; }
        if ($pass !== (string)($in['password_confirm'] ?? '')) { $errors['password_confirm'] = 'Passwords do not match.'; }
        $pe = Validator::passwordErrors($pass);
        if ($pe) { $errors['password'] = implode(' ', $pe); }
        if (!$errors && UserModel::findByEmail($email)) { $errors['email'] = 'An account with this email already exists.'; }
        if ($errors) { Response::error('Please fix the highlighted fields.', 422, $errors); }

        // First account becomes admin; later self-registrations are managers who only see their own records.
        $role = UserModel::count() === 0 ? 'admin' : 'manager';
        $id = UserModel::create($name, $email, $pass, $role);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        Audit::log($id, 'user.register', 'user', $id, ['role' => $role]);
        Response::ok(['redirect' => APP_URL . '/index.php?page=dashboard'], 201);
    }

    public static function login(array $in): never
    {
        Auth::requireCsrf();
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $pass = (string)($in['password'] ?? '');
        if ($email === '' || $pass === '') { Response::error('Email and password are required.', 422); }
        if (self::tooManyAttempts($email)) {
            Response::error('Too many failed attempts. Please wait ' . self::WINDOW_MIN . ' minutes and try again.', 429);
        }
        $user = UserModel::findByEmail($email);
        // Always run a hash check so response time does not reveal whether the email exists.
        $hash = $user['password_hash'] ?? '$2y$10$sPSW/O114Ek1QubbLz.2ROoSbyboDNeVLbqhaU2Qwc/Zo1lLNAIbi';
        $valid = password_verify($pass, $hash) && $user && (int)$user['is_active'] === 1;
        if (!$valid) {
            Database::pdo()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?,?)')->execute([$email, self::ip()]);
            Audit::log($user['id'] ?? null, 'auth.login_failed', 'user', $user['id'] ?? null);
            Response::error('Invalid email or password.', 401);
        }
        Database::pdo()->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$email]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) { UserModel::updatePassword((int)$user['id'], $pass); }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        Database::pdo()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        Audit::log((int)$user['id'], 'auth.login', 'user', (int)$user['id']);
        Response::ok(['redirect' => APP_URL . '/index.php?page=dashboard']);
    }

    public static function logout(): never
    {
        $u = Auth::requireApi();
        Audit::log((int)$u['id'], 'auth.logout', 'user', (int)$u['id']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        Response::ok(['redirect' => APP_URL . '/index.php?page=login']);
    }

    public static function changePassword(array $in): never
    {
        $u = Auth::requireApi();
        $st = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id=?');
        $st->execute([$u['id']]);
        $hash = (string)$st->fetchColumn();
        if (!password_verify((string)($in['current_password'] ?? ''), $hash)) {
            Response::error('Current password is incorrect.', 422, ['current_password' => 'Current password is incorrect.']);
        }
        $new = (string)($in['new_password'] ?? '');
        $pe = Validator::passwordErrors($new);
        if ($pe) { Response::error('Please fix the highlighted fields.', 422, ['new_password' => implode(' ', $pe)]); }
        if ($new !== (string)($in['new_password_confirm'] ?? '')) {
            Response::error('Passwords do not match.', 422, ['new_password_confirm' => 'Passwords do not match.']);
        }
        UserModel::updatePassword((int)$u['id'], $new);
        session_regenerate_id(true);
        Audit::log((int)$u['id'], 'user.password_changed', 'user', (int)$u['id']);
        Response::ok(['message' => 'Password updated.']);
    }

    public static function updateProfile(array $in): never
    {
        $u = Auth::requireApi();
        $name = trim((string)($in['name'] ?? $u['name']));
        $theme = (string)($in['theme'] ?? $u['theme']);
        if (!Validator::name($name)) { Response::error('Enter a valid name.', 422, ['name' => 'Enter a valid name (2-120 characters).']); }
        if (!in_array($theme, ['dark', 'light'], true)) { Response::error('Invalid theme.', 422); }
        Database::pdo()->prepare('UPDATE users SET name=?, theme=? WHERE id=?')->execute([$name, $theme, $u['id']]);
        Audit::log((int)$u['id'], 'user.profile_updated', 'user', (int)$u['id']);
        Response::ok(['message' => 'Profile saved.', 'theme' => $theme]);
    }
}
