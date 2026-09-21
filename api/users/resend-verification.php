<?php
header('Content-Type: application/json');
require '../../config/db.php';
require_once '../../config/ensure_schema.php';
require_once '../../config/mailer.php';

ensureUserVerificationColumns($conn);

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["error" => "Email address is required"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, first_name, email, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["error" => "No account found with this email address"]);
    exit;
}

if (intval($user['is_verified']) === 1) {
    echo json_encode([
        "message" => "This email is already verified. You can sign in directly.",
        "already_verified" => true
    ]);
    exit;
}

// Generate new token
$new_token = bin2hex(random_bytes(32));
$updateStmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE user_id = ?");
$updateStmt->bind_param("si", $new_token, $user['user_id']);

if ($updateStmt->execute()) {
    $frontendUrl = getenv('FRONTEND_URL') ?: (getenv('APP_URL') ?: 'http://localhost:3000');
    $magicLink = rtrim($frontendUrl, '/') . '/?verify_token=' . $new_token;

    $htmlEmail = getVerificationEmailHtml($user['first_name'], $magicLink);
    $textEmail = "Hello {$user['first_name']},\n\nPlease verify your ShopIt account by clicking this link:\n{$magicLink}\n\nThank you,\nShopIt Commerce";
    sendShopItEmail($email, $user['first_name'], "Verify Your ShopIt Account", $htmlEmail, $textEmail);

    echo json_encode([
        "message" => "A new magic verification link has been sent to your email.",
        "email" => $email,
        "verification_link" => $magicLink,
        "debug_token" => $new_token
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to generate new verification link: " . $conn->error]);
}
?>
