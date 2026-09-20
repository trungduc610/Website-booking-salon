<?php
// ============================================================
//  core/Router.php
//  Xử lý URL → gọi đúng Controller::method()
// ============================================================

class Router
{
    /** @var array Bảng định tuyến đã nạp từ config/routes.php */
    private array $routes = [];

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Phân tích URL hiện tại và gọi controller tương ứng
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; // GET | POST | ...
        $uri    = $this->parseUri();                   // VD: /salon/bookings/5/confirm

        $routeMap = $this->routes[$method] ?? [];

        foreach ($routeMap as $pattern => [$controllerName, $actionName]) {
            $params = $this->match($pattern, $uri);

            if ($params !== false) {
                $this->callAction($controllerName, $actionName, $params);
                return;
            }
        }

        // Không khớp route nào → 404
        http_response_code(404);
        echo $this->render404();
    }

    // --------------------------------------------------------
    //  Private helpers
    // --------------------------------------------------------

    /**
     * Lấy URI sạch (bỏ query string, bỏ base path)
     */
    private function parseUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $uri = rawurldecode($uri);

        // Bỏ phần base path nếu chạy trong thư mục con của Web server (vd: XAMPP htdocs)
        // Chỉ tính basePath nếu SCRIPT_NAME là file .php thật
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('/\.php$/i', $scriptName)) {
            $basePath = rtrim(dirname($scriptName), '/');
            if ($basePath !== '' && $basePath !== '/' && $basePath !== '.' && str_starts_with($uri, $basePath)) {
                $uri = substr($uri, strlen($basePath));
            }
        }

        $clean = trim($uri, '/');
        return $clean === '' ? '/' : '/' . $clean;
    }

    /**
     * So khớp URL với pattern có tham số {param}
     * Trả về mảng params nếu khớp, false nếu không
     *
     * VD: pattern '/salon/bookings/{id}/confirm'
     *     uri     '/salon/bookings/42/confirm'
     *     → ['id' => '42']
     */
    private function match(string $pattern, string $uri): array|false
    {
        // Chuyển pattern sang regex
        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#i';

        if (preg_match($regex, $uri, $matches)) {
            // Chỉ lấy các nhóm tên (bỏ các phần tử số)
            return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Tải controller class và gọi method
     */
    private function callAction(string $controllerName, string $actionName, array $params): void
    {
        $file = BASE_PATH . '/app/controllers/' . $controllerName . '.php';

        if (!file_exists($file)) {
            http_response_code(500);
            die("Controller không tồn tại: {$controllerName}");
        }

        require_once $file;

        if (!class_exists($controllerName)) {
            http_response_code(500);
            die("Không tìm thấy class: {$controllerName}");
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $actionName)) {
            http_response_code(500);
            die("Không tìm thấy method: {$controllerName}::{$actionName}");
        }

        // Gọi method, truyền params dưới dạng named arguments
        $controller->$actionName(...array_values($params));
    }

    private function render404(): string
    {
        return "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>404 - Không tìm thấy</title>
                <style>body{font-family:sans-serif;text-align:center;padding:80px 20px;color:#333}
                h1{font-size:72px;margin:0;color:#e53e3e}
                a{display:inline-block;margin-top:20px;color:#e53e3e;text-decoration:none;font-weight:600}</style></head>
                <body><h1>404</h1><p>Trang bạn tìm kiếm không tồn tại.</p>
                <a href='/'>← Về trang chủ</a></body></html>";
    }
}
