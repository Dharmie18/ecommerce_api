<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';

requireAdmin($conn);

$method = $_SERVER['REQUEST_METHOD'];

// Extract ID from URL if present
$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('subscribers.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$subscriber_id = is_numeric($path) ? intval($path) : null;

if ($method === 'GET') {
    $result = $conn->query("SELECT subscriber_id, email, status, subscribed_at FROM newslettersubscribers ORDER BY subscriber_id DESC");
    $subscribers = [];
    while ($row = $result->fetch_assoc()) {
        $subscribers[] = $row;
    }
    echo json_encode($subscribers);

} elseif ($method === 'DELETE') {
    if (!$subscriber_id) {
        http_response_code(400);
        echo json_encode(["error" => "subscriber_id is required in the URL"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM newslettersubscribers WHERE subscriber_id = ?");
    $stmt->bind_param("i", $subscriber_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "Subscriber removed successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Subscriber not found."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
