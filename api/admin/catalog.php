<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

// Verify admin authorization
requireAdmin($conn);
ensureAllSchemas($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit;
}

// Fetch all products with joined category names
$products = [];
$resProd = $conn->query("
    SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.category_id, p.image_url, p.created_at,
           COALESCE(c.category_name, 'Uncategorised') AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");
if ($resProd) {
    while ($row = $resProd->fetch_assoc()) {
        $row['product_id'] = (int)$row['product_id'];
        $row['price'] = (float)$row['price'];
        $row['stock_quantity'] = (int)$row['stock_quantity'];
        if (isset($row['category_id'])) {
            $row['category_id'] = (int)$row['category_id'];
        }
        $products[] = $row;
    }
}

// Fetch all categories with product counts
$categories = [];
$resCat = $conn->query("
    SELECT c.category_id, c.category_name, c.parent_category_id, c.image_url,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.category_id) AS product_count
    FROM categories c
    ORDER BY c.category_name ASC
");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $row['category_id'] = (int)$row['category_id'];
        if (isset($row['parent_category_id']) && $row['parent_category_id'] !== null) {
            $row['parent_category_id'] = (int)$row['parent_category_id'];
        }
        $row['product_count'] = (int)$row['product_count'];
        $categories[] = $row;
    }
}

// Return unified catalog payload
echo json_encode([
    'products'   => $products,
    'categories' => $categories
]);
