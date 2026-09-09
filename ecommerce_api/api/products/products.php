<?php
header('Content-Type: application/json');
require '../../config/db.php';

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('products.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$product_id = is_numeric($path) ? intval($path) : null;

if ($product_id) {
    // Single product
    $stmt = $conn->prepare("SELECT * FROM Products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        echo json_encode($product);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Product not found"]);
    }

} else {
    // All products
    $result = $conn->query("SELECT * FROM Products");
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode($products);
}