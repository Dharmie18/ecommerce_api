<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/ensure_schema.php';

// Ensure all database schemas, tables, and admin credentials are fully initialized
ensureAllSchemas($conn);

$results = [];

// Seed all categories requested
$categoriesList = [
    'Office Furniture',
    'Home Furniture',
    'Stationery & Office Tech',
    'Gadgets & Electronics',
    'Food & Provisions',
    'Tops & Fashion',
    'Gym & Fitness Equipment',
    'Makeup & Beauty Care',
    'Kitchen Utensils & Cookware'
];

$catMap = [];
foreach ($categoriesList as $catName) {
    $esc = mysqli_real_escape_string($conn, $catName);
    $chk = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = '{$esc}'");
    if ($chk && $row = mysqli_fetch_assoc($chk)) {
        $catMap[$catName] = (int)$row['category_id'];
    } else {
        mysqli_query($conn, "INSERT INTO categories (category_name) VALUES ('{$esc}')");
        $catMap[$catName] = mysqli_insert_id($conn);
        $results[] = "Created Category: {$catName}";
    }
}

// Seed catalog products for every requested category
$productsData = [
    // Office Furniture
    [
        'name' => 'Nordic Solid Oak Executive Office Desk (160cm)',
        'desc' => 'Solid oak workstation with concealed cable management, dual soft-close drawers, and scratch-resistant matte finish.',
        'image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=800&q=80',
        'price' => 385000.00,
        'stock' => 15,
        'cat' => 'Office Furniture'
    ],
    [
        'name' => 'ErgoPosture High-Back Mesh Task Chair',
        'desc' => '3D adjustable armrests, adaptive lumbar support, 135-degree recline with locking tilt mechanism, high-density foam seat.',
        'image' => 'https://images.unsplash.com/photo-1580481077197-037a34e04740?auto=format&fit=crop&w=800&q=80',
        'price' => 195000.00,
        'stock' => 28,
        'cat' => 'Office Furniture'
    ],
    [
        'name' => 'Modular 6-Seater Boardroom Conference Table',
        'desc' => 'Heavy-gauge steel frame with walnut melamine top, built-in pop-up multi-plug AC and USB charging ports.',
        'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800&q=80',
        'price' => 540000.00,
        'stock' => 8,
        'cat' => 'Office Furniture'
    ],
    [
        'name' => 'Industrial 4-Drawer Heavy Steel Filing Cabinet',
        'desc' => 'Anti-tilt mechanism, central key locking system, reinforced ball-bearing drawer slides for A4 and legal size folders.',
        'image' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=800&q=80',
        'price' => 145000.00,
        'stock' => 20,
        'cat' => 'Office Furniture'
    ],

    // Home Furniture
    [
        'name' => 'Contemporary 3-Seater Velvet Living Room Sofa',
        'desc' => 'High-resilience foam cushioning, stain-resistant velvet fabric upholstery with reinforced hardwood inner frame.',
        'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80',
        'price' => 420000.00,
        'stock' => 12,
        'cat' => 'Home Furniture'
    ],
    [
        'name' => 'Minimalist Solid Mahogany Coffee Table',
        'desc' => 'Handcrafted solid timber coffee table with lower open storage shelf and water-repellent satin lacquer finish.',
        'image' => 'https://images.unsplash.com/photo-1533090481720-856c6e3c1fdc?auto=format&fit=crop&w=800&q=80',
        'price' => 95000.00,
        'stock' => 22,
        'cat' => 'Home Furniture'
    ],

    // Stationery & Office Tech
    [
        'name' => 'Premium Leatherette A5 Executive Notebook & Pen Set',
        'desc' => '100gsm acid-free bleed-proof ivory paper, bookmark ribbon, elastic closure, with weighted matte brass ballpoint pen.',
        'image' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=800&q=80',
        'price' => 14500.00,
        'stock' => 85,
        'cat' => 'Stationery & Office Tech'
    ],
    [
        'name' => 'Thermal High-Speed Desktop Receipt & Barcode Printer',
        'desc' => 'Auto-cutter, USB and Ethernet LAN interfaces, 260mm/sec ultra-fast printing compatible with all POS software.',
        'image' => 'https://images.unsplash.com/photo-1612815154858-60aa4c59eaa6?auto=format&fit=crop&w=800&q=80',
        'price' => 85000.00,
        'stock' => 30,
        'cat' => 'Stationery & Office Tech'
    ],
    [
        'name' => '12-Digit Financial Solar Desktop Calculator',
        'desc' => 'Tilted large LCD display, durable acrylic key tops, tax calculation and grand total functions with dual solar-battery power.',
        'image' => 'https://images.unsplash.com/photo-1587145820266-a5951ee6f620?auto=format&fit=crop&w=800&q=80',
        'price' => 18500.00,
        'stock' => 60,
        'cat' => 'Stationery & Office Tech'
    ],

    // Gadgets & Electronics
    [
        'name' => 'UltraSlim 15.6-inch Portable FHD IPS USB-C Monitor',
        'desc' => 'Full HD 1080p anti-glare screen, dual USB-C and Mini-HDMI inputs, built-in stereo speakers with magnetic protective cover.',
        'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=800&q=80',
        'price' => 145000.00,
        'stock' => 18,
        'cat' => 'Gadgets & Electronics'
    ],
    [
        'name' => 'Wireless Hybrid Active Noise-Cancelling ANC Headphones',
        'desc' => '40mm dynamic drivers, 45-hour battery playtime, plush memory foam earcups, multi-point Bluetooth 5.3 pairing.',
        'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80',
        'price' => 78000.00,
        'stock' => 35,
        'cat' => 'Gadgets & Electronics'
    ],
    [
        'name' => '65W GaN Multi-Port USB-C Fast Charging Hub',
        'desc' => 'Dual Type-C and single USB-A ports, Power Delivery 3.0, intelligent heat dissipation for laptops, phones, and tablets.',
        'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=800&q=80',
        'price' => 28000.00,
        'stock' => 75,
        'cat' => 'Gadgets & Electronics'
    ],

    // Food & Provisions
    [
        'name' => 'Premium Long Grain Parboiled Rice (50kg Bag)',
        'desc' => 'Stone-free, polished non-sticky Nigerian long-grain rice, double-cleaned and vacuum packed for extended pantry freshness.',
        'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=800&q=80',
        'price' => 78000.00,
        'stock' => 50,
        'cat' => 'Food & Provisions'
    ],
    [
        'name' => 'Pure Refined Vegetable Cooking Oil (25 Litres)',
        'desc' => 'Zero cholesterol, triple-refined vitamin A fortified vegetable cooking oil in heavy-duty tamper-proof jerrycan.',
        'image' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=800&q=80',
        'price' => 65000.00,
        'stock' => 40,
        'cat' => 'Food & Provisions'
    ],
    [
        'name' => 'Instant Whole Cream Milk Powder (1kg Refill Pack x 6)',
        'desc' => 'Full cream instant soluble milk rich in calcium and essential vitamins, bundle of 6 family refill pouches.',
        'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=800&q=80',
        'price' => 42000.00,
        'stock' => 55,
        'cat' => 'Food & Provisions'
    ],

    // Tops & Fashion
    [
        'name' => 'Tailored Executive Egyptian Cotton Dress Shirt',
        'desc' => '100% long-staple Egyptian cotton, wrinkle-resistant twill weave, reinforced collar stays and double barrel cuffs.',
        'image' => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=800&q=80',
        'price' => 32000.00,
        'stock' => 45,
        'cat' => 'Tops & Fashion'
    ],
    [
        'name' => 'Heavyweight 240gsm Combed Cotton Boxy Tee',
        'desc' => 'Pre-shrunk ring-spun combed cotton, seamless ribbed crew neck, dropped shoulder relaxed silhouette.',
        'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=800&q=80',
        'price' => 18000.00,
        'stock' => 90,
        'cat' => 'Tops & Fashion'
    ],
    [
        'name' => 'Cashmere-Blend Fine Knit Casual V-Neck Sweater',
        'desc' => 'Ultra-soft lightweight merino and cashmere yarn, ribbed hem and cuffs, breathable thermal comfort.',
        'image' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=800&q=80',
        'price' => 45000.00,
        'stock' => 25,
        'cat' => 'Tops & Fashion'
    ],

    // Gym & Fitness Equipment
    [
        'name' => 'Commercial Heavy-Duty Adjustable FID Workout Bench',
        'desc' => 'Flat, incline, and decline 7-position adjustments, commercial gauge 2x3 steel frame, tear-proof sweat-resistant vinyl pad.',
        'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=800&q=80',
        'price' => 135000.00,
        'stock' => 14,
        'cat' => 'Gym & Fitness Equipment'
    ],
    [
        'name' => 'Rubber-Encased Hex Dumbbell Pair (15kg x 2)',
        'desc' => 'Solid cast iron core, heavy-duty odorless virgin rubber coating, ergonomically contoured chrome knurled handles.',
        'image' => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=800&q=80',
        'price' => 65000.00,
        'stock' => 30,
        'cat' => 'Gym & Fitness Equipment'
    ],
    [
        'name' => 'Compact Foldable Motorized Treadmill with Digital Console',
        'desc' => '2.5HP quiet brushless motor, multi-layer shock absorption running belt, 12 pre-set workout routines with pulse sensors.',
        'image' => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=800&q=80',
        'price' => 520000.00,
        'stock' => 6,
        'cat' => 'Gym & Fitness Equipment'
    ],

    // Makeup & Beauty Care
    [
        'name' => 'Flawless 24-Hour Matte Full Coverage Liquid Foundation',
        'desc' => 'Oil-free, sweat-proof, pore-blurring lightweight liquid foundation enriched with vitamin E and SPF 15 protection.',
        'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=800&q=80',
        'price' => 24000.00,
        'stock' => 70,
        'cat' => 'Makeup & Beauty Care'
    ],
    [
        'name' => 'Professional 18-Piece Synthetic Vegan Makeup Brush Kit',
        'desc' => 'Dense ultra-soft cruelty-free bristles, electroplated wooden handles, includes luxury travel roll-up pouch.',
        'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=800&q=80',
        'price' => 35000.00,
        'stock' => 50,
        'cat' => 'Makeup & Beauty Care'
    ],
    [
        'name' => 'Sunset Palette 35-Color Velvet Matte & Shimmer Eyeshadow',
        'desc' => 'High-pigment, blendable, non-creasing warm earthy and sunset tones with mirror compact.',
        'image' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=800&q=80',
        'price' => 28000.00,
        'stock' => 45,
        'cat' => 'Makeup & Beauty Care'
    ],

    // Kitchen Utensils & Cookware
    [
        'name' => 'GraniteStone 12-Piece Non-Stick Die-Cast Cookware Set',
        'desc' => 'PFOA-free granite coating, induction compatible base, tempered glass lids with steam vent, stay-cool bakelite handles.',
        'image' => 'https://images.unsplash.com/photo-1584990347449-397a61d858e7?auto=format&fit=crop&w=800&q=80',
        'price' => 165000.00,
        'stock' => 32,
        'cat' => 'Kitchen Utensils & Cookware'
    ],
    [
        'name' => 'MasterChef Japanese Stainless Steel 8-Piece Knife Block Set',
        'desc' => 'High-carbon German steel blades, razor-sharp edge retention, full tang ergonomic handles with wooden storage block.',
        'image' => 'https://images.unsplash.com/photo-1593618998160-e34014e67546?auto=format&fit=crop&w=800&q=80',
        'price' => 88000.00,
        'stock' => 45,
        'cat' => 'Kitchen Utensils & Cookware'
    ],
    [
        'name' => 'Commercial 11-Litre Heavy Aluminum Pressure Cooker',
        'desc' => 'Tri-ply encapsulated safety base, dual pressure regulator valves, airtight locking lid for fast bulk institutional cooking.',
        'image' => 'https://images.unsplash.com/photo-1585515320310-259814833e62?auto=format&fit=crop&w=800&q=80',
        'price' => 74000.00,
        'stock' => 25,
        'cat' => 'Kitchen Utensils & Cookware'
    ],
    [
        'name' => 'Professional 24-Piece Heat-Resistant Silicone Cooking Utensils',
        'desc' => 'BPA-free food grade silicone heads, solid acacia wood handles, non-scratch for non-stick pots, includes utensil holder.',
        'image' => 'https://images.unsplash.com/photo-1590794056226-79ef3a8147e1?auto=format&fit=crop&w=800&q=80',
        'price' => 36000.00,
        'stock' => 60,
        'cat' => 'Kitchen Utensils & Cookware'
    ]
];

$stmtP = $conn->prepare("INSERT INTO products (product_name, description, image_url, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
$prodCount = 0;
foreach ($productsData as $np) {
    $pName = mysqli_real_escape_string($conn, $np['name']);
    $chkProd = mysqli_query($conn, "SELECT product_id FROM products WHERE product_name = '{$pName}'");
    if ($chkProd && mysqli_num_rows($chkProd) === 0) {
        $catId = $catMap[$np['cat']] ?? null;
        if ($stmtP) {
            $stmtP->bind_param("sssdis", $np['name'], $np['desc'], $np['image'], $np['price'], $np['stock'], $catId);
            $stmtP->execute();
            $prodCount++;
        }
    }
}
if ($prodCount > 0) {
    $results[] = "Seeded {$prodCount} new catalog products.";
}

// Seed customer users with referral links between them
$customers = [
    [
        'first_name' => 'Emeka',
        'last_name'  => 'Nwosu',
        'email'      => 'emeka.nwosu@apexventures.ng',
        'password'   => 'Customer@123',
        'ref_code'   => 'EME-APEX1',
        'referrer'   => null
    ],
    [
        'first_name' => 'Amina',
        'last_name'  => 'Bello',
        'email'      => 'amina.bello@abujaenterprises.com',
        'password'   => 'Customer@123',
        'ref_code'   => 'AMI-ABJ02',
        'referrer'   => 'emeka.nwosu@apexventures.ng'
    ],
    [
        'first_name' => 'Tunde',
        'last_name'  => 'Bakare',
        'email'      => 'tunde.bakare@ibadanlogistics.ng',
        'password'   => 'Customer@123',
        'ref_code'   => 'TUN-IBD03',
        'referrer'   => 'emeka.nwosu@apexventures.ng'
    ],
    [
        'first_name' => 'Fatima',
        'last_name'  => 'Garba',
        'email'      => 'fatima.garba@kanotrade.org',
        'password'   => 'Customer@123',
        'ref_code'   => 'FAT-KAN04',
        'referrer'   => 'amina.bello@abujaenterprises.com'
    ],
    [
        'first_name' => 'Khalid',
        'last_name'  => 'Adamu',
        'email'      => 'khalid.adamu@sokototrade.ng',
        'password'   => 'Customer@123',
        'ref_code'   => 'KHA-SOK05',
        'referrer'   => null
    ],
    [
        'first_name' => 'Chidinma',
        'last_name'  => 'Eze',
        'email'      => 'chidinma.eze@onitshamarket.com',
        'password'   => 'Customer@123',
        'ref_code'   => 'CHI-ONI06',
        'referrer'   => 'khalid.adamu@sokototrade.ng'
    ],
    [
        'first_name' => 'Bolanle',
        'last_name'  => 'Adeyemi',
        'email'      => 'bolanle.adeyemi@lagosboutique.ng',
        'password'   => 'Customer@123',
        'ref_code'   => 'BOL-LAG07',
        'referrer'   => 'tunde.bakare@ibadanlogistics.ng'
    ],
    [
        'first_name' => 'Damilola',
        'last_name'  => 'Ojo',
        'email'      => 'damilola.ojo@ibadanfitness.com',
        'password'   => 'Customer@123',
        'ref_code'   => 'DAM-IBD08',
        'referrer'   => null
    ]
];

$userIds = [];
$stmtUser = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, password, role, is_verified, referral_code, referred_by_id) VALUES (?, ?, ?, ?, ?, 'customer', 1, ?, ?)");

foreach ($customers as $c) {
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $chk->bind_param("s", $c['email']);
    $chk->execute();
    $found = $chk->get_result()->fetch_assoc();
    if ($found) {
        $userIds[$c['email']] = (int)$found['user_id'];
    } else {
        $hash = password_hash($c['password'], PASSWORD_BCRYPT);
        $refById = ($c['referrer'] && isset($userIds[$c['referrer']])) ? $userIds[$c['referrer']] : null;
        if ($stmtUser) {
            $stmtUser->bind_param("ssssssi", $c['first_name'], $c['last_name'], $c['email'], $hash, $hash, $c['ref_code'], $refById);
            $stmtUser->execute();
            $userIds[$c['email']] = (int)$stmtUser->insert_id;
            $results[] = "Created customer user: {$c['first_name']} {$c['last_name']}";
        }
    }
}

// Seed reward coupons for active users
$sampleCoupons = [
    ['email' => 'emeka.nwosu@apexventures.ng', 'code' => 'WELCOME20-EMEK', 'discount' => 20.00, 'min_items' => 3, 'used' => 0],
    ['email' => 'emeka.nwosu@apexventures.ng', 'code' => 'REF5-REWARD1',   'discount' => 5.00,  'min_items' => 2, 'used' => 0],
    ['email' => 'amina.bello@abujaenterprises.com', 'code' => 'WELCOME20-AMIN', 'discount' => 20.00, 'min_items' => 3, 'used' => 1],
    ['email' => 'amina.bello@abujaenterprises.com', 'code' => 'REF5-REWARD2',   'discount' => 5.00,  'min_items' => 2, 'used' => 0],
    ['email' => 'tunde.bakare@ibadanlogistics.ng', 'code' => 'WELCOME20-TUND', 'discount' => 20.00, 'min_items' => 3, 'used' => 0],
    ['email' => 'fatima.garba@kanotrade.org', 'code' => 'PROMO10-KANOTRADE', 'discount' => 10.00, 'min_items' => 2, 'used' => 0]
];

$stmtCp = $conn->prepare("INSERT INTO coupons (user_id, code, discount_percent, min_items, is_used, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
$cpCount = 0;
foreach ($sampleCoupons as $sc) {
    $uid = $userIds[$sc['email']] ?? null;
    if (!$uid) continue;
    $chkCp = $conn->prepare("SELECT coupon_id FROM coupons WHERE code = ?");
    $chkCp->bind_param("s", $sc['code']);
    $chkCp->execute();
    if ($chkCp->get_result()->num_rows === 0 && $stmtCp) {
        $stmtCp->bind_param("isdii", $uid, $sc['code'], $sc['discount'], $sc['min_items'], $sc['used']);
        $stmtCp->execute();
        $cpCount++;
    }
}
if ($cpCount > 0) {
    $results[] = "Seeded {$cpCount} active reward coupons.";
}

// Seed sample orders with diverse statuses and fulfillment history
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
        'email' => 'khalid.adamu@sokototrade.ng',
        'amount' => 195000.00,
        'status' => 'Pending',
        'date' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'address' => '18 Sultan Abubakar Road, Sokoto State',
        'method' => 'Bank Transfer'
    ],
    [
        'email' => 'chidinma.eze@onitshamarket.com',
        'amount' => 145000.00,
        'status' => 'Delivered',
        'date' => date('Y-m-d H:i:s', strtotime('-60 days')),
        'address' => '5 Main Market Road, Onitsha, Anambra State',
        'method' => 'Credit Card'
    ],
    [
        'email' => 'bolanle.adeyemi@lagosboutique.ng',
        'amount' => 88000.00,
        'status' => 'Shipped',
        'date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'address' => '7 Admiralty Way, Lekki Phase 1, Lagos State',
        'method' => 'Credit Card'
    ],
    [
        'email' => 'damilola.ojo@ibadanfitness.com',
        'amount' => 520000.00,
        'status' => 'Cancelled',
        'date' => date('Y-m-d H:i:s', strtotime('-10 days')),
        'address' => '22 Bodija Estate, Ibadan, Oyo State',
        'method' => 'Bank Transfer'
    ]
];

// Fetch available products to attach to order line items
$prodRes = $conn->query("SELECT product_id, price FROM products LIMIT 10");
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

    $chkOrd = $conn->prepare("SELECT order_id FROM orders WHERE user_id = ? AND total_amount = ?");
    $chkOrd->bind_param("id", $uid, $so['amount']);
    $chkOrd->execute();
    if ($chkOrd->get_result()->num_rows === 0) {
        $stmtOrd = $conn->prepare("INSERT INTO orders (user_id, total_amount, order_status, order_date, shipping_address) VALUES (?, ?, ?, ?, ?)");
        $stmtOrd->bind_param("idsss", $uid, $so['amount'], $so['status'], $so['date'], $so['address']);
        if ($stmtOrd->execute()) {
            $newOrderId = (int)$stmtOrd->insert_id;
            $orderCount++;

            // Create matching payment record
            $paymentStatus = ($so['status'] === 'Cancelled') ? 'Failed' : (($so['status'] === 'Pending') ? 'Pending' : 'Completed');
            $stmtPay = $conn->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status, payment_date) VALUES (?, ?, ?, ?, ?)");
            $stmtPay->bind_param("idsss", $newOrderId, $so['amount'], $so['method'], $paymentStatus, $so['date']);
            $stmtPay->execute();

            // Create matching line item
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
    $results[] = "Seeded {$orderCount} orders with varying statuses and payments.";
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
    'zainab.danladi@kadunawarehouse.com',
    'retail@westafricatrade.com',
    'supplies@capitaldistributors.ng'
];

$stmtSub = $conn->prepare("INSERT IGNORE INTO newslettersubscribers (email, status, subscribed_at) VALUES (?, 'active', NOW())");
$subCount = 0;
foreach ($subscribers as $subEmail) {
    $chkSub = $conn->prepare("SELECT subscriber_id FROM newslettersubscribers WHERE email = ?");
    $chkSub->bind_param("s", $subEmail);
    $chkSub->execute();
    if ($chkSub->get_result()->num_rows === 0 && $stmtSub) {
        $stmtSub->bind_param("s", $subEmail);
        $stmtSub->execute();
        $subCount++;
    }
}
if ($subCount > 0) {
    $results[] = "Seeded {$subCount} verified newsletter subscribers.";
}

echo json_encode([
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'admin_accounts' => [
        'primary' => 'admin@shopit.co (Password: Gx$925]b{*_9)',
        'backup'  => 'admin@shopit.ng (Password: Admin@123)'
    ],
    'results' => $results
]);
