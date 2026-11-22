# ShopIt - REST API Backend Engine

> **Production PHP 8.x + MySQL / TiDB Cloud REST API Engine**  
> Native stateless backend providing JWT-secured commerce services for the ShopIt Storefront and Admin Control Room. Zero hardcoded mock data.

---

## System Architecture

- **Base URL (Local)**: `http://localhost/ecommerce-api`
- **Base URL (Production)**: `https://ecommerce-api-eibj.onrender.com`
- **Server Runtime**: Apache 2.4+ & PHP 8.x (via XAMPP / Docker on Render)
- **Database**: MySQL 8.x / TiDB Cloud Distributed SQL (Database: `ecommerce` / `ecommerce_db`)
- **Authentication**: Stateless JSON Web Tokens (`Authorization: Bearer <token>`)
- **Dynamic White-Labeling**: Centralized `site_settings` table managed via Admin API; zero hardcoded frontend or backend fallback strings.
- **Auto-Schema Migration**: Idempotent table, column, and seed synchronization via `config/ensure_schema.php`.

---

## Directory Layout

```
ecommerce-api/
├── api/
│   ├── admin/             # Operations & bootstrap endpoints (Role: admin)
│   │   ├── init.php       # Unified admin bootstrap (settings + reports in 1 call)
│   │   ├── settings.php   # Dynamic settings read & write (PUT / GET)
│   │   ├── product.php    # Admin product CRUD & inventory updates
│   │   ├── orders.php     # Order fulfillment queue & status updates
│   │   ├── payments.php   # Payment audit logs & manual overrides
│   │   ├── users.php      # Customer & staff administration
│   │   └── migrate.php    # Database seeder (categories, products, customers, orders)
│   ├── redirect/          # HTTP 302 Redirection services
│   │   ├── privacy.php    # Redirects to configured Privacy Policy URL
│   │   └── delivery-returns.php # Redirects to Delivery & Returns URL
│   ├── storefront/        # Unified storefront bootstrap
│   │   └── init.php       # Fast bootstrap: settings + categories + products + stats + past orders
│   ├── cart/              # User shopping bag synchronization
│   ├── categories/        # Public product taxonomy (14 categories)
│   ├── checkout/          # Atomic checkout & virtual bank accounts
│   ├── coupons/           # Promotional & referral coupon validation
│   ├── newsletter/        # Trade newsletter subscribers
│   ├── products/          # Public inventory catalog with supply intake dates
│   ├── referrals/         # Growth engine & referral attribution
│   ├── stats/             # Live platform metrics & delivered order counts
│   └── users/             # Auth, verification, OTP password reset & order history
├── config/
│   ├── db.php             # MySQL & TiDB Cloud SSL connection with CORS
│   ├── jwt.php            # HMAC-SHA256 JWT generation & admin guards
│   ├── ensure_schema.php  # Automated idempotent database schema validator
│   └── mailer.php         # Socket-based SMTP & HTML receipt dispatcher
├── index.php              # Health check & service discovery endpoint
└── README.md
```

---

## Unified Bootstrap & Discovery Endpoints

### 1. `GET /api/storefront/init.php`
Fetches the entire storefront bootstrap state in **one network round-trip**:
- `settings`: Active company branding, currency, tax rates, support numbers, office address, and policy URLs from `site_settings`.
- `categories`: Full taxonomy across 14 industry sectors.
- `products`: Catalog items with pricing, description, stock quantities, and `created_at` (date added to supply).
- `stats`: Verified platform KPIs (delivered orders, total SKUs, active categories, subscribers, sales volume).
- `recent_dispatches`: Real past orders from the database (order number, destination city/state, item summary, order date) powering the Storefront Hero 3D Fulfillment Matrix.

### 2. `GET /api/admin/init.php`
Fetches all operational management data in one call:
- `settings`: All site configuration parameters.
- `reports`: Low stock alerts (`stock < 10`), top 10 customers by lifetime spend, and monthly sales volume.

---

## Complete API Endpoints Reference

### Authentication & Customer Accounts (`api/users/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `POST` | `/api/users/register.php` | No | User registration with referral attribution and welcome coupon. |
| `POST` | `/api/users/login.php` | No | Authenticate user, issue JWT Bearer token, dispatch login alert. |
| `GET` | `/api/users/profile.php` | Bearer | Retrieve authenticated user profile. |
| `PUT` | `/api/users/profile.php` | Bearer | Update user profile and shipping details. |
| `POST` | `/api/users/verify-email.php` | No | Confirm email address with token; auto-issues login token. |
| `POST` | `/api/users/forgot-password.php` | No | Generate 6-digit OTP and send via email. |
| `POST` | `/api/users/reset-password.php` | No | Validate OTP and hash new password using `PASSWORD_BCRYPT`. |
| `GET` | `/api/users/orders.php` | Bearer | Retrieve customer's personal order history and tracking statuses. |

### Catalog & Discovery (`api/products/`, `api/categories/`, `api/stats/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/products/products.php` | No | Fetch all active catalog products including `created_at` supply intake dates. |
| `GET` | `/api/products/products.php/{id}` | No | Fetch single product specification by ID. |
| `GET` | `/api/categories/categories.php` | No | List all 14 product categories. |
| `GET` | `/api/stats/overview.php` | No | Live platform overview KPIs. |

### Cart & Orders (`api/cart/`, `api/checkout/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/cart/cart.php` | Bearer | Retrieve saved shopping cart lines with inventory levels. |
| `POST` | `/api/cart/cart.php` | Bearer | Sync cart with database (`replace` or `merge` mode). |
| `DELETE` | `/api/cart/cart.php` | Bearer | Remove item or empty shopping cart. |
| `POST` | `/api/checkout/checkout.php` | Bearer | Atomic checkout transaction: verify stock, create order, deduct stock, redeem coupon, record payment, empty cart, and send receipt. |
| `POST` | `/api/checkout/virtual-account.php` | Bearer | Generate 30-minute dynamic virtual bank account (Providus, Wema, Sterling). |

### Referrals & Growth (`api/referrals/`, `api/coupons/`, `api/newsletter/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/referrals/my-referrals.php` | Bearer | Customer referral code, link, referred user tree, and active coupons. |
| `POST` | `/api/coupons/validate.php` | Bearer | Validate coupon ownership, minimum item threshold, and expiration. |
| `POST` | `/api/newsletter/subscribe.php` | No | Subscribe trade email to active distribution list. |

### Admin Operations (`api/admin/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/admin/init.php` | Admin | One-call dashboard bootstrap: settings + low stock + top customers + monthly sales. |
| `GET` | `/api/admin/settings.php` | Admin | Read all site settings. |
| `PUT` | `/api/admin/settings.php` | Admin | Persist updated settings (company info, contacts, legal URLs, socials). |
| `GET` / `POST` / `PUT` / `DELETE` | `/api/admin/product.php` | Admin | Complete product CRUD with pricing and inventory control. |
| `GET` / `PUT` | `/api/admin/orders.php` | Admin | View orders queue and update statuses (`Pending` $\to$ `Shipped` $\to$ `Delivered`). |
| `GET` / `PUT` | `/api/admin/payments.php` | Admin | Audit payment transactions and override statuses. |
| `GET` / `DELETE` | `/api/admin/users.php` | Admin | Manage user accounts and roles. |
| `GET` | `/api/admin/migrate.php` | Admin | Comprehensive database seeding script. |

### Redirection Services (`api/redirect/`)
| Method | Endpoint | Auth | Description |
|:---|:---|:---:|:---|
| `GET` | `/api/redirect/privacy.php` | No | HTTP 302 redirect to active Privacy Policy URL configured in Admin Settings. |
| `GET` | `/api/redirect/delivery-returns.php` | No | HTTP 302 redirect to active Delivery & Returns URL configured in Admin Settings. |

---

## Local Development & Testing

```powershell
# Check PHP syntax across all endpoints
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l api/storefront/init.php
C:\xampp\php\php.exe -l api/admin/init.php
C:\xampp\php\php.exe -l api/admin/migrate.php
```
