<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

requireAdmin($conn); 

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('reports.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$reportType = $path;

if ($reportType === 'low-stock') {
    // Products with fewer than 10 in stock
    $result = $conn->query("SELECT * FROM products WHERE stock_quantity < 10");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode($products);

} elseif ($reportType === 'top-customers') {
    $sql = "SELECT u.user_id, u.first_name, u.last_name, 
                   COUNT(o.order_id) AS orders_placed, 
                   SUM(o.total_amount) AS lifetime_value
            FROM users u
            JOIN orders o ON u.user_id = o.user_id
            GROUP BY u.user_id
            ORDER BY lifetime_value DESC
            LIMIT 5";
    $result = $conn->query($sql);
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    echo json_encode($customers);

} elseif ($reportType === 'monthly-sales') {
    $sql = "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, 
                   SUM(total_amount) AS total_sales
            FROM orders
            WHERE order_status != 'Cancelled'
            GROUP BY month
            ORDER BY month DESC";
    $result = $conn->query($sql);
    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    echo json_encode($sales);

} else {
    http_response_code(400);
    echo json_encode(["error" => "Unknown report type. Use: low-stock, top-customers, or monthly-sales"]);
}