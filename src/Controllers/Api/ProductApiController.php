<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\ProductService;

final class ProductApiController extends ApiBaseController
{
    private ProductService $productService;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $activeOnly = (bool)$request->get('active_only', false);
        $products = $this->productService->getTenantProducts($tenantId, $activeOnly);

        $this->jsonSuccess($products);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$product) {
            $this->jsonError('Product not found.', 404, 'NOT_FOUND');
        }

        $this->jsonSuccess($product);
    }

    public function store(Request $request): void
    {
        $tenantId = $this->tenantId();
        $name = trim((string)$request->input('name', ''));
        $code = strtolower(trim((string)$request->input('code', '')));
        $unit = trim((string)$request->input('unit', 'L'));
        $defaultRate = (float)$request->input('default_rate', 0.0);
        $sortOrder = (int)$request->input('sort_order', 0);

        if (empty($code)) {
            $code = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        }

        $errors = $this->validate([
            'name' => $name,
            'code' => $code,
            'unit' => $unit,
            'default_rate' => $defaultRate,
        ], [
            'name' => 'required',
            'code' => 'required',
            'unit' => 'required',
            'default_rate' => 'numeric',
        ]);

        if ($defaultRate <= 0) {
            $errors['default_rate'] = 'Default rate must be greater than 0.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $existing = $this->db->fetchOne(
            "SELECT id FROM products WHERE tenant_id = ? AND code = ?",
            [$tenantId, $code]
        );

        if ($existing) {
            $this->jsonError("Product with code '{$code}' already exists.", 422, 'DUPLICATE_CODE');
        }

        $this->db->execute(
            "INSERT INTO products (tenant_id, name, code, unit, default_rate, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)",
            [$tenantId, $name, $code, $unit, $defaultRate, $sortOrder]
        );

        $newId = (int)$this->db->lastInsertId();
        $product = $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$newId]);

        $this->jsonSuccess($product, 'Product created successfully', 201);
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$product) {
            $this->jsonError('Product not found.', 404, 'NOT_FOUND');
        }

        $name = trim((string)$request->input('name', $product['name']));
        $unit = trim((string)$request->input('unit', $product['unit']));
        $defaultRate = (float)$request->input('default_rate', $product['default_rate']);
        $sortOrder = (int)$request->input('sort_order', $product['sort_order']);

        $errors = $this->validate([
            'name' => $name,
            'unit' => $unit,
            'default_rate' => $defaultRate,
        ], [
            'name' => 'required',
            'unit' => 'required',
            'default_rate' => 'numeric',
        ]);

        if ($defaultRate <= 0) {
            $errors['default_rate'] = 'Default rate must be greater than 0.';
        }

        if (!empty($errors)) {
            $this->jsonError('Validation failed', 422, 'VALIDATION_ERROR', $errors);
        }

        $this->db->execute(
            "UPDATE products 
             SET name = ?, unit = ?, default_rate = ?, sort_order = ?
             WHERE id = ? AND tenant_id = ?",
            [$name, $unit, $defaultRate, $sortOrder, $id, $tenantId]
        );

        $updated = $this->db->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
        $this->jsonSuccess($updated, 'Product updated successfully');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$product) {
            $this->jsonError('Product not found.', 404, 'NOT_FOUND');
        }

        $newStatus = $product['is_active'] ? 0 : 1;
        $this->db->execute(
            "UPDATE products SET is_active = ? WHERE id = ? AND tenant_id = ?",
            [$newStatus, $id, $tenantId]
        );

        $product['is_active'] = $newStatus;
        $this->jsonSuccess($product, 'Product status updated successfully');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int)($params['id'] ?? 0);

        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$product) {
            $this->jsonError('Product not found.', 404, 'NOT_FOUND');
        }

        if (in_array($product['code'], ['cow', 'buffalo'], true)) {
            $this->jsonError('Default platform products (Cow & Buffalo) cannot be permanently deleted. You can deactivate them instead.', 400, 'CANNOT_DELETE_DEFAULT');
        }

        $activeSubCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM customer_subscriptions WHERE product_id = ? AND tenant_id = ? AND is_active = 1",
            [$id, $tenantId]
        );

        if ($activeSubCount > 0) {
            $this->jsonError("Cannot delete product: {$activeSubCount} active customer subscription(s) depend on it. Please reassign or deactivate the product.", 422, 'PRODUCT_IN_USE');
        }

        $this->db->execute("DELETE FROM products WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        $this->jsonSuccess(null, 'Product deleted successfully');
    }
}
