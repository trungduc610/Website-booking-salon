<!-- app/views/customer/profile.php — Hồ sơ cá nhân -->
<div style="max-width:640px;margin:0 auto">
  <div class="page-header">
    <h1>👤 Hồ sơ cá nhân</h1>
  </div>

  <div class="card">
    <form action="/customer/profile/update" method="POST" enctype="multipart/form-data">
      <?= Session::csrfField() ?>

      <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
        <?php if (!empty($user['avatar'])): ?>
          <img id="avatar-preview" src="<?= uploadUrl($user['avatar']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
        <?php else: ?>
          <div id="avatar-preview" style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem">
            <?= strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <label class="form-label">Ảnh đại diện</label>
          <input type="file" name="avatar" accept="image/*" data-preview="avatar-preview">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Họ và tên <span style="color:var(--danger)">*</span></label>
        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name'] ?? '') ?>" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Email (không thể đổi)</label>
          <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" disabled style="background:#edf2f7;cursor:not-allowed">
        </div>
        <div class="form-group">
          <label class="form-label">Số điện thoại</label>
          <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Ngày sinh</label>
          <input type="date" name="date_of_birth" class="form-control" value="<?= e($user['date_of_birth'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Giới tính</label>
          <select name="gender" class="form-control">
            <option value="">-- Chọn --</option>
            <option value="FEMALE" <?= ($user['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Nữ</option>
            <option value="MALE" <?= ($user['gender'] ?? '') === 'MALE' ? 'selected' : '' ?>>Nam</option>
            <option value="OTHER" <?= ($user['gender'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Khác</option>
          </select>
        </div>
      </div>

      <div style="margin-top:10px">
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>
