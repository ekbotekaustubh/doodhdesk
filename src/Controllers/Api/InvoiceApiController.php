<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\BillingService;

final class InvoiceApiController extends ApiBaseController
{
    private BillingService $billingService;

    public function __construct()
    {
        parent::__construct();
        $this->billingService = new BillingService();
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $month = (int)$request->get('month', date('n'));
        $year = (int)$request->get('year', date('Y'));
        $status = trim((string)$request->get('status', ''));
        $customerId = (int)$request->get('customer_id', 0);

        $sql = "SELECT i.*, c.name as customer_name, c.phone as customer_phone, c.route_name
                FROM invoices i
                JOIN customers c ON c.id = i.customer_id
                WHERE i.tenant_id = ?";
        $params = [$tenantId];

        if ($month > 0) {
            $sql .= " AND i.billing_month = ?";
            $params[] = $month;
        }

        if ($year > 0) {
            $sql .= " AND i.billing_year = ?";
            $params[] = $year;
        }

        if (!empty($status)) {
            $sql .= " AND i.payment_status = ?";
            $params[] = $status;
        }

        if ($customerId > 0) {
            $sql .= " AND i.customer_id = ?";
            $params[] = $customerId;
        }

        $sql .= " ORDER BY i.billing_year DESC, i.billing_month DESC, c.route_name ASC, c.name ASC";
        $invoices = $this->db->fetchAll($sql, $params);

        foreach ($invoices as &$inv) {
            $inv['total_units'] = (float)$inv['total_units'];
            $inv['milk_subtotal'] = (float)$inv['milk_subtotal'];
            $inv['additional_charges_total'] = (float)$inv['additional_charges_total'];
            $inv['tax_amount'] = (float)$inv['tax_amount'];
            $inv['previous_balance'] = (float)$inv['previous_balance'];
            $inv['final_amount'] = (float)$inv['final_amount'];
            if (!empty($inv['breakdown_json'])) {
                $inv['breakdown'] = json_decode((string)$inv['breakdown_json'], true);
            }
            unset($inv['breakdown_json']);
        }

        $this->jsonSuccess($invoices, 'Invoices retrieved', 200, [
            'total_count' => count($invoices),
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $invoice = $this->db->fetchOne(
            "SELECT i.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, c.route_name
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ? AND i.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$invoice) {
            $this->jsonError('Invoice not found.', 404, 'NOT_FOUND');
        }

        $payments = $this->db->fetchAll(
            "SELECT * FROM payments WHERE invoice_id = ? AND tenant_id = ? ORDER BY payment_date ASC",
            [$id, $tenantId]
        );

        $paidAmount = array_sum(array_column($payments, 'amount'));
        $balanceRemaining = max(0.0, round(((float)$invoice['final_amount']) - $paidAmount, 2));

        $breakdown = !empty($invoice['breakdown_json']) ? json_decode((string)$invoice['breakdown_json'], true) : null;
        unset($invoice['breakdown_json']);

        $settings = $this->db->fetchOne(
            "SELECT tax_enabled, tax_label, tax_percentage, tax_number, upi_id, invoice_footer_note FROM tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $this->jsonSuccess([
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'payments' => $payments,
            'summary' => [
                'final_amount' => (float)$invoice['final_amount'],
                'paid_amount' => round($paidAmount, 2),
                'balance_remaining' => $balanceRemaining,
                'status' => $invoice['payment_status'],
            ],
            'settings' => $settings,
        ]);
    }

    public function generateBatch(Request $request): void
    {
        $tenantId = $this->tenantId();
        $month = (int)$request->input('month', date('n'));
        $year = (int)$request->input('year', date('Y'));

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2050) {
            $this->jsonError('Invalid month or year.', 422, 'VALIDATION_ERROR');
        }

        $results = $this->billingService->generateBatchInvoices($tenantId, $year, $month);

        $this->jsonSuccess($results, "Batch generation completed: {$results['generated_count']} invoice(s) processed.");
    }

    public function generateSingle(Request $request): void
    {
        $tenantId = $this->tenantId();
        $customerId = (int)$request->input('customer_id', 0);
        $month = (int)$request->input('month', date('n'));
        $year = (int)$request->input('year', date('Y'));

        $errors = $this->validate([
            'customer_id' => $customerId,
            'month' => $month,
            'year' => $year,
        ], [
            'customer_id' => 'required|numeric',
            'month' => 'required|numeric',
            'year' => 'required|numeric',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $customer = $this->db->fetchOne("SELECT id, name FROM customers WHERE id = ? AND tenant_id = ?", [$customerId, $tenantId]);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404, 'NOT_FOUND');
        }

        $invoice = $this->billingService->generateOrUpdateInvoice($tenantId, $customerId, $year, $month);
        if (isset($invoice['invoice_id'])) {
            $invoice['id'] = (int)$invoice['invoice_id'];
        }

        $this->jsonSuccess($invoice, "Invoice generated successfully for {$customer['name']}.");
    }

    public function share(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $invoice = $this->db->fetchOne(
            "SELECT i.*, c.name as customer_name, c.phone as customer_phone
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ? AND i.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$invoice) {
            $this->jsonError('Invoice not found.', 404, 'NOT_FOUND');
        }

        $settings = $this->db->fetchOne(
            "SELECT upi_id FROM tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        );
        $tenant = $this->tenant();

        $monthName = date('F Y', mktime(0, 0, 0, (int)$invoice['billing_month'], 10, (int)$invoice['billing_year']));
        $finalAmt = number_format((float)$invoice['final_amount'], 2);

        $text = "Namaste {$invoice['customer_name']},\n\n";
        $text .= "Your milk bill for {$monthName} from " . ($tenant['business_name'] ?? 'Dairy') . " is ready.\n";
        $text .= "• Total Delivered: {$invoice['total_units']} Liters\n";
        $text .= "• Net Amount Due: ₹{$finalAmt}\n\n";

        if (!empty($settings['upi_id'])) {
            $text .= "Pay easily via UPI ID: {$settings['upi_id']}\n";
            $text .= "UPI Payment Link: upi://pay?pa={$settings['upi_id']}&pn=" . urlencode($tenant['business_name'] ?? 'Dairy') . "&am={$finalAmt}&tn=" . urlencode("Milk Bill #{$id}") . "\n\n";
        }

        $text .= "Thank you for choosing pure milk daily!";

        $cleanPhone = preg_replace('/[^\d]/', '', (string)$invoice['customer_phone']);
        $whatsappUrl = "https://api.whatsapp.com/send?phone=91{$cleanPhone}&text=" . urlencode($text);

        $this->jsonSuccess([
            'phone' => $cleanPhone,
            'message_text' => $text,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }
}
