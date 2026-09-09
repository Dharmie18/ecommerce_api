<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('payments.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$payment_id = is_numeric($path) ? intval($path) : null;

if ($method === 'GET') {

    if ($payment_id) {
        $stmt = $conn->prepare("SELECT * FROM Payments WHERE payment_id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if ($payment) {
            echo json_encode($payment);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Payment not found"]);
        }

    } else {
        $result = $conn->query("SELECT * FROM Payments");
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        echo json_encode($payments);
    }

} elseif ($method === 'PUT') {
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(["error" => "payment_id is required in the URL"]);
        exit;
    }

    $payment_status = $data['payment_status'] ?? '';
    $validStatuses = ['Pending', 'Completed', 'Failed', 'Refunded'];

    if (!in_array($payment_status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(["error" => "payment_status must be one of: " . implode(', ', $validStatuses)]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE Payments SET payment_status = ? WHERE payment_id = ?");
    $stmt->bind_param("si", $payment_status, $payment_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Payment status updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}