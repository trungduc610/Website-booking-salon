<!-- app/views/public/booking_form.php — Form đặt lịch -->

<div style="max-width:760px;margin:0 auto">

  <!-- Tiêu đề -->
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:28px">
    <?php if ($branch['logo']): ?>
      <img src="<?= uploadUrl($branch['logo']) ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
    <?php else: ?>
      <div style="width:56px;height:56px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem">💅</div>
    <?php endif; ?>
    <div>
      <h1 style="font-size:1.5rem;font-weight:700"><?= e($branch['business_name']) ?></h1>
      <p style="color:var(--text-light)">Chi nhánh: <?= e($branch['name']) ?></p>
    </div>
  </div>

  <form action="/book" method="POST" id="booking-form">
    <?= Session::csrfField() ?>
    <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">

    <!-- BƯỚC 1: Chọn dịch vụ -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">1️⃣ Chọn dịch vụ</div>
      <?php if (empty($allServices)): ?>
        <p style="color:var(--text-light)">Chi nhánh chưa có dịch vụ nào.</p>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($allServices as $svc): ?>
            <?php $checked = in_array($svc['id'], array_column($selectedServices, 'id')); ?>
            <label class="service-option<?= $checked ? ' is-selected' : '' ?>" style="display:flex;align-items:center;gap:12px">
              <input type="checkbox" name="service_ids[]" value="<?= $svc['id'] ?>"
                     data-price="<?= (float)$svc['price'] ?>" data-duration="<?= (int)$svc['duration_minutes'] ?>"
                     <?= $checked ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--primary)">
              <div style="flex:1">
                <strong><?= e($svc['name']) ?></strong>
                <span style="color:var(--text-light);font-size:0.85rem;margin-left:8px">⏱ <?= formatDuration($svc['duration_minutes']) ?></span>
              </div>
              <strong style="color:var(--primary)"><?= formatMoney($svc['price']) ?></strong>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- BƯỚC 2: Chọn nhân viên -->
    <?php if (!empty($staffList)): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">2️⃣ Chọn nhân viên <span style="font-weight:400;color:var(--text-light)">(không bắt buộc)</span></div>
      <div class="grid-3" style="gap:12px">
        <label class="staff-option is-selected" style="text-align:center">
          <input type="radio" name="staff_id" value="0" checked>
          <div style="font-size:2rem;margin-bottom:6px">🎲</div>
          <div style="font-size:0.85rem;font-weight:600">Bất kỳ</div>
          <div style="font-size:0.78rem;color:var(--text-light)">Tự động phân công</div>
        </label>
        <?php foreach ($staffList as $st): ?>
          <label class="staff-option" style="text-align:center">
            <input type="radio" name="staff_id" value="<?= $st['id'] ?>">
            <?php if ($st['avatar']): ?>
              <img src="<?= uploadUrl($st['avatar']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;margin:0 auto 8px">
            <?php else: ?>
              <div style="width:48px;height:48px;background:var(--secondary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;margin:0 auto 8px">
                <?= strtoupper(mb_substr($st['full_name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div style="font-size:0.85rem;font-weight:600"><?= e($st['full_name']) ?></div>
            <div style="font-size:0.78rem;color:var(--text-light)"><?= e($st['position'] ?? '') ?></div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- BƯỚC 3: Chọn ngày giờ -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">3️⃣ Chọn ngày và giờ</div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Ngày hẹn <span style="color:var(--danger)">*</span></label>
          <input type="date" name="appointment_date" class="form-control"
                 min="<?= date('Y-m-d') ?>"
                 max="<?= date('Y-m-d', strtotime('+90 days')) ?>"
                 value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Giờ bắt đầu <span style="color:var(--danger)">*</span></label>
          <select name="appointment_start_time" class="form-control" required>
            <?php for ($h = 8; $h <= 20; $h++): ?>
              <?php foreach (['00','30'] as $m): ?>
                <?php $time = sprintf('%02d:%s:00', $h, $m); ?>
                <option value="<?= $time ?>"><?= sprintf('%02d:%s', $h, $m) ?></option>
              <?php endforeach; ?>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <p style="font-size:0.85rem;color:var(--text-light);margin-top:10px">
        Thời lượng: <strong id="duration-display">—</strong>
        &nbsp;·&nbsp; Dự kiến kết thúc: <strong id="endtime-display">—</strong>
      </p>
      <p id="availability-note" class="form-hint"></p>

      <div class="form-group" style="margin-top:14px">
        <label class="form-label">Ghi chú</label>
        <textarea name="note" class="form-control" rows="2" placeholder="Yêu cầu đặc biệt, dị ứng, ..."></textarea>
      </div>
    </div>

    <!-- BƯỚC 4: Thông tin khách (nếu chưa đăng nhập) -->
    <?php if (!Session::isLoggedIn()): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">4️⃣ Thông tin liên hệ</div>
      <p style="color:var(--text-light);font-size:0.9rem;margin-bottom:16px">
        Bạn chưa đăng nhập. Nhập thông tin để nhận xác nhận lịch hẹn.
        <a href="/login">Đăng nhập</a> để trải nghiệm tốt hơn.
      </p>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Họ tên <span style="color:var(--danger)">*</span></label>
          <input type="text" name="guest_name" class="form-control" placeholder="Nguyễn Văn A" required>
        </div>
        <div class="form-group">
          <label class="form-label">Số điện thoại <span style="color:var(--danger)">*</span></label>
          <input type="tel" name="guest_phone" class="form-control" placeholder="0912 345 678" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email (để nhận xác nhận)</label>
        <input type="email" name="guest_email" class="form-control" placeholder="your@email.com">
      </div>
    </div>
    <?php endif; ?>

    <!-- Tổng kết & nút đặt -->
    <div class="card booking-summary" style="background:var(--bg);display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-size:0.85rem;color:var(--text-light)">Tổng tiền dự kiến</div>
        <div id="total-display" style="font-size:1.6rem;font-weight:700;color:var(--primary)">
          <?= formatMoney(array_sum(array_column($selectedServices, 'price'))) ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">
        ✅ Xác nhận đặt lịch
      </button>
    </div>

  </form>
</div>

<script src="/js/booking-form.js"></script>
