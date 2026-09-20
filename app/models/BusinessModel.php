<?php
// ============================================================
//  app/models/BusinessModel.php
// ============================================================
require_once BASE_PATH . '/core/Model.php';

class BusinessModel extends Model
{
    protected string $table = 'businesses';

    /** Lấy danh sách salon đã duyệt (cho trang chủ / tìm kiếm) */
    public function getActive(int $limit = 12, int $offset = 0, string $search = ''): array
    {
        $params = ['ACTIVE'];
        $where  = "b.status = ?";

        if ($search) {
            $where   .= " AND (b.name LIKE ? OR b.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        return $this->db->query(
            "SELECT b.*,
                    COALESCE(AVG(r.overall_rating), 0) AS avg_rating,
                    COUNT(DISTINCT r.id) AS review_count,
                    br.name AS district_name,
                    p.name AS province_name
             FROM businesses b
             LEFT JOIN branches  bra ON bra.business_id = b.id AND bra.operational_status = 'ACTIVE' AND bra.deleted_at IS NULL
             LEFT JOIN reviews   r   ON r.branch_id = bra.id AND r.status = 'PUBLISHED'
             LEFT JOIN districts br  ON br.id = bra.district_id
             LEFT JOIN provinces p   ON p.id  = br.province_id
             WHERE {$where} AND b.deleted_at IS NULL
             GROUP BY b.id
             ORDER BY avg_rating DESC, b.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /** Tìm kiếm theo tên + tỉnh + dịch vụ */
    public function search(string $keyword = '', int $provinceId = 0, int $page = 1, int $perPage = 12): array
    {
        $params = ['ACTIVE'];
        $having = '';
        $join   = '';
        $where  = "b.status = ?";

        if ($keyword) {
            $where   .= " AND (b.name LIKE ? OR s.name LIKE ? OR b.description LIKE ?)";
            $kw       = "%{$keyword}%";
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
            $join    .= " LEFT JOIN branches bra2 ON bra2.business_id = b.id
                          LEFT JOIN services s ON s.branch_id = bra2.id AND s.deleted_at IS NULL";
        }
        if ($provinceId) {
            $where   .= " AND p.id = ?";
            $params[] = $provinceId;
        }

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT DISTINCT b.*,
                    COALESCE(AVG(r.overall_rating), 0) AS avg_rating,
                    COUNT(DISTINCT r.id) AS review_count,
                    br.name  AS district_name,
                    p.name   AS province_name
                FROM businesses b
                LEFT JOIN branches  bra ON bra.business_id = b.id AND bra.deleted_at IS NULL
                LEFT JOIN reviews   r   ON r.branch_id = bra.id AND r.status = 'PUBLISHED'
                LEFT JOIN districts br  ON br.id  = bra.district_id
                LEFT JOIN provinces p   ON p.id   = br.province_id
                {$join}
                WHERE {$where} AND b.deleted_at IS NULL
                GROUP BY b.id
                ORDER BY avg_rating DESC, b.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        // Đếm tổng
        $countSql = "SELECT COUNT(DISTINCT b.id) AS cnt
                     FROM businesses b
                     LEFT JOIN branches  bra ON bra.business_id = b.id AND bra.deleted_at IS NULL
                     LEFT JOIN districts br  ON br.id  = bra.district_id
                     LEFT JOIN provinces p   ON p.id   = br.province_id
                     {$join}
                     WHERE {$where} AND b.deleted_at IS NULL";

        $total = (int)($this->db->queryOne($countSql, $params)['cnt'] ?? 0);

        return [
            'data'    => $this->db->query($sql, $params),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int)ceil($total / $perPage),
        ];
    }

    /** Chi tiết 1 salon theo slug */
    public function findBySlug(string $slug): array|false
    {
        return $this->db->queryOne(
            "SELECT b.*,
                    COALESCE(AVG(r.overall_rating), 0) AS avg_rating,
                    COUNT(DISTINCT r.id) AS review_count,
                    u.full_name AS owner_name, u.email AS owner_email
             FROM businesses b
             JOIN  business_owner_profiles op ON op.id = b.owner_id
             JOIN  users u  ON u.id  = op.user_id
             LEFT JOIN branches bra ON bra.business_id = b.id AND bra.deleted_at IS NULL
             LEFT JOIN reviews  r   ON r.branch_id = bra.id AND r.status = 'PUBLISHED'
             WHERE b.slug = ? AND b.deleted_at IS NULL
             GROUP BY b.id LIMIT 1",
            [$slug]
        );
    }

    /** Lấy salon của 1 owner */
    public function findByOwner(int $userId): array
    {
        return $this->db->query(
            "SELECT b.* FROM businesses b
             JOIN business_owner_profiles op ON op.id = b.owner_id
             WHERE op.user_id = ? AND b.deleted_at IS NULL
             ORDER BY b.created_at DESC",
            [$userId]
        );
    }

    /** Danh sách salon cho Admin (kèm bộ lọc trạng thái) */
    public function adminList(int $page = 1, int $perPage = 20, string $status = '', string $search = ''): array
    {
        $params = [];
        $where  = "b.deleted_at IS NULL";
        if ($status) { $where .= " AND b.status = ?"; $params[] = $status; }
        if ($search) {
            $where .= " AND (b.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT b.*, u.full_name AS owner_name, u.email AS owner_email
                FROM businesses b
                JOIN business_owner_profiles op ON op.id = b.owner_id
                JOIN users u ON u.id = op.user_id
                WHERE {$where}
                ORDER BY b.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $total = (int)($this->db->queryOne(
            "SELECT COUNT(*) AS cnt FROM businesses b
             JOIN business_owner_profiles op ON op.id = b.owner_id
             JOIN users u ON u.id = op.user_id
             WHERE {$where}", $params
        )['cnt'] ?? 0);

        return [
            'data'    => $this->db->query($sql, $params),
            'total'   => $total, 'page' => $page,
            'perPage' => $perPage,
            'pages'   => (int)ceil($total / $perPage),
        ];
    }
}
