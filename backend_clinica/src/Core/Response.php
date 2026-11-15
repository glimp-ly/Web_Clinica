<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function sendHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }

    public static function json(array $data, int $status = 200): never
    {
        self::sendHeaders();
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        $payload = ['ok' => false, 'message' => $message];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }
}
