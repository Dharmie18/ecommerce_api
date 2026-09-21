<?php
header('Content-Type: application/json');
require '../../config/db.php';
require_once '../../config/ensure_schema.php';
require_once '../../config/mailer.php';

ensurePasswordResetTable($conn);

$data = json_decode(file_get_contents("php://input"), true);
$email        = trim($data['email'] ?? '');
$code         = trim($data['code'] ?? '');
$new_password = $data['new_password'] ?? '';

if (empty($email) || empty($code) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Email, verification code, and new password are required"]);
    exit;
}

// Validate password strength
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "Password must be at least 8 characters"]);
    exit;
}
if (!preg_match("/[A-Z]/", $new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one uppercase letter"]);
    exit;
}
if (!preg_match("/[a-z]/", $new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one lowercase letter"]);
    exit;
}
if (!preg_match("/[0-9]/", $new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one number"]);
    exit;
}
if (!preg_match("/[\W_]/", $new_password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one special character"]);
    exit;
}

// Check OTP validity
$stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND token_or_otp = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$result = $stmt->get_result();
$resetRecord = $result->fetch_assoc();

if (!$resetRecord) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or expired verification code"]);
    exit;
}

// Hash new password and update user
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$updateUser = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$updateUser->bind_param("ss", $password_hash, $email);

if ($updateUser->execute()) {
    // Invalidate reset code
    $updateReset = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
    $updateReset->bind_param("i", $resetRecord['id']);
    $updateReset->execute();

    // Send Password Changed Confirmation Email
    try {
        $uStmt = $conn->prepare("SELECT first_name FROM users WHERE email = ?");
        $uStmt->bind_param("s", $email);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $fName = $uRow['first_name'] ?? 'Customer';
        $timeStr = date('F j, Y - g:i A') . ' UTC';
        $changedHtml = getPasswordChangedEmailHtml($fName, $email, $timeStr);
        sendShopItEmail($email, $fName, "Security Alert: Password Changed for Your ShopIt Account", $changedHtml);
    } catch (Exception $e) {
        // Suppress email exception
    }

    echo json_encode([
        "message" => "Password updated successfully. You can now sign in with your new password."
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update password: " . $conn->error]);
}
?>
