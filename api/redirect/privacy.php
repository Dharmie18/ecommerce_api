<?php
// Dynamic redirection endpoint for Privacy Policy
require_once '../../config/db.php';
require_once '../../config/ensure_schema.php';

$settings = getSiteSettings($conn);
$target = !empty($settings['privacy_policy_url']) ? $settings['privacy_policy_url'] : '/';

header("Location: " . $target, true, 302);
exit;
