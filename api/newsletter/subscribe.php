<?php
header('Content-Type: application/json');
require '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["error" => "Email address is required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Please enter a valid email address."]);
    exit;
}

// Check if already subscribed
$check = $conn->prepare("SELECT subscriber_id, status FROM newslettersubscribers WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    if ($existing['status'] === 'unsubscribed') {
        $update = $conn->prepare("UPDATE NewsletterSubscribers SET status = 'active', subscribed_at = NOW() WHERE subscriber_id = ?");
        $update->bind_param("i", $existing['subscriber_id']);
        $update->execute();
        echo json_encode(["message" => "Subscription reactivated! Welcome back to ShopIt Trade Dispatch."]);
    } else {
        echo json_encode(["message" => "You are already subscribed to ShopIt Trade Dispatch!"]);
    }
    exit;
}

// Insert new subscriber
$stmt = $conn->prepare("INSERT INTO newslettersubscribers (email, status) VALUES (?, 'active')");
$stmt->bind_param("s", $email);

if ($stmt->execute()) {
    echo json_encode([
        "message" => "Subscribed successfully! You will receive verified trade dispatches.",
        "subscriber_id" => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to subscribe: " . $stmt->error]);
}
