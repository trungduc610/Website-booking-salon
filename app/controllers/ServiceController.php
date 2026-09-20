<?php
// ============================================================
//  app/controllers/ServiceController.php  — BẢN VÁ
//
//  LỖ HỔNG ĐÃ VÁ (IDOR đa người thuê / multi-tenant):
//  Bản cũ chỉ kiểm tra "user có role BUSINESS_OWNER không", rồi:
//     - create(): lấy branch_id và business_id THẲNG TỪ FORM POST
//       → chủ salon A gửi branch_id của salon B là chèn được dịch vụ vào salon B.
//     - update()/delete(): findById($id) mà không hỏi "dịch vụ này của ai?"
//       → đổi {id} trên URL là sửa/xoá được bảng giá của mọi salon khác.
//
//  Bản mới: mọi branch_id đều được đối chiếu với danh sách chi nhánh
//  mà người dùng thực sự sở hữu (BranchAccess::idsFor()).
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/ServiceModel.php';
require_once BASE_PATH . '/app/support/BranchAccess.php';

class ServiceController extends Controller
{
    private ServiceModel $services;

    public function __construct()
    {
        $this->services = new ServiceModel();
    }

    // POST /salon/services/create
    public function create(): void
    {
        $this->requireRole(['BUSINESS_OWNER', 'BRANCH_MANAGER']);
        $this->verifyCsrf();

        $branchId = (int)$this->input('branch_id', 0);

        // ---- CHỐT BẢO MẬT: chi nhánh phải thuộc về user đang đăng nhập ----
        if (!BranchAccess::owns($branchId)) {
            $this->abort(403, 'Bạn không có quyền thao tác trên chi nhánh này.');
        }
        // business_id KHÔNG lấy từ form nữa mà suy ra từ chi nhánh
        $businessId = BranchAccess::businessIdOf($branchId);

        $categoryId  = (int)$this->input('category_id', 0);
        $name        = mb_substr(trim((string)$this->input('name')), 0, 200);
        $description = mb_substr(trim((string)$this->input('description')), 0, 2000);
        $price       = (float)$this->input('price', 0);
        $duration    = (int)$this->input('duration_minutes', 60);

        $errors = [];
        if (mb_strlen($name) < 2)                 $errors[] = 'Tên dịch vụ phải có ít nhất 2 ký tự.';
        if ($price < 0 || $price > 999999999)     $errors[] = 'Giá dịch vụ không hợp lệ.';
        if ($duration < 5 || $duration > 600)     $errors[] = 'Thời lượng phải từ 5 đến 600 phút.';

        // Danh mục phải thuộc đúng salon này
        $cat = \Database::getInstance()->queryOne(
            "SELECT id FROM service_categories
             WHERE id = ? AND business_id = ? AND deleted_at IS NULL LIMIT 1",
            [$categoryId, $businessId]
        );
        if (!$cat) $errors[] = 'Danh mục dịch vụ không hợp lệ.';

        if ($errors) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/salon/services');
            return;
        }

        $this->services->create([
            'branch_id'        => $branchId,
            'business_id'      => $businessId,
            'category_id'      => $categoryId,
            'name'             => $name,
            'description'      => $description ?: null,
            'price'            => $price,
            'duration_minutes' => $duration,
            'bookable'         => 1,
            'status'           => 'ACTIVE',
            'cover_image'      => uploadImage('cover_image', 'services'),
        ]);

        $this->flash('success', 'Đã thêm dịch vụ mới.');
        $this->redirect('/salon/services');
    }

    // POST /salon/services/{id}/edit
    public function update(int $id): void
    {
        $this->requireRole(['BUSINESS_OWNER', 'BRANCH_MANAGER']);
        $this->verifyCsrf();

        $service = $this->services->findById($id);
        if (!$service) $this->abort(404, 'Dịch vụ không tồn tại.');

        // ---- CHỐT BẢO MẬT ----
        if (!BranchAccess::owns((int)$service['branch_id'])) {
            $this->abort(403, 'Dịch vụ này không thuộc salon của bạn.');
        }

        $name     = mb_substr(trim((string)$this->input('name')), 0, 200);
        $price    = (float)$this->input('price', 0);
        $duration = (int)$this->input('duration_minutes', 60);

        if (mb_strlen($name) < 2 || $price < 0 || $duration < 5 || $duration > 600) {
            $this->flash('error', 'Dữ liệu dịch vụ không hợp lệ.');
            $this->redirect('/salon/services');
            return;
        }

        // Nếu đổi danh mục thì danh mục mới cũng phải thuộc salon này
        $categoryId = (int)$this->input('category_id', 0);
        $cat = \Database::getInstance()->queryOne(
            "SELECT id FROM service_categories
             WHERE id = ? AND business_id = ? AND deleted_at IS NULL LIMIT 1",
            [$categoryId, (int)$service['business_id']]
        );

        $data = [
            'name'             => $name,
            'price'            => $price,
            'duration_minutes' => $duration,
            'description'      => mb_substr(trim((string)$this->input('description')), 0, 2000) ?: null,
            'status'           => $this->input('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ];
        if ($cat) $data['category_id'] = $categoryId;

        $cover = uploadImage('cover_image', 'services');
        if ($cover) $data['cover_image'] = $cover;

        $this->services->update($id, $data);
        $this->flash('success', 'Đã cập nhật dịch vụ.');
        $this->redirect('/salon/services');
    }

    // POST /salon/services/{id}/delete
    public function delete(int $id): void
    {
        $this->requireRole(['BUSINESS_OWNER', 'BRANCH_MANAGER']);
        $this->verifyCsrf();

        $service = $this->services->findById($id);
        if (!$service) $this->abort(404, 'Dịch vụ không tồn tại.');

        // ---- CHỐT BẢO MẬT ----
        if (!BranchAccess::owns((int)$service['branch_id'])) {
            $this->abort(403, 'Dịch vụ này không thuộc salon của bạn.');
        }

        // Không cho xoá khi còn lịch hẹn chưa phục vụ xong → tránh mồ côi dữ liệu
        $pending = \Database::getInstance()->queryOne(
            "SELECT bs.id FROM booking_services bs
             JOIN bookings bk ON bk.id = bs.booking_id
             WHERE bs.service_id = ?
               AND bk.status IN ('PENDING','CONFIRMED','CHECKED_IN','IN_PROGRESS')
               AND bk.deleted_at IS NULL LIMIT 1",
            [$id]
        );
        if ($pending) {
            $this->flash('warning', 'Dịch vụ đang có lịch hẹn chưa hoàn tất. Hãy chuyển sang trạng thái "Ngừng bán" thay vì xoá.');
            $this->redirect('/salon/services');
            return;
        }

        $this->services->delete($id);
        $this->flash('success', 'Đã xoá dịch vụ.');
        $this->redirect('/salon/services');
    }
}
