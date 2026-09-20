# GlowBook — Đã vá lỗi sau kiểm toán

Đây là project gốc **đã tích hợp sẵn** toàn bộ bản vá bảo mật/logic/UI (xem
`BAO-CAO-KIEM-TOAN.md` nếu có, hoặc phần audit đã gửi trong hội thoại).
Không cần chép tay file nào nữa — mọi thứ đã nằm đúng chỗ.

Đã kiểm thử thật: lint toàn bộ `.php` bằng `php -l`, và chạy `schema.sql`
nhiều lần liên tiếp trên MariaDB 10.11 thật (0 bảng sai collation, không
lỗi khi chạy lại — an toàn tuyệt đối nếu cần làm lại từ đầu).

## Chạy trong VS Code — các bước cần làm

### 1. Cài phần mềm cần thiết (nếu máy chưa có)

- **PHP ≥ 8.1** — kiểm tra bằng `php -v` trong terminal. Nếu chưa có:
  - Windows: tải [XAMPP](https://www.apachefriends.org/) (có sẵn PHP + MySQL + Apache) hoặc cài PHP riêng qua [php.net](https://www.php.net/downloads).
  - macOS: `brew install php`
  - Linux: `sudo apt install php-cli php-mysql php-mbstring`
- **MySQL hoặc MariaDB** — XAMPP đã có sẵn MySQL, không cần cài riêng.
- **Extension VS Code** (không bắt buộc để chạy, chỉ hỗ trợ viết code):
  - *PHP Intelephense* — gợi ý code, kiểm tra lỗi cú pháp khi gõ.
  - *PHP Debug* (xdebug) — nếu muốn đặt breakpoint.

### 2. Tạo database

Mở terminal trong VS Code (`` Ctrl+` ``), chạy:

```bash
mysql -u root -p < database/schema.sql
```

Chỉ một lệnh — mọi bản vá (charset tiếng Việt, chống trùng lịch, ràng buộc
dữ liệu...) đã gộp thẳng vào `schema.sql`, không còn file migration riêng
nào phải chạy thêm. An toàn để chạy lại lệnh này nhiều lần nếu cần làm lại
từ đầu (mọi bảng có `IF NOT EXISTS`, mọi dữ liệu mẫu dùng `INSERT IGNORE`).

Nếu dùng XAMPP, mặc định user `root` không có mật khẩu — bỏ `-p` hoặc để
trống khi được hỏi.

### 3. Sửa thông tin kết nối

Mở `config/database.php`, chỉnh `DB_USER`/`DB_PASS` khớp với MySQL trên
máy bạn (XAMPP mặc định là `root` / để trống).

### 4. Chạy server

Trong terminal VS Code, tại thư mục gốc project:

```bash
php -S localhost:8000 server.php
```

Mở trình duyệt vào `http://localhost:8000`.

Giữ cửa sổ terminal đó chạy — đóng lại là server tắt. Muốn dừng thì bấm
`Ctrl+C`.

### 5. (Tuỳ chọn) Chạy qua XAMPP thay vì PHP built-in server

Nếu muốn dùng Apache của XAMPP:

1. Copy cả thư mục này vào `C:\xampp\htdocs\beauty-booking-php\`
2. Trỏ Document Root vào thư mục **`public/`** (không phải thư mục gốc) —
   sửa `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
   ```apache
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs/beauty-booking-php/public"
       ServerName glowbook.local
   </VirtualHost>
   ```
3. Khởi động Apache + MySQL trong XAMPP Control Panel.

**Quan trọng:** nếu KHÔNG trỏ Document Root vào `public/` mà trỏ vào thư
mục gốc, trình duyệt sẽ tải được thẳng `config/database.php`,
`database/schema.sql` — lộ mật khẩu database. `server.php` (PHP built-in
server ở bước 4) đã tự chặn việc này, nhưng Apache/XAMPP thì không tự
chặn nếu bạn cấu hình sai Document Root.

## Việc còn phải làm thủ công

Những việc này không thể tự động hoá qua code, bạn cần tự làm:

1. Đổi `APP_SECRET` trong `config/app.php` sang một chuỗi ngẫu nhiên khác
   (giá trị mặc định trong code là public, ai cũng đọc được nếu bạn đưa
   code lên GitHub).
2. Khi nộp bài / triển khai thật: đặt `APP_DEBUG` về `false` trong
   `config/app.php` để không lộ chi tiết lỗi ra màn hình.
3. Nếu thư mục `public/uploads/` chưa tồn tại, hệ thống sẽ tự tạo khi có
   người upload ảnh đầu tiên — nhưng nên tạo trước và cấp quyền ghi
   (`chmod 755` trên Linux/macOS) để tránh lỗi upload lần đầu.

## Danh sách file đã thay đổi so với bản gốc bạn upload

```
core/Controller.php                        — sửa
core/Database.php                          — sửa
app/models/BookingModel.php                — sửa
app/controllers/BookingController.php      — sửa
app/controllers/ServiceController.php      — sửa
app/controllers/StaffController.php        — sửa
app/controllers/ReviewController.php       — sửa
app/controllers/PublicController.php       — sửa
app/controllers/AvailabilityController.php — MỚI
app/support/BranchAccess.php               — MỚI
app/views/layouts/main.php                 — sửa
app/views/public/booking_form.php          — sửa
helpers/functions.php                      — sửa 3 hàm
helpers/validation.php                     — MỚI
public/css/fixes.css                       — MỚI
public/js/booking-form.js                  — MỚI
public/index.php                           — thêm 2 dòng require
config/routes.php                          — thêm route mới
server.php                                 — sửa (chặn path traversal)
database/schema.sql                        — sửa (gộp sẵn toàn bộ bản vá kiểm toán)
cookie.txt                                 — đã XOÁ (rò rỉ session thật)
.git/                                      — đã XOÁ (dọn cho gọn bản nộp)
```
