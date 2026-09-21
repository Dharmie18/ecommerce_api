<?php
header('Content-Type: application/json');
require '../../config/db.php';

$results = [];

// users table: referral_code & referred_by_id
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'referral_code'");
if (mysqli_num_rows($checkCol) === 0) {
    $q1 = "ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE AFTER role, ADD COLUMN referred_by_id INT NULL AFTER referral_code";
    if (mysqli_query($conn, $q1)) {
        $results[] = "Added referral_code and referred_by_id to users table.";
    } else {
        $results[] = "Error adding referral columns: " . mysqli_error($conn);
    }
} else {
    $results[] = "users table already has referral columns.";
}

// Generate referral codes for users lacking one
$resUsers = mysqli_query($conn, "SELECT user_id, first_name, email FROM users WHERE referral_code IS NULL OR referral_code = ''");
if ($resUsers) {
    $genCount = 0;
    while ($u = mysqli_fetch_assoc($resUsers)) {
        $clean = preg_replace('/[^a-zA-Z]/', '', $u['first_name']);
        $prefix = strtoupper(substr($clean, 0, 3));
        if (strlen($prefix) < 3) $prefix = 'SI';
        $code = $prefix . '-' . strtoupper(substr(md5($u['user_id'] . $u['email'] . 'shopit2026'), 0, 5));
        mysqli_query($conn, "UPDATE users SET referral_code = '{$code}' WHERE user_id = {$u['user_id']}");
        $genCount++;
    }
    $results[] = "Generated {$genCount} referral codes for existing users.";
}

// Create coupons table
$qCoupons = "CREATE TABLE IF NOT EXISTS coupons (
    coupon_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2) NOT NULL,
    min_items INT DEFAULT 3,
    is_used TINYINT(1) DEFAULT 0,
    order_id INT NULL,
    used_at TIMESTAMP NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (mysqli_query($conn, $qCoupons)) {
    $results[] = "coupons table verified.";
} else {
    $results[] = "Error creating coupons table: " . mysqli_error($conn);
}

// Ensure expires_at column exists and backfill 7 days
$checkExp = mysqli_query($conn, "SHOW COLUMNS FROM coupons LIKE 'expires_at'");
if (mysqli_num_rows($checkExp) === 0) {
    mysqli_query($conn, "ALTER TABLE coupons ADD COLUMN expires_at DATETIME NULL AFTER used_at");
    mysqli_query($conn, "UPDATE coupons SET expires_at = DATE_ADD(created_at, INTERVAL 7 DAY) WHERE expires_at IS NULL");
    $results[] = "Added 7-day expires_at column to coupons table.";
} else {
    mysqli_query($conn, "UPDATE coupons SET expires_at = DATE_ADD(created_at, INTERVAL 7 DAY) WHERE expires_at IS NULL");
}

// orders table: coupon_id & discount_amount
$checkOrdCol = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'discount_amount'");
if (mysqli_num_rows($checkOrdCol) === 0) {
    $qOrd = "ALTER TABLE orders ADD COLUMN coupon_id INT NULL AFTER shipping_address, ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_id";
    if (mysqli_query($conn, $qOrd)) {
        $results[] = "Added coupon_id and discount_amount to orders table.";
    } else {
        $results[] = "Error altering orders table: " . mysqli_error($conn);
    }
} else {
    $results[] = "orders table already has discount columns.";
}

// 5. Categories: Home & Office Furniture, Kitchen Utensils & Cookware
$newCategories = [
    'Home & Office Furniture',
    'Kitchen Utensils & Cookware'
];

foreach ($newCategories as $catName) {
    $esc = mysqli_real_escape_string($conn, $catName);
    $chk = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = '{$esc}'");
    if (mysqli_num_rows($chk) === 0) {
        mysqli_query($conn, "INSERT INTO categories (category_name) VALUES ('{$esc}')");
        $results[] = "Created Category: {$catName}";
    } else {
        $results[] = "Category exists: {$catName}";
    }
}

$resFurn = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = 'Home & Office Furniture'");
$furnId = ($resFurn && $row = mysqli_fetch_assoc($resFurn)) ? $row['category_id'] : 11;

$resKitch = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = 'Kitchen Utensils & Cookware'");
$kitchId = ($resKitch && $row = mysqli_fetch_assoc($resKitch)) ? $row['category_id'] : 12;

// 6. Products
$newProducts = [
    [
        'name' => 'Nordic Solid Oak Executive Office Desk (160cm)',
        'desc' => 'Handcrafted solid oak workstation with concealed cable management, dual soft-close drawers, and scratch-resistant matte finish.',
        'image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=800&q=80',
        'price' => 385000.00,
        'stock' => 15,
        'cat_id' => $furnId
    ],
    [
        'name' => 'ErgoPosture High-Back Mesh Task Chair',
        'desc' => '3D adjustable armrests, adaptive lumbar support, 135-degree recline with locking tilt mechanism, high-density foam seat.',
        'image' => 'https://images.unsplash.com/photo-1580481077197-037a34e04740?auto=format&fit=crop&w=800&q=80',
        'price' => 195000.00,
        'stock' => 28,
        'cat_id' => $furnId
    ],
    [
        'name' => 'Modular 6-Seater Boardroom Conference Table',
        'desc' => 'Heavy-gauge steel frame with walnut melamine top, built-in pop-up multi-plug AC and USB charging ports.',
        'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800&q=80',
        'price' => 540000.00,
        'stock' => 8,
        'cat_id' => $furnId
    ],
    [
        'name' => 'Industrial 4-Drawer Heavy Steel Filing Cabinet',
        'desc' => 'Anti-tilt mechanism, central key locking system, reinforced ball-bearing drawer slides for A4 and legal size folders.',
        'image' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=800&q=80',
        'price' => 145000.00,
        'stock' => 20,
        'cat_id' => $furnId
    ],
    [
        'name' => 'GraniteStone 12-Piece Non-Stick Die-Cast Cookware Set',
        'desc' => 'PFOA-free granite coating, induction compatible base, tempered glass lids with steam vent, stay-cool bakelite handles.',
        'image' => 'https://images.unsplash.com/photo-1584990347449-397a61d858e7?auto=format&fit=crop&w=800&q=80',
        'price' => 165000.00,
        'stock' => 32,
        'cat_id' => $kitchId
    ],
    [
        'name' => 'MasterChef Japanese Stainless Steel 8-Piece Knife Block Set',
        'desc' => 'High-carbon German steel blades, razor-sharp edge retention, full tang ergonomic handles with wooden storage block.',
        'image' => 'https://images.unsplash.com/photo-1593618998160-e34014e67546?auto=format&fit=crop&w=800&q=80',
        'price' => 88000.00,
        'stock' => 45,
        'cat_id' => $kitchId
    ],
    [
        'name' => 'Commercial 11-Litre Heavy Aluminum Pressure Cooker',
        'desc' => 'Tri-ply encapsulated safety base, dual pressure regulator valves, airtight locking lid for fast bulk institutional cooking.',
        'image' => 'https://images.unsplash.com/photo-1585515320310-259814833e62?auto=format&fit=crop&w=800&q=80',
        'price' => 74000.00,
        'stock' => 25,
        'cat_id' => $kitchId
    ],
    [
        'name' => 'Professional 24-Piece Heat-Resistant Silicone Cooking Utensil Set',
        'desc' => 'BPA-free food grade silicone heads, solid acacia wood handles, non-scratch for non-stick pots, includes utensil holder.',
        'image' => 'https://images.unsplash.com/photo-1590794056226-79ef3a8147e1?auto=format&fit=crop&w=800&q=80',
        'price' => 36000.00,
        'stock' => 60,
        'cat_id' => $kitchId
    ]
];

foreach ($newProducts as $np) {
    $pName = mysqli_real_escape_string($conn, $np['name']);
    $chkProd = mysqli_query($conn, "SELECT product_id FROM products WHERE product_name = '{$pName}'");
    if (mysqli_num_rows($chkProd) === 0) {
        $stmtP = mysqli_prepare($conn, "INSERT INTO products (product_name, description, image_url, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmtP, "sssdis", $np['name'], $np['desc'], $np['image'], $np['price'], $np['stock'], $np['cat_id']);
        mysqli_stmt_execute($stmtP);
        $results[] = "Added product: {$np['name']}";
    } else {
        $results[] = "Product exists: {$np['name']}";
    }
}

echo json_encode([
    'status' => 'success',
    'results' => $results
]);
