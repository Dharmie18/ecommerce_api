<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

requireAdmin($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "user_id parameter is required"]);
    exit;
}

// Fetch user profile info
$uStmt = $conn->prepare("SELECT user_id, first_name, last_name, email, role, created_at FROM users WHERE user_id = ?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit;
}

// Fetch all orders for this user
$oStmt = $conn->prepare("SELECT o.*, p.payment_method, p.payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.user_id = ? ORDER BY o.order_date DESC");
$oStmt->bind_param("i", $user_id);
$oStmt->execute();
$ordersResult = $oStmt->get_result();

$orders = [];
$lifetimeSpend = 0;

while ($order = $ordersResult->fetch_assoc()) {
    $lifetimeSpend += floatval($order['total_amount']);
    
    // Fetch items for each order
    $itemStmt = $conn->prepare("SELECT oi.*, pr.product_name, pr.category_id FROM order_items oi JOIN products pr ON oi.product_id = pr.product_id WHERE oi.order_id = ?");
    $itemStmt->bind_param("i", $order['order_id']);
    $itemStmt->execute();
    $itemsRes = $itemStmt->get_result();
    
    $items = [];
    while ($item = $itemsRes->fetch_assoc()) {
        $items[] = $item;
    }
    $order['items'] = $items;
    $orders[] = $order;
}

echo json_encode([
    "user" => $user,
    "lifetime_spend" => $lifetimeSpend,
    "total_orders" => count($orders),
    "orders" => $orders
]);
