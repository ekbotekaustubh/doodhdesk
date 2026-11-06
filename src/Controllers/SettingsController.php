<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ProductService;
use App\Services\QuotaService;

final class SettingsController
{
    private Database $db;
    private QuotaService $quotaService;
    private ProductService $productService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->quotaService = new QuotaService();
        $this->productService = new ProductService();
    }

    public function index(Request $request): void
    {
        $tenantId = Auth::tenantId();

        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $settings = $this->db->fetchOne("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenantId]);
        $charges = $this->db->fetchAll("SELECT * FROM tenant_additional_charges WHERE tenant_id = ? ORDER BY id ASC", [$tenantId]);
        $products = $this->productService->getTenantProducts($tenantId, false);
        $quota = $this->quotaService->getTenantQuota($tenantId);

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
        $tenantId = Auth::tenantId();
        $name = trim((string)$request->post('name', ''));
        $code = trim((string)$request->post('code', ''));
        $unit = trim((string)$request->post('unit', 'L'));
        $defaultRate = (float)$request->post('default_rate', 60.0);

        if (empty($name) || $defaultRate <= 0) {
            Auth::flash('error', 'Please provide a valid product name and default rate.');
            Response::redirect(url('/settings'));
        }

        $this->productService->createProduct($tenantId, $name, $code, $unit, $defaultRate);
        Auth::flash('success', "Product '{$name}' added to catalog successfully.");
        Response::redirect(url('/settings'));
    }

    public function toggleProduct(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $productId = (int)($params['id'] ?? 0);
        $this->productService->toggleProduct($tenantId, $productId);
        Auth::flash('success', 'Product status updated.');
        Response::redirect(url('/settings'));
    }

    public function deleteProduct(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $productId = (int)($params['id'] ?? 0);
        $this->productService->deleteProduct($tenantId, $productId);
        Auth::flash('success', 'Product removed from catalog.');
        Response::redirect(url('/settings'));
    }

    public function updateProfile(Request $request): void
    {
        $tenantId = Auth::tenantId();

        $businessName = trim((string)$request->post('business_name', ''));
        $ownerName = trim((string)$request->post('owner_name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $email = trim((string)$request->post('email', ''));

        if (empty($businessName) || empty($ownerName) || empty($phone)) {
            Auth::flash('error', 'Business name, owner name, and phone cannot be empty.');
            Response::redirect(url('/settings'));
        }

        $this->db->execute(
            "UPDATE tenants SET business_name = ?, owner_name = ?, phone = ?, email = ? WHERE id = ?",
            [$businessName, $ownerName, $phone, empty($email) ? null : $email, $tenantId]
        );

        // Update session
        $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        Auth::loginTenant($tenant);

        Auth::flash('success', 'Business profile updated successfully.');
        Response::redirect(url('/settings'));
    }

    public function updateTaxes(Request $request): void
    {
        $tenantId = Auth::tenantId();

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

            if (in_array($mime, $allowedTypes)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $uploadDir = __DIR__ . '/../../public/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = "qr_tenant_{$tenantId}_" . time() . ".{$ext}";
                $dest = "{$uploadDir}/{$filename}";
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $qrPath = "uploads/{$filename}";
                }
            }
        }

        $sql = "UPDATE tenant_settings 
                SET tax_enabled = ?, tax_label = ?, tax_percentage = ?, tax_number = ?, 
                    upi_id = ?, invoice_footer_note = ?" . ($qrPath ? ", upi_qr_path = ?" : "") . "
                WHERE tenant_id = ?";

        $params = [
            $taxEnabled,
            empty($taxLabel) ? 'GST' : $taxLabel,
            $taxPercentage,
            empty($taxNumber) ? null : $taxNumber,
            empty($upiId) ? null : $upiId,
            empty($footerNote) ? null : $footerNote,
        ];
        if ($qrPath) {
            $params[] = $qrPath;
        }
        $params[] = $tenantId;

        $this->db->execute($sql, $params);

        Auth::flash('success', 'Tax, branding and UPI settings saved.');
        Response::redirect(url('/settings'));
    }

    public function addCharge(Request $request): void
    {
        $tenantId = Auth::tenantId();

        $chargeName = trim((string)$request->post('charge_name', ''));
        $chargeType = (string)$request->post('charge_type', 'flat_monthly');
        $amount = (float)$request->post('amount', 0.0);
        $isTaxable = (int)$request->post('is_taxable', 0);
        $isActive = (int)$request->post('is_active', 1);

        if (empty($chargeName) || $amount < 0) {
            Auth::flash('error', 'Please provide a valid charge name and amount.');
            Response::redirect(url('/settings'));
        }

        if (!in_array($chargeType, ['flat_monthly', 'per_liter', 'per_delivery_day'])) {
            $chargeType = 'flat_monthly';
        }

        $this->db->execute(
            "INSERT INTO tenant_additional_charges (tenant_id, charge_name, charge_type, amount, is_taxable, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $chargeName, $chargeType, $amount, $isTaxable, $isActive]
        );

        Auth::flash('success', "Charge '{$chargeName}' created.");
        Response::redirect(url('/settings'));
    }

    public function toggleCharge(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $charge = $this->db->fetchOne("SELECT is_active FROM tenant_additional_charges WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if ($charge) {
            $newStatus = $charge['is_active'] ? 0 : 1;
            $this->db->execute("UPDATE tenant_additional_charges SET is_active = ? WHERE id = ? AND tenant_id = ?", [$newStatus, $id, $tenantId]);
            Auth::flash('success', 'Charge status updated.');
        }

        Response::redirect(url('/settings'));
    }

    public function deleteCharge(Request $request, array $params): void
    {
        $tenantId = Auth::tenantId();
        $id = (int)($params['id'] ?? 0);

        $this->db->execute("DELETE FROM tenant_additional_charges WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        Auth::flash('success', 'Charge removed.');
        Response::redirect(url('/settings'));
    }

    public function subscriptionView(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $quota = $this->quotaService->getTenantQuota($tenantId);

        View::render('settings/subscription', [
            'quota' => $quota,
            'success' => Auth::flash('success'),
            'error' => Auth::flash('error'),
        ], 'main');
    }

    public function upgradeQuota(Request $request): void
    {
        $tenantId = Auth::tenantId();
        $blocks = max(1, (int)$request->post('blocks', 1));

        $quota = $this->quotaService->upgradeCapacity($tenantId, $blocks);

        Auth::flash('success', "Upgraded successfully! Added {$blocks} block(s). You can now manage up to {$quota['max_allowed']} customers.");
        Response::redirect(url('/subscription'));
    }
}
