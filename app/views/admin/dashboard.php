<!-- app/views/admin/dashboard.php — Bảng điều khiển Platform Admin -->
<div class="page-header">
  <div>
    <h1>🛡️ Bảng điều khiển Quản trị viên</h1>
    <p style="color:var(--text-light)">Giám sát toàn bộ hoạt động của hệ thống GlowBook</p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <!-- Thống kê hệ thống -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Tổng người dùng</div>
        <div class="stat-value"><?= $stats['total_users'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🏪</div>
        <div class="stat-label">Tổng số Salon</div>
        <div class="stat-value"><?= $stats['total_salons'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">Salon chờ duyệt</div>
        <div class="stat-value" style="color:var(--warning)"><?= $stats['pending_salons'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-label">Lịch hẹn hôm nay</div>
        <div class="stat-value" style="color:var(--primary)"><?= $stats['today_bookings'] ?></div>
      </div>
    </div>

    <!-- Danh sách Salon chờ duyệt -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>⚠️ Salon cần xét duyệt (<?= count($pendingSalons) ?>)</span>
        <a href="/admin/salons?status=PENDING" style="font-size:0.85rem">Xem tất cả →</a>
      </div>

      <?php if (empty($pendingSalons)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-light)">
          <div style="font-size:2rem;margin-bottom:6px">✨</div>
          <p>Hiện không có salon nào chờ xét duyệt.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tên Salon</th>
                <th>Chủ sở hữu</th>
                <th>Hotline</th>
                <th>Ngày nộp</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingSalons as $s): ?>
                <tr>
                  <td><strong><?= e($s['name']) ?></strong></td>
                  <td>
                    <?= e($s['owner_name']) ?><br>
                    <small style="color:var(--text-light)"><?= e($s['owner_email']) ?></small>
                  </td>
                  <td><?= e($s['contact_phone'] ?? '—') ?></td>
                  <td><?= formatDate($s['created_at']) ?></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <form method="POST" action="/admin/salons/<?= $s['id'] ?>/approve" style="display:inline">
                        <?= Session::csrfField() ?>
                        <button class="btn btn-success btn-sm">Duyệt</button>
                      </form>
                      <form method="POST" action="/admin/salons/<?= $s['id'] ?>/reject" style="display:inline">
                        <?= Session::csrfField() ?>
                        <input type="hidden" name="reason" value="Hồ sơ chưa đạt tiêu chuẩn">
                        <button class="btn btn-danger btn-sm" data-confirm="Bạn chắc chắn muốn từ chối salon này?">Từ chối</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Người dùng mới đăng ký -->
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span>👤 Người dùng mới đăng ký</span>
        <a href="/admin/users" style="font-size:0.85rem">Quản lý người dùng →</a>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Họ tên</th>
              <th>Email</th>
              <th>Vai trò</th>
              <th>Ngày tham gia</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentUsers as $u): ?>
              <tr>
                <td><strong><?= e($u['full_name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge badge-info"><?= e($u['role_code'] ?? 'CUSTOMER') ?></span></td>
                <td><?= formatDate($u['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
