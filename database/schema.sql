-- DairyFlow Multi-Tenant SaaS Database Schema
-- Strict Types & Composite Multi-Tenant Indexing

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS daily_deliveries;
DROP TABLE IF EXISTS customer_leaves;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS tenant_subscriptions;
DROP TABLE IF EXISTS tenant_additional_charges;
DROP TABLE IF EXISTS tenant_settings;
DROP TABLE IF EXISTS tenants;
DROP TABLE IF EXISTS super_admins;
DROP TABLE IF EXISTS system_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Global Platform Settings (Managed by Super-Admin)
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Super Administrators
CREATE TABLE super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tenants (Milkmen / Dairies)
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Milkman Business Settings (Taxes, Branding, Invoice Info)
CREATE TABLE tenant_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    tax_enabled TINYINT(1) DEFAULT 0,
    tax_label VARCHAR(50) DEFAULT 'GST',
    tax_percentage DECIMAL(5, 2) DEFAULT 0.00,
    tax_number VARCHAR(100) NULL,
    upi_id VARCHAR(100) NULL,
    upi_qr_path VARCHAR(255) NULL,
    invoice_footer_note TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_setting (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Milkman Custom Additional Charges
CREATE TABLE tenant_additional_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    charge_name VARCHAR(100) NOT NULL,
    charge_type ENUM('flat_monthly', 'per_liter', 'per_delivery_day') DEFAULT 'flat_monthly',
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_taxable TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_charges (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tenant Subscriptions (Quota limits)
CREATE TABLE tenant_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    purchased_blocks INT DEFAULT 0,
    max_customers_allowed INT DEFAULT 5,
    amount_paid DECIMAL(10, 2) DEFAULT 0.00,
    valid_until DATE NULL,
    payment_status ENUM('active', 'expired', 'free') DEFAULT 'free',
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_sub (tenant_id, payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Customers
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    route_name VARCHAR(50) DEFAULT 'Main Route',
    default_quantity DECIMAL(6, 2) NOT NULL DEFAULT 1.00,
    rate_per_unit DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_customer (tenant_id, status),
    INDEX idx_tenant_route (tenant_id, route_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Customer Leaves / Vacations
CREATE TABLE customer_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_tenant_customer_leaves (tenant_id, customer_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Daily Delivery Tracking (Core Anti-Error Engine)
CREATE TABLE daily_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    shift ENUM('morning', 'evening') DEFAULT 'morning',
    quantity DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    status ENUM('delivered', 'skipped', 'leave') NOT NULL,
    notes VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_delivery (tenant_id, customer_id, delivery_date, shift),
    INDEX idx_delivery_date (tenant_id, delivery_date),
    INDEX idx_delivery_shift (tenant_id, delivery_date, shift)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Invoices
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    billing_month TINYINT NOT NULL,
    billing_year SMALLINT NOT NULL,
    total_units DECIMAL(10, 2) NOT NULL,
    milk_subtotal DECIMAL(10, 2) NOT NULL,
    additional_charges_total DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    previous_balance DECIMAL(10, 2) DEFAULT 0.00,
    final_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
    breakdown_json JSON NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_customer_billing_period (tenant_id, customer_id, billing_year, billing_month),
    INDEX idx_tenant_invoice_period (tenant_id, billing_year, billing_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payments Received (Customer Ledger)
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'upi', 'bank_transfer', 'cheque', 'other') DEFAULT 'upi',
    reference_no VARCHAR(100) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    INDEX idx_tenant_customer_payments (tenant_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
