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
