<?php
// Admin site settings and brand configuration manager
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

// Verify admin authorization
requireAdmin($conn);
ensureSettingsTable($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return structured site configuration
    echo json_encode(getSiteSettings($conn));
    exit;
}

if ($method === 'PUT') {
    // Update site branding and metadata
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid settings payload"]);
        exit;
    }

    $allowedKeys = [
        'company_name',
        'tagline',
        'logo_url',
        'support_phone',
        'whatsapp_number',
        'primary_email',
        'office_address',
        'rc_number',
        'currency_symbol',
        'currency_code',
        'vat_percent',
        'social_twitter',
        'social_instagram',
        'social_facebook',
        'social_linkedin',
        'social_whatsapp'
    ];

    $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($data as $key => $val) {
        if (in_array($key, $allowedKeys)) {
            $stmt->bind_param("ss", $key, strval($val));
            $stmt->execute();
        }
    }

    echo json_encode([
        "message" => "Site settings updated successfully",
        "settings" => getSiteSettings($conn)
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
?>
