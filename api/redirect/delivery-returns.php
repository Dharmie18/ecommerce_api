<?php
// Dynamic redirection endpoint for Delivery and Returns Policy
require_once '../../config/db.php';
require_once '../../config/ensure_schema.php';

$settings = getSiteSettings($conn);
$target = !empty($settings['delivery_returns_url']) ? $settings['delivery_returns_url'] : '/';

header("Location: " . $target, true, 302);
exit;
