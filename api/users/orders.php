<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

ensureOrdersSchema($conn);
ensureOrderItemsSchema($conn);
ensurePaymentsSchema($conn);

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
    // Single order — for authenticated user
    $stmt = $conn->prepare("SELECT o.*, COALESCE(p.payment_method, '') AS payment_method, COALESCE(p.payment_status, 'Completed') AS payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.order_id = ? AND o.user_id = ?");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
    }
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        echo json_encode(["error" => "Order not found"]);
        exit;
    }

    $itemsStmt = $conn->prepare(
        "SELECT oi.product_id, COALESCE(p.product_name, 'Product Item') AS product_name, oi.quantity, oi.unit_price
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?"
    );
    if ($itemsStmt) {
        $itemsStmt->bind_param("i", $order_id);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();

        $items = [];
        while ($row = $itemsResult->fetch_assoc()) {
            $items[] = $row;
        }
        $order['items'] = $items;
    } else {
        $order['items'] = [];
    }

    $order['order_status'] = $order['order_status'] ?? $order['status'] ?? 'Pending';
    $order['order_date'] = $order['order_date'] ?? $order['created_at'] ?? date('Y-m-d H:i:s');
    echo json_encode($order);

} else {
    // All orders for authenticated user
    $stmt = $conn->prepare("SELECT o.*, COALESCE(p.payment_method, '') AS payment_method, COALESCE(p.payment_status, 'Completed') AS payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.user_id = ? ORDER BY o.order_id DESC");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_id DESC");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $oid = (int)$row['order_id'];
        $itemsStmt = $conn->prepare(
            "SELECT oi.product_id, COALESCE(p.product_name, 'Product Item') AS product_name, COALESCE(p.image_url, '') AS image_url, oi.quantity, oi.unit_price
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = ?"
        );
        if ($itemsStmt) {
            $itemsStmt->bind_param("i", $oid);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();

            $items = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $items[] = $itemRow;
            }
            $row['items'] = $items;
        } else {
            $row['items'] = [];
        }

        $row['order_status'] = $row['order_status'] ?? $row['status'] ?? 'Pending';
        $row['order_date'] = $row['order_date'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
        $orders[] = $row;
    }

    echo json_encode($orders);
}