<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;

abstract class ApiBaseController
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function tenantId(): int
    {
        return Auth::tenantId();
    }

    protected function tenant(): ?array
    {
        return Auth::tenant();
    }

    protected function admin(): ?array
    {
        return Auth::admin();
    }

    protected function jsonSuccess(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): void
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        Response::json($payload, $statusCode);
    }

    protected function jsonError(string $error, int $statusCode = 400, ?string $code = null, mixed $details = null): void
    {
        $payload = [
            'success' => false,
            'error' => $error,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        if ($details !== null) {
            $payload['details'] = $details;
        }

        Response::json($payload, $statusCode);
    }

    /**
     * Validate an input array against simple rules (e.g. ['name' => 'required', 'phone' => 'required'])
     *
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @return array<string, string> Error messages indexed by field name
     */
    protected function validate(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $val = $input[$field] ?? null;

            foreach ($ruleList as $r) {
                if ($r === 'required') {
                    if ($val === null || (is_string($val) && trim($val) === '') || (is_array($val) && empty($val))) {
                        $errors[$field] = "The {$field} field is required.";
                    }
                } elseif ($r === 'numeric') {
                    if ($val !== null && $val !== '' && !is_numeric($val)) {
                        $errors[$field] = "The {$field} field must be a valid number.";
                    }
                } elseif ($r === 'email') {
                    if ($val !== null && $val !== '' && !filter_var((string)$val, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = "The {$field} must be a valid email address.";
                    }
                }
            }
        }
        return $errors;
    }
}
