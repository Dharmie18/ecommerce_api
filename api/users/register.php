<?php
header('Content-Type: application/json');
require '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$first_name = $data['first_name'] ?? '';
$last_name  = $data['last_name'] ?? '';
$email      = $data['email'] ?? '';
$password   = $data['password'] ?? '';

// Check required fields
if (empty($first_name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

// Validate name contains letters only
if (!preg_match("/^[a-zA-Z\s\-]+$/", $first_name)) {
    http_response_code(400);
    echo json_encode(["error" => "First name must contain letters only"]);
    exit;
}
if (!empty($last_name) && !preg_match("/^[a-zA-Z\s\-]+$/", $last_name)) {
    http_response_code(400);
    echo json_encode(["error" => "Last name must contain letters only"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "Password must be at least 8 characters"]);
    exit;
}
if (!preg_match("/[A-Z]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one uppercase letter"]);
    exit;
}
if (!preg_match("/[a-z]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one lowercase letter"]);
    exit;
}
if (!preg_match("/[0-9]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one number"]);
    exit;
}
if (!preg_match("/[\W_]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one special character"]);
    exit;
}

// Check if email is already registered
$checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Email already registered"]);
    exit;
}

$referral_code = strtoupper(trim($data['referral_code'] ?? ''));
$referred_by_id = null;
$referrer_user = null;

if (!empty($referral_code)) {
    $refStmt = $conn->prepare("SELECT user_id, first_name, email FROM users WHERE referral_code = ?");
    $refStmt->bind_param("s", $referral_code);
    $refStmt->execute();
    $referrer_user = $refStmt->get_result()->fetch_assoc();

    if (!$referrer_user) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid referral code '{$referral_code}'. Please double check or leave empty."]);
        exit;
    }
    $referred_by_id = (int)$referrer_user['user_id'];
}

// Generate unique referral code for the new user
$cleanName = preg_replace('/[^a-zA-Z]/', '', $first_name);
$prefix = strtoupper(substr($cleanName, 0, 3));
if (strlen($prefix) < 3) $prefix = 'SI';
$my_referral_code = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, referral_code, referred_by_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssi", $first_name, $last_name, $email, $password_hash, $my_referral_code, $referred_by_id);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    $unlocked_reward = null;

    // If registered via referral, award 20% coupon to new user & 5% coupon to referrer (valid for 7 days)
    if ($referred_by_id && $referrer_user) {
        // 1. 20% coupon for new user
        $welcomeCode = 'WELCOME20-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $cpStmt1 = $conn->prepare("INSERT INTO coupons (user_id, code, discount_percent, min_items, expires_at) VALUES (?, ?, 20.00, 3, DATE_ADD(NOW(), INTERVAL 7 DAY))");
        $cpStmt1->bind_param("is", $new_user_id, $welcomeCode);
        $cpStmt1->execute();

        // 2. 5% coupon for referrer
        $refCode = 'REF5-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $cpStmt2 = $conn->prepare("INSERT INTO coupons (user_id, code, discount_percent, min_items, expires_at) VALUES (?, ?, 5.00, 3, DATE_ADD(NOW(), INTERVAL 7 DAY))");
        $cpStmt2->bind_param("is", $referred_by_id, $refCode);
        $cpStmt2->execute();

        $unlocked_reward = [
            "coupon_code" => $welcomeCode,
            "discount_percent" => 20,
            "min_items" => 3,
            "validity_days" => 7,
            "message" => "Congratulations! You unlocked an exclusive 20% discount coupon on your first order of 3+ items! Valid for 7 days."
        ];
    }

    echo json_encode([
        "message" => "User registered successfully",
        "user_id" => $new_user_id,
        "referral_code" => $my_referral_code,
        "reward" => $unlocked_reward
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Registration failed: " . $stmt->error]);
}