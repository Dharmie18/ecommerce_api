<?php
// ============================================================================
// 1. INITIAL SETUP & DEPENDENCIES
// ============================================================================
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

ensureVirtualBankAccountsTable($conn);

// ============================================================================
// 2. AUTHENTICATION & USER PROFILE RETRIEVAL
// ============================================================================
$user_id = getAuthenticatedUserId();

$uStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$userRow = $uStmt->get_result()->fetch_assoc();

if (!$userRow) {
    http_response_code(404);
    echo json_encode(["error" => "User account not found"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$amount = floatval($data['amount'] ?? 0);

// ============================================================================
// 3. SETTLEMENT BANK SELECTION & IDENTIFIER GENERATION
// ============================================================================
$banks = [
    ['name' => 'Providus Bank', 'code' => '101'],
    ['name' => 'Wema Bank', 'code' => '035'],
    ['name' => 'Sterling Bank', 'code' => '232']
];

$selectedBank = $banks[array_rand($banks)];

$prefix = '99';
$randomDigits = str_pad(strval(random_int(10000000, 99999999)), 8, '0', STR_PAD_LEFT);
$dynamicAccountNumber = $prefix . $randomDigits;

$accountRef = 'SI-TRF-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

$customerFullName = strtoupper(trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? '')));
$accountName = "SHOPIT - " . ($customerFullName ?: 'CUSTOMER');

// ============================================================================
// 4. PURGE EXPIRED ACCOUNTS & RECORD NEW VIRTUAL ACCOUNT
// ============================================================================
$cleanStmt = $conn->prepare("DELETE FROM virtual_bank_accounts WHERE user_id = ? AND (expires_at < NOW() OR is_settled = 0)");
$cleanStmt->bind_param("i", $user_id);
$cleanStmt->execute();

$insStmt = $conn->prepare("INSERT INTO virtual_bank_accounts (user_id, account_reference, bank_name, account_number, account_name, amount, expires_at) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$insStmt->bind_param("issssd", $user_id, $accountRef, $selectedBank['name'], $dynamicAccountNumber, $accountName, $amount);

// ============================================================================
// 5. RESPONSE DISPATCH
// ============================================================================
if ($insStmt->execute()) {
    echo json_encode([
        "success" => true,
        "bank_name" => $selectedBank['name'],
        "account_number" => $dynamicAccountNumber,
        "account_name" => $accountName,
        "amount" => $amount,
        "reference" => $accountRef,
        "expires_in_minutes" => 30,
        "expires_at" => date('Y-m-d H:i:s', time() + (30 * 60)),
        "message" => "Dynamic bank transfer account generated successfully."
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to generate dynamic virtual account: " . $conn->error]);
}
?>

