<?php
declare(strict_types=1);
namespace TermTrail\middleware;

use TermTrail\config\Database;
use TermTrail\helpers\Response;
use TermTrail\helpers\Security;

final class Auth
{
    public const ROLES = ['admin' => 3, 'manager' => 2, 'viewer' => 1];

    /** Returns the current user row from DB (never trusts session role blindly). */
    public static function user(): ?array
    {
        static $cache = null;
        if ($cache !== null) { return $cache ?: null; }
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) { $cache = false; return null; }
        $st = Database::pdo()->prepare('SELECT id,name,email,role,theme FROM users WHERE id=? AND is_active=1');
        $st->execute([(int)$id]);
        $row = $st->fetch();
        $cache = $row ?: false;
        return $row ?: null;
    }

    public static function hasRole(string $minRole): bool
    {
        $u = self::user();
        return $u !== null && self::ROLES[$u['role']] >= self::ROLES[$minRole];
    }

    /** For API endpoints: JSON 401/403 + CSRF verification on state-changing methods. */
    public static function requireApi(string $minRole = 'viewer'): array
    {
        $u = self::user();
        if (!$u) { Response::error('Authentication required.', 401); }
        if (self::ROLES[$u['role']] < self::ROLES[$minRole]) { Response::error('You do not have permission to do this.', 403); }
        self::requireCsrf();
        return $u;
    }

    public static function requireCsrf(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
            if (!Security::verifyCsrf($tok)) { Response::error('Security token invalid or expired. Refresh the page and try again.', 419); }
        }
    }

    /** For HTML pages: redirect to login when unauthenticated. */
    public static function requirePage(string $minRole = 'viewer'): array
    {
        $u = self::user();
        if (!$u) { header('Location: ' . APP_URL . '/index.php?page=login'); exit; }
        if (self::ROLES[$u['role']] < self::ROLES[$minRole]) { http_response_code(403); exit('403 Forbidden'); }
        return $u;
    }
}
