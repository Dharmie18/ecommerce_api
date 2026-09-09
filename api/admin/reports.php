<?php
header('Content-Type: application/json');
require '../../config/db.php';

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('reports.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$reportType = $path;

if ($reportType === 'low-stock') {
    // Products with fewer than 10 in stock — uses WHERE, which you already know
    $result = $conn->query("SELECT * FROM Products WHERE stock_quantity < 10");
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode($products);

} elseif ($reportType === 'top-customers') {
    // NOTE: This uses a JOIN, which combines matching rows from two tables (Users + Orders).
    // You haven't covered this yet, but it's necessary to connect "which user" to "how much they spent."
    $sql = "SELECT u.user_id, u.first_name, u.last_name, 
                   COUNT(o.order_id) AS orders_placed, 
                   SUM(o.total_amount) AS lifetime_value
            FROM Users u
            JOIN Orders o ON u.user_id = o.user_id
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
    // Groups total sales by month, using DATE_FORMAT — similar to the DAY()/DAYOFYEAR() you've already used
    $sql = "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, 
                   SUM(total_amount) AS total_sales
            FROM Orders
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