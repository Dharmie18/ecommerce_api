<?php
header('Content-Type: application/json');
require '../../config/db.php';
require_once '../../config/ensure_schema.php';

ensurePasswordResetTable($conn);

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$code  = trim($data['code'] ?? '');

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["error" => "Email and verification code are required"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND token_or_otp = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "valid" => true,
        "message" => "Verification code is valid."
    ]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or expired verification code. Please request a new one."]);
}
?>
