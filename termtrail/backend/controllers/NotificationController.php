<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\services\{Mailer, ReminderService};

final class NotificationController
{
    public static function list(): never
    {
        $u = Auth::requireApi();
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $st->execute([$u['id']]);
        $unread = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT id,type,title,message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 20');
        $st->execute([$u['id']]);
        Response::ok(['unread' => $unread, 'items' => $st->fetchAll()]);
    }

    public static function read(array $in): never
    {
        $u = Auth::requireApi();
        Database::pdo()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int)($in['id'] ?? 0), $u['id']]);
        Response::ok();
    }

    public static function readAll(): never
    {
        $u = Auth::requireApi();
        Database::pdo()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0')->execute([$u['id']]);
        Response::ok();
    }

    /** Admin-only: shows whether scheduled reminders have ever run and whether email is configured. */
    public static function reminderStatus(): never
    {
        Auth::requireApi('admin');
        Response::ok(['last_run' => ReminderService::lastRun(), 'email_configured' => Mailer::configured()]);
    }

    public static function runReminders(): never
    {
        $u = Auth::requireApi('admin');
        $r = ReminderService::run();
        Audit::log((int)$u['id'], 'reminders.run', null, null, ['notifications' => $r['notifications'], 'emails' => $r['emails']]);
        Response::ok(['result' => $r, 'last_run' => ReminderService::lastRun()]);
    }
}
