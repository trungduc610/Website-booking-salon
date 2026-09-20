<?php
// ============================================================
//  core/Session.php
//  Helper quản lý Session và Flash message
// ============================================================

class Session
{
    /**
     * Khởi động session (gọi 1 lần trong index.php)
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            // Cài đặt session an toàn hơn
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_samesite', 'Lax');
            @session_start();
        }

        // Tự động hết hạn sau SESSION_LIFETIME giây không hoạt động
        if (isset($_SESSION['_last_active'])) {
            if (time() - $_SESSION['_last_active'] > SESSION_LIFETIME) {
                self::destroy();
                return;
            }
        }
        $_SESSION['_last_active'] = time();

        // Tạo CSRF token nếu chưa có
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /** Lưu giá trị vào session */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Lấy giá trị từ session */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Xóa 1 key khỏi session */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Lưu thông tin user đăng nhập */
    public static function login(array $user): void
    {
        session_regenerate_id(true); // Chống session fixation
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role_code'] ?? 'CUSTOMER';
    }

    /** Kiểm tra đã đăng nhập chưa */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Lấy user_id đang đăng nhập */
    public static function userId(): int|string|null
    {
        return $_SESSION['user_id'] ?? null;
    }

    /** Lấy role của user đang đăng nhập */
    public static function userRole(): string
    {
        return $_SESSION['user_role'] ?? 'GUEST';
    }

    /** Kiểm tra user có role nhất định không */
    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::userRole(), $roles, true);
    }

    /** Huỷ session (đăng xuất) */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    // --------------------------------------------------------
    //  Flash Message — thông báo 1 lần
    // --------------------------------------------------------

    /** Đặt flash message */
    public static function flash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }

    /**
     * Lấy và xoá flash message (dùng 1 lần)
     * $type : 'success' | 'error' | 'warning' | 'info'
     */
    public static function getFlash(string $type): ?string
    {
        $key = "flash_{$type}";
        $msg = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $msg;
    }

    /** Kiểm tra có flash message không */
    public static function hasFlash(string $type): bool
    {
        return isset($_SESSION["flash_{$type}"]);
    }

    // --------------------------------------------------------
    //  CSRF Token
    // --------------------------------------------------------

    /** Lấy CSRF token hiện tại */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /** Tạo hidden input CSRF (dùng trong form) */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Xác minh CSRF token từ POST */
    public static function verifyCsrf(?string $token): bool
    {
        $sessionToken = self::csrfToken();
        if (!$sessionToken || !$token) return false;
        return hash_equals($sessionToken, $token);
    }
}
