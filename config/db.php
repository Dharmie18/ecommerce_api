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
$dbname   = getenv('DB_NAME') ?: "ecommerce";
$port     = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

if ($is_local) {
    $conn = @new mysqli($host, $username, $password, $dbname, $port);
} else {
    // Cloud database connection (TiDB Cloud / Aiven) requiring SSL/TLS
    $conn = mysqli_init();

    // Check common Linux system CA bundle paths (Render Ubuntu/Debian environment)
    $system_cas = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
        '/etc/ssl/cert.pem'
    ];
    $ca_cert = null;
    foreach ($system_cas as $path) {
        if (file_exists($path)) {
            $ca_cert = $path;
            break;
        }
    }

    if ($ca_cert) {
        $conn->ssl_set(NULL, NULL, $ca_cert, NULL, NULL);
    } else {
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    }

    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

    $connected = @$conn->real_connect($host, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
    if (!$connected) {
        // Retry with SSL without strict host verification
        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $connected = @$conn->real_connect($host, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
    }
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    $err = $conn ? $conn->connect_error : mysqli_connect_error();
    die(json_encode(["error" => "Database connection failed: " . $err]));
}
?>
