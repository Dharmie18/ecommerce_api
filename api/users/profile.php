<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? '';

    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(["error" => "user_id is required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, created_at FROM Users WHERE user_id = ?");
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

    $user_id    = $data['user_id'] ?? '';
    $first_name = $data['first_name'] ?? '';
    $last_name  = $data['last_name'] ?? '';
    $email      = $data['email'] ?? '';

    if (empty($user_id) || empty($first_name) || empty($email)) {
        http_response_code(400);
        echo json_encode(["error" => "user_id, first_name, and email are required"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE Users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
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