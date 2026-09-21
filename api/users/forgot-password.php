<?php
header('Content-Type: application/json');
require '../../config/db.php';
require_once '../../config/ensure_schema.php';
require_once '../../config/mailer.php';

ensurePasswordResetTable($conn);

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["error" => "Email address is required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "No account found associated with this email address"]);
    exit;
}

// Invalidate any existing unused reset codes for this email
$invalidateStmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE email = ? AND is_used = 0");
$invalidateStmt->bind_param("s", $email);
$invalidateStmt->execute();

// Generate a secure 6-digit OTP code
$otp = sprintf("%06d", mt_rand(100000, 999999));

$insertStmt = $conn->prepare("INSERT INTO password_resets (email, token_or_otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
$insertStmt->bind_param("ss", $email, $otp);

if ($insertStmt->execute()) {
    $htmlEmail = getPasswordResetEmailHtml($user['first_name'], $otp);
    $textEmail = "Hello {$user['first_name']},\n\nYour 6-digit password reset code is: {$otp}\n\nThis code will expire in 15 minutes.\n\nShopIt Commerce";
    sendShopItEmail($email, $user['first_name'], "ShopIt - Password Reset Code", $htmlEmail, $textEmail);

    echo json_encode([
        "message" => "A 6-digit password reset code has been sent to your email address.",
        "email" => $email,
        "debug_code" => $otp // Provided for seamless testing
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to generate password reset code: " . $conn->error]);
}
?>
