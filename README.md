# ShopIt — REST API Backend

> **Production PHP 8.x + MySQL REST API Engine**  
> Native stateless backend providing JWT-secured commerce services for the ShopIt Storefront and Admin Control Room. Zero mock data.

---

## Architecture & Overview

- **Base URL**: `http://localhost/ecommerce-api`
- **Server Runtime**: Apache 2.4+ & PHP 8.x (via XAMPP)
- **Database**: MySQL / MariaDB (Database: `ecommerce` or `ecommerce_db`)
- **Authentication**: Stateless JSON Web Tokens (`Authorization: Bearer <token>`)
- **Schema Auto-Migration**: Automated table and column verification in `config/ensure_schema.php`

---

## Directory Layout

```
ecommerce-api/
├── api/
│   ├── admin/             # Operations management endpoints (Role: admin)
│   ├── cart/              # User shopping cart synchronization
│   ├── categories/        # Public product taxonomy
│   ├── checkout/          # Orders, payments & dynamic virtual accounts
│   ├── coupons/           # Referral & promotional coupon validation
│   ├── newsletter/        # Trade subscriber management
│   ├── products/          # Public inventory catalog
│   ├── referrals/         # Growth engine & referral attribution
│   ├── stats/             # Public live platform metrics
│   └── users/             # Authentication, verification, passwords & orders
├── config/
│   ├── db.php             # MySQL database connection & CORS headers
│   ├── jwt.php            # JWT generation, signature verification & admin guard
│   ├── ensure_schema.php  # Idempotent database schema validator
│   └── mailer.php         # Socket-based SMTP & HTML receipt dispatcher
├── index.php              # Health check & service discovery endpoint
└── ecommerce_schema_data.sql # Initial database structure and seed data
```

---

## Complete Grouped API Reference

The 27 API endpoints are categorized into **6 core business domains**:

### 1. Authentication & Identity (`api/users/`)

| Method | Endpoint | Auth Required | Description |
|:---|:---|:---:|:---|
| `POST` | `/api/users/register.php` | No | Register new user. Automatically generates a unique referral code, awards a 20% welcome coupon, and dispatches an activation email. |
| `POST` | `/api/users/login.php` | No | Authenticate user credentials, issue JWT Bearer token, and dispatch login security notification email. |
| `GET` | `/api/users/profile.php` | Yes (Bearer) | Retrieve profile of the currently authenticated user (`user_id`, `name`, `email`, `role`, `is_verified`). |
| `PUT` | `/api/users/profile.php` | Yes (Bearer) | Update profile details (first name, last name, phone, etc.). |
| `POST` | `/api/users/verify-email.php` | No | Confirm email address using 32-byte cryptographic token. Auto-issues login JWT upon confirmation. |
| `POST` | `/api/users/resend-verification.php` | No | Re-issue a fresh email verification link. |
| `POST` | `/api/users/forgot-password.php` | No | Generate a secure 6-digit OTP code and dispatch it to user's registered email. |
| `POST` | `/api/users/verify-reset-code.php` | No | Verify 6-digit OTP code prior to allowing password reset. |
| `POST` | `/api/users/reset-password.php` | No | Validate OTP, hash new password using `PASSWORD_BCRYPT`, update user, and invalidate OTP. |

---

### 2. Public Storefront & Catalog (`api/products/`, `api/categories/`, `api/stats/`)

| Method | Endpoint | Auth Required | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/products/products.php` | No | Fetch all active products including stock quantity, unit prices in Naira (`₦`), and category mappings. |
| `GET` | `/api/products/products.php/{id}` | No | Fetch detailed information for a single product by numeric ID. |
| `GET` | `/api/categories/categories.php` | No | Fetch all product categories and taxonomies. |
| `GET` | `/api/categories/categories.php/{id}` | No | Fetch a single category by numeric ID. |
| `GET` | `/api/stats/overview.php` | No | Real-time aggregate platform stats: delivered orders, live SKUs, active categories, total trade subscribers, and gross sales volume. |

---

### 3. Shopping Cart & Checkout (`api/cart/`, `api/checkout/`)

| Method | Endpoint | Auth Required | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/cart/cart.php` | Yes (Bearer) | Retrieve user's saved shopping cart line items with product details and current inventory levels. |
| `POST` | `/api/cart/cart.php` | Yes (Bearer) | Synchronize cart items with database in either `replace` or `merge` mode. |
| `DELETE` | `/api/cart/cart.php` | Yes (Bearer) | Remove single item (`?product_id={id}`) or clear entire cart when no ID is provided. |
| `POST` | `/api/checkout/checkout.php` | Yes (Bearer) | Atomic checkout: verifies stock, creates order, deducts inventory, redeems coupon, records payment, empties cart, and sends HTML receipt email. |
| `POST` | `/api/checkout/virtual-account.php` | Yes (Bearer) | Generates a 30-minute dynamic 10-digit virtual bank account (Providus, Wema, Sterling) for direct bank transfer settlement. |

---

### 4. Growth, Referrals & Coupons (`api/referrals/`, `api/coupons/`, `api/newsletter/`)

| Method | Endpoint | Auth Required | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/referrals/my-referrals.php` | Yes (Bearer) | Returns user's referral code, shareable link, referred users list (with masked emails), and active coupons with live expiration countdowns. |
| `POST` | `/api/coupons/validate.php` | Yes (Bearer) | Validates coupon code, verifies ownership, minimum items requirement (default 3 items), and expiration date before applying discount. |
| `POST` | `/api/newsletter/subscribe.php` | No | Subscribes an email address to trade newsletter dispatches. Prevents duplicate registrations. |

---

### 5. Customer Orders (`api/users/orders.php`)

| Method | Endpoint | Auth Required | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/users/orders.php` | Yes (Bearer) | Fetch chronological order history for the logged-in customer, complete with line items and fulfillment status. |
| `GET` | `/api/users/orders.php/{id}` | Yes (Bearer) | Fetch full invoice details and line items for a specific order owned by the user. |

---

### 6. Admin Control Room (`api/admin/`)
*All endpoints in this group require `Authorization: Bearer <token>` and `role: admin`.*

| Method | Endpoint | Description |
|:---|:---|:---|
| `POST` | `/api/admin/product.php` | Create a new catalog product (SKU, title, price, stock, category). |
| `PUT` | `/api/admin/product.php/{id}` | Update existing product details, inventory stock levels, or pricing. |
| `DELETE` | `/api/admin/product.php/{id}` | Remove a product from the catalog. |
| `POST` | `/api/admin/category.php` | Create a new product category. |
| `PUT` | `/api/admin/category.php/{id}` | Rename or update an existing category. |
| `DELETE` | `/api/admin/category.php/{id}` | Delete a category. |
| `GET` | `/api/admin/orders.php` | List all customer orders across the platform with customer details. |
| `PUT` | `/api/admin/orders.php/{id}` | Update fulfillment status (`Pending` $\to$ `Shipped` $\to$ `Delivered` $\to$ `Cancelled`). |
| `GET` | `/api/admin/user-orders.php?user_id={id}` | Retrieve complete order history for a specific customer. |
| `GET` | `/api/admin/payments.php` | Audit all payment transactions, gateways, and settlements. |
| `PUT` | `/api/admin/payments.php/{id}` | Update payment transaction status (`Pending`, `Completed`, `Failed`). |
| `GET` | `/api/admin/users.php` | List all registered user accounts with roles and registration dates. |
| `PUT` | `/api/admin/users.php/{id}` | Update user roles (e.g., promote customer to `admin`). |
| `DELETE` | `/api/admin/users.php/{id}` | Delete a customer account (Root superadmin account is shielded). |
| `GET` | `/api/admin/subscribers.php` | List all active trade newsletter subscribers with registration dates. |
| `DELETE` | `/api/admin/subscribers.php/{id}` | Unsubscribe or remove a newsletter subscriber. |
| `GET` | `/api/admin/reports.php/monthly-sales` | Monthly revenue aggregate report. |
| `GET` | `/api/admin/reports.php/top-customers` | Leaderboard of top 5 customers by lifetime order spend. |
| `GET` | `/api/admin/reports.php/low-stock` | Inventory alert list of all items with stock fewer than 10 units. |
| `GET` | `/api/admin/migrate.php` | Database schema self-healing & maintenance migration script. |

---

## Authentication & Authorization Header

Protected endpoints require the standard HTTP Bearer token header:

```http
Authorization: Bearer <your_jwt_token_here>
```

Tokens are signed using `HMAC-SHA256` in `config/jwt.php` and contain the user payload:
```json
{
  "user_id": 1,
  "email": "admin@shopit.ng",
  "role": "admin",
  "exp": 1740000000
}
```

---

## Health Check Endpoint

```http
GET http://localhost/ecommerce-api/index.php
```

Response:
```json
{
  "status": "online",
  "service": "ShopIt REST API",
  "version": "1.0.0",
  "message": "ShopIt backend is operational",
  "endpoints": {
    "products": "/api/products/products.php",
    "categories": "/api/categories/categories.php",
    "stats": "/api/stats/overview.php",
    "users": "/api/users/profile.php",
    "orders": "/api/users/orders.php"
  }
}
```
