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


