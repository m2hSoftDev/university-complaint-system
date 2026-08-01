<?php

//  Database  
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_complaint_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Directory 
define('BACKEND_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
define('ROOT_PATH', str_replace('\\', '/', dirname(dirname(__DIR__))) . '/');
define('FRONTEND_PATH', ROOT_PATH . 'frontend/');
define('UPLOAD_PATH', BACKEND_PATH . 'uploads/');
define('COMPLAINT_IMG_PATH', UPLOAD_PATH . 'complaints/');
define('REPAIR_IMG_PATH', UPLOAD_PATH . 'repairs/');

$_scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$_scriptDir = preg_replace('#/(frontend|backend|admin|student|staff|config|includes|ajax)(/.*)?$#', '', $_scriptDir);
$_scriptDir = rtrim($_scriptDir, '/');

if (!defined('BASE_URL')) {
    define('BASE_URL', $_scriptDir);
}
if (!defined('BACKEND_URL')) {
    define('BACKEND_URL', BASE_URL . '/backend');
}
if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', BASE_URL . '/frontend');
}

// Database Connection 
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Connection Error</title>
    <style>body{font-family:Inter,sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:40px;max-width:500px;text-align:center;box-shadow:0 10px 25px -5px rgba(0,0,0,0.05)}
    h2{color:#ef4444;margin-bottom:16px}p{color:#64748b;line-height:1.6}code{background:#e0e7ff;padding:2px 8px;border-radius:6px;font-size:13px;color:#4338ca}</style></head>
    <body><div class="box"><h2> Database Connection Failed</h2>
    <p>Please ensure <strong>MySQL</strong> is running in XAMPP and the database <code>' . DB_NAME . '</code> exists.</p>
    <p style="margin-top:16px;font-size:13px;color:#94a3b8">' . htmlspecialchars($e->getMessage()) . '</p></div></body></html>');
}
