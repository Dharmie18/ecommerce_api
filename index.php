<?php
// Public API health check and endpoint directory
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/ensure_schema.php';

$settings = getSiteSettings($conn);
$brandName = $settings['company_name'] ?? 'ShopIt';

echo json_encode([
    "status" => "online",
    "service" => "{$brandName} REST API",
    "version" => "2.0.0",
    "message" => "{$brandName} backend is operational and ready to serve requests",
    "endpoints" => [
        "storefront_init" => "/api/storefront/init.php",
        "admin_init"      => "/api/admin/init.php",
        "admin_catalog"   => "/api/admin/catalog.php",
        "admin_settings"  => "/api/admin/settings.php",
        "users_dashboard" => "/api/users/dashboard.php",
        "products"        => "/api/products/products.php",
        "categories"      => "/api/categories/categories.php",
        "stats"           => "/api/stats/overview.php",
        "cart"            => "/api/cart/cart.php",
        "checkout"        => "/api/checkout/checkout.php",
        "newsletter"      => "/api/newsletter/subscribe.php",
        "referrals"       => "/api/referrals/my-referrals.php",
        "users_login"     => "/api/users/login.php",
        "users_register"  => "/api/users/register.php",
        "users_profile"   => "/api/users/profile.php",
        "users_orders"    => "/api/users/orders.php",
        "admin_migrate"   => "/api/admin/migrate.php"
    ]
]);
