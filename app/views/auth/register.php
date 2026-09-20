<!-- app/views/auth/register.php -->
<div style="max-width:480px;margin:48px auto">
  <div class="card">
    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:2.5rem">✨</div>
      <h1 style="font-size:1.5rem;font-weight:700;margin:8px 0 4px">Tạo tài khoản</h1>
      <p style="color:var(--text-light)">Đăng ký miễn phí để đặt lịch làm đẹp dễ dàng hơn</p>
    </div>

    <form action="/register" method="POST">
      <?= Session::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="full_name">Họ và tên <span style="color:var(--danger)">*</span></label>
        <input id="full_name" type="text" name="full_name" class="form-control"
               placeholder="Nguyễn Văn A" required autofocus
               value="<?= e($_POST['full_name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email <span style="color:var(--danger)">*</span></label>
        <input id="email" type="email" name="email" class="form-control"
               placeholder="your@email.com" required
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">Số điện thoại</label>
        <input id="phone" type="tel" name="phone" class="form-control"
               placeholder="0912 345 678"
               value="<?= e($_POST['phone'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mật khẩu <span style="color:var(--danger)">*</span></label>
        <input id="password" type="password" name="password" class="form-control"
               placeholder="Tối thiểu 8 ký tự" required minlength="8">
        <p class="form-hint">Ít nhất 8 ký tự, nên có chữ + số</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Xác nhận mật khẩu <span style="color:var(--danger)">*</span></label>
        <input id="password_confirm" type="password" name="password_confirm" class="form-control"
               placeholder="Nhập lại mật khẩu" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px">
        Đăng ký tài khoản →
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;color:var(--text-light);font-size:0.9rem">
      Đã có tài khoản?
      <a href="/login" style="font-weight:600">Đăng nhập</a>
    </div>
  </div>
</div>
