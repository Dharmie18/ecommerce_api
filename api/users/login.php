<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

$data = json_decode(file_get_contents("php://input"), true);

$email    = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Email and password are required"]);
    exit;
}

// Validate email format before even querying
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password_hash FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password_hash'])) {
    $token = generateJWT($user['user_id']);

    echo json_encode([
        "message" => "Login successful",
        "token" => $token,
        "user_id" => $user['user_id'],
        "first_name" => $user['first_name'],
        "last_name" => $user['last_name'],
        "email" => $user['email']
    ]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid email or password"]);
}