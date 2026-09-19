<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

// So the identity comes from the verified token 
$user_id = getAuthenticatedUserId();

$data = json_decode(file_get_contents("php://input"), true);

$shipping_address = $data['shipping_address'] ?? '';
$items            = $data['items'] ?? [];
$payment_method   = $data['payment_method'] ?? '';

if (empty($shipping_address) || empty($items) || empty($payment_method)) {
    http_response_code(400);
    echo json_encode(["error" => "shipping_address, items, and payment_method are required"]);
    exit;
}

$validMethods = ['Credit Card', 'PayPal', 'Crypto', 'Bank Transfer'];
if (!in_array($payment_method, $validMethods)) {
    http_response_code(400);
    echo json_encode(["error" => "payment_method must be one of: " . implode(', ', $validMethods)]);
    exit;
}

$conn->begin_transaction();

try {
    $total_amount = 0;
    $orderItemsData = [];

    foreach ($items as $item) {
        $product_id = $item['product_id'] ?? null;
        $quantity   = $item['quantity'] ?? null;

        if (!$product_id || !$quantity || $quantity <= 0) {
            throw new Exception("Each item needs a valid product_id and quantity");
        }

        $stmt = $conn->prepare("SELECT price, stock_quantity FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product ID $product_id not found");
        }
        if ($product['stock_quantity'] < $quantity) {
            throw new Exception("Not enough stock for product ID $product_id");
        }

        $unit_price = $product['price'];
        $total_amount += $unit_price * $quantity;

        $orderItemsData[] = [
            'product_id' => $product_id,
            'quantity'   => $quantity,
            'unit_price' => $unit_price
        ];
    }

    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total_amount, $shipping_address);
    $stmt->execute();
    $order_id = $stmt->insert_id;

    foreach ($orderItemsData as $orderItem) {
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $orderItem['product_id'], $orderItem['quantity'], $orderItem['unit_price']);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $stmt->bind_param("ii", $orderItem['quantity'], $orderItem['product_id']);
        $stmt->execute();
    }

    $stmt = $conn->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Completed')");
    $stmt->bind_param("ids", $order_id, $total_amount, $payment_method);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "message" => "Order placed and payment recorded successfully",
        "order_id" => $order_id,
        "total_amount" => $total_amount
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Checkout failed: " . $e->getMessage()]);
}