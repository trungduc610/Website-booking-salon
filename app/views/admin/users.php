<!-- app/views/admin/users.php — Quản lý người dùng cho Platform Admin -->
<div class="page-header">
  <div>
    <h1>👥 Quản lý người dùng</h1>
    <p style="color:var(--text-light)">Danh sách tài khoản khách hàng, chủ salon và quản trị viên</p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Tìm kiếm -->
    <div class="card" style="margin-bottom:20px">
      <form method="GET" action="/admin/users" style="display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="margin:0;flex:1">
          <label class="form-label">Tìm kiếm người dùng</label>
          <input type="text" name="q" class="form-control" placeholder="Họ tên, email, số điện thoại..." value="<?= e($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Tìm</button>
        <a href="/admin/users" class="btn btn-white">Đặt lại</a>
      </form>
    </div>

    <!-- Bảng người dùng -->
    <div class="card">
      <?php if (empty($result['data'])): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light)">
          <p>Không tìm thấy người dùng nào phù hợp.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Họ và tên</th>
                <th>Email</th>
                <th>Số điện thoại</th>
                <th>Vai trò</th>
                <th>Ngày tạo</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($result['data'] as $u): ?>
                <tr>
                  <td><strong><?= e($u['full_name']) ?></strong></td>
                  <td><?= e($u['email']) ?></td>
                  <td><?= e($u['phone'] ?? '—') ?></td>
                  <td>
                    <?php
                      $badgeClass = match($u['role_code'] ?? 'CUSTOMER') {
                        'PLATFORM_ADMIN'   => 'badge-primary',
                        'BUSINESS_OWNER'   => 'badge-success',
                        'BRANCH_MANAGER'   => 'badge-warning',
                        'STAFF'            => 'badge-info',
                        default            => 'badge-gray',
                      };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= e($u['role_code'] ?? 'CUSTOMER') ?></span>
                  </td>
                  <td><?= formatDate($u['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php $pUrl = '/admin/users?q=' . urlencode($search); ?>
        <?= renderPagination($result, $pUrl) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
