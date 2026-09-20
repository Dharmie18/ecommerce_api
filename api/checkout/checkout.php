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
$coupon_code      = strtoupper(trim($data['coupon_code'] ?? ''));

if (empty($shipping_address) || empty($items) || empty($payment_method)) {
    http_response_code(400);
    echo json_encode(["error" => "shipping_address, items, and payment_method are required"]);
    exit;
}

$validMethods = ['Credit Card', 'PayPal', 'Crypto', 'Bank Transfer', 'Debit Card', 'Corporate Invoice'];
if (!in_array($payment_method, $validMethods)) {
    http_response_code(400);
    echo json_encode(["error" => "payment_method must be one of: " . implode(', ', $validMethods)]);
    exit;
}

$conn->begin_transaction();

try {
    $subtotal = 0;
    $total_items_count = 0;
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
        $subtotal += $unit_price * $quantity;
        $total_items_count += (int)$quantity;

        $orderItemsData[] = [
            'product_id' => $product_id,
            'quantity'   => $quantity,
            'unit_price' => $unit_price
        ];
    }

    // Coupon Validation & Discount Application
    $appliedCoupon = null;
    $discount_amount = 0.00;
    $coupon_id = null;

    if (!empty($coupon_code)) {
        $stmtCp = $conn->prepare("SELECT coupon_id, user_id, code, discount_percent, min_items, is_used, expires_at, created_at FROM coupons WHERE code = ?");
        $stmtCp->bind_param("s", $coupon_code);
        $stmtCp->execute();
        $appliedCoupon = $stmtCp->get_result()->fetch_assoc();

        if (!$appliedCoupon) {
            throw new Exception("Coupon code '{$coupon_code}' not found");
        }
        if ($appliedCoupon['user_id'] != $user_id) {
            throw new Exception("Coupon code '{$coupon_code}' belongs to a different account");
        }
        if ($appliedCoupon['is_used']) {
            throw new Exception("Coupon '{$coupon_code}' has already been used");
        }

        $expiresAt = $appliedCoupon['expires_at'] ?: date('Y-m-d H:i:s', strtotime($appliedCoupon['created_at'] . ' +7 days'));
        if (strtotime($expiresAt) < time()) {
            throw new Exception("Coupon '{$coupon_code}' has expired. Referral coupons are valid for 7 days from issue date.");
        }

        $minItemsReq = intval($appliedCoupon['min_items'] ?: 3);
        if ($total_items_count < $minItemsReq) {
            throw new Exception("Coupon requires a minimum of {$minItemsReq} items in your order. You have {$total_items_count}.");
        }

        $discount_percent = floatval($appliedCoupon['discount_percent']);
        $discount_amount = round($subtotal * ($discount_percent / 100), 2);
        $coupon_id = (int)$appliedCoupon['coupon_id'];
    }

    $final_total = max(0, $subtotal - $discount_amount);

    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, coupon_id, discount_amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsid", $user_id, $final_total, $shipping_address, $coupon_id, $discount_amount);
    $stmt->execute();
    $order_id = $stmt->insert_id;

    // Record line items & decrement stock
    foreach ($orderItemsData as $orderItem) {
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $orderItem['product_id'], $orderItem['quantity'], $orderItem['unit_price']);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $stmt->bind_param("ii", $orderItem['quantity'], $orderItem['product_id']);
        $stmt->execute();
    }

    // Mark coupon as redeemed
    if ($coupon_id) {
        $updCp = $conn->prepare("UPDATE coupons SET is_used = 1, order_id = ?, used_at = NOW() WHERE coupon_id = ?");
        $updCp->bind_param("ii", $order_id, $coupon_id);
        $updCp->execute();
    }

    // Record payment
    $stmt = $conn->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'Completed')");
    $stmt->bind_param("ids", $order_id, $final_total, $payment_method);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "message" => "Order placed and payment recorded successfully",
        "order_id" => $order_id,
        "subtotal" => $subtotal,
        "discount_amount" => $discount_amount,
        "total_amount" => $final_total,
        "coupon_applied" => $appliedCoupon ? [
            "code" => $appliedCoupon['code'],
            "discount_percent" => $appliedCoupon['discount_percent']
        ] : null
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["error" => "Checkout failed: " . $e->getMessage()]);
}