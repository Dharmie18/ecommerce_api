<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('category.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$category_id = is_numeric($path) ? intval($path) : null;

if ($method === 'POST') {
    $category_name = $data['category_name'] ?? '';
    $parent_category_id = $data['parent_category_id'] ?? null;

    if (empty($category_name)) {
        http_response_code(400);
        echo json_encode(["error" => "category_name is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO Categories (category_name, parent_category_id) VALUES (?, ?)");
    $stmt->bind_param("si", $category_name, $parent_category_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Category created successfully", "category_id" => $stmt->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Create failed: " . $stmt->error]);
    }

} elseif ($method === 'PUT') {
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(["error" => "category_id is required in the URL"]);
        exit;
    }

    $category_name = $data['category_name'] ?? '';

    $stmt = $conn->prepare("UPDATE Categories SET category_name = ? WHERE category_id = ?");
    $stmt->bind_param("si", $category_name, $category_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Category updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
    }

} elseif ($method === 'DELETE') {
    if (!$category_id) {
        http_response_code(400);
        echo json_encode(["error" => "category_id is required in the URL"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM Categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Category deleted successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}