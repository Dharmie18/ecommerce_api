<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

$user_id = getAuthenticatedUserId();
ensureUserReferralColumns($conn);

function maskEmail($email) {
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

$conn->query("CREATE TABLE IF NOT EXISTS coupons (
    coupon_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2) NOT NULL,
    min_items INT DEFAULT 3,
    is_used TINYINT(1) DEFAULT 0,
    order_id INT NULL,
    used_at TIMESTAMP NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

//  Fetch user's own referral code
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, referral_code, referred_by_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "User profile not found"]);
    exit;
}

$refCode = $user['referral_code'];
if (empty($refCode)) {
    $clean = preg_replace('/[^a-zA-Z]/', '', $user['first_name']);
    $prefix = strtoupper(substr($clean, 0, 3));
    if (strlen($prefix) < 3) $prefix = 'SI';
    $refCode = $prefix . '-' . strtoupper(substr(md5($user_id . $user['email'] . 'shopit2026'), 0, 5));
    $upd = $conn->prepare("UPDATE users SET referral_code = ? WHERE user_id = ?");
    $upd->bind_param("si", $refCode, $user_id);
    $upd->execute();
}

// Fetch who referred this user (if any)
$referredBy = null;
if (!empty($user['referred_by_id'])) {
    $stmtRefBy = $conn->prepare("SELECT first_name, last_name, referral_code FROM users WHERE user_id = ?");
    $stmtRefBy->bind_param("i", $user['referred_by_id']);
    $stmtRefBy->execute();
    $refByRow = $stmtRefBy->get_result()->fetch_assoc();
    if ($refByRow) {
        $referredBy = [
            'name' => $refByRow['first_name'] . ' ' . $refByRow['last_name'],
            'referral_code' => $refByRow['referral_code']
        ];
    }
}

// Fetch list of users who registered with this user's code
$stmtRefs = $conn->prepare("SELECT user_id, first_name, last_name, email, created_at FROM users WHERE referred_by_id = ? ORDER BY created_at DESC");
$stmtRefs->bind_param("i", $user_id);
$stmtRefs->execute();
$resRefs = $stmtRefs->get_result();

$referralsList = [];
while ($r = $resRefs->fetch_assoc()) {
    $referralsList[] = [
        'user_id' => $r['user_id'],
        'name' => $r['first_name'] . ' ' . (substr($r['last_name'], 0, 1) ? substr($r['last_name'], 0, 1) . '.' : ''),
        'full_name' => $r['first_name'] . ' ' . $r['last_name'],
        'masked_email' => maskEmail($r['email']),
        'registered_at' => $r['created_at']
    ];
}

// Fetch user's coupons
$stmtC = $conn->prepare("SELECT coupon_id, code, discount_percent, min_items, is_used, used_at, expires_at, created_at FROM coupons WHERE user_id = ? ORDER BY is_used ASC, created_at DESC");
$stmtC->bind_param("i", $user_id);
$stmtC->execute();
$resC = $stmtC->get_result();

$couponsList = [];
$activeCouponsList = [];
$nowTime = time();

while ($c = $resC->fetch_assoc()) {
    $expiresAtStr = $c['expires_at'];
    if (empty($expiresAtStr)) {
        // Fallback default 7 days from creation
        $expiresAtStr = date('Y-m-d H:i:s', strtotime($c['created_at'] . ' +7 days'));
    }

    $expTimestamp = strtotime($expiresAtStr);
    $isExpired = ($expTimestamp < $nowTime);
    $secondsLeft = max(0, $expTimestamp - $nowTime);
    $isUsed = (bool)$c['is_used'];

    $couponObj = [
        'coupon_id' => (int)$c['coupon_id'],
        'code' => $c['code'],
        'discount_percent' => (float)$c['discount_percent'],
        'min_items' => (int)$c['min_items'],
        'is_used' => $isUsed,
        'used_at' => $c['used_at'],
        'expires_at' => $expiresAtStr,
        'is_expired' => $isExpired,
        'seconds_left' => $secondsLeft,
        'created_at' => $c['created_at']
    ];

    $couponsList[] = $couponObj;
    if (!$isUsed && !$isExpired) {
        $activeCouponsList[] = $couponObj;
    }
}

echo json_encode([
    "referral_code" => $refCode,
    "referral_link" => "https://shopit-users.vercel.app/?ref=" . $refCode,
    "referred_by" => $referredBy,
    "total_referrals" => count($referralsList),
    "active_coupons_count" => count($activeCouponsList),
    "referrals" => $referralsList,
    "coupons" => $couponsList,
    "active_coupons" => $activeCouponsList
]);
