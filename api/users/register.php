<?php
header('Content-Type: application/json');
require '../../config/db.php';

// Reads incoming JSON data (from Postman)
$data = json_decode(file_get_contents("php://input"), true);

$first_name = $data['first_name'] ?? '';
$last_name  = $data['last_name'] ?? '';
$email      = $data['email'] ?? '';
$password   = $data['password'] ?? '';

// Validate required fields
if (empty($first_name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert into Users table
$stmt = $conn->prepare("INSERT INTO Users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $first_name, $last_name, $email, $password_hash);

if ($stmt->execute()) {
    echo json_encode([
        "message" => "User registered successfully",
        "user_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Registration failed: " . $stmt->error]);
}