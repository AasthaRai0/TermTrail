<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\Response;
use TermTrail\middleware\Auth;

final class UserController
{
    /** Minimal list (id, name, role) used to pick a contract reviewer. */
    public static function options(): never
    {
        Auth::requireApi('manager');
        $rows = Database::pdo()->query("SELECT id, name, role FROM users WHERE is_active=1 ORDER BY name LIMIT 500")->fetchAll();
        Response::ok(['items' => $rows]);
    }
}
