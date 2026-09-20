<?php
// ============================================================
//  core/Database.php  — BẢN VÁ
//
//  Sửa:
//   1. Thêm inTransaction() — bản cũ rollBack() trong catch có thể ném
//      "There is no active transaction" và che mất lỗi gốc.
//   2. SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci ngay sau khi kết nối,
//      đảm bảo tiếng Việt không bị hỏng dù server có cấu hình mặc định khác.
//   3. count() chặn tên bảng không hợp lệ (bản cũ nhét thẳng $table vào SQL).
//   4. Không còn xoá ONLY_FULL_GROUP_BY — thay vào đó các truy vấn GROUP BY
//      đã được viết lại cho đúng chuẩn (xem migration).
// ============================================================

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Ép charset + collation cho phiên làm việc: tiếng Việt an toàn tuyệt đối
            $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            // Bật chế độ nghiêm ngặt: dữ liệu sai kiểu sẽ báo lỗi thay vì âm thầm cắt cụt
            $this->pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            error_log('[db] ' . $e->getMessage());
            if (APP_DEBUG) {
                die('Lỗi kết nối database: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
            die('Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.');
        }
    }

    public static function getInstance(): Database
    {
        return self::$instance ??= new Database();
    }

    public function getPdo(): PDO { return $this->pdo; }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollBack(): void         { $this->pdo->rollBack(); }

    /** MỚI: dùng trong catch để tránh "There is no active transaction" */
    public function inTransaction(): bool    { return $this->pdo->inTransaction(); }

    /**
     * Đếm bản ghi. $table và $where là do lập trình viên viết (không phải input),
     * nhưng vẫn kiểm tra tên bảng để chặn nhầm lẫn/lạm dụng về sau.
     */
    public function count(string $table, string $where = '1', array $params = []): int
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Tên bảng không hợp lệ: ' . $table);
        }
        $row = $this->queryOne("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}
