<!-- app/views/admin/salons.php — Quản lý danh sách salon toàn hệ thống -->
<div class="page-header">
  <div>
    <h1>🏪 Quản lý Salon</h1>
    <p style="color:var(--text-light)">Xét duyệt và quản lý trạng thái các đối tác salon</p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Bộ lọc -->
    <div class="card" style="margin-bottom:20px">
      <form method="GET" action="/admin/salons" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:2;min-width:200px">
          <label class="form-label">Tìm kiếm</label>
          <input type="text" name="q" class="form-control" placeholder="Tên salon, email chủ..." value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-control">
            <option value="">Tất cả</option>
            <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Chờ duyệt</option>
            <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Đang hoạt động</option>
            <option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Đã từ chối</option>
            <option value="SUSPENDED" <?= $status === 'SUSPENDED' ? 'selected' : '' ?>>Đang tạm khóa</option>
          </select>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary">Lọc</button>
          <a href="/admin/salons" class="btn btn-white">Đặt lại</a>
        </div>
      </form>
    </div>

    <!-- Bảng salon -->
    <div class="card">
      <?php if (empty($result['data'])): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <p>Không tìm thấy salon nào phù hợp.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tên Salon</th>
                <th>Chủ sở hữu</th>
                <th>Liên hệ</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result['data'] as $s): ?>
                <tr>
                  <td>
                    <div style="font-weight:600"><?= e($s['name']) ?></div>
                    <small><a href="/salon/<?= e($s['slug']) ?>" target="_blank" style="color:var(--secondary)">Xem trang công khai ↗</a></small>
                  </td>
                  <td>
                    <?= e($s['owner_name']) ?><br>
                    <small style="color:var(--text-light)"><?= e($s['owner_email']) ?></small>
                  </td>
                  <td>
                    <?= e($s['contact_phone'] ?? '—') ?><br>
                    <small style="color:var(--text-light)"><?= e($s['contact_email'] ?? '') ?></small>
                  </td>
                  <td><?= businessStatusLabel($s['status']) ?></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <?php if (in_array($s['status'], ['PENDING', 'PENDING_REVIEW', 'REJECTED'])): ?>
                        <form method="POST" action="/admin/salons/<?= $s['id'] ?>/approve" style="display:inline">
                          <?= Session::csrfField() ?>
                          <button class="btn btn-success btn-sm">Duyệt</button>
                        </form>
                      <?php endif; ?>
                      <?php if ($s['status'] === 'ACTIVE'): ?>
                        <form method="POST" action="/admin/salons/<?= $s['id'] ?>/reject" style="display:inline">
                          <?= Session::csrfField() ?>
                          <input type="hidden" name="reason" value="Tạm khóa bởi quản trị viên">
                          <button class="btn btn-danger btn-sm" data-confirm="Tạm khóa salon này?">Khóa</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php $pUrl = '/admin/salons?q=' . urlencode($search) . '&status=' . urlencode($status); ?>
        <?= renderPagination($result, $pUrl) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
