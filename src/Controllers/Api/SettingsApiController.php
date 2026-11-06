<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;

final class SettingsApiController extends ApiBaseController
{
    public function getSettings(Request $request): void
    {
        $tenantId = $this->tenantId();

        $tenant = $this->db->fetchOne(
            "SELECT id, business_name, owner_name, phone, email FROM tenants WHERE id = ?",
            [$tenantId]
        );

        $settings = $this->db->fetchOne(
            "SELECT tax_enabled, tax_label, tax_percentage, tax_number, upi_id, upi_qr_path, invoice_footer_note
             FROM tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $charges = $this->db->fetchAll(
            "SELECT * FROM tenant_additional_charges WHERE tenant_id = ? ORDER BY id ASC",
            [$tenantId]
        );

        $this->jsonSuccess([
            'profile' => $tenant,
            'settings' => $settings,
            'charges' => $charges,
        ]);
    }

    public function updateProfile(Request $request): void
    {
        $tenantId = $this->tenantId();
        $businessName = trim((string)$request->input('business_name', ''));
        $ownerName = trim((string)$request->input('owner_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $email = trim((string)$request->input('email', ''));
        $invoiceFooterNote = trim((string)$request->input('invoice_footer_note', ''));

        $errors = $this->validate([
            'business_name' => $businessName,
            'owner_name' => $ownerName,
            'phone' => $phone,
        ], [
            'business_name' => 'required',
            'owner_name' => 'required',
            'phone' => 'required',
        ]);

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $this->db->execute(
            "UPDATE tenants SET business_name = ?, owner_name = ?, phone = ?, email = ? WHERE id = ?",
            [$businessName, $ownerName, $phone, empty($email) ? null : $email, $tenantId]
        );

        $this->db->execute(
            "UPDATE tenant_settings SET invoice_footer_note = ? WHERE tenant_id = ?",
            [$invoiceFooterNote, $tenantId]
        );

        $this->jsonSuccess(null, 'Profile updated successfully');
    }

    public function updateTaxes(Request $request): void
    {
        $tenantId = $this->tenantId();
        $taxEnabled = (bool)$request->input('tax_enabled', false) ? 1 : 0;
        $taxLabel = trim((string)$request->input('tax_label', 'GST'));
        $taxPercentage = (float)$request->input('tax_percentage', 5.00);
        $taxNumber = trim((string)$request->input('tax_number', ''));

        if ($taxPercentage < 0 || $taxPercentage > 100) {
            $this->jsonError('Tax percentage must be between 0 and 100.', 422, 'VALIDATION_ERROR');
        }

        $this->db->execute(
            "UPDATE tenant_settings 
             SET tax_enabled = ?, tax_label = ?, tax_percentage = ?, tax_number = ?
             WHERE tenant_id = ?",
            [$taxEnabled, empty($taxLabel) ? 'GST' : $taxLabel, $taxPercentage, empty($taxNumber) ? null : $taxNumber, $tenantId]
        );

        $this->jsonSuccess([
            'tax_enabled' => (bool)$taxEnabled,
            'tax_label' => $taxLabel,
            'tax_percentage' => $taxPercentage,
            'tax_number' => $taxNumber,
        ], 'Tax settings updated successfully');
    }

    public function updateUpi(Request $request): void
    {
        $tenantId = $this->tenantId();
        $upiId = trim((string)$request->input('upi_id', ''));

        $this->db->execute(
            "UPDATE tenant_settings SET upi_id = ? WHERE tenant_id = ?",
            [empty($upiId) ? null : $upiId, $tenantId]
        );

        $this->jsonSuccess(['upi_id' => $upiId], 'UPI ID updated successfully');
    }

    public function getCharges(Request $request): void
    {
        $tenantId = $this->tenantId();
        $charges = $this->db->fetchAll(
            "SELECT * FROM tenant_additional_charges WHERE tenant_id = ? ORDER BY id ASC",
            [$tenantId]
        );

        $this->jsonSuccess($charges);
    }

    public function addCharge(Request $request): void
    {
        $tenantId = $this->tenantId();
        $name = trim((string)$request->input('charge_name', ''));
        $type = strtolower(trim((string)$request->input('charge_type', 'flat_monthly')));
        $amount = (float)$request->input('amount', 0.0);
        $isTaxable = (bool)$request->input('is_taxable', false) ? 1 : 0;

        $errors = $this->validate([
            'charge_name' => $name,
            'charge_type' => $type,
            'amount' => $amount,
        ], [
            'charge_name' => 'required',
            'charge_type' => 'required',
            'amount' => 'required|numeric',
        ]);

        if (!in_array($type, ['flat_monthly', 'per_liter', 'per_delivery_day'], true)) {
            $errors['charge_type'] = 'Charge type must be flat_monthly, per_liter, or per_delivery_day.';
        }

        if ($amount < 0) {
            $errors['amount'] = 'Charge amount cannot be negative.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $this->db->execute(
            "INSERT INTO tenant_additional_charges (tenant_id, charge_name, charge_type, amount, is_taxable, is_active)
             VALUES (?, ?, ?, ?, ?, 1)",
            [$tenantId, $name, $type, $amount, $isTaxable]
        );

        $newId = (int)$this->db->lastInsertId();
        $charge = $this->db->fetchOne("SELECT * FROM tenant_additional_charges WHERE id = ?", [$newId]);

        $this->jsonSuccess($charge, 'Additional charge added successfully', 201);
    }

    public function toggleCharge(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $charge = $this->db->fetchOne(
            "SELECT * FROM tenant_additional_charges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$charge) {
            $this->jsonError('Charge not found.', 404, 'NOT_FOUND');
        }

        $newStatus = $charge['is_active'] ? 0 : 1;
        $this->db->execute(
            "UPDATE tenant_additional_charges SET is_active = ? WHERE id = ? AND tenant_id = ?",
            [$newStatus, $id, $tenantId]
        );

        $charge['is_active'] = $newStatus;
        $this->jsonSuccess($charge, 'Charge status updated successfully');
    }

    public function deleteCharge(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $charge = $this->db->fetchOne(
            "SELECT * FROM tenant_additional_charges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$charge) {
            $this->jsonError('Charge not found.', 404, 'NOT_FOUND');
        }

        $this->db->execute(
            "DELETE FROM tenant_additional_charges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->jsonSuccess(null, 'Charge deleted successfully');
    }
}
