<?php
// ============================================================
//  app/controllers/StaffController.php  — BẢN VÁ
//
//  LỖ HỔNG ĐÃ VÁ: bản cũ nhận branch_id thẳng từ form POST và chèn
//  luôn vào staff_profiles mà không kiểm tra chi nhánh đó có phải của
//  mình không → chủ salon A tạo được "nhân viên ma" trong salon B,
//  và nhân viên ma đó sẽ xuất hiện trên trang đặt lịch công khai của B.
//
//  Ngoài ra bản cũ chưa xử lý UNIQUE KEY uq_branch_code(branch_id,
//  employee_code) → trùng mã nhân viên làm sập trang bằng lỗi SQL 500.
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/support/BranchAccess.php';

class StaffController extends Controller
{
    // POST /salon/staff/create
    public function create(): void
    {
        $this->requireRole(['BUSINESS_OWNER', 'BRANCH_MANAGER']);
        $this->verifyCsrf();

        $branchId = (int)$this->input('branch_id', 0);

        // ---- CHỐT BẢO MẬT ----
        if (!BranchAccess::owns($branchId)) {
            $this->abort(403, 'Bạn không có quyền thêm nhân viên vào chi nhánh này.');
        }

        $fullName     = mb_substr(trim((string)$this->input('full_name')), 0, 150);
        $position     = mb_substr(trim((string)$this->input('position')), 0, 100);
        $employeeCode = mb_substr(trim((string)$this->input('employee_code')), 0, 50);
        $bio          = mb_substr(trim((string)$this->input('bio')), 0, 2000);
        $expYears     = (int)$this->input('experience_years', 0);
        $isBookable   = isset($_POST['is_bookable']) ? 1 : 0;
        $emgPhoneRaw  = trim((string)$this->input('emergency_contact_phone'));

        if (mb_strlen($fullName) < 2) {
            $this->flash('error', 'Vui lòng nhập họ tên nhân viên (tối thiểu 2 ký tự).');
            $this->redirect('/salon/staff');
            return;
        }
        if ($expYears < 0 || $expYears > 60) {
            $this->flash('error', 'Số năm kinh nghiệm không hợp lệ.');
            $this->redirect('/salon/staff');
            return;
        }

        $emgPhone = null;
        if ($emgPhoneRaw !== '') {
            $emgPhone = normalizeVnPhone($emgPhoneRaw);
            if ($emgPhone === null) {
                $this->flash('error', 'Số điện thoại khẩn cấp không hợp lệ.');
                $this->redirect('/salon/staff');
                return;
            }
        }

        $db = \Database::getInstance();

        // Kiểm tra trùng mã nhân viên TRƯỚC khi insert (thân thiện hơn lỗi SQL 500)
        if ($employeeCode !== '') {
            $dup = $db->queryOne(
                "SELECT id FROM staff_profiles WHERE branch_id = ? AND employee_code = ? LIMIT 1",
                [$branchId, $employeeCode]
            );
            if ($dup) {
                $this->flash('error', 'Mã nhân viên "' . $employeeCode . '" đã tồn tại tại chi nhánh này.');
                $this->redirect('/salon/staff');
                return;
            }
        }

        try {
            $db->execute(
                "INSERT INTO staff_profiles
                 (branch_id, full_name, position, employee_code, experience_years, bio,
                  is_bookable, emergency_contact_phone, avatar, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW(), NOW())",
                [
                    $branchId, $fullName, $position ?: null, $employeeCode ?: null,
                    $expYears, $bio ?: null, $isBookable, $emgPhone,
                    uploadImage('avatar', 'staff'),
                ]
            );
        } catch (\PDOException $e) {
            error_log('[staff.create] ' . $e->getMessage());
            $this->flash('error', 'Không thể thêm nhân viên. Vui lòng kiểm tra lại dữ liệu.');
            $this->redirect('/salon/staff');
            return;
        }

        $this->flash('success', 'Đã thêm nhân viên ' . $fullName . '.');
        $this->redirect('/salon/staff');
    }
}
