<?php

function ensureUserReferralColumns(mysqli $conn): void
{
    $columns = [
        'referral_code' => "ALTER TABLE users ADD COLUMN referral_code VARCHAR(20) UNIQUE AFTER role",
        'referred_by_id' => "ALTER TABLE users ADD COLUMN referred_by_id INT NULL AFTER referral_code",
    ];

    foreach ($columns as $column => $statement) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
        if ($check === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to inspect user account schema']);
            exit;
        }

        if ($check->num_rows === 0 && !$conn->query($statement)) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to prepare user account schema']);
            exit;
        }
    }
}

function ensurePasswordResetTable(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token_or_otp VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_otp (email, token_or_otp, is_used)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($query)) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to prepare password reset schema: ' . $conn->error]);
        exit;
    }
}

function ensureUserVerificationColumns(mysqli $conn): void
{
    $columns = [
        'is_verified' => "ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER role",
        'verification_token' => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER is_verified",
        'verification_expires_at' => "ALTER TABLE users ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_token",
    ];

    foreach ($columns as $column => $statement) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '{$column}'");
        if ($check !== false && $check->num_rows === 0) {
            $conn->query($statement);
        }
    }
}

function ensureCartTable(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS cart_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_product (user_id, product_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($query)) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to prepare cart schema: ' . $conn->error]);
        exit;
    }
}

function ensureVirtualBankAccountsTable(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS virtual_bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_reference VARCHAR(64) UNIQUE NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(20) NOT NULL,
        account_name VARCHAR(150) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        expires_at DATETIME NOT NULL,
        is_settled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_ref (user_id, account_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($query)) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to prepare virtual bank accounts schema: ' . $conn->error]);
        exit;
    }
}

function ensureCouponsTable(mysqli $conn): void
{
    $query = "CREATE TABLE IF NOT EXISTS coupons (
        coupon_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(64) UNIQUE NOT NULL,
        discount_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
        min_items INT NOT NULL DEFAULT 3,
        is_used TINYINT(1) DEFAULT 0,
        order_id INT NULL,
        expires_at DATETIME NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coupon_user (user_id, is_used),
        INDEX idx_coupon_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->query($query);
}

function ensureOrdersSchema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        order_status VARCHAR(50) DEFAULT 'Pending',
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        shipping_address TEXT,
        coupon_id INT NULL,
        discount_amount DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (order_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $columns = [
        'coupon_id' => "ALTER TABLE orders ADD COLUMN coupon_id INT NULL AFTER shipping_address",
        'discount_amount' => "ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_id",
        'order_status' => "ALTER TABLE orders ADD COLUMN order_status VARCHAR(50) DEFAULT 'Pending' AFTER total_amount",
        'order_date' => "ALTER TABLE orders ADD COLUMN order_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER order_status",
        'shipping_address' => "ALTER TABLE orders ADD COLUMN shipping_address TEXT NULL AFTER total_amount",
    ];

    foreach ($columns as $column => $statement) {
        $check = $conn->query("SHOW COLUMNS FROM orders LIKE '{$column}'");
        if ($check !== false && $check->num_rows === 0) {
            $conn->query($statement);
        }
    }
}

function ensureOrderItemsSchema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id),
        INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function ensurePaymentsSchema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_status VARCHAR(50) DEFAULT 'Completed',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function ensureSettingsTable(mysqli $conn): void
{
    // Create site settings key-value store if not exists
    $query = "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->query($query);

    // Seed default branding and contact configurations
    $defaults = [
        'company_name'      => 'ShopIt',
        'tagline'           => 'Direct Retail & Wholesale Marketplace for Nigeria',
        'logo_url'          => '/favicon.png',
        'support_phone'     => '+234 800 746 748',
        'whatsapp_number'   => '+234800746748',
        'primary_email'     => 'support@shopit.ng',
        'office_address'    => 'Plot 14B, Commercial Avenue, Ikeja, Lagos State, Nigeria',
        'rc_number'         => 'RC: 1894203',
        'currency_symbol'   => '₦',
        'currency_code'     => 'NGN',
        'vat_percent'       => '7.5',
        'social_twitter'    => 'https://x.com/shopit_ng',
        'social_instagram'  => 'https://instagram.com/shopit_ng',
        'social_facebook'   => 'https://facebook.com/shopit_ng',
        'social_linkedin'   => 'https://linkedin.com/company/shopit-ng',
        'social_whatsapp'   => 'https://wa.me/234800746748'
    ];

    $checkStmt = $conn->prepare("SELECT setting_key FROM site_settings WHERE setting_key = ?");
    $insertStmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($defaults as $key => $value) {
        $checkStmt->bind_param("s", $key);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $insertStmt->bind_param("ss", $key, $value);
            $insertStmt->execute();
        }
    }
}

// Fetch structured site settings object
function getSiteSettings(mysqli $conn): array
{
    ensureSettingsTable($conn);
    $result = $conn->query("SELECT setting_key, setting_value FROM site_settings");
    $raw = [];
    while ($row = $result->fetch_assoc()) {
        $raw[$row['setting_key']] = $row['setting_value'];
    }

    return [
        'company_name'    => $raw['company_name'] ?? 'ShopIt',
        'tagline'         => $raw['tagline'] ?? 'Direct Retail & Wholesale Marketplace for Nigeria',
        'logo_url'        => $raw['logo_url'] ?? '/favicon.png',
        'support_phone'   => $raw['support_phone'] ?? '+234 800 746 748',
        'whatsapp_number' => $raw['whatsapp_number'] ?? '+234800746748',
        'primary_email'   => $raw['primary_email'] ?? 'support@shopit.ng',
        'office_address'  => $raw['office_address'] ?? 'Plot 14B, Commercial Avenue, Ikeja, Lagos State, Nigeria',
        'rc_number'       => $raw['rc_number'] ?? 'RC: 1894203',
        'currency_symbol' => $raw['currency_symbol'] ?? '₦',
        'currency_code'   => $raw['currency_code'] ?? 'NGN',
        'vat_percent'     => floatval($raw['vat_percent'] ?? 7.5),
        'socials'         => [
            'twitter'   => $raw['social_twitter'] ?? 'https://x.com/shopit_ng',
            'instagram' => $raw['social_instagram'] ?? 'https://instagram.com/shopit_ng',
            'facebook'  => $raw['social_facebook'] ?? 'https://facebook.com/shopit_ng',
            'linkedin'  => $raw['social_linkedin'] ?? 'https://linkedin.com/company/shopit-ng',
            'whatsapp'  => $raw['social_whatsapp'] ?? 'https://wa.me/234800746748'
        ]
    ];
}

function ensureAllSchemas(mysqli $conn): void
{
    ensureUserReferralColumns($conn);
    ensureUserVerificationColumns($conn);
    ensurePasswordResetTable($conn);
    ensureCartTable($conn);
    ensureVirtualBankAccountsTable($conn);
    ensureCouponsTable($conn);
    ensureOrdersSchema($conn);
    ensureOrderItemsSchema($conn);
    ensurePaymentsSchema($conn);
    ensureSettingsTable($conn);
}



