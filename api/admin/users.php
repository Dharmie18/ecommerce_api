<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Extract ID from URL if present, e.g. /users.php/3
$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('users.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$user_id = is_numeric($path) ? intval($path) : null;

if ($method === 'GET') {

    if ($user_id) {
        // Single user
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

    } else {
        // All users
        $result = $conn->query("SELECT user_id, first_name, last_name, email, created_at FROM Users");
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        echo json_encode($users);
    }

} elseif ($method === 'DELETE') {

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(["error" => "user_id is required in the URL"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "User deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}