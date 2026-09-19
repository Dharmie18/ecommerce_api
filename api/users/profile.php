<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

// Identity now comes ONLY from the verified token
$user_id = getAuthenticatedUserId();

if ($method === 'GET') {

    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, role, created_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
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