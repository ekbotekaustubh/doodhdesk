<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;

final class LeaveApiController extends ApiBaseController
{
    public function index(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne("SELECT id FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $leaves = $this->db->fetchAll(
            "SELECT * FROM customer_leaves WHERE tenant_id = ? AND customer_id = ? ORDER BY start_date DESC",
            [$tenantId, $customerId]
        );

        $this->jsonSuccess($leaves);
    }

    public function store(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne("SELECT id, name FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $startDate = (string)$request->input('start_date', '');
        $endDate = (string)$request->input('end_date', '');
        $reason = trim((string)$request->input('reason', 'Vacation / Pause'));

        $errors = $this->validate([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if (empty($errors) && $startDate > $endDate) {
            $errors['end_date'] = 'End date cannot be earlier than start date.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $this->db->execute(
            "INSERT INTO customer_leaves (tenant_id, customer_id, start_date, end_date, reason)
             VALUES (?, ?, ?, ?, ?)",
            [$tenantId, $customerId, $startDate, $endDate, $reason]
        );

        $newId = (int)$this->db->lastInsertId();
        $leave = $this->db->fetchOne("SELECT * FROM customer_leaves WHERE id = ?", [$newId]);

        $this->jsonSuccess($leave, "Vacation scheduled from {$startDate} to {$endDate}. Deliveries will automatically pause.", 201);
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)($params['id'] ?? 0);
        $leaveId = (int)($params['leave_id'] ?? 0);

        $leave = $this->db->fetchOne(
            "SELECT * FROM customer_leaves WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            [$leaveId, $customerId, $tenantId]
        );

        if (!$leave) {
            $this->jsonError('Leave record not found.', 404, 'NOT_FOUND');
        }

        $this->db->execute(
            "DELETE FROM customer_leaves WHERE id = ? AND customer_id = ? AND tenant_id = ?",
            [$leaveId, $customerId, $tenantId]
        );

        $this->jsonSuccess(null, 'Vacation schedule removed successfully');
    }
}
