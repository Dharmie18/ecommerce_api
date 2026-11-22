<?php
// Storefront bootstrap initialization endpoint
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/ensure_schema.php';

// Ensure all database schemas and default settings exist
ensureAllSchemas($conn);

try {
    // Fetch dynamic company branding and metadata
    $settings = getSiteSettings($conn);

    // Fetch product category tree
    $catResult = $conn->query("SELECT category_id, category_name, parent_category_id, image_url FROM categories ORDER BY category_name ASC");
    $categories = [];
    while ($cat = $catResult->fetch_assoc()) {
        $categories[] = [
            'category_id'        => (int)$cat['category_id'],
            'category_name'      => $cat['category_name'],
            'parent_category_id' => $cat['parent_category_id'] ? (int)$cat['parent_category_id'] : null,
            'image_url'          => $cat['image_url']
        ];
    }

    // Fetch product catalog with category associations
    $prodQuery = "
        SELECT 
            p.product_id, 
            p.product_name, 
            p.description, 
            p.price, 
            p.stock_quantity, 
            p.category_id, 
            c.category_name, 
            p.image_url, 
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.product_id ASC
    ";
    $prodResult = $conn->query($prodQuery);
    $products = [];
    while ($p = $prodResult->fetch_assoc()) {
        $products[] = [
            'product_id'     => (int)$p['product_id'],
            'product_name'   => $p['product_name'],
            'description'    => $p['description'],
            'price'          => (float)$p['price'],
            'stock_quantity' => (int)$p['stock_quantity'],
            'category_id'    => $p['category_id'] ? (int)$p['category_id'] : null,
            'category_name'  => $p['category_name'] ?? 'General',
            'image_url'      => $p['image_url'],
            'created_at'     => $p['created_at']
        ];
    }

    // Fetch live platform overview KPIs
    $orderQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END), 0) AS orders_delivered,
            COALESCE(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 0) AS total_sales_volume
        FROM orders
    ");
    $orderStats = $orderQuery ? $orderQuery->fetch_assoc() : ['total_orders' => 0, 'orders_delivered' => 0, 'total_sales_volume' => 0];

    $subQuery = $conn->query("SELECT COUNT(*) AS total_subscribers FROM newslettersubscribers WHERE status = 'active'");
    $subStats = $subQuery ? $subQuery->fetch_assoc() : ['total_subscribers' => 0];

    $stats = [
        'orders_delivered'   => (int)$orderStats['orders_delivered'],
        'total_orders'       => (int)$orderStats['total_orders'],
        'total_products'     => count($products),
        'total_categories'   => count($categories),
        'total_sales_volume' => (float)$orderStats['total_sales_volume'],
        'total_subscribers'  => (int)$subStats['total_subscribers']
    ];

    // Fetch real past orders from database for live storefront dispatch showcase
    $dispatchQuery = "
        SELECT 
            o.order_id, 
            o.total_amount, 
            o.order_status, 
            o.order_date, 
            o.shipping_address,
            COALESCE((
                SELECT p.product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = o.order_id 
                LIMIT 1
            ), 'Direct Wholesale Order') AS item_summary
        FROM orders o
        WHERE o.order_status != 'Cancelled'
        ORDER BY o.order_date DESC, o.order_id DESC
        LIMIT 10
    ";
    $dispatchRes = $conn->query($dispatchQuery);
    $recentDispatches = [];
    if ($dispatchRes) {
        while ($d = $dispatchRes->fetch_assoc()) {
            $addr = trim($d['shipping_address'] ?? '');
            $addrParts = explode(',', $addr);
            $cleanCity = count($addrParts) >= 2 
                ? trim($addrParts[count($addrParts) - 2]) . ', ' . trim(end($addrParts))
                : ($addr !== '' ? $addr : 'Lagos, Nigeria');

            $recentDispatches[] = [
                'order_id'     => (int)$d['order_id'],
                'order_number' => 'SHP-' . str_pad($d['order_id'], 4, '0', STR_PAD_LEFT),
                'city'         => $cleanCity,
                'status'       => $d['order_status'],
                'amount'       => (float)$d['total_amount'],
                'item_summary' => $d['item_summary'],
                'order_date'   => $d['order_date']
            ];
        }
    }

    // Return combined bootstrap response in a single payload
    echo json_encode([
        'settings'          => $settings,
        'categories'        => $categories,
        'products'          => $products,
        'stats'             => $stats,
        'recent_dispatches' => $recentDispatches
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Storefront initialization failed: ' . $e->getMessage()]);
}
?>
