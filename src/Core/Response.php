<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    public static function html(string $content, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $content;
        exit;
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo "<div class='p-4 text-rose-700 bg-rose-100 rounded-lg border border-rose-300 font-medium'>" . htmlspecialchars($message) . "</div>";
        exit;
    }
}
