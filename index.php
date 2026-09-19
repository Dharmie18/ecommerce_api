<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
echo json_encode([
    "status" => "online",
    "service" => "ShopIt REST API",
    "version" => "1.0.0",
    "message" => "ShopIt backend is operational",
    "endpoints" => [
        "products" => "/api/products/products.php",
        "categories" => "/api/categories/categories.php",
        "stats" => "/api/stats/overview.php",
        "users" => "/api/users/profile.php",
        "orders" => "/api/users/orders.php"
    ]
]);
