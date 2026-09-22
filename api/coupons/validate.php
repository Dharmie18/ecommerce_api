<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

$user_id = getAuthenticatedUserId();

$data = json_decode(file_get_contents("php://input"), true);
$code = strtoupper(trim($data['coupon_code'] ?? ''));
$cart_items_count = intval($data['cart_items_count'] ?? ($data['item_count'] ?? 0));

if (empty($code)) {
    http_response_code(400);
    echo json_encode(["error" => "Coupon code is required"]);
    exit;
}

$stmt = $conn->prepare("SELECT coupon_id, user_id, code, discount_percent, min_items, is_used, expires_at, created_at FROM coupons WHERE code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();

if (!$coupon) {
    http_response_code(404);
    echo json_encode(["error" => "Coupon code '{$code}' not found"]);
    exit;
}

if ($coupon['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(["error" => "This coupon code belongs to a different account"]);
    exit;
}

if ($coupon['is_used']) {
    http_response_code(400);
    echo json_encode(["error" => "Coupon '{$code}' has already been redeemed"]);
    exit;
}

$expiresAt = $coupon['expires_at'] ?: date('Y-m-d H:i:s', strtotime($coupon['created_at'] . ' +7 days'));
if (strtotime($expiresAt) < time()) {
    http_response_code(400);
    echo json_encode(["error" => "Coupon '{$code}' expired on {$expiresAt}. Referral coupons are valid for 7 days from reward date."]);
    exit;
}

$minItems = intval($coupon['min_items'] ?: 3);
if ($cart_items_count < $minItems) {
    http_response_code(400);
    echo json_encode([
        "error" => "Coupon requires a minimum of {$minItems} items in your shopping bag. You currently have {$cart_items_count}."
    ]);
    exit;
}

echo json_encode([
    "valid" => true,
    "coupon_id" => (int)$coupon['coupon_id'],
    "code" => $coupon['code'],
    "discount_percent" => (float)$coupon['discount_percent'],
    "min_items" => $minItems,
    "coupon" => [
        "coupon_id" => (int)$coupon['coupon_id'],
        "code" => $coupon['code'],
        "discount_percent" => (float)$coupon['discount_percent'],
        "min_items" => $minItems,
        "expires_at" => $expiresAt,
        "is_used" => 0,
    ],
    "message" => "{$coupon['discount_percent']}% discount successfully applied!"
]);
