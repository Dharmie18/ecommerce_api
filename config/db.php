<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$host     = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "");
$is_local = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1']);
$dbname   = getenv('DB_NAME') ?: ($is_local ? "ecommerce" : "defaultdb");
$port     = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

if ($is_local) {
    $conn = @new mysqli($host, $username, $password, $dbname, $port);
} else {
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    if (!@$conn->real_connect($host, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL)) {
        $conn = @new mysqli($host, $username, $password, $dbname, $port);
    }
}

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}
?>
