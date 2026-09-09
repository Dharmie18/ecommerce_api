<?php
header('Content-Type: application/json');
require '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('product.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$product_id = is_numeric($path) ? intval($path) : null;

if ($method === 'POST') {
    $product_name = $data['product_name'] ?? '';
    $description  = $data['description'] ?? '';
    $price        = $data['price'] ?? 0;
    $stock_quantity = $data['stock_quantity'] ?? 0;
    $category_id  = $data['category_id'] ?? null;

    if (empty($product_name) || $price <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "product_name and a valid price are required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO Products (product_name, description, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdii", $product_name, $description, $price, $stock_quantity, $category_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Product added successfully", "product_id" => $stmt->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Create failed: " . $stmt->error]);
    }

} elseif ($method === 'PUT') {
    if (!$product_id) {
        http_response_code(400);
        echo json_encode(["error" => "product_id is required in the URL"]);
        exit;
    }

    $product_name = $data['product_name'] ?? '';
    $description  = $data['description'] ?? '';
    $price        = $data['price'] ?? 0;
    $stock_quantity = $data['stock_quantity'] ?? 0;

    $stmt = $conn->prepare("UPDATE Products SET product_name = ?, description = ?, price = ?, stock_quantity = ? WHERE product_id = ?");
    $stmt->bind_param("ssdii", $product_name, $description, $price, $stock_quantity, $product_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Product updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . $stmt->error]);
    }

} elseif ($method === 'DELETE') {
    if (!$product_id) {
        http_response_code(400);
        echo json_encode(["error" => "product_id is required in the URL"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM Products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Product removed successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}