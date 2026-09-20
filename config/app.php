<?php
// ============================================================
//  config/app.php
//  Cấu hình chung của ứng dụng
// ============================================================

define('APP_NAME',        'GlowBook');

// Tự động nhận diện APP_URL linh hoạt (chạy được cả qua XAMPP và PHP Built-in server)
if (!defined('APP_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base   = rtrim($script, '/');
    if ($base === '/' || $base === '.') $base = '';
    define('APP_URL', $scheme . '://' . $host . $base);
}

define('APP_VERSION',     '1.0.0');
define('APP_TIMEZONE',    'Asia/Ho_Chi_Minh');
define('APP_DEBUG',       true);   // Đặt false khi lên production

// Đường dẫn gốc của project (thư mục cha của public/)
define('BASE_PATH', dirname(__DIR__));

// Thời gian sống của session (giây) — 2 giờ
define('SESSION_LIFETIME', 7200);

// Secret key dùng để tạo token (đổi khi lên production)
define('APP_SECRET', 'glowbook-secret-key-change-in-production');

// Kích thước tối đa file upload (bytes) — 5 MB
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// Các loại file ảnh được phép upload
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Múi giờ mặc định
date_default_timezone_set(APP_TIMEZONE);
