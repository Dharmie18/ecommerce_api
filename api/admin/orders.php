<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('orders.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$order_id = is_numeric($path) ? intval($path) : null;

if ($method === 'GET') {

    if ($order_id) {
        $stmt = $conn->prepare("SELECT * FROM Orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if ($order) {
            echo json_encode($order);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Order not found"]);
        }

    } else {
        $result = $conn->query("SELECT * FROM Orders");
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        echo json_encode($orders);
    }

} elseif ($method === 'PUT') {
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "order_id is required in the URL"]);
        exit;
    }

    $order_status = $data['order_status'] ?? '';
    $validStatuses = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];

    if (!in_array($order_status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(["error" => "order_status must be one of: " . implode(', ', $validStatuses)]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE Orders SET order_status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $order_status, $order_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Order status updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}