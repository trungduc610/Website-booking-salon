<?php
// ============================================================
//  app/support/BranchAccess.php  — FILE MỚI
//
//  Một nơi duy nhất trả lời câu hỏi: "User đang đăng nhập được
//  phép thao tác trên chi nhánh nào?"
//
//  Toàn bộ lỗ hổng IDOR trong bản gốc đến từ việc mỗi controller
//  tự kiểm tra một kiểu (hoặc không kiểm tra gì). Tập trung logic
//  vào đây để không bỏ sót chỗ nào.
//
//  Cách dùng:
//      require_once BASE_PATH . '/app/support/BranchAccess.php';
//      if (!BranchAccess::owns($branchId)) $this->abort(403);
// ============================================================

final class BranchAccess
{
    /** Cache trong 1 request để không truy vấn lặp */
    private static ?array $cache = null;

    /** Xoá cache (gọi sau khi user tạo chi nhánh mới) */
    public static function forget(): void { self::$cache = null; }

    /**
     * Tất cả branch_id mà user hiện tại được thao tác.
     * Gồm 2 nguồn:
     *   (1) Chủ salon  → mọi chi nhánh của mọi business họ đứng tên
     *   (2) Nhân sự    → chi nhánh được gán trong salon_members
     *                    (branch_id NULL = được quản lý toàn business)
     */
    public static function ids(): array
    {
        if (self::$cache !== null) return self::$cache;

        $userId = Session::userId();
        if (!$userId) return self::$cache = [];

        $db = \Database::getInstance();

        $rows = $db->query(
            "SELECT br.id, br.business_id
             FROM branches br
             JOIN businesses b               ON b.id  = br.business_id
             JOIN business_owner_profiles op  ON op.id = b.owner_id
             WHERE op.user_id = ? AND br.deleted_at IS NULL AND b.deleted_at IS NULL",
            [$userId]
        );

        $rows = array_merge($rows, $db->query(
            "SELECT br.id, br.business_id
             FROM salon_members sm
             JOIN branches br ON br.business_id = sm.business_id
                             AND (sm.branch_id IS NULL OR sm.branch_id = br.id)
             WHERE sm.user_id = ? AND sm.is_active = 1 AND sm.deleted_at IS NULL
               AND br.deleted_at IS NULL",
            [$userId]
        ));

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['id']] = (int)$r['business_id'];
        }
        return self::$cache = $map;   // [branch_id => business_id]
    }

    /** User có quyền trên chi nhánh này không? */
    public static function owns(int $branchId): bool
    {
        return $branchId > 0 && array_key_exists($branchId, self::ids());
    }

    /** business_id của chi nhánh — chỉ trả về nếu user có quyền */
    public static function businessIdOf(int $branchId): int
    {
        return self::ids()[$branchId] ?? 0;
    }

    /** User có quyền trên business này không? */
    public static function ownsBusiness(int $businessId): bool
    {
        return $businessId > 0 && in_array($businessId, self::ids(), true);
    }

    /** Danh sách branch_id dạng mảng số — tiện cho WHERE IN */
    public static function branchIdList(): array
    {
        return array_keys(self::ids());
    }
}
