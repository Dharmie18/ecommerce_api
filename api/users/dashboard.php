<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

// Verify user authentication
$userId = getAuthenticatedUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit;
}

ensureUserReferralColumns($conn);
ensureUserVerificationColumns($conn);
ensureOrdersSchema($conn);
ensureOrderItemsSchema($conn);
ensurePaymentsSchema($conn);
ensureCartTable($conn);
ensureCouponsTable($conn);

// Privacy helper for masking referral emails
function maskUserEmail($email) {
    if (empty($email) || !str_contains($email, '@')) return '***@***.com';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '***';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

// Fetch user profile
$stmtUser = $conn->prepare("SELECT user_id, first_name, last_name, email, role, is_verified, referral_code, referred_by_id, created_at FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userProfile = $stmtUser->get_result()->fetch_assoc();

if (!$userProfile) {
    http_response_code(404);
    echo json_encode(["error" => "User account not found"]);
    exit;
}

if (empty($userProfile['referral_code'])) {
    $clean = preg_replace('/[^a-zA-Z]/', '', $userProfile['first_name']);
    $prefix = strtoupper(substr($clean, 0, 3));
    if (strlen($prefix) < 3) $prefix = 'SI';
    $refCode = $prefix . '-' . strtoupper(substr(md5($userId . $userProfile['email'] . 'shopit2026'), 0, 5));
    $upd = $conn->prepare("UPDATE users SET referral_code = ? WHERE user_id = ?");
    $upd->bind_param("si", $refCode, $userId);
    $upd->execute();
    $userProfile['referral_code'] = $refCode;
}
$userProfile['is_verified'] = (bool)($userProfile['is_verified'] ?? 0);

// Fetch user orders with line items
$ordersList = [];
$stmtOrders = $conn->prepare("
    SELECT o.*, COALESCE(p.payment_method, '') AS payment_method, COALESCE(p.payment_status, 'Completed') AS payment_status
    FROM orders o
    LEFT JOIN payments p ON o.order_id = p.order_id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
");
if ($stmtOrders) {
    $stmtOrders->bind_param("i", $userId);
    $stmtOrders->execute();
    $resOrders = $stmtOrders->get_result();

    $stmtItems = $conn->prepare("
        SELECT oi.product_id, COALESCE(p.product_name, 'Product Item') AS product_name, oi.quantity, oi.unit_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");

    while ($ord = $resOrders->fetch_assoc()) {
        $ordId = (int)$ord['order_id'];
        $items = [];
        if ($stmtItems) {
            $stmtItems->bind_param("i", $ordId);
            $stmtItems->execute();
            $resItems = $stmtItems->get_result();
            while ($item = $resItems->fetch_assoc()) {
                $item['quantity'] = (int)$item['quantity'];
                $item['unit_price'] = (float)$item['unit_price'];
                $items[] = $item;
            }
        }
        $ord['items'] = $items;
        $ord['total_amount'] = (float)$ord['total_amount'];
        $ord['discount_amount'] = (float)($ord['discount_amount'] ?? 0);
        $ordersList[] = $ord;
    }
}

// Fetch user referrals and reward coupons
$refCode = $userProfile['referral_code'];
$referredBy = null;
if (!empty($userProfile['referred_by_id'])) {
    $stmtRefBy = $conn->prepare("SELECT first_name, last_name, referral_code FROM users WHERE user_id = ?");
    $stmtRefBy->bind_param("i", $userProfile['referred_by_id']);
    $stmtRefBy->execute();
    $refByRow = $stmtRefBy->get_result()->fetch_assoc();
    if ($refByRow) {
        $referredBy = [
            'name' => $refByRow['first_name'] . ' ' . $refByRow['last_name'],
            'referral_code' => $refByRow['referral_code']
        ];
    }
}

$stmtRefs = $conn->prepare("SELECT user_id, first_name, last_name, email, created_at FROM users WHERE referred_by_id = ? ORDER BY created_at DESC");
$stmtRefs->bind_param("i", $userId);
$stmtRefs->execute();
$resRefs = $stmtRefs->get_result();
$referralsList = [];
while ($r = $resRefs->fetch_assoc()) {
    $referralsList[] = [
        'user_id'       => (int)$r['user_id'],
        'name'          => $r['first_name'] . ' ' . (substr($r['last_name'], 0, 1) ? substr($r['last_name'], 0, 1) . '.' : ''),
        'full_name'     => $r['first_name'] . ' ' . $r['last_name'],
        'masked_email'  => maskUserEmail($r['email']),
        'joined_date'   => $r['created_at']
    ];
}

$stmtCoupons = $conn->prepare("SELECT coupon_id, code, discount_percent, min_items, is_used, used_at, expires_at, created_at FROM coupons WHERE user_id = ? ORDER BY is_used ASC, created_at DESC");
$stmtCoupons->bind_param("i", $userId);
$stmtCoupons->execute();
$resCoupons = $stmtCoupons->get_result();
$couponsList = [];
$activeCouponsList = [];
$nowTime = time();

while ($c = $resCoupons->fetch_assoc()) {
    $expiresAtStr = $c['expires_at'];
    if (empty($expiresAtStr)) {
        $expiresAtStr = date('Y-m-d H:i:s', strtotime($c['created_at'] . ' +7 days'));
    }
    $expTimestamp = strtotime($expiresAtStr);
    $isExpired = ($expTimestamp < $nowTime);
    $secondsLeft = max(0, $expTimestamp - $nowTime);
    $isUsed = (bool)$c['is_used'];

    $couponObj = [
        'coupon_id'         => (int)$c['coupon_id'],
        'code'              => $c['code'],
        'discount_percent'  => (float)$c['discount_percent'],
        'min_items'         => (int)$c['min_items'],
        'is_used'           => $isUsed,
        'used_at'           => $c['used_at'],
        'expires_at'        => $expiresAtStr,
        'is_expired'        => $isExpired,
        'seconds_left'      => $secondsLeft,
        'created_at'        => $c['created_at']
    ];
    $couponsList[] = $couponObj;
    if (!$isUsed && !$isExpired) {
        $activeCouponsList[] = $couponObj;
    }
}

$referralData = [
    'referral_code'         => $refCode,
    'referral_link'         => 'https://shopit-users.vercel.app/?ref=' . $refCode,
    'referred_by'           => $referredBy,
    'referrals_count'       => count($referralsList),
    'active_coupons_count'  => count($activeCouponsList),
    'referrals'             => $referralsList,
    'coupons'               => $couponsList,
    'active_coupons'        => $activeCouponsList
];

// Fetch synchronized cart items
$stmtCart = $conn->prepare("
    SELECT c.product_id, c.quantity, p.product_name, p.price, p.stock_quantity, p.image_url, p.category_id, cat.category_name
    FROM cart_items c
    JOIN products p ON c.product_id = p.product_id
    LEFT JOIN categories cat ON p.category_id = cat.category_id
    WHERE c.user_id = ?
    ORDER BY c.created_at ASC
");
$stmtCart->bind_param("i", $userId);
$stmtCart->execute();
$resCart = $stmtCart->get_result();

$cartLines = [];
while ($row = $resCart->fetch_assoc()) {
    $cartLines[] = [
        'product_id' => (int)$row['product_id'],
        'quantity'   => (int)$row['quantity'],
        'product'    => [
            'product_id'     => (int)$row['product_id'],
            'product_name'   => $row['product_name'],
            'price'          => (float)$row['price'],
            'stock_quantity' => (int)$row['stock_quantity'],
            'image_url'      => $row['image_url'],
            'category_id'    => (int)$row['category_id'],
            'category_name'  => $row['category_name'] ?? 'General'
        ]
    ];
}

// Return unified customer account payload
echo json_encode([
    'profile'   => $userProfile,
    'orders'    => $ordersList,
    'referrals' => $referralData,
    'cart'      => $cartLines
]);
