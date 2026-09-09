<?php
header('Content-Type: application/json');
require '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$email    = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Validate required fields
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Email and password are required"]);
    exit;
}

// Look up user by email
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash FROM Users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check user exists AND password matches the stored hash
if ($user && password_verify($password, $user['password_hash'])) {
    echo json_encode([
        "message" => "Login successful",
        "user_id" => $user['user_id'],
        "first_name" => $user['first_name'],
        "last_name" => $user['last_name'],
        "email" => $user['email']
    ]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid email or password"]);
}