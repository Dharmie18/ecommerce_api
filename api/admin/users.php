<?php
header('Content-Type: application/json');
require '../../config/db.php';
require '../../config/jwt.php';
require_once '../../config/ensure_schema.php';

requireAdmin($conn); 
ensureUserReferralColumns($conn);

$method = $_SERVER['REQUEST_METHOD'];

// Extract ID from URL if present
$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('users.php', $requestUri);
$path = isset($parts[1]) ? trim($parts[1], '/') : '';
$user_id = is_numeric($path) ? intval($path) : null;

if ($method === 'GET') {
    // Auto-generate codes for any user missing one
    $missingUsers = $conn->query("SELECT user_id, first_name, email FROM users WHERE referral_code IS NULL OR referral_code = ''");
    if ($missingUsers) {
        while ($u = $missingUsers->fetch_assoc()) {
            $clean = preg_replace('/[^a-zA-Z]/', '', $u['first_name']);
            $prefix = strtoupper(substr($clean, 0, 3));
            if (strlen($prefix) < 3) $prefix = 'SI';
            $c = $prefix . '-' . strtoupper(substr(md5($u['user_id'] . $u['email'] . 'shopit2026'), 0, 5));
            $conn->query("UPDATE users SET referral_code = '{$c}' WHERE user_id = {$u['user_id']}");
        }
    }

    if ($user_id) {
        // Single user
        $stmt = $conn->prepare("
            SELECT 
                u.user_id, u.first_name, u.last_name, u.email, u.role, u.referral_code, u.referred_by_id, u.created_at,
                CONCAT(r.first_name, ' ', r.last_name) AS referred_by_name,
                r.referral_code AS referred_by_code,
                (SELECT COUNT(*) FROM users ref WHERE ref.referred_by_id = u.user_id) AS referrals_count
            FROM users u
            LEFT JOIN users r ON u.referred_by_id = r.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
        }

    } else {
        // All users
        $query = "
            SELECT 
                u.user_id, u.first_name, u.last_name, u.email, u.role, u.referral_code, u.referred_by_id, u.created_at,
                CONCAT(r.first_name, ' ', r.last_name) AS referred_by_name,
                r.referral_code AS referred_by_code,
                (SELECT COUNT(*) FROM users ref WHERE ref.referred_by_id = u.user_id) AS referrals_count
            FROM users u
            LEFT JOIN users r ON u.referred_by_id = r.user_id
            ORDER BY u.user_id DESC
        ";
        $result = $conn->query($query);
        $users = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        } else {
            // Fallback to simple query if JOIN fails
            $fallback = $conn->query("SELECT user_id, first_name, last_name, email, role, created_at FROM users ORDER BY user_id DESC");
            if ($fallback) {
                while ($row = $fallback->fetch_assoc()) {
                    $users[] = $row;
                }
            }
        }

        echo json_encode($users);
    }

} elseif ($method === 'DELETE') {

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(["error" => "user_id is required in the URL"]);
        exit;
    }

    // Safeguard: Prevent deleting admin accounts
    $checkAdmin = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $checkAdmin->bind_param("i", $user_id);
    $checkAdmin->execute();
    $targetUser = $checkAdmin->get_result()->fetch_assoc();

    if ($targetUser && $targetUser['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(["error" => "Administrator accounts cannot be deleted"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["message" => "User deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . $stmt->error]);
    }

} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}