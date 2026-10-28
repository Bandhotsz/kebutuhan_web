<?php
// api_core/config.php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load .env file if exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Database Credentials
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'undangan_digital');

// Mode Aplikasi
define('IS_DEV_MODE', (isset($_ENV['IS_DEV_MODE']) && $_ENV['IS_DEV_MODE'] === 'true'));

// SMTP Config
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_USER', $_ENV['SMTP_USER'] ?? 'email_kamu@gmail.com');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? 'xxxxxxxxxxxxxxxx');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'noreply@nikahyuk.com');
define('OTP_EXPIRY_MINUTES', 10);

// Midtrans Config
define('MIDTRANS_SERVER_KEY', $_ENV['MIDTRANS_SERVER_KEY'] ?? 'SB-Mid-server-YOUR_SERVER_KEY');
define('MIDTRANS_IS_PRODUCTION', (isset($_ENV['MIDTRANS_IS_PRODUCTION']) && $_ENV['MIDTRANS_IS_PRODUCTION'] === 'true'));

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken() {
    $headers = apache_request_headers();
    $token = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? $headers['x-csrf-token'] ?? '';
    
    // Ignore CSRF for webhook from Midtrans
    if (isset($_GET['action']) && $_GET['action'] === 'midtrans_notification') {
        return true; 
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            echo json_encode(["success" => false, "message" => "Aksi ditolak: Token CSRF tidak valid atau kadaluarsa."]);
            exit();
        }
    }
    return true;
}

// XSS Sanitization Helper
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }
    return is_string($input) ? htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8') : $input;
}

function sendEmailOTP($toEmail, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom(SMTP_USER, 'Nikah Yuk');
        $mail->addAddress($toEmail);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function requireAdmin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(["success" => false, "message" => "Akses ditolak. Anda bukan admin."]);
        exit();
    }
}

// Global PDO Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Create DB & Select
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Note: In production, table creation should ideally be handled via migration scripts, 
    // but to avoid breaking current flow, we keep it here.
    require_once __DIR__ . '/db_setup.php';

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit();
}

// Verify CSRF for POST requests
verifyCsrfToken();
?>
