<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;

final class PaymentApiController extends ApiBaseController
{
    public function invoicePayments(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $invoiceId = (int)($params['id'] ?? 0);

        $invoice = $this->db->fetchOne("SELECT id, customer_id, final_amount, payment_status FROM invoices WHERE id = ? AND tenant_id = ?", [$invoiceId, $tenantId]);
        if (!$invoice) {
            $this->jsonError('Invoice not found.', 404, 'NOT_FOUND');
        }

        $payments = $this->db->fetchAll(
            "SELECT * FROM payments WHERE invoice_id = ? AND tenant_id = ? ORDER BY payment_date DESC, id DESC",
            [$invoiceId, $tenantId]
        );

        $totalPaid = array_sum(array_column($payments, 'amount'));
        $balance = max(0.0, round(((float)$invoice['final_amount']) - $totalPaid, 2));

        $this->jsonSuccess($payments, 'Payments retrieved', 200, [
            'invoice_amount' => (float)$invoice['final_amount'],
            'total_paid' => round($totalPaid, 2),
            'balance_due' => $balance,
            'status' => $invoice['payment_status'],
        ]);
    }

    public function store(Request $request): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)$request->input('customer_id', 0);
        $invoiceId = (int)$request->input('invoice_id', 0);
        $amount = (float)$request->input('amount', 0.0);
        $paymentDate = (string)$request->input('payment_date', date('Y-m-d'));
        $method = strtolower(trim((string)$request->input('payment_method', 'upi')));
        $refNo = trim((string)$request->input('reference_no', ''));
        $notes = trim((string)$request->input('notes', ''));

        $errors = $this->validate([
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
        ], [
            'customer_id' => 'required|numeric',
            'amount' => 'required|numeric',
            'payment_date' => 'required',
        ]);

        if ($amount <= 0) {
            $errors['amount'] = 'Payment amount must be greater than 0.';
        }

        if (!in_array($method, ['upi', 'cash', 'bank_transfer', 'cheque', 'other'], true)) {
            $method = 'upi';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $customer = $this->db->fetchOne("SELECT id, name FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $paymentId = 0;
        $this->db->transaction(function () use ($tenantId, $customerId, $invoiceId, $amount, $paymentDate, $method, $refNo, $notes, &$paymentId) {
            $this->db->execute(
                "INSERT INTO payments (tenant_id, customer_id, invoice_id, amount, payment_date, payment_method, reference_no, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $customerId, $invoiceId > 0 ? $invoiceId : null, $amount, $paymentDate, $method, empty($refNo) ? null : $refNo, empty($notes) ? null : $notes]
            );
            $paymentId = (int)$this->db->lastInsertId();

            // If linked to an invoice, recalculate payment_status
            if ($invoiceId > 0) {
                $invoice = $this->db->fetchOne("SELECT final_amount FROM invoices WHERE id = ? AND tenant_id = ?", [$invoiceId, $tenantId]);
                if ($invoice) {
                    $totalPaid = (float)$this->db->fetchValue(
                        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?",
                        [$invoiceId, $tenantId]
                    );

                    $finalAmt = (float)$invoice['final_amount'];
                    $newStatus = 'unpaid';
                    if ($totalPaid >= $finalAmt) {
                        $newStatus = 'paid';
                    } elseif ($totalPaid > 0) {
                        $newStatus = 'partial';
                    }

                    $this->db->execute("UPDATE invoices SET payment_status = ? WHERE id = ?", [$newStatus, $invoiceId]);
                }
            }
        });

        $payment = $this->db->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);

        $this->jsonSuccess($payment, 'Payment recorded successfully', 201);
    }

    public function customerLedger(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)($params['id'] ?? 0);

        $customer = $this->db->fetchOne("SELECT id, name, phone, address, route_name FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $invoices = $this->db->fetchAll(
            "SELECT id, billing_month, billing_year, final_amount, payment_status, generated_at as entry_date
             FROM invoices WHERE tenant_id = ? AND customer_id = ? ORDER BY billing_year ASC, billing_month ASC",
            [$tenantId, $customerId]
        );

        $payments = $this->db->fetchAll(
            "SELECT id, invoice_id, amount, payment_method, reference_no, notes, payment_date as entry_date
             FROM payments WHERE tenant_id = ? AND customer_id = ? ORDER BY payment_date ASC, id ASC",
            [$tenantId, $customerId]
        );

        // Build unified chronological ledger entries
        $ledger = [];
        foreach ($invoices as $inv) {
            $ledger[] = [
                'type' => 'invoice',
                'id' => (int)$inv['id'],
                'date' => substr((string)$inv['entry_date'], 0, 10),
                'description' => "Milk Invoice for " . date('F Y', mktime(0, 0, 0, (int)$inv['billing_month'], 10, (int)$inv['billing_year'])),
                'debit' => (float)$inv['final_amount'],
                'credit' => 0.00,
                'status' => $inv['payment_status'],
            ];
        }

        foreach ($payments as $pay) {
            $ledger[] = [
                'type' => 'payment',
                'id' => (int)$pay['id'],
                'date' => (string)$pay['entry_date'],
                'description' => "Payment received (" . strtoupper((string)$pay['payment_method']) . ")" . (!empty($pay['reference_no']) ? " Ref: {$pay['reference_no']}" : ''),
                'debit' => 0.00,
                'credit' => (float)$pay['amount'],
                'status' => 'completed',
            ];
        }

        // Sort by date ascending
        usort($ledger, fn($a, $b) => strcmp($a['date'], $b['date']));

        $runningBalance = 0.0;
        foreach ($ledger as &$entry) {
            $runningBalance += ($entry['debit'] - $entry['credit']);
            $entry['balance_after'] = round($runningBalance, 2);
        }

        $this->jsonSuccess([
            'customer' => $customer,
            'current_balance_due' => round($runningBalance, 2),
            'ledger' => $ledger,
        ]);
    }
}
