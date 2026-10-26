<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/ensure_schema.php';

// Ensure all database schemas and tables are present
ensureAllSchemas($conn);

$results = [];

// Referral columns verification
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'referral_code'");
if (mysqli_num_rows($checkCol) === 0) {
    $q1 = "ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE AFTER role, ADD COLUMN referred_by_id INT NULL AFTER referral_code";
    if (mysqli_query($conn, $q1)) {
        $results[] = "Added referral_code and referred_by_id to users table.";
    }
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
    if ($genCount > 0) {
        $results[] = "Generated {$genCount} referral codes for existing users.";
    }
}

// Seed product categories
$newCategories = [
    'Food Staples & Groceries',
    'Industrial Tools & Hardware',
    'Solar & Renewable Power',
    'Office Stationery & Tech',
    'Home & Office Furniture',
    'Kitchen Utensils & Cookware',
    'Electrical & Electronics',
    'Security & Surveillance'
];

foreach ($newCategories as $catName) {
    $esc = mysqli_real_escape_string($conn, $catName);
    $chk = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = '{$esc}'");
    if (mysqli_num_rows($chk) === 0) {
        mysqli_query($conn, "INSERT INTO categories (category_name) VALUES ('{$esc}')");
        $results[] = "Created Category: {$catName}";
    }
}

$resFurn = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = 'Home & Office Furniture'");
$furnId = ($resFurn && $row = mysqli_fetch_assoc($resFurn)) ? $row['category_id'] : 1;

$resKitch = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = 'Kitchen Utensils & Cookware'");
$kitchId = ($resKitch && $row = mysqli_fetch_assoc($resKitch)) ? $row['category_id'] : 2;

// Seed catalog products
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
    }
}

// Seed newsletter subscribers
$subscribers = [
    'procurement@dangltd.ng',
    'ibrahim.k@apexhaul.com',
    'stores@firstchoice.ng',
    'adesola.adeyemi@primeventures.ng',
    'mercy.o@lagosretailers.org',
    'info@novatechsolar.ng',
    'purchase@bellasuperstores.com',
    'chidi.okafor@enugutrade.com',
    'folake.alabi@ibadancorporate.ng',
    'zainab.danladi@kadunawarehouse.com'
];

$stmtSub = $conn->prepare("INSERT IGNORE INTO newslettersubscribers (email, status, subscribed_at) VALUES (?, 'active', NOW())");
$subCount = 0;
foreach ($subscribers as $subEmail) {
    $chkSub = $conn->prepare("SELECT subscriber_id FROM newslettersubscribers WHERE email = ?");
    $chkSub->bind_param("s", $subEmail);
    $chkSub->execute();
    if ($chkSub->get_result()->num_rows === 0) {
        $stmtSub->bind_param("s", $subEmail);
        $stmtSub->execute();
        $subCount++;
    }
}
if ($subCount > 0) {
    $results[] = "Seeded {$subCount} verified newsletter subscribers.";
}

// Seed sample customer accounts for orders
$customers = [
    [
        'first_name' => 'Emeka',
        'last_name'  => 'Nwosu',
        'email'      => 'emeka.nwosu@apexventures.ng',
        'password'   => password_hash('Customer@123', PASSWORD_BCRYPT),
        'role'       => 'customer',
        'ref_code'   => 'EME-APEX1'
    ],
    [
        'first_name' => 'Amina',
        'last_name'  => 'Bello',
        'email'      => 'amina.bello@abujaenterprises.com',
        'password'   => password_hash('Customer@123', PASSWORD_BCRYPT),
        'role'       => 'customer',
        'ref_code'   => 'AMI-ABJ02'
    ],
    [
        'first_name' => 'Tunde',
        'last_name'  => 'Bakare',
        'email'      => 'tunde.bakare@ibadanlogistics.ng',
        'password'   => password_hash('Customer@123', PASSWORD_BCRYPT),
        'role'       => 'customer',
        'ref_code'   => 'TUN-IBD03'
    ],
    [
        'first_name' => 'Fatima',
        'last_name'  => 'Garba',
        'email'      => 'fatima.garba@kanotrade.org',
        'password'   => password_hash('Customer@123', PASSWORD_BCRYPT),
        'role'       => 'customer',
        'ref_code'   => 'FAT-KAN04'
    ]
];

$userIds = [];
$stmtUser = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, is_verified, referral_code) VALUES (?, ?, ?, ?, ?, 1, ?)");

foreach ($customers as $c) {
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $chk->bind_param("s", $c['email']);
    $chk->execute();
    $found = $chk->get_result()->fetch_assoc();
    if ($found) {
        $userIds[$c['email']] = $found['user_id'];
    } else {
        $stmtUser->bind_param("ssssss", $c['first_name'], $c['last_name'], $c['email'], $c['password'], $c['role'], $c['ref_code']);
        $stmtUser->execute();
        $userIds[$c['email']] = $stmtUser->insert_id;
        $results[] = "Created customer user: {$c['first_name']} {$c['last_name']}";
    }
}

// Seed sample orders, items, and payments
$sampleOrders = [
    [
        'email' => 'emeka.nwosu@apexventures.ng',
        'amount' => 580000.00,
        'status' => 'Delivered',
        'date' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'address' => 'Plot 88, Adeola Odeku Street, Victoria Island, Lagos State',
        'method' => 'Bank Transfer'
    ],
    [
        'email' => 'amina.bello@abujaenterprises.com',
        'amount' => 385000.00,
        'status' => 'Delivered',
        'date' => date('Y-m-d H:i:s', strtotime('-18 days')),
        'address' => 'Suite 302, Central Business District, Abuja, FCT',
        'method' => 'Credit Card'
    ],
    [
        'email' => 'tunde.bakare@ibadanlogistics.ng',
        'amount' => 240000.00,
        'status' => 'Shipped',
        'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'address' => '12 Ring Road, Challenge, Ibadan, Oyo State',
        'method' => 'Bank Transfer'
    ],
    [
        'email' => 'fatima.garba@kanotrade.org',
        'amount' => 745000.00,
        'status' => 'Delivered',
        'date' => date('Y-m-d H:i:s', strtotime('-45 days')),
        'address' => '44 Bompai Road, Commercial Area, Kano State',
        'method' => 'Bank Transfer'
    ],
    [
        'email' => 'emeka.nwosu@apexventures.ng',
        'amount' => 195000.00,
        'status' => 'Pending',
        'date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'address' => 'Plot 88, Adeola Odeku Street, Victoria Island, Lagos State',
        'method' => 'Bank Transfer'
    ]
];

// Fetch available product IDs for linking order items
$prodRes = $conn->query("SELECT product_id, price FROM products LIMIT 5");
$availableProducts = [];
if ($prodRes) {
    while ($pr = $prodRes->fetch_assoc()) {
        $availableProducts[] = $pr;
    }
}

$orderCount = 0;
foreach ($sampleOrders as $so) {
    $uid = $userIds[$so['email']] ?? null;
    if (!$uid) continue;

    // Check if an order already exists for this user on this address
    $chkOrd = $conn->prepare("SELECT order_id FROM orders WHERE user_id = ? AND total_amount = ?");
    $chkOrd->bind_param("id", $uid, $so['amount']);
    $chkOrd->execute();
    if ($chkOrd->get_result()->num_rows === 0) {
        $stmtOrd = $conn->prepare("INSERT INTO orders (user_id, total_amount, order_status, order_date, shipping_address) VALUES (?, ?, ?, ?, ?)");
        $stmtOrd->bind_param("idsss", $uid, $so['amount'], $so['status'], $so['date'], $so['address']);
        if ($stmtOrd->execute()) {
            $newOrderId = $stmtOrd->insert_id;
            $orderCount++;

            // Create matching payment record
            $paymentStatus = ($so['status'] === 'Cancelled') ? 'Failed' : (($so['status'] === 'Pending') ? 'Pending' : 'Completed');
            $stmtPay = $conn->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status, payment_date) VALUES (?, ?, ?, ?, ?)");
            $stmtPay->bind_param("idsss", $newOrderId, $so['amount'], $so['method'], $paymentStatus, $so['date']);
            $stmtPay->execute();

            // Create sample order item if product exists
            if (!empty($availableProducts)) {
                $chosenProduct = $availableProducts[array_rand($availableProducts)];
                $qty = 1;
                $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmtItem->bind_param("iiid", $newOrderId, $chosenProduct['product_id'], $qty, $so['amount']);
                $stmtItem->execute();
            }
        }
    }
}

if ($orderCount > 0) {
    $results[] = "Seeded {$orderCount} live customer orders and associated payments.";
}

echo json_encode([
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $results
]);
