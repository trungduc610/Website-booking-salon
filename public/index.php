<?php
// ============================================================
//  public/index.php
//  Entry point duy nhất — mọi request đều đi qua đây
// ============================================================

// 0. Lớp bảo vệ thứ hai cho file tĩnh (css/js/ảnh/font).
//    server.php (dùng với `php -S`) đã tự phục vụ file tĩnh TRƯỚC KHI
//    chạm tới file này. Nhưng nếu triển khai qua Apache/XAMPP với
//    .htaccess rewrite MỌI request (kể cả /css/*, /js/*) thẳng vào
//    index.php, thì không có server.php nào đứng chắn — request sẽ rơi
//    xuống Router, Router không có route nào khớp "/css/style.css",
//    trả về trang 404 dạng HTML, và trình duyệt từ chối áp dụng nó làm
//    stylesheet (sai content-type) → mất toàn bộ CSS. Chặn ở đây cho
//    chắc, bất kể chạy bằng cách nào.
$__staticExt = pathinfo(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
if ($__staticExt !== '') {
    $__staticMap = [
        'css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8', 'map' => 'application/json; charset=UTF-8',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    ];
    $__ext = strtolower($__staticExt);
    if (isset($__staticMap[$__ext])) {
        $__publicDir = realpath(__DIR__);
        $__reqPath   = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $__candidate = $__publicDir . '/' . ltrim($__reqPath, '/');
        $__real      = realpath($__candidate);
        // So khớp không phân biệt hoa/thường trên Windows — cùng lý do
        // đã giải thích trong server.php.
        $__inside = $__real !== false && (PHP_OS_FAMILY === 'Windows'
            ? stripos($__real, $__publicDir . DIRECTORY_SEPARATOR) === 0
            : str_starts_with($__real, $__publicDir . DIRECTORY_SEPARATOR));
        if ($__inside && is_file($__real) && !str_starts_with(basename($__real), '.')) {
            header('Content-Type: ' . $__staticMap[$__ext]);
            header('X-Content-Type-Options: nosniff');
            readfile($__real);
            exit;
        }
    }
}
unset($__staticExt, $__staticMap, $__ext, $__publicDir, $__reqPath, $__candidate, $__real, $__inside);

// 1. Load cấu hình
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// 2. Load các class core
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Router.php';

// 3. Load helpers
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/validation.php';
require_once __DIR__ . '/../app/support/BranchAccess.php';

// 4. Khởi động session
Session::start();

// 5. Nạp bảng định tuyến
$routes = require_once __DIR__ . '/../config/routes.php';

// 6. Khởi tạo Router và xử lý request
$router = new Router($routes);
$router->dispatch();
