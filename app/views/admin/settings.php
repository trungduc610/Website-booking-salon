<!-- app/views/admin/settings.php — Cài đặt cấu hình platform -->
<div class="page-header">
  <div>
    <h1>⚙️ Cài đặt hệ thống</h1>
    <p style="color:var(--text-light)">Các tham số cấu hình toàn sàn GlowBook</p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <div class="card">
      <div class="card-header">Danh sách thông số cấu hình</div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Khoá cấu hình (Key)</th>
              <th>Giá trị hiện tại (JSON)</th>
              <th>Cập nhật lần cuối</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($settings as $st): ?>
              <tr>
                <td><code><?= e($st['key']) ?></code></td>
                <td><pre style="background:var(--bg);padding:6px 10px;border-radius:4px;font-size:0.85rem;margin:0;max-width:400px;overflow-x:auto"><?= e($st['value']) ?></pre></td>
                <td><?= formatDate($st['updated_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
