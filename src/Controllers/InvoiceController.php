<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\PdfExportService;

final class InvoiceController
{
    public function index(Request $request): void
    {
        $year = (int)$request->get('year', (int)date('Y'));
        $month = (int)$request->get('month', (int)date('m'));
        $status = (string)$request->get('status', '');

        // 1. Fetch invoices list via REST API
        $res = ApiClient::get('/api/invoices', [
            'year' => $year,
            'month' => $month,
            'status' => $status,
        ]);
        $invoices = $res['data'] ?? [];

        // 2. Fetch customer list to determine total active customer count via REST API
        $custRes = ApiClient::get('/api/customers', ['status' => 'active']);
        $activeCustomersCount = count($custRes['data'] ?? []);

        // Compute summaries
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $unpaidCount = 0;
        foreach ($invoices as $inv) {
            $totalBilled += (float)($inv['final_amount'] ?? 0.0);
            if (($inv['payment_status'] ?? '') === 'paid') {
                $totalPaid += (float)($inv['final_amount'] ?? 0.0);
            } elseif (($inv['payment_status'] ?? '') === 'unpaid') {
                $unpaidCount++;
            }
        }

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
        $year = (int)$request->post('year', (int)date('Y'));
        $month = (int)$request->post('month', (int)date('m'));

        // Batch generate via REST API
        $res = ApiClient::post('/api/invoices/generate-batch', [
            'year' => $year,
            'month' => $month,
        ]);

        if ($res['success']) {
            $count = $res['data']['generated_count'] ?? 0;
            Auth::flash('success', "Successfully generated {$count} invoices for " . sprintf('%02d/%04d', $month, $year) . "!");
        } else {
            Auth::flash('error', $res['error'] ?? 'Batch invoice generation failed.');
        }

        Response::redirect(url("/invoices?year={$year}&month={$month}"));
    }

    public function generateSingle(Request $request): void
    {
        $customerId = (int)$request->post('customer_id');
        $year = (int)$request->post('year', (int)date('Y'));
        $month = (int)$request->post('month', (int)date('m'));

        // Single invoice generation via REST API
        $res = ApiClient::post('/api/invoices/generate-single', [
            'customer_id' => $customerId,
            'year' => $year,
            'month' => $month,
        ]);

        if ($res['success'] && !empty($res['data'])) {
            $bill = $res['data'];
            $invoiceId = $bill['id'] ?? $bill['invoice_id'] ?? 0;
            Auth::flash('success', "Invoice updated for customer ({$month}/{$year}).");
            Response::redirect(url("/invoices/{$invoiceId}"));
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to generate customer invoice.');
            Response::redirect(url('/invoices'));
        }
    }

    public function show(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        // Fetch invoice details via REST API
        $res = ApiClient::get("/api/invoices/{$id}");
        if (!$res['success'] || empty($res['data']['invoice'])) {
            Auth::flash('error', 'Invoice not found.');
            Response::redirect(url('/invoices'));
        }

        $data = $res['data'];
        $invoice = $data['invoice'];
        $breakdown = $this->ensureBreakdownMatrix($data['breakdown'] ?? [], $invoice);
        $payments = $data['payments'] ?? [];
        $settings = $data['settings'] ?? [];
        $tenant = Auth::tenant();

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
        $id = (int)($params['id'] ?? 0);

        $res = ApiClient::get("/api/invoices/{$id}");
        if (!$res['success'] || empty($res['data']['invoice'])) {
            Response::error('Invoice not found', 404);
        }

        $data = $res['data'];
        $invoice = $data['invoice'];
        $breakdown = $this->ensureBreakdownMatrix($data['breakdown'] ?? [], $invoice);
        $settings = $data['settings'] ?? [];
        $tenant = Auth::tenant();

        View::render('invoices/a4', [
            'invoice' => $invoice,
            'breakdown' => $breakdown,
            'tenant' => $tenant,
            'settings' => $settings,
        ], 'print');
    }

    public function printThermal(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $format = (string)$request->get('format', '80mm');

        $res = ApiClient::get("/api/invoices/{$id}");
        if (!$res['success'] || empty($res['data']['invoice'])) {
            Response::error('Invoice not found', 404);
        }

        $data = $res['data'];
        $invoice = $data['invoice'];
        $breakdown = $this->ensureBreakdownMatrix($data['breakdown'] ?? [], $invoice);
        $settings = $data['settings'] ?? [];
        $tenant = Auth::tenant();

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
        $id = (int)($params['id'] ?? 0);
        $type = (string)$request->get('type', 'a4');

        $res = ApiClient::get("/api/invoices/{$id}");
        if (!$res['success'] || empty($res['data']['invoice'])) {
            Response::error('Invoice not found', 404);
        }

        $data = $res['data'];
        $invoice = $data['invoice'];
        $breakdown = $this->ensureBreakdownMatrix($data['breakdown'] ?? [], $invoice);
        $settings = $data['settings'] ?? [];
        $tenant = Auth::tenant();

        $viewName = str_starts_with($type, 'thermal') ? 'invoices/thermal' : 'invoices/a4';
        $paper = match ($type) {
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

        $filename = "Invoice-{$invoice['billing_year']}-{$invoice['billing_month']}-" . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($invoice['customer_name'] ?? 'Bill')) . ".pdf";
        PdfExportService::streamPdf($html, $filename, $paper);
    }

    public function recordPayment(Request $request, array $params): void
    {
        $invoiceId = (int)($params['id'] ?? 0);
        $amount = (float)$request->post('amount');
        $paymentDate = (string)$request->post('payment_date', date('Y-m-d'));
        $paymentMethod = (string)$request->post('payment_method', 'cash');
        $referenceNo = trim((string)$request->post('reference_no', ''));
        $notes = trim((string)$request->post('notes', ''));

        // Record payment via REST API
        $res = ApiClient::post('/api/payments', [
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_mode' => $paymentMethod,
            'reference_number' => $referenceNo,
            'notes' => $notes,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Payment of " . formatMoney($amount) . " recorded successfully!");
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to record payment.');
        }

        Response::redirect(url("/invoices/{$invoiceId}"));
    }

    private function ensureBreakdownMatrix(array $breakdown, array $invoice): array
    {
        if (empty($breakdown['calendar_matrix']) && function_exists('buildCalendarMatrix')) {
            $matrixData = buildCalendarMatrix($breakdown, (int)($invoice['billing_year'] ?? date('Y')), (int)($invoice['billing_month'] ?? date('m')));
            $breakdown['calendar_matrix'] = $matrixData['matrix'] ?? [];
            $breakdown['first_day_of_week'] = $matrixData['first_day_of_week'] ?? 1;
            $breakdown['days_in_month'] = $matrixData['days_in_month'] ?? 30;
        }
        return $breakdown;
    }
}
