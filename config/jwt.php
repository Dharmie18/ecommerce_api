<?php
define('JWT_SECRET', 'x7K9pL2mQ8vR4tN6wZ1yB3sD5fG0hJ8cU2eA9iO7uP4rT6qW1x');

function generateJWT($user_id) {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'user_id' => $user_id,
        'exp' => time() + (60 * 60 * 24)
    ]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    list($header, $payload, $signature) = $parts;

    $validSignature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if ($signature !== $validSignature) return false;

    $payloadData = json_decode(base64_decode($payload), true);
    if ($payloadData['exp'] < time()) return false;

    return $payloadData;
}

function getAuthenticatedUserId() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(["error" => "Missing or invalid Authorization header"]);
        exit;
    }

    $token = substr($authHeader, 7);
    $payload = verifyJWT($token);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }

    return $payload['user_id'];
}

// Nconfirms the logged-in user is specifically an admin, not just any user
function requireAdmin($conn) {
    $user_id = getAuthenticatedUserId();

    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Admin access required"]);
        exit;
    }

    return $user_id;
}