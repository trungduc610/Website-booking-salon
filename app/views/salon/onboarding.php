<!-- app/views/salon/onboarding.php — Đăng ký salon mới -->
<div style="max-width:680px;margin:0 auto">
  <div class="page-header">
    <div>
      <h1>🚀 Đăng ký mở Salon trên GlowBook</h1>
      <p style="color:var(--text-light)">Điền thông tin doanh nghiệp để chúng tôi xét duyệt nhanh chóng</p>
    </div>
  </div>

  <div class="card">
    <form action="/salon/profile/update" method="POST" enctype="multipart/form-data">
      <?= Session::csrfField() ?>

      <div class="form-group">
        <label class="form-label">Tên thương hiệu Salon <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="Ví dụ: Bella Spa & Nails" required>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Tên công ty / Hộ kinh doanh</label>
          <input type="text" name="company_name" class="form-control" placeholder="Công ty TNHH Bella...">
        </div>
        <div class="form-group">
          <label class="form-label">Mã số thuế</label>
          <input type="text" name="tax_code" class="form-control" placeholder="0101234567">
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Email liên hệ <span style="color:var(--danger)">*</span></label>
          <input type="email" name="contact_email" class="form-control" placeholder="contact@salon.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Số điện thoại hotline <span style="color:var(--danger)">*</span></label>
          <input type="tel" name="contact_phone" class="form-control" placeholder="0901 234 567" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Địa chỉ trụ sở chính</label>
        <input type="text" name="address_line" class="form-control" placeholder="Số 123 đường ABC, Phường X, Quận Y...">
      </div>

      <div class="form-group">
        <label class="form-label">Mô tả giới thiệu salon</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Các dịch vụ thế mạnh, trang thiết bị, không gian..."></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%">Gửi hồ sơ đăng ký xét duyệt</button>
    </form>
  </div>
</div>
