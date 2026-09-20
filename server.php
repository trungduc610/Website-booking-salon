<?php
// ============================================================
//  server.php  — BẢN VÁ (CRITICAL)
//  php -S localhost:8000 server.php
//
//  LỖ HỔNG BẢN CŨ — PATH TRAVERSAL + LỘ MÃ NGUỒN:
//
//    $uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
//    $file = __DIR__ . '/public' . $uri;
//    if ($uri !== '/' && file_exists($file) && !is_dir($file)) { readfile($file); }
//
//  1. urldecode() chạy SAU parse_url, nên "%2e%2e%2f" biến thành "../"
//     → GET /..%2f..%2fconfig/database.php
//       đọc thẳng file cấu hình và IN RA user/password MySQL.
//     Tương tự đọc được core/, app/, database/schema.sql, cả .git/config.
//  2. Kể cả không traversal: mọi file .php trong public/ (gồm index.php)
//     đều bị readfile() → trả về MÃ NGUỒN thay vì thực thi.
//
//  BẢN VÁ:
//   • realpath() + kiểm tra tiền tố thư mục public/  → chặn thoát thư mục
//   • whitelist phần mở rộng tĩnh                    → .php không bao giờ readfile
//   • chặn file ẩn (.env, .git, .htaccess)
//   • gửi charset UTF-8 cho nội dung văn bản
// ============================================================

$publicDir = realpath(__DIR__ . '/public');
if ($publicDir === false) {
    http_response_code(500);
    exit('Không tìm thấy thư mục public/.');
}

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rawurldecode($uri);

// Chỉ những đuôi file này mới được phục vụ như file tĩnh.
// Mọi thứ khác (đặc biệt .php, .sql, .md, .env) đi vào front controller.
const STATIC_TYPES = [
    'css'   => 'text/css; charset=UTF-8',
    'js'    => 'application/javascript; charset=UTF-8',
    'mjs'   => 'application/javascript; charset=UTF-8',
    'map'   => 'application/json; charset=UTF-8',
    'json'  => 'application/json; charset=UTF-8',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'avif'  => 'image/avif',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'txt'   => 'text/plain; charset=UTF-8',
];

$served = false;

/**
 * So khớp tiền tố đường dẫn — CASE-INSENSITIVE trên Windows.
 *
 * FIX (lỗi chỉ lộ ra trên Windows): realpath() trên Windows đôi khi trả về
 * ổ đĩa với chữ hoa/thường KHÁC NHAU giữa 2 lần gọi khác nhau — ví dụ
 * realpath(__DIR__.'/public') có thể ra "C:\xampp\htdocs\..." trong khi
 * realpath($candidate) của cùng thư mục đó lại ra "c:\xampp\htdocs\...".
 * str_starts_with() so khớp PHÂN BIỆT hoa/thường, nên dù 2 đường dẫn thực
 * chất là MỘT nơi trên đĩa, hàm vẫn báo "không nằm trong public/" →
 * server.php từ chối phục vụ file tĩnh → request rơi xuống router của
 * app → router không có route nào khớp "/css/style.css" → trả về trang
 * 404 của app (không phải file CSS) → trình duyệt nhận HTML thay vì CSS,
 * âm thầm từ chối áp dụng style → giao diện mất hết CSS đúng như hiện
 * tượng người dùng gặp trên Windows.
 * Trên Linux/macOS (hệ thống file phân biệt hoa/thường), vẫn so khớp
 * chính xác từng ký tự như cũ.
 */
function pathStartsWith(string $haystack, string $needle): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return stripos($haystack, $needle) === 0;
    }
    return str_starts_with($haystack, $needle);
}

if ($path !== '/' && !str_contains($path, "\0")) {
    $candidate = $publicDir . '/' . ltrim($path, '/');
    $real      = realpath($candidate);

    $ext      = strtolower(pathinfo($real ?: $candidate, PATHINFO_EXTENSION));
    $basename = basename($real ?: $candidate);

    $inside     = $real !== false && pathStartsWith($real, $publicDir . DIRECTORY_SEPARATOR);
    $isFile     = $real !== false && is_file($real);
    $isAllowed  = isset(STATIC_TYPES[$ext]);
    $isHidden   = str_starts_with($basename, '.');

    if ($inside && $isFile && $isAllowed && !$isHidden) {
        header('Content-Type: ' . STATIC_TYPES[$ext]);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($real));

        // SVG do người dùng upload có thể chứa <script> → ép tải về, không render
        if ($ext === 'svg' && str_contains($real, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) {
            header('Content-Disposition: attachment');
            header("Content-Security-Policy: default-src 'none'; sandbox");
        }

        readfile($real);
        $served = true;
    }
}

if (!$served) {
    require_once __DIR__ . '/public/index.php';
}
