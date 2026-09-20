<!-- app/views/salon/staff.php — Quản lý nhân viên của salon -->
<div class="page-header">
  <div>
    <h1>👥 Quản lý nhân viên</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Form thêm nhân viên -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-header">+ Thêm nhân viên mới</div>
      <form action="/salon/staff/create" method="POST" enctype="multipart/form-data">
        <?= Session::csrfField() ?>
        <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">

        <div class="grid-3">
          <div class="form-group">
            <label class="form-label">Họ và tên <span style="color:var(--danger)">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="Nguyễn Văn B" required>
          </div>
          <div class="form-group">
            <label class="form-label">Vị trí / Chức vụ</label>
            <input type="text" name="position" class="form-control" placeholder="Chuyên viên làm tóc, KTV Spa...">
          </div>
          <div class="form-group">
            <label class="form-label">Mã nhân viên</label>
            <input type="text" name="employee_code" class="form-control" placeholder="NV-01">
          </div>
        </div>

        <div class="grid-3">
          <div class="form-group">
            <label class="form-label">Kinh nghiệm (năm)</label>
            <input type="number" name="experience_years" class="form-control" value="2" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Ảnh chân dung</label>
            <input type="file" name="avatar" accept="image/*">
          </div>
          <div class="form-group" style="display:flex;align-items:center;margin-top:24px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_bookable" value="1" checked style="width:18px;height:18px;accent-color:var(--primary)">
              <span style="font-size:0.9rem">Cho phép khách chọn đặt lịch</span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Giới thiệu ngắn (Bio)</label>
          <textarea name="bio" class="form-control" rows="2" placeholder="Sở trường, phong cách, tay nghề..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary">+ Thêm nhân viên</button>
      </form>
    </div>

    <!-- Danh sách nhân viên -->
    <div class="card">
      <div class="card-header">Danh sách nhân viên (<?= count($staff) ?>)</div>

      <?php if (empty($staff)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <div style="font-size:2.5rem;margin-bottom:8px">👤</div>
          <p>Chưa có nhân viên nào. Hãy thêm nhân viên ở biểu mẫu trên!</p>
        </div>
      <?php else: ?>
        <div class="grid-3">
          <?php foreach ($staff as $st): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center;background:var(--bg)">
              <?php if (!empty($st['avatar'])): ?>
                <img src="<?= uploadUrl($st['avatar']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin:0 auto 10px;border:2px solid var(--border)">
              <?php else: ?>
                <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 10px">
                  <?= strtoupper(mb_substr($st['full_name'], 0, 1)) ?>
                </div>
              <?php endif; ?>

              <h4 style="margin-bottom:4px"><?= e($st['full_name']) ?></h4>
              <div style="color:var(--text-light);font-size:0.85rem;margin-bottom:6px"><?= e($st['position'] ?? 'Nhân viên') ?></div>

              <div style="display:flex;gap:6px;justify-content:center;margin-top:8px">
                <?php if ($st['is_bookable']): ?>
                  <span class="badge badge-success">Đặt hẹn: Bật</span>
                <?php else: ?>
                  <span class="badge badge-gray">Đặt hẹn: Tắt</span>
                <?php endif; ?>
                <span class="badge badge-info"><?= (int)$st['experience_years'] ?> năm KN</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
