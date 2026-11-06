<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class CustomerController
{
    public function index(Request $request): void
    {
        $search = trim((string)$request->get('q', ''));
        $route = trim((string)$request->get('route', ''));
        $status = trim((string)$request->get('status', ''));
        $milkType = trim((string)$request->get('milk_type', ''));

        // 1. Fetch customer list via REST API
        $res = ApiClient::get('/api/customers', [
            'q' => $search,
            'route' => $route,
            'status' => $status,
            'milk_type' => $milkType,
        ]);
        $customers = $res['data'] ?? [];
        $quota = $res['meta']['quota'] ?? null;

        // 2. Fetch routes and products via REST API
        $routesRes = ApiClient::get('/api/customers/routes');
        $routes = $routesRes['data'] ?? [];

        $productsRes = ApiClient::get('/api/products', ['active_only' => true]);
        $products = $productsRes['data'] ?? [];

        // Fallback quota if meta wasn't returned
        if (!$quota) {
            $subRes = ApiClient::get('/api/subscription');
            $quota = $subRes['data']['quota'] ?? [];
        }

        View::render('customers/index', [
            'customers' => $customers,
            'routes' => $routes,
            'products' => $products,
            'search' => $search,
            'selectedRoute' => $route,
            'selectedStatus' => $status,
            'selectedMilkType' => $milkType,
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
            'paywall' => Auth::flash('paywall'),
            'import_skipped' => Auth::flash('import_skipped'),
        ], 'main');
    }

    public function createView(Request $request): void
    {
        $subRes = ApiClient::get('/api/subscription');
        $quota = $subRes['data']['quota'] ?? [];

        // Guard: check quota before showing create form
        if (!empty($quota) && empty($quota['can_add'])) {
            Auth::flash('paywall', 'limit_reached');
            Response::redirect(url('/customers'));
        }

        $prodRes = ApiClient::get('/api/products', ['active_only' => true]);
        $products = $prodRes['data'] ?? [];

        View::render('customers/form', [
            'customer' => null,
            'quota' => $quota,
            'products' => $products,
            'subscriptions' => [],
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function store(Request $request): void
    {
        $name = trim((string)$request->post('name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $address = trim((string)$request->post('address', ''));
        $routeName = trim((string)$request->post('route_name', 'Main Route'));
        $status = (string)$request->post('status', 'active');

        // Parse subscriptions payload
        $rawSubs = $request->post('subscriptions');
        $subsList = [];
        if (is_array($rawSubs)) {
            foreach ($rawSubs as $pId => $subData) {
                if (is_array($subData) && !empty($subData['active'])) {
                    $qty = (float)($subData['quantity'] ?? 0.0);
                    $rate = isset($subData['custom_rate']) && $subData['custom_rate'] !== '' ? (float)$subData['custom_rate'] : null;
                    $shift = (string)($subData['shift'] ?? 'both');
                    if ($qty > 0) {
                        $subsList[] = [
                            'product_id' => (int)$pId,
                            'quantity' => $qty,
                            'custom_rate' => $rate,
                            'shift' => $shift,
                        ];
                    }
                }
            }
        }

        // Fallback for simple legacy form submissions
        if (empty($subsList)) {
            $defaultQty = (float)$request->post('default_quantity', 1.0);
            $customRate = $request->post('rate_per_unit') !== null && $request->post('rate_per_unit') !== '' ? (float)$request->post('rate_per_unit') : null;
            $subsList[] = [
                'product_id' => 1,
                'quantity' => $defaultQty > 0 ? $defaultQty : 1.0,
                'custom_rate' => $customRate,
                'shift' => 'both',
            ];
        }

        // 1. Execute customer creation via REST API
        $res = ApiClient::post('/api/customers', [
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'route_name' => $routeName,
            'status' => $status,
            'subscriptions' => $subsList,
        ]);

        if ($res['status'] === 201 || $res['success']) {
            Auth::flash('success', "Customer '{$name}' created successfully.");
            Response::redirect(url('/customers'));
        }

        if ($res['status'] === 403) {
            Auth::flash('error', $res['error'] ?? 'Customer limit reached for your current subscription plan.');
            Auth::flash('paywall', 'limit_reached');
            Response::redirect(url('/customers'));
        }

        Auth::flash('error', $res['error'] ?? 'Failed to create customer.');
        Response::redirect(url('/customers/create'));
    }

    public function editView(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        // Fetch customer details via REST API
        $custRes = ApiClient::get("/api/customers/{$id}");
        if (!$custRes['success'] || empty($custRes['data'])) {
            Auth::flash('error', 'Customer not found.');
            Response::redirect(url('/customers'));
        }
        $customer = $custRes['data'];

        $subRes = ApiClient::get('/api/subscription');
        $quota = $subRes['data']['quota'] ?? [];

        $prodRes = ApiClient::get('/api/products', ['active_only' => true]);
        $products = $prodRes['data'] ?? [];

        View::render('customers/form', [
            'customer' => $customer,
            'quota' => $quota,
            'products' => $products,
            'subscriptions' => $customer['subscriptions'] ?? [],
            'error' => Auth::flash('error'),
            'success' => Auth::flash('success'),
        ], 'main');
    }

    public function update(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $name = trim((string)$request->post('name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $address = trim((string)$request->post('address', ''));
        $routeName = trim((string)$request->post('route_name', 'Main Route'));
        $status = (string)$request->post('status', 'active');

        // Parse subscriptions
        $rawSubs = $request->post('subscriptions');
        $subsList = [];
        if (is_array($rawSubs)) {
            foreach ($rawSubs as $pId => $subData) {
                if (is_array($subData) && !empty($subData['active'])) {
                    $qty = (float)($subData['quantity'] ?? 0.0);
                    $rate = isset($subData['custom_rate']) && $subData['custom_rate'] !== '' ? (float)$subData['custom_rate'] : null;
                    $shift = (string)($subData['shift'] ?? 'both');
                    if ($qty > 0) {
                        $subsList[] = [
                            'product_id' => (int)$pId,
                            'quantity' => $qty,
                            'custom_rate' => $rate,
                            'shift' => $shift,
                        ];
                    }
                }
            }
        }

        // 1. Update customer via REST API
        $res = ApiClient::put("/api/customers/{$id}", [
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'route_name' => $routeName,
            'status' => $status,
            'subscriptions' => $subsList,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Customer '{$name}' updated successfully.");
            Response::redirect(url('/customers'));
        }

        if ($res['status'] === 403) {
            Auth::flash('error', $res['error'] ?? 'Quota exceeded when attempting to activate customer.');
            Auth::flash('paywall', 'limit_reached');
            Response::redirect(url("/customers/{$id}/edit"));
        }

        Auth::flash('error', $res['error'] ?? 'Failed to update customer.');
        Response::redirect(url("/customers/{$id}/edit"));
    }

    public function addLeave(Request $request, array $params): void
    {
        $customerId = (int)($params['id'] ?? 0);
        $startDate = (string)$request->post('start_date');
        $endDate = (string)$request->post('end_date');
        $reason = trim((string)$request->post('reason', 'Vacation / Pause'));

        // Schedule pause via REST API
        $res = ApiClient::post("/api/customers/{$customerId}/leaves", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Vacation schedule recorded! Deliveries between {$startDate} and {$endDate} will automatically pause.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Please provide a valid leave date range.');
        }

        Response::redirect(url("/customers/{$customerId}/edit"));
    }

    public function deleteLeave(Request $request, array $params): void
    {
        $customerId = (int)($params['id'] ?? 0);
        $leaveId = (int)($params['leave_id'] ?? 0);

        // Cancel pause via REST API
        $res = ApiClient::delete("/api/customers/{$customerId}/leaves/{$leaveId}");

        if ($res['success']) {
            Auth::flash('success', 'Leave schedule removed.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to remove leave schedule.');
        }

        Response::redirect(url("/customers/{$customerId}/edit"));
    }

    public function sampleCsv(Request $request): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = "sample_customers.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'Name', 'Phone', 'Address', 'Route',
            'Cow Qty (L)', 'Cow Rate', 'Buffalo Qty (L)', 'Buffalo Rate', 'Status'
        ], ',', '"', "\\");

        fputcsv($out, ['Aarav Sharma', '9822011111', 'Flat 101, Gokul Heights', 'Green Valley Route', '2.00', '65.00', '', '', 'active'], ',', '"', "\\");
        fputcsv($out, ['Priya Verma', '9822022222', 'B-204, Sunrise Residency', 'Green Valley Route', '', '', '1.50', '75.00', 'active'], ',', '"', "\\");
        fputcsv($out, ['Rajesh Gupta', '9822033333', 'House #14, Civil Lines', 'North City Route', '1.00', '65.00', '2.00', '75.00', 'active'], ',', '"', "\\");

        fclose($out);
        exit;
    }

    public function exportCsv(Request $request): void
    {
        $search = trim((string)$request->get('q', ''));
        $route = trim((string)$request->get('route', ''));
        $status = trim((string)$request->get('status', ''));
        $milkType = trim((string)$request->get('milk_type', ''));

        // Fetch all matching customers via REST API
        $res = ApiClient::get('/api/customers', [
            'q' => $search,
            'route' => $route,
            'status' => $status,
            'milk_type' => $milkType,
        ]);
        $customers = $res['data'] ?? [];

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $dateStr = date('Y-m-d');
        $filename = "customers_export_{$dateStr}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'ID', 'Name', 'Phone', 'Address', 'Route',
            'Cow Qty (L)', 'Cow Rate', 'Buffalo Qty (L)', 'Buffalo Rate', 'Status', 'Created Date'
        ], ',', '"', "\\");

        foreach ($customers as $c) {
            $subs = $c['subscriptions'] ?? [];
            $cowSub = null;
            $bufSub = null;
            foreach ($subs as $s) {
                if (($s['product_code'] ?? '') === 'cow') {
                    $cowSub = $s;
                } elseif (($s['product_code'] ?? '') === 'buffalo') {
                    $bufSub = $s;
                }
            }

            fputcsv($out, [
                $c['id'],
                $c['name'],
                $c['phone'],
                $c['address'] ?? '',
                $c['route_name'] ?? 'Main Route',
                $cowSub ? number_format((float)$cowSub['default_quantity'], 2, '.', '') : '',
                $cowSub ? number_format((float)($cowSub['effective_rate'] ?? $cowSub['custom_rate'] ?? 0), 2, '.', '') : '',
                $bufSub ? number_format((float)$bufSub['default_quantity'], 2, '.', '') : '',
                $bufSub ? number_format((float)($bufSub['effective_rate'] ?? $bufSub['custom_rate'] ?? 0), 2, '.', '') : '',
                $c['status'],
                substr((string)($c['created_at'] ?? ''), 0, 10)
            ], ',', '"', "\\");
        }

        fclose($out);
        exit;
    }

    public function importCsv(Request $request): void
    {
        $file = $request->file('csv_file');
        if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('error', 'Please select a valid CSV file to upload.');
            Response::redirect(url('/customers'));
        }

        $prodRes = ApiClient::get('/api/products', ['active_only' => true]);
        $products = $prodRes['data'] ?? [];
        $prodMap = [];
        foreach ($products as $p) {
            $prodMap[$p['code']] = (int)$p['id'];
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Auth::flash('error', 'Unable to read the uploaded CSV file.');
            Response::redirect(url('/customers'));
        }

        // Strip UTF-8 BOM
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, null, ',', '"', "\\");
        if (!$header || empty($header)) {
            fclose($handle);
            Auth::flash('error', 'Uploaded CSV file is empty.');
            Response::redirect(url('/customers'));
        }

        // Map column indexes
        $colMap = [];
        foreach ($header as $idx => $rawCol) {
            $clean = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$rawCol)));
            if (in_array($clean, ['name', 'customername', 'fullname'], true)) $colMap['name'] = $idx;
            elseif (in_array($clean, ['phone', 'mobile', 'phonenumber'], true)) $colMap['phone'] = $idx;
            elseif (in_array($clean, ['address', 'deliveryaddress'], true)) $colMap['address'] = $idx;
            elseif (in_array($clean, ['route', 'routename'], true)) $colMap['route'] = $idx;
            elseif (in_array($clean, ['cowqtyl', 'cowqty', 'cowmilkqty'], true)) $colMap['cow_qty'] = $idx;
            elseif (in_array($clean, ['cowrate', 'cowrateperunit'], true)) $colMap['cow_rate'] = $idx;
            elseif (in_array($clean, ['buffaloqtyl', 'buffaloqty', 'buffalomilkqty'], true)) $colMap['buf_qty'] = $idx;
            elseif (in_array($clean, ['buffalorate', 'buffalorateperunit'], true)) $colMap['buf_rate'] = $idx;
            elseif (in_array($clean, ['status'], true)) $colMap['status'] = $idx;
        }

        $imported = 0;
        $skipped = 0;
        $quotaFull = false;

        while (($row = fgetcsv($handle, null, ',', '"', "\\")) !== false) {
            if (empty(array_filter($row))) continue;

            $name = trim((string)($row[$colMap['name'] ?? 0] ?? ''));
            $phone = preg_replace('/[^0-9]/', '', (string)($row[$colMap['phone'] ?? 1] ?? ''));
            if (empty($name) || empty($phone)) {
                $skipped++;
                continue;
            }

            $address = trim((string)($row[$colMap['address'] ?? -1] ?? ''));
            $route = trim((string)($row[$colMap['route'] ?? -1] ?? 'Main Route'));
            $status = strtolower(trim((string)($row[$colMap['status'] ?? -1] ?? 'active'))) === 'inactive' ? 'inactive' : 'active';

            $subs = [];
            $cowQty = isset($colMap['cow_qty']) ? (float)($row[$colMap['cow_qty']] ?? 0) : 0.0;
            $cowRate = isset($colMap['cow_rate']) && $row[$colMap['cow_rate']] !== '' ? (float)$row[$colMap['cow_rate']] : null;
            if ($cowQty > 0 && isset($prodMap['cow'])) {
                $subs[] = ['product_id' => $prodMap['cow'], 'quantity' => $cowQty, 'custom_rate' => $cowRate, 'shift' => 'both'];
            }

            $bufQty = isset($colMap['buf_qty']) ? (float)($row[$colMap['buf_qty']] ?? 0) : 0.0;
            $bufRate = isset($colMap['buf_rate']) && $row[$colMap['buf_rate']] !== '' ? (float)$row[$colMap['buf_rate']] : null;
            if ($bufQty > 0 && isset($prodMap['buffalo'])) {
                $subs[] = ['product_id' => $prodMap['buffalo'], 'quantity' => $bufQty, 'custom_rate' => $bufRate, 'shift' => 'both'];
            }

            // Create customer via REST API
            $createRes = ApiClient::post('/api/customers', [
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'route_name' => empty($route) ? 'Main Route' : $route,
                'status' => $status,
                'subscriptions' => $subs,
            ]);

            if ($createRes['status'] === 201 || $createRes['success']) {
                $imported++;
            } elseif ($createRes['status'] === 403) {
                $quotaFull = true;
                $skipped++;
                break;
            } else {
                $skipped++;
            }
        }
        fclose($handle);

        if ($quotaFull) {
            Auth::flash('error', "Quota reached! Successfully imported {$imported} customers before hitting plan capacity.");
            Auth::flash('paywall', 'limit_reached');
        } else {
            Auth::flash('success', "Import completed: {$imported} customers created." . ($skipped > 0 ? " ({$skipped} skipped)" : ""));
        }

        Response::redirect(url('/customers'));
    }
}
