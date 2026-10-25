<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\helpers\Env;

/** Optional SMTP email via PHPMailer. Returns false (never throws) when not configured or sending fails. */
final class Mailer
{
    public static function configured(): bool
    {
        return (Env::get('SMTP_HOST') ?? '') !== '' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        if (!self::configured() || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host = (string)Env::get('SMTP_HOST');
            $m->Port = (int)(Env::get('SMTP_PORT', '587'));
            $user = (string)Env::get('SMTP_USERNAME', '');
            $m->SMTPAuth = $user !== '';
            if ($m->SMTPAuth) { $m->Username = $user; $m->Password = (string)Env::get('SMTP_PASSWORD', ''); }
            $enc = strtolower((string)Env::get('SMTP_ENCRYPTION', 'tls'));
            $m->SMTPSecure = $enc === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : ($enc === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
            $m->SMTPAutoTLS = $enc === 'tls';
            $m->Timeout = 10;
            $m->CharSet = 'UTF-8';
            $m->setFrom((string)(Env::get('SMTP_FROM') ?: ($user ?: 'no-reply@localhost')), 'TermTrail AI');
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->Body = $body;
            $m->isHTML(false);
            return $m->send();
        } catch (\Throwable $e) {
            error_log('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }
}
