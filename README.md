# DoodhDesk - Multi-Tenant SaaS Web Application

DoodhDesk is a multi-tenant, mobile-first SaaS web application designed for milkmen, dairy farmers, and dairy delivery businesses. It eradicates billing disputes with customers by tracking daily milk deliveries in real-time and excluding missed or paused days from monthly invoices.

---

## 1. Key Business & Architectural Features

### A. Multi-Tenancy Architecture
- **Single-Database Multi-Tenant Isolation:** All tenant-scoped tables (`customers`, `daily_deliveries`, `invoices`, `tenant_settings`, `tenant_additional_charges`, `tenant_subscriptions`, `customer_leaves`, `payments`) strictly incorporate a `tenant_id` column with composite indexing.
- Every read and write query enforces tenant boundary filtering via `Auth::tenantId()`, guaranteeing data isolation.

### B. SaaS Subscription Tiering (Global Config)
Managed via the `system_settings` table and editable in the Super-Admin dashboard:
- `free_customer_limit`: Up to **5 active customers** per milkman on the Free Tier.
- `paid_tier_unit_size`: **100 customers** per block.
- `paid_tier_unit_price`: **₹200.00** per block.
- `currency_symbol`: **₹**.
- **Customer Quota & Paywall Guard:** Checks `Total Active Customers < (Free Limit + Purchased Capacity)` before allowing customer creation or reactivation. Triggers an upgrade paywall modal when the quota is reached.

### C. Milkman Business Settings (Taxes & Surcharges)
Each milkman can configure their own dairy settings:
- **Tax Configuration:** Enable/disable tax, custom tax label (e.g., "GST", "VAT"), percentage (e.g., 5.00%), and GSTIN / VAT ID.
- **Custom Additional Charges (Surcharges):**
  - Multiple active charge rows (e.g., Monthly Delivery Fee, Glass Bottle Maintenance Fee).
  - Surcharge types: `flat_monthly`, `per_liter`, or `per_delivery_day`.
  - Taxable flag (included in the GST base).
- **Branding & UPI Payment Info:** Dairy name, owner name, contact phone, invoice footer note, and dynamic/uploaded UPI payment QR codes.

### D. Daily Delivery Tracker (Core Anti-Error Engine)
- Mobile-first daily run-sheet with date and shift selector (Morning 🌅 / Evening 🌆).
- Sunlight-contrast tap targets for fast action:
  - `[Delivered]` (Green)
  - `[Missed / Skipped]` (Red)
  - `[Customer On Leave]` (Auto-greyed out)
  - `[Quantity Edit]` (Quick adjustment for extra/less milk)
- **Zero-Charge Rule:** Missed or paused days are strictly logged with `0.00L` billed.
- **Pending Alert Banner:** Live notification banner alerting unverified houses before the shift closes.
- Instant, zero-reload AJAX toggling powered by HTMX and Alpine.js.

### E. Billing & Invoice Engine (Exact 4-Step Formula)
1. $\text{Base Milk Subtotal} = \sum (\text{Actual Delivered Liters} \times \text{Customer Rate})$
2. $\text{Additional Charges} = \sum (\text{Active Custom Charges based on flat/per-liter/per-day rules})$
3. $\text{Tax Amount} = (\text{Base Subtotal} + \text{Taxable Charges}) \times \left(\frac{\text{Tax \%}}{100}\right)$
4. $\text{Total Payable} = \text{Base Subtotal} + \text{Additional Charges} + \text{Tax Amount} + \text{Previous Balance} - \text{Payments Received}$

### F. Thermal & A4 Printing / PDF Export
- **80mm & 58mm Thermal Receipts:** POS receipt format with `@media print` CSS for Bluetooth/USB thermal printers.
- **Standard A4 Invoices:** Complete tax invoice layout with itemized delivery calendar, tax breakdown, GSTIN, and milkman UPI QR code.
- **Dompdf PDF Export:** Native on-demand PDF streaming (`?type=a4` or `?type=thermal80`).

---

## 2. Tech Stack
- **Backend:** PHP 8.2+ (`declare(strict_types=1);`, Enums, Readonly properties, Match expressions)
- **Database:** MySQL 8.0+ / MariaDB 10.5+ with foreign keys and composite indexes
- **Frontend:** Tailwind CSS, Alpine.js, HTMX
- **PDF & QR Code:** Dompdf 3.1+, Chillerlan PHP-QRCode (offline native base64/SVG QR generator)
- **Web Server:** Apache 2.4 with proxy_fcgi PHP-FPM

---

## 3. Demo Credentials & Quick Switcher

Access the application in your browser at:
**`http://localhost/doodhdesk/`**

| Portal | Role / Account | Login Credential | Password | Note |
|---|---|---|---|---|
| **Milkman Portal (Paid Tier)** | Krishna Pure Milk Dairy | `9876543210` or `ramesh@krishnadairy.com` | `password123` | Paid tier, 105 max capacity, 6 active customers, GST 5%, custom surcharges, generated bills |
| **Milkman Portal (Free Tier)** | Radha Fresh Cow Dairy | `9876500000` or `suresh@radhadairy.com` | `password123` | Free tier, 4/5 slots used (ready to test Quota Guard & Paywall) |
| **Super Admin Portal** | SaaS Platform Admin | `admin@doodhdesk.com` | `admin123` | Manages global SaaS subscription tiers and tenant quotas |

> **Note:** A 1-click **Quick Demo Switcher** bar is present at the bottom of the screen for instant previewing across all 3 roles without manual logins.

---

## 4. Running the Automated Test Suite

To verify data isolation, the zero-charge rule, quota guards, and the 4-step billing formula:

```bash
/usr/bin/php8.4 /var/www/html/doodhdesk/tests/test_suite.php
```
