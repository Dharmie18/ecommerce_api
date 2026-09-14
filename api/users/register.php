<?php
header('Content-Type: application/json');
require '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$first_name = $data['first_name'] ?? '';
$last_name  = $data['last_name'] ?? '';
$email      = $data['email'] ?? '';
$password   = $data['password'] ?? '';

// Check required fields
if (empty($first_name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

// Validate name contains letters only
if (!preg_match("/^[a-zA-Z\s\-]+$/", $first_name)) {
    http_response_code(400);
    echo json_encode(["error" => "First name must contain letters only"]);
    exit;
}
if (!empty($last_name) && !preg_match("/^[a-zA-Z\s\-]+$/", $last_name)) {
    http_response_code(400);
    echo json_encode(["error" => "Last name must contain letters only"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "Password must be at least 8 characters"]);
    exit;
}
if (!preg_match("/[A-Z]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one uppercase letter"]);
    exit;
}
if (!preg_match("/[a-z]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one lowercase letter"]);
    exit;
}
if (!preg_match("/[0-9]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one number"]);
    exit;
}
if (!preg_match("/[\W_]/", $password)) {
    http_response_code(400);
    echo json_encode(["error" => "Password must contain at least one special character"]);
    exit;
}

// Check if email is already registered
$checkStmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Email already registered"]);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

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