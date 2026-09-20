<!-- app/views/auth/login.php -->
<div style="max-width:420px;margin:60px auto">
  <div class="card">
    <div style="text-align:center;margin-bottom:28px">
      <div style="font-size:3rem">💅</div>
      <h1 style="font-size:1.6rem;font-weight:700;margin:8px 0 4px">Đăng nhập</h1>
      <p style="color:var(--text-light)">Chào mừng quay lại <?= APP_NAME ?>!</p>
    </div>

    <form action="/login" method="POST">
      <?= Session::csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input id="email" type="email" name="email" class="form-control"
               placeholder="your@email.com" required autofocus
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mật khẩu</label>
        <input id="password" type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
        Đăng nhập →
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;color:var(--text-light);font-size:0.9rem">
      Chưa có tài khoản?
      <a href="/register" style="font-weight:600">Đăng ký ngay</a>
    </div>

    <hr style="margin:20px 0;border-color:var(--border)">
    <div style="font-size:0.82rem;color:var(--text-light);text-align:center">
      <strong>Tài khoản demo:</strong><br>
      Admin: admin@glowbook.vn / Password123!
    </div>
  </div>
</div>
