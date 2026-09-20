<?php
// ============================================================
//  core/Model.php
//  Lớp Model gốc — các Model cụ thể kế thừa lớp này
// ============================================================

abstract class Model
{
    /** @var Database Kết nối DB dùng chung */
    protected Database $db;

    /** @var string Tên bảng — mỗi Model con phải khai báo */
    protected string $table = '';

    /** @var string Tên cột khoá chính */
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // --------------------------------------------------------
    //  CRUD cơ bản
    // --------------------------------------------------------

    /**
     * Tìm bản ghi theo khoá chính
     */
    public function findById(int|string $id): array|false
    {
        return $this->db->queryOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? AND deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }

    /**
     * Lấy tất cả bản ghi (chưa bị soft-delete)
     */
    public function findAll(string $orderBy = 'id DESC', int $limit = 100): array
    {
        return $this->db->query(
            "SELECT * FROM `{$this->table}` WHERE deleted_at IS NULL ORDER BY {$orderBy} LIMIT {$limit}"
        );
    }

    /**
     * Tìm bản ghi theo 1 cột = 1 giá trị
     */
    public function findBy(string $column, mixed $value): array
    {
        return $this->db->query(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? AND deleted_at IS NULL",
            [$value]
        );
    }

    /**
     * Tìm 1 bản ghi theo 1 cột = 1 giá trị
     */
    public function findOneBy(string $column, mixed $value): array|false
    {
        return $this->db->queryOne(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? AND deleted_at IS NULL LIMIT 1",
            [$value]
        );
    }

    /**
     * Thêm bản ghi mới
     * $data = ['column' => 'value', ...]
     * Trả về ID của bản ghi vừa thêm
     */
    public function create(array $data): string
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})";
        return $this->db->insert($sql, array_values($data));
    }

    /**
     * Cập nhật bản ghi theo khoá chính
     * $data = ['column' => 'value', ...]
     */
    public function update(int|string $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $setParts = [];
        foreach (array_keys($data) as $col) {
            $setParts[] = "`{$col}` = ?";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE `{$this->table}` SET {$setClause} WHERE `{$this->primaryKey}` = ?";
        $params = array_values($data);
        $params[] = $id;

        return $this->db->execute($sql, $params);
    }

    /**
     * Soft delete — chỉ ghi deleted_at, không xoá thật
     */
    public function delete(int|string $id): int
    {
        return $this->db->execute(
            "UPDATE `{$this->table}` SET deleted_at = ?, updated_at = ? WHERE `{$this->primaryKey}` = ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Đếm số bản ghi theo điều kiện
     */
    public function count(string $where = '1=1', array $params = []): int
    {
        return $this->db->count($this->table, $where . ' AND deleted_at IS NULL', $params);
    }

    /**
     * Phân trang — trả về ['data' => [...], 'total' => N, 'page' => N, 'perPage' => N]
     */
    public function paginate(int $page = 1, int $perPage = 20, string $where = '1=1', array $params = [], string $orderBy = 'id DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->count($where, $params);

        $sql  = "SELECT * FROM `{$this->table}` WHERE {$where} AND deleted_at IS NULL ";
        $sql .= "ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";

        return [
            'data'    => $this->db->query($sql, $params),
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int)ceil($total / $perPage),
        ];
    }
}
