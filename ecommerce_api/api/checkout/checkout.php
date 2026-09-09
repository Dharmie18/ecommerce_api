<?php
header('Content-Type: application/json');
require '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id          = $data['user_id'] ?? '';
$shipping_address = $data['shipping_address'] ?? '';
$items            = $data['items'] ?? [];        // array of {product_id, quantity}
$payment_method   = $data['payment_method'] ?? '';

// Validate required fields
if (empty($user_id) || empty($shipping_address) || empty($items) || empty($payment_method)) {
    http_response_code(400);
    echo json_encode(["error" => "user_id, shipping_address, items, and payment_method are required"]);
    exit;
}

// Start a transaction: everything below succeeds together, or nothing does
$conn->begin_transaction();

try {
    $total_amount = 0;
    $orderItemsData = [];

    // Step 1: Check stock and calculate total
    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity   = $item['quantity'];

        $stmt = $conn->prepare("SELECT price, stock_quantity FROM Products WHERE product_id = ?");
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

    // Step 2: Create the Order
    $stmt = $conn->prepare("INSERT INTO Orders (user_id, total_amount, shipping_address) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total_amount, $shipping_address);
    $stmt->execute();
    $order_id = $stmt->insert_id;

    // Step 3: Create Order_Items and reduce stock
    foreach ($orderItemsData as $orderItem) {
        $stmt = $conn->prepare("INSERT INTO Order_Items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $orderItem['product_id'], $orderItem['quantity'], $orderItem['unit_price']);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE Products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $stmt->bind_param("ii", $orderItem['quantity'], $orderItem['product_id']);
        $stmt->execute();
    }

    // Step 4: Record the Payment
    $stmt = $conn->prepare("INSERT INTO Payments (order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Completed')");
    $stmt->bind_param("ids", $order_id, $total_amount, $payment_method);
    $stmt->execute();

    // All good — save everything permanently
    $conn->commit();

    echo json_encode([
        "message" => "Order placed and payment recorded successfully",
        "order_id" => $order_id,
        "total_amount" => $total_amount
    ]);

} catch (Exception $e) {
    // Something failed — undo everything from this transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Checkout failed: " . $e->getMessage()]);
}