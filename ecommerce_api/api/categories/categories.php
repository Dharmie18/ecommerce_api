<?php
header('Content-Type: application/json');
require '../../config/db.php';

$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('categories.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$category_id = is_numeric($path) ? intval($path) : null;

if ($category_id) {
    // Single category
    $stmt = $conn->prepare("SELECT * FROM Categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    if ($category) {
        echo json_encode($category);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Category not found"]);
    }

} else {
    // All categories
    $result = $conn->query("SELECT * FROM Categories");
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    echo json_encode($categories);
}