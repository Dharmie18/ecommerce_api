<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

// Verify admin access
requireAdmin($conn);
ensureAllSchemas($conn);

// Fetch site settings
$settings = getSiteSettings($conn);

// Low stock products (quantity < 10)
$lowStock = [];
$lowStockResult = $conn->query("
    SELECT p.product_id, p.product_name, p.stock_quantity, p.price,
           COALESCE(c.category_name, 'Uncategorised') AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.stock_quantity < 10
    ORDER BY p.stock_quantity ASC
");
if ($lowStockResult) {
    while ($row = $lowStockResult->fetch_assoc()) {
        $row['price'] = (float)$row['price'];
        $row['stock_quantity'] = (int)$row['stock_quantity'];
        $lowStock[] = $row;
    }
}

// Top 10 customers by lifetime spend
$topCustomers = [];
$topCustResult = $conn->query("
    SELECT u.user_id, u.first_name, u.last_name, u.email,
           COUNT(o.order_id) AS orders_placed,
           COALESCE(SUM(o.total_amount), 0) AS lifetime_value
    FROM users u
    LEFT JOIN orders o ON u.user_id = o.user_id
    WHERE u.role = 'customer'
    GROUP BY u.user_id
    ORDER BY lifetime_value DESC
    LIMIT 10
");
if ($topCustResult) {
    while ($row = $topCustResult->fetch_assoc()) {
        $row['orders_placed'] = (int)$row['orders_placed'];
        $row['lifetime_value'] = (float)$row['lifetime_value'];
        $topCustomers[] = $row;
    }
}

// Monthly sales for the last 12 months
$monthlySales = [];
$salesResult = $conn->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m') AS month,
           COALESCE(SUM(total_amount), 0) AS total_sales,
           COUNT(*) AS order_count
    FROM orders
    WHERE order_status != 'Cancelled'
      AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month DESC
");
if ($salesResult) {
    while ($row = $salesResult->fetch_assoc()) {
        $row['total_sales'] = (float)$row['total_sales'];
        $row['order_count'] = (int)$row['order_count'];
        $monthlySales[] = $row;
    }
}

echo json_encode([
    'settings' => $settings,
    'reports' => [
        'low_stock'      => $lowStock,
        'top_customers'  => $topCustomers,
        'monthly_sales'  => $monthlySales,
    ]
]);
