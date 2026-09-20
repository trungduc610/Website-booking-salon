<?php
// ============================================================
//  app/models/UserModel.php
// ============================================================
require_once BASE_PATH . '/core/Model.php';

class UserModel extends Model
{
    protected string $table = 'users';

    /** Tìm user theo email (kể cả đã xóa mềm) */
    public function findByEmail(string $email): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.code AS role_code FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1",
            [$email]
        );
    }

    /** Tìm user kèm role */
    public function findWithRole(int $id): array|false
    {
        return $this->db->queryOne(
            "SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }

    /** Đăng ký user mới + gán role CUSTOMER */
    public function register(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $userId = (int)$this->create([
                'email'         => $data['email'],
                'password_hash' => hashPassword($data['password']),
                'full_name'     => $data['full_name'],
                'phone'         => $data['phone'] ?? null,
                'is_active'     => 1,
            ]);

            // Tạo customer_profile
            $this->db->execute(
                "INSERT INTO customer_profiles (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())",
                [$userId]
            );

            // Gán role CUSTOMER
            $role = $this->db->queryOne("SELECT id FROM roles WHERE code = 'CUSTOMER' LIMIT 1");
            if ($role) {
                $this->db->execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$userId, $role['id']]
                );
            }

            $this->db->commit();
            return $userId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Cập nhật thời điểm đăng nhập cuối */
    public function updateLastLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET last_login_at = NOW() WHERE id = ?",
            [$userId]
        );
    }

    /** Kiểm tra email đã tồn tại chưa */
    public function emailExists(string $email): bool
    {
        return $this->db->count('users', 'email = ? AND deleted_at IS NULL', [$email]) > 0;
    }

    /** Lấy danh sách user phân trang (cho admin) */
    public function listWithRole(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $where  = '1=1';
        $params = [];
        if ($search) {
            $where  = 'u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?';
            $s      = "%{$search}%";
            $params = [$s, $s, $s];
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT u.*, r.code AS role_code, r.name AS role_name
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE {$where} AND u.deleted_at IS NULL
                ORDER BY u.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $total = $this->db->queryOne(
            "SELECT COUNT(*) AS cnt FROM users u WHERE {$where} AND u.deleted_at IS NULL",
            $params
        )['cnt'] ?? 0;

        return [
            'data'    => $this->db->query($sql, $params),
            'total'   => (int)$total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int)ceil($total / $perPage),
        ];
    }
}
