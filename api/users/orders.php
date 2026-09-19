<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

$user_id = getAuthenticatedUserId();   
$method  = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Extract an order_id from the URL if present
$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('orders.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$order_id = is_numeric($path) ? intval($path) : null;

if ($order_id) {
    // Single order — but ONLY if it belongs to this user
    $stmt = $conn->prepare("SELECT * FROM Orders WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        echo json_encode(["error" => "Order not found"]);
        exit;
    }

    $itemsStmt = $conn->prepare(
        "SELECT oi.product_id, p.product_name, oi.quantity, oi.unit_price
         FROM Order_Items oi
         JOIN Products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?"
    );
    $itemsStmt->bind_param("i", $order_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = $row;
    }

    $order['items'] = $items;
    echo json_encode($order);

} else {
    // All of THIS user's orders — never anyone else's
    $stmt = $conn->prepare("SELECT * FROM Orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode($orders);
}