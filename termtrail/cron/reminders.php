<?php
/**
 * TermTrail AI reminder job — run from the command line / Windows Task Scheduler.
 *   C:\xampp\php\php.exe C:\xampp\htdocs\termtrail\cron\reminders.php
 * Creates in-app notifications (and emails when SMTP is configured) for expiring contracts,
 * renewal notice deadlines, upcoming/overdue payments and obligations. Each reminder fires once.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("This script can only be run from the command line.\n"); }

require dirname(__DIR__) . '/backend/config/bootstrap.php';

use TermTrail\services\ReminderService;

try {
    $r = ReminderService::run();
    printf("[%s] Reminders checked. Notifications created: %d. Emails sent: %d (email %s).\n",
        date('Y-m-d H:i:s'), $r['notifications'], $r['emails'], $r['email_configured'] ? 'configured' : 'not configured');
    exit(0);
} catch (Throwable $e) {
    error_log('Reminder job failed: ' . $e->getMessage());
    fwrite(STDERR, "Reminder job failed: " . $e->getMessage() . "\n");
    exit(1);
}
