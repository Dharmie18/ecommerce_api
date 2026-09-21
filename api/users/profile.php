<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

ensureUserReferralColumns($conn);
ensureUserVerificationColumns($conn);

$method = $_SERVER['REQUEST_METHOD'];

// Identity now comes ONLY from the verified token
$user_id = getAuthenticatedUserId();

if ($method === 'GET') {

    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, role, is_verified, referral_code, referred_by_id, created_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;

    if ($user) {
        if (empty($user['referral_code'])) {
            $clean = preg_replace('/[^a-zA-Z]/', '', $user['first_name']);
            $prefix = strtoupper(substr($clean, 0, 3));
            if (strlen($prefix) < 3) $prefix = 'SI';
            $refCode = $prefix . '-' . strtoupper(substr(md5($user_id . $user['email'] . 'shopit2026'), 0, 5));
            $upd = $conn->prepare("UPDATE users SET referral_code = ? WHERE user_id = ?");
            $upd->bind_param("si", $user_id);
            $upd->execute();
            $user['referral_code'] = $refCode;
        }
        $user['is_verified'] = (bool)($user['is_verified'] ?? 0);
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "User not found"]);
    }

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);

    $first_name = $data['first_name'] ?? '';
    $last_name  = $data['last_name'] ?? '';
    $email      = $data['email'] ?? '';

    if (empty($first_name) || empty($email)) {
        http_response_code(400);
        echo json_encode(["error" => "first_name and email are required"]);
        exit;
    }

    if (!preg_match("/^[a-zA-Z\s\-]+$/", $first_name)) {
        http_response_code(400);
        echo json_encode(["error" => "First name must contain letters only"]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid email format"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Profile updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
?>