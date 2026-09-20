<?php
// ============================================================
//  app/models/ServiceModel.php
// ============================================================
require_once BASE_PATH . '/core/Model.php';

class ServiceModel extends Model
{
    protected string $table = 'services';

    /** Dịch vụ theo chi nhánh (kèm danh mục) */
    public function findByBranch(int $branchId, bool $onlyBookable = false): array
    {
        $where = "s.branch_id = ? AND s.deleted_at IS NULL";
        if ($onlyBookable) $where .= " AND s.bookable = 1 AND s.status = 'ACTIVE'";
        return $this->db->query(
            "SELECT s.*, sc.name AS category_name
             FROM services s
             LEFT JOIN service_categories sc ON sc.id = s.category_id
             WHERE {$where}
             ORDER BY sc.name, s.name",
            [$branchId]
        );
    }

    /** Danh mục + dịch vụ lồng nhau (cho trang chi tiết salon) */
    public function groupedByBranch(int $branchId): array
    {
        $rows = $this->findByBranch($branchId, true);
        $grouped = [];
        foreach ($rows as $row) {
            $cat = $row['category_name'] ?? 'Khác';
            $grouped[$cat][] = $row;
        }
        return $grouped;
    }

    /** Tìm dịch vụ theo ID và xác nhận thuộc đúng chi nhánh */
    public function findForBranch(int $serviceId, int $branchId): array|false
    {
        return $this->db->queryOne(
            "SELECT s.*, sc.name AS category_name FROM services s
             LEFT JOIN service_categories sc ON sc.id = s.category_id
             WHERE s.id = ? AND s.branch_id = ? AND s.deleted_at IS NULL LIMIT 1",
            [$serviceId, $branchId]
        );
    }

    /** Danh sách danh mục của 1 business */
    public function categoriesByBusiness(int $businessId): array
    {
        return $this->db->query(
            "SELECT * FROM service_categories WHERE business_id = ? AND deleted_at IS NULL ORDER BY name",
            [$businessId]
        );
    }
}
