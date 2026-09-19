<?php
header('Content-Type: application/json');
require '../../config/db.php';

// Public GET endpoint for live platform metrics
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit;
}

try {
    // Orders counts and volume
    $orderQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END), 0) AS orders_delivered,
            COALESCE(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 0) AS total_sales_volume
        FROM orders
    ");
    $orderStats = $orderQuery ? $orderQuery->fetch_assoc() : ['total_orders' => 0, 'orders_delivered' => 0, 'total_sales_volume' => 0];

    // Products count
    $productQuery = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    $productStats = $productQuery ? $productQuery->fetch_assoc() : ['total_products' => 0];

    // Categories count
    $categoryQuery = $conn->query("SELECT COUNT(*) AS total_categories FROM categories");
    $categoryStats = $categoryQuery ? $categoryQuery->fetch_assoc() : ['total_categories' => 0];

    // Verified Subscribers
    $subQuery = $conn->query("SELECT COUNT(*) AS total_subscribers FROM newslettersubscribers WHERE status = 'active'");
    $subStats = $subQuery ? $subQuery->fetch_assoc() : ['total_subscribers' => 0];

    echo json_encode([
        "orders_delivered" => (int)$orderStats['orders_delivered'],
        "total_orders" => (int)$orderStats['total_orders'],
        "total_products" => (int)$productStats['total_products'],
        "total_categories" => (int)$categoryStats['total_categories'],
        "total_sales_volume" => (float)$orderStats['total_sales_volume'],
        "total_subscribers" => (int)$subStats['total_subscribers']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch platform metrics: " . $e->getMessage()]);
}
