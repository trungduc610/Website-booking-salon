<!-- app/views/salon/services.php — Quản lý dịch vụ của salon -->
<div class="page-header">
  <div>
    <h1>✂️ Quản lý dịch vụ</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Form thêm dịch vụ mới -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-header">+ Thêm dịch vụ mới</div>
      <form action="/salon/services/create" method="POST" enctype="multipart/form-data">
        <?= Session::csrfField() ?>
        <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
        <input type="hidden" name="business_id" value="<?= $business['id'] ?>">

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Tên dịch vụ <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Cắt tóc tạo kiểu, Chăm sóc da..." required>
          </div>
          <div class="form-group">
            <label class="form-label">Danh mục <span style="color:var(--danger)">*</span></label>
            <select name="category_id" class="form-control" required>
              <option value="">-- Chọn danh mục --</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid-3">
          <div class="form-group">
            <label class="form-label">Giá (VNĐ) <span style="color:var(--danger)">*</span></label>
            <input type="number" name="price" class="form-control" placeholder="150000" min="0" step="1000" required>
          </div>
          <div class="form-group">
            <label class="form-label">Thời lượng (phút)</label>
            <input type="number" name="duration_minutes" class="form-control" value="60" min="5" step="5" required>
          </div>
          <div class="form-group">
            <label class="form-label">Ảnh minh hoạ</label>
            <input type="file" name="cover_image" accept="image/*">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mô tả chi tiết</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Chi tiết quy trình dịch vụ..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary">+ Thêm dịch vụ</button>
      </form>
    </div>

    <!-- Danh sách dịch vụ hiện có -->
    <div class="card">
      <div class="card-header">Danh sách dịch vụ hiện có (<?= count($services) ?>)</div>

      <?php if (empty($services)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <div style="font-size:2.5rem;margin-bottom:8px">💇</div>
          <p>Chưa có dịch vụ nào. Hãy thêm dịch vụ ở biểu mẫu phía trên!</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Dịch vụ</th>
                <th>Danh mục</th>
                <th>Thời lượng</th>
                <th>Giá niêm yết</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $s): ?>
                <tr>
                  <td>
                    <div style="font-weight:600"><?= e($s['name']) ?></div>
                    <?php if (!empty($s['description'])): ?>
                      <small style="color:var(--text-light)"><?= truncate(e($s['description']), 60) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= e($s['category_name'] ?? 'Chưa phân loại') ?></td>
                  <td>⏱ <?= formatDuration($s['duration_minutes']) ?></td>
                  <td><strong style="color:var(--primary)"><?= formatMoney($s['price']) ?></strong></td>
                  <td>
                    <?= $s['status'] === 'ACTIVE' ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-gray">Tạm ngưng</span>' ?>
                  </td>
                  <td>
                    <form action="/salon/services/<?= $s['id'] ?>/delete" method="POST" style="display:inline">
                      <?= Session::csrfField() ?>
                      <button type="submit" class="btn btn-danger btn-sm" data-confirm="Bạn có chắc muốn xoá dịch vụ này?">Xoá</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
