<?php
header('Content-Type: application/json');
require '../../config/db.php';
require_once '../../config/ensure_schema.php';
require_once '../../config/jwt.php';

ensureUserVerificationColumns($conn);

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    $data = json_decode(file_get_contents("php://input"), true);
    $token = trim($data['token'] ?? '');
}

if (empty($token)) {
    http_response_code(400);
    echo json_encode(["error" => "Verification token is required"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, role, referral_code, verification_expires_at FROM users WHERE verification_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(400);
    echo json_encode(["error" => "This verification link is invalid or has already been activated."]);
    exit;
}

// Check 30-minute expiration
if (!empty($user['verification_expires_at']) && strtotime($user['verification_expires_at']) < time()) {
    http_response_code(410);
    echo json_encode([
        "error" => "This verification link has expired (links are valid for 30 minutes). Please request a new verification link.",
        "expired" => true,
        "email" => $user['email']
    ]);
    exit;
}

// Mark user as verified
$updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires_at = NULL WHERE user_id = ?");
$updateStmt->bind_param("i", $user['user_id']);

if ($updateStmt->execute()) {
    // Generate JWT token for auto-login
    $jwt = generateJWT($user['user_id']);

    echo json_encode([
        "success" => true,
        "message" => "Congratulations! Your email has been verified successfully.",
        "token" => $jwt,
        "user_id" => $user['user_id'],
        "first_name" => $user['first_name'],
        "last_name" => $user['last_name'],
        "email" => $user['email'],
        "role" => $user['role'],
        "is_verified" => true,
        "referral_code" => $user['referral_code']
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update verification status: " . $conn->error]);
}
?>
