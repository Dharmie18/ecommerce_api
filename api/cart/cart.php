<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

ensureCartTable($conn);

$user_id = getAuthenticatedUserId();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($method === 'GET') {
    $stmt = $conn->prepare(
        "SELECT c.product_id, c.quantity, p.product_name, p.price, p.stock_quantity, p.image_url, p.category_id, cat.category_name
         FROM cart_items c
         JOIN products p ON c.product_id = p.product_id
         LEFT JOIN categories cat ON p.category_id = cat.category_id
         WHERE c.user_id = ?
         ORDER BY c.created_at ASC"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cart = [];
    while ($row = $result->fetch_assoc()) {
        $cart[] = [
            'product_id' => (int)$row['product_id'],
            'quantity'   => (int)$row['quantity'],
            'product'    => [
                'product_id'     => (int)$row['product_id'],
                'product_name'   => $row['product_name'],
                'price'          => (float)$row['price'],
                'stock_quantity' => (int)$row['stock_quantity'],
                'image_url'      => $row['image_url'],
                'category_id'    => (int)$row['category_id'],
                'category_name'  => $row['category_name'] ?? 'General'
            ]
        ];
    }

    echo json_encode(['cart' => $cart]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $items = $data['items'] ?? [];
    $mode = $data['mode'] ?? 'replace'; // 'replace' or 'merge'

    if (!is_array($items)) {
        http_response_code(400);
        echo json_encode(["error" => "Items must be an array"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($mode === 'replace') {
            $delStmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $delStmt->bind_param("i", $user_id);
            $delStmt->execute();

            foreach ($items as $item) {
                $pid = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;

                if ($pid > 0 && $qty > 0) {
                    // Check stock
                    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
                    $stockStmt->bind_param("i", $pid);
                    $stockStmt->execute();
                    $stockRes = $stockStmt->get_result()->fetch_assoc();

                    if ($stockRes) {
                        $actualQty = min((int)$stockRes['stock_quantity'], $qty);
                        if ($actualQty > 0) {
                            $insStmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
                            $insStmt->bind_param("iii", $user_id, $pid, $actualQty);
                            $insStmt->execute();
                        }
                    }
                }
            }
        } elseif ($mode === 'merge') {
            foreach ($items as $item) {
                $pid = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;

                if ($pid > 0 && $qty > 0) {
                    $stockStmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
                    $stockStmt->bind_param("i", $pid);
                    $stockStmt->execute();
                    $stockRes = $stockStmt->get_result()->fetch_assoc();

                    if ($stockRes) {
                        $maxStock = (int)$stockRes['stock_quantity'];
                        // Get current qty in db
                        $curStmt = $conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
                        $curStmt->bind_param("ii", $user_id, $pid);
                        $curStmt->execute();
                        $curRes = $curStmt->get_result()->fetch_assoc();

                        $combinedQty = $qty;
                        if ($curRes) {
                            $combinedQty += (int)$curRes['quantity'];
                        }
                        $finalQty = min($maxStock, $combinedQty);

                        if ($finalQty > 0) {
                            $insStmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
                            $insStmt->bind_param("iiii", $user_id, $pid, $finalQty, $finalQty);
                            $insStmt->execute();
                        }
                    }
                }
            }
        }

        $conn->commit();

        // Return updated cart
        $stmt = $conn->prepare(
            "SELECT c.product_id, c.quantity, p.product_name, p.price, p.stock_quantity, p.image_url, p.category_id, cat.category_name
             FROM cart_items c
             JOIN products p ON c.product_id = p.product_id
             LEFT JOIN categories cat ON p.category_id = cat.category_id
             WHERE c.user_id = ?
             ORDER BY c.created_at ASC"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $cart = [];
        while ($row = $result->fetch_assoc()) {
            $cart[] = [
                'product_id' => (int)$row['product_id'],
                'quantity'   => (int)$row['quantity'],
                'product'    => [
                    'product_id'     => (int)$row['product_id'],
                    'product_name'   => $row['product_name'],
                    'price'          => (float)$row['price'],
                    'stock_quantity' => (int)$row['stock_quantity'],
                    'image_url'      => $row['image_url'],
                    'category_id'    => (int)$row['category_id'],
                    'category_name'  => $row['category_name'] ?? 'General'
                ]
            ];
        }

        echo json_encode(["message" => "Cart updated successfully", "cart" => $cart]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["error" => "Failed to update cart: " . $e->getMessage()]);
        exit;
    }
}

if ($method === 'DELETE') {
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    if ($product_id > 0) {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        echo json_encode(["message" => "Item removed from cart"]);
    } else {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(["message" => "Cart cleared successfully"]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
