<?php
// ============================================================
//  core/Controller.php  — BẢN VÁ
//  Thay thế toàn bộ file cũ.
//
//  Sửa:
//   1. input() KHÔNG còn htmlspecialchars khi NHẬN dữ liệu.
//      Bản cũ escape ngay lúc nhận → "Nguyễn Văn A & Con" bị lưu vào DB thành
//      "Nguyễn Văn A &amp; Con", rồi view gọi e() lần nữa → "&amp;amp;" hiện ra
//      màn hình. Dấu nháy tiếng Việt, & và < trong tên/ghi chú đều hỏng.
//      → Nguyên tắc đúng: LƯU nguyên bản (UTF-8), ESCAPE khi XUẤT bằng e().
//   2. Thêm header bảo mật + Content-Type kèm charset=UTF-8 (chống mojibake khi
//      Apache đặt AddDefaultCharset khác UTF-8 — header HTTP thắng thẻ <meta>).
//   3. verifyCsrf() bắt buộc cho MỌI request thay đổi dữ liệu + chống timing attack.
//   4. requireRole() không còn rò rỉ trạng thái đăng nhập.
// ============================================================

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $this->sendHtmlHeaders();

        extract($data, EXTR_SKIP);

        $viewFile   = BASE_PATH . '/app/views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/views/' . $layout . '.php';

        // Chặn path traversal nếu tên view lỡ đến từ biến
        if (!str_starts_with(realpath($viewFile) ?: '', realpath(BASE_PATH . '/app/views') ?: '@')) {
            $this->abort(404, "View không hợp lệ.");
        }
        if (!file_exists($viewFile)) {
            $this->abort(404, "View không tồn tại: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /** Gửi Content-Type + các header bảo mật cơ bản */
    protected function sendHtmlHeaders(): void
    {
        if (headers_sent()) return;
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; "
             . "img-src 'self' data:; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com; "
             . "script-src 'self' 'unsafe-inline'; "
             . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    }

    protected function redirect(string $url): void
    {
        // Chỉ cho phép redirect nội bộ → chống open redirect
        if (!str_starts_with($url, '/')) {
            $url = '/';
        }
        header('Location: ' . APP_URL . $url);
        exit;
    }

    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        $this->sendHtmlHeaders();

        $messages = [
            400 => 'Yêu cầu không hợp lệ',
            401 => 'Chưa đăng nhập',
            403 => 'Không có quyền truy cập',
            404 => 'Không tìm thấy trang',
            419 => 'Phiên làm việc đã hết hạn',
            429 => 'Bạn thao tác quá nhanh',
            500 => 'Lỗi máy chủ nội bộ',
        ];
        $displayMsg = $message ?: ($messages[$code] ?? 'Có lỗi xảy ra');

        // e() để thông điệp động không trở thành lỗ hổng XSS
        echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'>
              <meta name='viewport' content='width=device-width,initial-scale=1'>
              <title>Lỗi {$code}</title>
              <style>body{font-family:'Be Vietnam Pro',system-ui,sans-serif;text-align:center;
              padding:80px 20px;color:#2d3748;line-height:1.6}
              h1{font-size:72px;margin:0;color:#e53e3e;line-height:1.2} p{font-size:18px}</style></head>
              <body><h1>{$code}</h1><p>" . e($displayMsg) . "</p>
              <a href='/'>← Về trang chủ</a></body></html>";
        exit;
    }

    protected function requireLogin(): void
    {
        if (!Session::isLoggedIn()) {
            if ($this->isGet()) {
                Session::set('redirect_after_login', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
            }
            $this->redirect('/login');
        }
    }

    protected function requireRole(array $roles): void
    {
        $this->requireLogin();
        if (!in_array(Session::userRole(), $roles, true)) {
            $this->abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }
    }

    // --------------------------------------------------------
    //  ĐẦU VÀO — trả về dữ liệu NGUYÊN BẢN đã trim.
    //  Escape là việc của tầng hiển thị: dùng e() trong view.
    // --------------------------------------------------------

    protected function input(string $key, mixed $default = ''): mixed
    {
        $val = $_POST[$key] ?? $default;
        if (is_string($val)) {
            // Loại ký tự điều khiển (\x00-\x08, \x0B, \x0C, \x0E-\x1F) nhưng GIỮ NGUYÊN
            // mọi ký tự UTF-8 có dấu tiếng Việt.
            $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
            return trim($val);
        }
        return $val;
    }

    protected function query(string $key, mixed $default = ''): mixed
    {
        $val = $_GET[$key] ?? $default;
        if (is_string($val)) {
            $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
            return trim($val);
        }
        return $val;
    }

    /** Giữ lại cho tương thích ngược — nay giống hệt input() */
    protected function rawInput(string $key, mixed $default = ''): mixed
    {
        return $this->input($key, $default);
    }

    protected function isPost(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
    protected function isGet(): bool  { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';  }

    /**
     * Xác thực CSRF. Gọi ở ĐẦU mọi action POST/PUT/DELETE.
     * Kiểm tra thêm Origin/Referer để chặn cả trường hợp token bị rò qua XSS bên thứ ba.
     */
    protected function verifyCsrf(): void
    {
        if (!$this->isPost()) {
            $this->abort(405, 'Phương thức không được hỗ trợ.');
        }

        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Session::verifyCsrf(is_string($token) ? $token : '')) {
            $this->abort(419, 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $appHost    = parse_url(APP_URL, PHP_URL_HOST);
            if ($originHost && $appHost && strcasecmp($originHost, $appHost) !== 0) {
                $this->abort(403, 'Yêu cầu đến từ nguồn không hợp lệ.');
            }
        }
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}
