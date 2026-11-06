<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiClient;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class SettingsController
{
    public function index(Request $request): void
    {
        // 1. Fetch settings bundle via REST API
        $settingsRes = ApiClient::get('/api/settings');
        $data = $settingsRes['data'] ?? [];

        $tenant = $data['profile'] ?? Auth::tenant();
        $settings = $data['settings'] ?? [];
        $charges = $data['charges'] ?? [];

        // 2. Fetch catalog products via REST API
        $prodRes = ApiClient::get('/api/products');
        $products = $prodRes['data'] ?? [];

        // 3. Fetch subscription and quota via REST API
        $subRes = ApiClient::get('/api/subscription');
        $quota = $subRes['data']['quota'] ?? [];

        View::render('settings/index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'charges' => $charges,
            'products' => $products,
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function addProduct(Request $request): void
    {
        $name = trim((string)$request->post('name', ''));
        $code = trim((string)$request->post('code', ''));
        $unit = trim((string)$request->post('unit', 'L'));
        $defaultRate = (float)$request->post('default_rate', 60.0);

        // Create product via REST API
        $res = ApiClient::post('/api/products', [
            'name' => $name,
            'code' => $code,
            'unit' => $unit,
            'default_rate' => $defaultRate,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Product '{$name}' added to catalog successfully.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Please provide a valid product name and default rate.');
        }

        Response::redirect(url('/settings'));
    }

    public function toggleProduct(Request $request, array $params): void
    {
        $productId = (int)($params['id'] ?? 0);

        // Toggle product via REST API
        $res = ApiClient::post("/api/products/{$productId}/toggle");

        if ($res['success']) {
            Auth::flash('success', 'Product status updated.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to update product status.');
        }

        Response::redirect(url('/settings'));
    }

    public function deleteProduct(Request $request, array $params): void
    {
        $productId = (int)($params['id'] ?? 0);

        // Delete product via REST API
        $res = ApiClient::delete("/api/products/{$productId}");

        if ($res['success']) {
            Auth::flash('success', 'Product removed from catalog.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to remove product.');
        }

        Response::redirect(url('/settings'));
    }

    public function updateProfile(Request $request): void
    {
        $businessName = trim((string)$request->post('business_name', ''));
        $ownerName = trim((string)$request->post('owner_name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $email = trim((string)$request->post('email', ''));

        // Update profile via REST API
        $res = ApiClient::put('/api/settings/profile', [
            'business_name' => $businessName,
            'owner_name' => $ownerName,
            'phone' => $phone,
            'email' => $email,
        ]);

        if ($res['success']) {
            // Update session cache
            $tenant = Auth::tenant();
            if ($tenant) {
                $tenant['business_name'] = $businessName;
                $tenant['owner_name'] = $ownerName;
                $tenant['phone'] = $phone;
                $tenant['email'] = $email;
                Auth::loginTenant($tenant);
            }
            Auth::flash('success', 'Business profile updated successfully.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Business name, owner name, and phone cannot be empty.');
        }

        Response::redirect(url('/settings'));
    }

    public function updateTaxes(Request $request): void
    {
        $taxEnabled = (int)$request->post('tax_enabled', 0);
        $taxLabel = trim((string)$request->post('tax_label', 'GST'));
        $taxPercentage = (float)$request->post('tax_percentage', 0.0);
        $taxNumber = trim((string)$request->post('tax_number', ''));
        $upiId = trim((string)$request->post('upi_id', ''));
        $footerNote = trim((string)$request->post('invoice_footer_note', ''));

        // Handle QR Code image upload
        $qrPath = null;
        $file = $request->file('upi_qr_file');
        if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (in_array($mime, $allowedTypes, true)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $uploadDir = __DIR__ . '/../../public/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $tenantId = Auth::tenantId();
                $filename = "qr_tenant_{$tenantId}_" . time() . ".{$ext}";
                $dest = "{$uploadDir}/{$filename}";
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $qrPath = "uploads/{$filename}";
                }
            }
        }

        // Update taxes & UPI via REST API
        $res = ApiClient::put('/api/settings/taxes', [
            'tax_enabled' => $taxEnabled,
            'tax_label' => empty($taxLabel) ? 'GST' : $taxLabel,
            'tax_percentage' => $taxPercentage,
            'tax_number' => $taxNumber,
            'upi_id' => $upiId,
            'invoice_footer_note' => $footerNote,
            'upi_qr_path' => $qrPath,
        ]);

        if ($res['success']) {
            Auth::flash('success', 'Tax, branding and UPI settings saved.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to update tax settings.');
        }

        Response::redirect(url('/settings'));
    }

    public function addCharge(Request $request): void
    {
        $chargeName = trim((string)$request->post('charge_name', ''));
        $chargeType = (string)$request->post('charge_type', 'flat_monthly');
        $amount = (float)$request->post('amount', 0.0);
        $isTaxable = (int)$request->post('is_taxable', 0);
        $isActive = (int)$request->post('is_active', 1);

        // Create charge via REST API
        $res = ApiClient::post('/api/settings/charges', [
            'charge_name' => $chargeName,
            'name' => $chargeName,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'is_taxable' => $isTaxable,
            'is_active' => $isActive,
        ]);

        if ($res['success']) {
            Auth::flash('success', "Charge '{$chargeName}' created.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Please provide a valid charge name and amount.');
        }

        Response::redirect(url('/settings'));
    }

    public function toggleCharge(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        // Toggle charge status via REST API
        $res = ApiClient::post("/api/settings/charges/{$id}/toggle");

        if ($res['success']) {
            Auth::flash('success', 'Charge status updated.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to update charge status.');
        }

        Response::redirect(url('/settings'));
    }

    public function deleteCharge(Request $request, array $params): void
    {
        $id = (int)($params['id'] ?? 0);

        // Delete charge via REST API
        $res = ApiClient::delete("/api/settings/charges/{$id}");

        if ($res['success']) {
            Auth::flash('success', 'Charge removed.');
        } else {
            Auth::flash('error', $res['error'] ?? 'Failed to remove charge.');
        }

        Response::redirect(url('/settings'));
    }

    public function subscriptionView(Request $request): void
    {
        // Fetch subscription and quota via REST API
        $subRes = ApiClient::get('/api/subscription');
        $quota = $subRes['data']['quota'] ?? [];

        View::render('settings/subscription', [
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function upgradeQuota(Request $request): void
    {
        $blocks = max(1, (int)$request->post('blocks', 1));

        // Upgrade quota via REST API
        $res = ApiClient::post('/api/subscription/upgrade', [
            'blocks' => $blocks,
        ]);

        if ($res['success']) {
            $quota = $res['data']['quota'] ?? [];
            $maxAllowed = $quota['max_allowed'] ?? ($blocks * 100);
            Auth::flash('success', "Upgraded successfully! Added {$blocks} block(s). You can now manage up to {$maxAllowed} customers.");
        } else {
            Auth::flash('error', $res['error'] ?? 'Subscription upgrade failed.');
        }

        Response::redirect(url('/subscription'));
    }
}
