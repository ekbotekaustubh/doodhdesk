<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\BillingService;
use App\Services\PdfExportService;
use App\Services\QrCodeService;

final class InvoiceController
{
    private Database $db;
    private BillingService $billingService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->billingService = new BillingService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $year = (int)$request->get('year', (int)date('Y'));
        $month = (int)$request->get('month', (int)date('m'));
        $status = (string)$request->get('status', '');

        $sql = "SELECT inv.*, c.name AS customer_name, c.phone AS customer_phone, c.route_name, c.milk_types
                FROM invoices inv
                JOIN customers c ON c.id = inv.customer_id
                WHERE inv.tenant_id = ? AND inv.billing_year = ? AND inv.billing_month = ?";
        $params = [$tenantId, $year, $month];

        if (!empty($status)) {
            $sql .= " AND inv.payment_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY c.route_name ASC, c.name ASC";
        $invoices = $this->db->fetchAll($sql, $params);

        // Summaries
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $unpaidCount = 0;
        foreach ($invoices as $inv) {
            $totalBilled += (float)$inv['final_amount'];
            if ($inv['payment_status'] === 'paid') {
                $totalPaid += (float)$inv['final_amount'];
            } elseif ($inv['payment_status'] === 'unpaid') {
                $unpaidCount++;
            }
        }

        // Active customers count to see how many bills are generated
        $activeCustomersCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        );

        View::render('invoices/index', [
            'invoices' => $invoices,
            'year' => $year,
            'month' => $month,
            'selectedStatus' => $status,
            'totalBilled' => $totalBilled,
            'totalPaid' => $totalPaid,
            'unpaidCount' => $unpaidCount,
            'activeCustomersCount' => $activeCustomersCount,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function generateBatch(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $year = (int)$request->post('year', (int)date('Y'));
        $month = (int)$request->post('month', (int)date('m'));

        $results = $this->billingService->generateBatchInvoices($tenantId, $year, $month);
        $count = count($results);

        Auth::flash('success', "Successfully generated {$count} invoices for " . sprintf('%02d/%04d', $month, $year) . "!");
        Response::redirect(url("/invoices?year={$year}&month={$month}"));
    }

    public function generateSingle(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $customerId = (int)$request->post('customer_id');
        $year = (int)$request->post('year', (int)date('Y'));
        $month = (int)$request->post('month', (int)date('m'));

        $bill = $this->billingService->generateOrUpdateInvoice($tenantId, $customerId, $year, $month);

        Auth::flash('success', "Invoice updated for {$bill['customer']['name']} ({$bill['billing_period']}).");
        Response::redirect(url("/invoices/{$bill['invoice_id']}"));
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $invoice = $this->getInvoiceWithCustomer($id, $tenantId);

        if (!$invoice) {
            Auth::flash('error', 'Invoice not found.');
            Response::redirect(url('/invoices'));
        }

        $breakdown = $this->prepareInvoiceBreakdown($invoice, $tenantId);

        // Tenant & branding settings
        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $settings = $this->db->fetchOne("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);

        // Payments for this invoice
        $payments = $this->db->fetchAll(
            "SELECT * FROM payments WHERE tenant_id = ? AND customer_id = ? AND (invoice_id = ? OR invoice_id IS NULL) ORDER BY payment_date DESC",
            [$tenantId, $invoice['customer_id'], $id]
        );

        View::render('invoices/show', [
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'tenant' => $tenant,
            'settings' => $settings,
            'payments' => $payments,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function printA4(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $invoice = $this->getInvoiceWithCustomer($id, $tenantId);

        if (!$invoice) {
            Response::error('Invoice not found', 404);
        }

        $breakdown = $this->prepareInvoiceBreakdown($invoice, $tenantId);
        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $settings = $this->db->fetchOne("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);

        View::render('invoices/a4', [
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'tenant' => $tenant,
            'settings' => $settings,
        ], 'print');
    }

    public function printThermal(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);
        $format = (string)$request->get('format', '80mm'); // 80mm or 58mm

        $invoice = $this->getInvoiceWithCustomer($id, $tenantId);

        if (!$invoice) {
            Response::error('Invoice not found', 404);
        }

        $breakdown = $this->prepareInvoiceBreakdown($invoice, $tenantId);
        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $settings = $this->db->fetchOne("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);

        View::render('invoices/thermal', [
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'tenant' => $tenant,
            'settings' => $settings,
            'format' => $format,
        ], 'print');
    }

    public function downloadPdf(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);
        $type = (string)$request->get('type', 'a4'); // a4, thermal80, thermal58

        $invoice = $this->getInvoiceWithCustomer($id, $tenantId);

        if (!$invoice) {
            Response::error('Invoice not found', 404);
        }

        $breakdown = $this->prepareInvoiceBreakdown($invoice, $tenantId);
        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $settings = $this->db->fetchOne("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);

        $viewName = str_starts_with($type, 'thermal') ? 'invoices/thermal' : 'invoices/a4';
        $paper = match($type) {
            'thermal80' => 'thermal80',
            'thermal58' => 'thermal58',
            default => 'A4',
        };

        $html = View::renderPartial($viewName, [
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'tenant' => $tenant,
            'settings' => $settings,
            'format' => $type === 'thermal58' ? '58mm' : '80mm',
            'isPdf' => true,
        ]);

        $filename = "Invoice-{$invoice['billing_year']}-{$invoice['billing_month']}-{$invoice['customer_name']}.pdf";
        PdfExportService::streamPdf($html, $filename, $paper);
    }

    private function getInvoiceWithCustomer(int $id, int $tenantId): ?array
    {
        return $this->db->fetchOne(
            "SELECT inv.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address, c.route_name, c.milk_types
             FROM invoices inv
             JOIN customers c ON c.id = inv.customer_id
             WHERE inv.id = ? AND inv.tenant_id = ?",
            [$id, $tenantId]
        );
    }

    private function prepareInvoiceBreakdown(array $invoice, int $tenantId): array
    {
        $breakdown = json_decode((string)$invoice['breakdown_json'], true) ?: [];

        $year = (int)$invoice['billing_year'];
        $month = (int)$invoice['billing_month'];
        $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Populate products breakdown if not stored in json
        if (empty($breakdown['products'])) {
            $dItems = $this->db->fetchAll(
                "SELECT ddi.*, p.name as product_name, p.code as product_code, p.unit
                 FROM daily_delivery_items ddi
                 JOIN daily_deliveries dd ON dd.id = ddi.delivery_id
                 JOIN products p ON p.id = ddi.product_id
                 WHERE ddi.tenant_id = ? AND dd.customer_id = ? AND dd.delivery_date BETWEEN ? AND ? AND ddi.status = 'delivered'",
                [$tenantId, (int)$invoice['customer_id'], $startDate, $endDate]
            );

            if (!empty($dItems)) {
                $pSumm = [];
                foreach ($dItems as $di) {
                    $pid = (int)$di['product_id'];
                    if (!isset($pSumm[$pid])) {
                        $pSumm[$pid] = [
                            'product_id' => $pid,
                            'name' => $di['product_name'],
                            'code' => $di['product_code'],
                            'unit' => $di['unit'],
                            'unit_price' => (float)$di['unit_price'],
                            'total_units' => 0.0,
                            'subtotal' => 0.0,
                        ];
                    }
                    $qty = (float)$di['quantity'];
                    $pSumm[$pid]['total_units'] += $qty;
                    $pSumm[$pid]['subtotal'] += round($qty * (float)$di['unit_price'], 2);
                }
                $breakdown['products'] = array_values($pSumm);
            }
        }

        if (empty($breakdown['calendar_matrix'])) {
            if (empty($breakdown['itemized_deliveries'])) {
                $customerRate = (float)($breakdown['customer_rate'] ?? 60.0);

                $deliveries = $this->db->fetchAll(
                    "SELECT delivery_date as date, shift, status, quantity, notes
                     FROM daily_deliveries
                     WHERE tenant_id = ? AND customer_id = ? AND delivery_date BETWEEN ? AND ?
                     ORDER BY delivery_date ASC, shift ASC",
                    [$tenantId, (int)$invoice['customer_id'], $startDate, $endDate]
                );

                foreach ($deliveries as &$del) {
                    $del['quantity'] = (float)$del['quantity'];
                    $del['amount'] = round($del['quantity'] * $customerRate, 2);
                }
                unset($del);

                $breakdown['itemized_deliveries'] = $deliveries;
            }

            $matrixData = buildCalendarMatrix($breakdown, (int)$invoice['billing_year'], (int)$invoice['billing_month']);
            $breakdown['calendar_matrix'] = $matrixData['matrix'];
            $breakdown['first_day_of_week'] = $matrixData['first_day_of_week'];
            $breakdown['days_in_month'] = $matrixData['days_in_month'];
        }

        return $breakdown;
    }

    public function recordPayment(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $invoiceId = (int)($params['id'] ?? 0);
        $amount = (float)$request->post('amount');
        $paymentDate = (string)$request->post('payment_date', date('Y-m-d'));
        $paymentMethod = (string)$request->post('payment_method', 'upi');
        $referenceNo = trim((string)$request->post('reference_no', ''));
        $notes = trim((string)$request->post('notes', ''));

        $invoice = $this->db->fetchOne("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?", [$invoiceId, $tenantId]);
        if (!$invoice) {
            Auth::flash('error', 'Invoice not found.');
            Response::redirect(url('/invoices'));
        }

        if ($amount <= 0) {
            Auth::flash('error', 'Payment amount must be greater than zero.');
            Response::redirect(url("/invoices/{$invoiceId}"));
        }

        $customerId = (int)$invoice['customer_id'];

        $this->db->transaction(function() use ($tenantId, $customerId, $invoiceId, $amount, $paymentDate, $paymentMethod, $referenceNo, $notes, $invoice) {
            // Record payment
            $this->db->execute(
                "INSERT INTO payments (tenant_id, customer_id, invoice_id, amount, payment_date, payment_method, reference_no, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $customerId, $invoiceId, $amount, $paymentDate, $paymentMethod, empty($referenceNo) ? null : $referenceNo, empty($notes) ? null : $notes]
            );

            // Re-check total payments for invoice
            $totalPaid = (float)$this->db->fetchValue(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND invoice_id = ?",
                [$tenantId, $invoiceId]
            );

            $finalAmount = (float)$invoice['final_amount'];
            $newStatus = $totalPaid >= $finalAmount ? 'paid' : ($totalPaid > 0 ? 'partial' : 'unpaid');

            $this->db->execute(
                "UPDATE invoices SET payment_status = ? WHERE id = ? AND tenant_id = ?",
                [$newStatus, $invoiceId, $tenantId]
            );
        });

        Auth::flash('success', "Payment of " . formatMoney($amount) . " recorded successfully!");
        Response::redirect(url("/invoices/{$invoiceId}"));
    }
}
