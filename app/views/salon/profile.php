<!-- app/views/salon/profile.php — Cài đặt thông tin salon -->
<div class="page-header">
  <div>
    <h1>⚙️ Cài đặt salon</h1>
    <p style="color:var(--text-light)"><?= e($business['name']) ?> — <?= e($branch['name']) ?></p>
  </div>
</div>

<div class="sidebar-layout">
  <?php require __DIR__ . '/_nav.php'; ?>

  <div>
    <div class="card" style="margin-bottom:24px">
      <div class="card-header">Thông tin chung</div>

      <form action="/salon/profile/update" method="POST" enctype="multipart/form-data">
        <?= Session::csrfField() ?>

        <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
          <?php if (!empty($business['logo'])): ?>
            <img src="<?= uploadUrl($business['logo']) ?>" style="width:72px;height:72px;border-radius:10px;object-fit:cover;border:1px solid var(--border)">
          <?php else: ?>
            <div style="width:72px;height:72px;border-radius:10px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem">
              💅
            </div>
          <?php endif; ?>
          <div>
            <label class="form-label">Logo thương hiệu</label>
            <input type="file" name="logo" accept="image/*">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Tên thương hiệu Salon <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($business['name']) ?>" required>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email liên hệ</label>
            <input type="email" name="contact_email" class="form-control" value="<?= e($business['contact_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Số điện thoại liên hệ</label>
            <input type="tel" name="contact_phone" class="form-control" value="<?= e($business['contact_phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Giới thiệu về salon</label>
          <textarea name="description" class="form-control" rows="3"><?= e($business['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Lưu cài đặt</button>
      </form>
    </div>

    <!-- Giờ mở cửa -->
    <div class="card">
      <div class="card-header">⏰ Khung giờ hoạt động (Chi nhánh: <?= e($branch['name']) ?>)</div>
      <?php if (empty($workingHours)): ?>
        <p style="color:var(--text-light)">Chưa cài đặt khung giờ làm việc riêng biệt.</p>
      <?php else: ?>
        <?php
          $days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Thứ</th>
                <th>Giờ mở cửa</th>
                <th>Giờ đóng cửa</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($workingHours as $wh): ?>
                <tr>
                  <td><strong><?= $days[$wh['day_of_week']] ?? $wh['day_of_week'] ?></strong></td>
                  <td><?= substr($wh['open_time'], 0, 5) ?></td>
                  <td><?= substr($wh['close_time'], 0, 5) ?></td>
                  <td>
                    <?= $wh['is_closed'] ? '<span class="badge badge-danger">Nghỉ</span>' : '<span class="badge badge-success">Mở cửa</span>' ?>
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
