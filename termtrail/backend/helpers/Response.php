<?php
declare(strict_types=1);
namespace TermTrail\helpers;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(array $data = [], int $status = 200): never
    {
        self::json(['success' => true] + $data, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
