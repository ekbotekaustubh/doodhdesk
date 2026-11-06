<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;

final class ApiDocsController extends ApiBaseController
{
    public function ui(Request $request): void
    {
        $filePath = dirname(__DIR__, 3) . '/docs/index.html';
        if (!file_exists($filePath)) {
            $this->jsonError('Swagger UI documentation file not found.', 404);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        readfile($filePath);

        if (defined('DOODHDESK_TEST_RUNNER')) {
            return;
        }
        exit;
    }

    public function spec(Request $request): void
    {
        $filePath = dirname(__DIR__, 3) . '/docs/openapi.json';
        if (!file_exists($filePath)) {
            $this->jsonError('OpenAPI specification not found.', 404);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
        }
        readfile($filePath);

        if (defined('DOODHDESK_TEST_RUNNER')) {
            return;
        }
        exit;
    }
}
