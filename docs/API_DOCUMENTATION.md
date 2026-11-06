# DoodhDesk REST API Documentation

Comprehensive enterprise API reference for **DoodhDesk** — Modern Multi-Tenant Milk & Daily Essentials Delivery ERP.

---

## 1. Quick Access & Swagger UI

| Resource | URL | Description |
|---|---|---|
| **Interactive Swagger UI (Direct)** | [http://localhost/doodhdesk/api/docs](http://localhost/doodhdesk/api/docs) | Browser-based interactive API console with "Try it out" and Bearer Token persistence |
| **Interactive Swagger UI (Docs folder)** | [http://localhost/doodhdesk/docs/](http://localhost/doodhdesk/docs/) | Static directory presentation in `docs/` |
| **Interactive Swagger UI (Doc alias)** | [http://localhost/doodhdesk/doc/](http://localhost/doodhdesk/doc/) | Direct symlink in `doc/` |
| **OpenAPI 3.0 Specification (JSON)** | [http://localhost/doodhdesk/docs/openapi.json](http://localhost/doodhdesk/docs/openapi.json) | Full OpenAPI 3.0 machine-readable schema (160+ KB) |
| **Application Web App** | [http://localhost/doodhdesk/index.php/dashboard](http://localhost/doodhdesk/index.php/dashboard) | Main DoodhDesk web portal |

---

## 2. Architecture & Design Principles

```
┌────────────────────────────────────────────────────────────────────────┐
│                        API Clients & Frontends                         │
│             (Mobile Delivery App / Web SPA / Swagger UI / ERP)         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP Requests with Bearer Token
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                     Routing & Gateway Security Layer                   │
│          api/.htaccess  ──►  routes_api.php  ──►  Auth Middleware       │
├────────────────────────────────────────────────────────────────────────┤
│ • Bearer Token Extractor (`Authorization: Bearer <token>`)             │
│ • Database Token Validator (`api_tokens` table)                        │
│ • Session Fallback (`$_SESSION['user_id']`)                            │
│ • Tenant Context Injector (`tenant_id`)                                │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
       ┌────────────────────────────┼────────────────────────────┐
       ▼                            ▼                            ▼
┌──────────────┐             ┌──────────────┐             ┌──────────────┐
│  ApiBase     │             │ QuotaGuard   │             │ Tenant       │
│  Controller  │             │ Middleware   │             │ Isolation    │
│  (JSON Resp) │             │ (5 Cust Cap) │             │ (WHERE t_id) │
└──────┬───────┘             └──────┬───────┘             └──────┬───────┘
       │                            │                            │
       └────────────────────────────┼────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                          Core REST API Modules                         │
├───────────────────┬───────────────────┬────────────────────────────────┤
│ 1. Auth & Me      │ 5. Leaves/Vacation│ 9. Settings, Tax & Charges     │
│ 2. Dashboard KPIs │ 6. Deliveries Run │ 10. Subscription & Quota       │
│ 3. Product Catalog│ 7. Billing Engine │ 11. Super Admin Platform       │
│ 4. Customers      │ 8. Payments/Ledger│                                │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       MySQL 8 Relational Database                      │
│   (customers, products, deliveries, invoices, tenant_subscriptions...) │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Authentication & Security

DoodhDesk API uses dual-mode authentication:
1. **Bearer Token Authentication** (Standard for Mobile Apps & external integrations):
   Include header in all authorized requests:
   ```http
   Authorization: Bearer <token>
   ```
2. **Web Session Fallback** (Seamless for internal browser calls sharing the login session).

### Acquiring a Bearer Token
#### Tenant Login:
```bash
curl -X POST http://localhost/doodhdesk/index.php/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@krushnadairy.com",
    "password": "password123"
  }'
```
**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "a1b2c3d4e5f6...",
    "user": {
      "id": 1,
      "name": "Krushna Admin",
      "email": "admin@krushnadairy.com",
      "role": "tenant_admin",
      "tenant_id": 1,
      "tenant_name": "Krushna Dairy Farm",
      "status": "active"
    }
  },
  "timestamp": "2026-11-06T18:00:00+05:30"
}
```

#### Super Admin Login:
```bash
curl -X POST http://localhost/doodhdesk/index.php/api/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@dairyflow.com",
    "password": "admin123"
  }'
```

---

## 4. Standard Response Envelope

Every endpoint returns a unified JSON format:

### Success Response:
```json
{
  "success": true,
  "message": "Operation description",
  "data": { ... },
  "meta": { ... },
  "timestamp": "2026-11-06T18:00:00+05:30"
}
```

### Error Response:
```json
{
  "success": false,
  "error": "Customer limit reached for your current subscription plan.",
  "code": "QUOTA_EXCEEDED",
  "errors": {
    "quota": {
      "max_customers": 5,
      "current_active_customers": 5
    }
  },
  "timestamp": "2026-11-06T18:00:00+05:30"
}
```

---

## 5. API Modules & Endpoints Summary (42 Endpoints)

### 1. Authentication
- `POST /api/auth/login` — Tenant user login & Bearer token generation
- `POST /api/auth/register` — Self-serve registration for new dairies (seeds default Free 5-customer tier)
- `GET /api/auth/me` — Authenticated identity, tenant profile, and active quota
- `POST /api/auth/logout` — Revoke active token & destroy session
- `POST /api/admin/auth/login` — Platform Super Admin login

### 2. Operational Dashboard
- `GET /api/dashboard/stats` — Real-time metrics: morning/evening shift deliveries, liters distributed, active customer counts, month-to-date billed amount, and payment collections

### 3. Products & Pricing
- `GET /api/products` — List all products (query: `?active_only=1`)
- `POST /api/products` — Create new milk/grocery product
- `GET /api/products/{id}` — Get product details
- `PUT /api/products/{id}` — Update product name, default rate, unit, sort order
- `POST /api/products/{id}/toggle` — Toggle active/inactive status
- `DELETE /api/products/{id}` — Deactivate / remove product

### 4. Customers & Subscriptions
- `GET /api/customers` — Search & list customers with assigned milk subscriptions and quota metrics (`?q=...&route=...&status=...&milk_type=...`)
- `POST /api/customers` — Create customer with multi-product subscription quantities & custom rates (Enforced by Quota Guard)
- `GET /api/customers/{id}` — Full customer profile with active subscriptions, vacation leaves, and recent invoices
- `PUT /api/customers/{id}` — Update customer details and product subscriptions
- `POST /api/customers/{id}/status` — Activate or suspend customer
- `GET /api/customers/routes` — Get distinct delivery route names

### 5. Customer Vacation Leaves (Anti-Dispute Engine)
- `GET /api/customers/{id}/leaves` — List planned customer delivery pauses
- `POST /api/customers/{id}/leaves` — Schedule pause period (`start_date`, `end_date`, `reason`)
- `DELETE /api/customers/{id}/leaves/{leave_id}` — Cancel vacation pause

### 6. Deliveries & Run-Sheet
- `GET /api/deliveries` — Daily delivery run-sheet (`?date=YYYY-MM-DD&shift=morning|evening|both&route=...`)
- `POST /api/deliveries/toggle` — Toggle delivery status between pending and delivered
- `POST /api/deliveries/edit-quantity` — Adjust delivered quantity for specific shift
- `POST /api/deliveries/bulk-deliver` — 1-Click mark all pending shift deliveries as delivered
- `GET /api/deliveries/summary` — Total liters dispatched and shift completion stats

### 7. Monthly Invoicing & Billing
- `GET /api/invoices` — List invoices (`?month=10&year=2026&status=unpaid|paid|partial`)
- `GET /api/invoices/{id}` — Invoice breakdown with daily delivery audit items and tax charges
- `POST /api/invoices/generate-batch` — Run billing cycle generation for all customers
- `POST /api/invoices/generate-single` — Generate/recalculate invoice for individual customer
- `GET /api/invoices/{id}/share` — Generates WhatsApp share link with prefilled payment message and public bill URL

### 8. Payments & Customer Ledger
- `GET /api/invoices/{id}/payments` — List payments received against an invoice
- `POST /api/payments` — Record cash or UPI payment (`invoice_id`, `amount`, `payment_mode`, `reference_number`)
- `GET /api/customers/{id}/ledger` — Complete financial statement of all debits (invoices), credits (payments), and balance

### 9. Settings, Taxes & Charges
- `GET /api/settings` — Profile, GST settings, UPI QR configuration, and recurring charges
- `PUT /api/settings/profile` — Update dairy business name, phone, address, and tagline
- `PUT /api/settings/taxes` — Configure GST percentage and GSTIN
- `PUT /api/settings/upi` — Configure UPI VPA (e.g., `dairy@okhdfcbank`)
- `GET /api/settings/charges` — List additional fixed/monthly fees (e.g., delivery fee)
- `POST /api/settings/charges` — Add additional recurring fee
- `POST /api/settings/charges/{id}/toggle` — Toggle charge active status
- `DELETE /api/settings/charges/{id}` — Remove recurring charge

### 10. Subscription & Quota Management
- `GET /api/subscription` — Current customer capacity utilization, plan details, and upgrade pricing
- `POST /api/subscription/upgrade` — Self-serve upgrade of customer capacity limit (`requested_capacity`)

### 11. Super Admin Platform Governance
- `GET /api/admin/stats` — Platform-wide metrics (total dairies, active tenants, total system billing)
- `GET /api/admin/settings` — Global SaaS parameters (free tier allowance, per-customer rate)
- `PUT /api/admin/settings` — Update platform pricing and limits
- `GET /api/admin/tenants` — Roster of all dairy businesses with customer counts
- `POST /api/admin/tenants/{id}/toggle-status` — Suspend or reactivate dairy tenant
- `POST /api/admin/tenants/{id}/quota` — Administrative override of customer quota capacity

---

## 6. Testing & Verification

The API is covered by automated unit and integration tests:

```bash
# Run API automated test suite (12 test groups)
php8.4 tests/api_test_suite.php

# Run full system test suite (13 test groups)
php8.4 tests/test_suite.php
```

All 25 test groups execute with zero errors and 100% pass rate.
