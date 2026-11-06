<?php
declare(strict_types=1);

namespace App\Core;

class HttpResponseException extends \RuntimeException
{
    public function __construct(public readonly mixed $data = null, public readonly int $statusCode = 200)
    {
        parent::__construct('HTTP Response Handled');
    }
}

final class Response
{
    private static int $lastStatusCode = 200;
    private static bool $interceptMode = false;
    private static mixed $interceptedData = null;

    public static function beginIntercept(): void
    {
        self::$interceptMode = true;
        self::$interceptedData = null;
    }

    public static function endIntercept(): void
    {
        self::$interceptMode = false;
    }

    public static function isIntercepting(): bool
    {
        return self::$interceptMode;
    }

    public static function getInterceptedData(): mixed
    {
        return self::$interceptedData;
    }

    public static function getStatusCode(): int
    {
        return self::$lastStatusCode;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): void
    {
        self::$lastStatusCode = $statusCode;
        if (self::$interceptMode) {
            self::$interceptedData = [
                'status' => $statusCode,
                'body' => $data,
                'headers' => $headers,
            ];
            throw new HttpResponseException($data, $statusCode);
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (defined('DOODHDESK_TEST_RUNNER') && DOODHDESK_TEST_RUNNER) {
            throw new HttpResponseException($data, $statusCode);
        }

        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        self::$lastStatusCode = $statusCode;
        if (self::$interceptMode) {
            self::$interceptedData = [
                'status' => $statusCode,
                'redirect' => $url,
            ];
            return;
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header("Location: {$url}");
        }

        if (defined('DOODHDESK_TEST_RUNNER') && DOODHDESK_TEST_RUNNER) {
            throw new HttpResponseException(['redirect' => $url], $statusCode);
        }

        exit;
    }

    public static function html(string $content, int $statusCode = 200, array $headers = []): void
    {
        if (self::$interceptMode) {
            self::$interceptedData = [
                'status' => $statusCode,
                'html' => $content,
                'headers' => $headers,
            ];
            return;
        }

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
        self::$lastStatusCode = $statusCode;
        if (self::$interceptMode) {
            self::$interceptedData = [
                'status' => $statusCode,
                'body' => ['success' => false, 'error' => $message],
            ];
            return;
        }

        http_response_code($statusCode);
        echo "<div class='p-4 text-rose-700 bg-rose-100 rounded-lg border border-rose-300 font-medium'>" . htmlspecialchars($message) . "</div>";
        exit;
    }
}
