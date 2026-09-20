# 💅 GlowBook — Dự Án Đặt Lịch Làm Đẹp (PHP MVC Thuần)

> **Môn học:** Lập trình mã nguồn mở  
> **Kiến trúc:** PHP thuần theo mô hình MVC, Hướng đối tượng (OOP), PDO Prepared Statements (bảo mật chống SQL Injection & XSS).  
> **Cơ sở dữ liệu:** MySQL (`glowbook_db`).

---

## 🎯 Giới Thiệu Tính Năng

- 🌐 **Khách hàng (Customer & Guest):**
  - Xem danh sách salon, tìm kiếm theo tên, tỉnh/thành phố, dịch vụ.
  - Xem chi tiết salon, bảng giá dịch vụ, danh sách nhân viên, ảnh và đánh giá.
  - Đặt lịch trực tuyến (hỗ trợ cả khách có tài khoản và khách vãng lai không cần đăng nhập).
  - Quản lý lịch hẹn cá nhân, xem chi tiết, hủy lịch, đánh giá dịch vụ sau khi hoàn thành.
  - Kho mã giảm giá (Voucher).
- 🏪 **Quản lý Salon (Business Owner & Branch Manager):**
  - Bảng điều khiển thống kê: lịch hẹn hôm nay, doanh thu tháng, lịch chờ duyệt.
  - Quản lý lịch hẹn: Xác nhận (Confirm), Từ chối (Reject), Hoàn thành (Complete).
  - Quản lý dịch vụ: Thêm mới, phân loại danh mục, giá tiền, thời lượng.
  - Quản lý nhân viên: Thêm nhân viên, chức vụ, kinh nghiệm, ảnh đại diện, bật/tắt đặt hẹn.
  - Chấm công nhân viên theo ngày, giờ vào/ra, tính phút đi trễ.
  - Xem đánh giá của khách hàng, quản lý voucher khuyến mãi.
  - Cài đặt thông tin thương hiệu, logo, hotline, giờ mở cửa.
- 🛡️ **Quản trị hệ thống (Platform Admin):**
  - Giám sát tổng quan toàn sàn: số lượng user, salon, booking, doanh thu.
  - Xét duyệt hoặc tạm khóa các cơ sở Salon đăng ký mới.
  - Quản lý danh sách người dùng, phân quyền theo vai trò.
  - Kiểm duyệt nhận xét & đánh giá.
  - Cài đặt thông số cấu hình hệ thống.

---

## 🔑 Tài Khoản Thử Nghiệm (Demo Accounts)

Tất cả tài khoản đều đã có sẵn trong file seed `database/schema.sql`:

| Vai trò | Email | Mật khẩu | Chức năng chính |
|---|---|---|---|
| **Platform Admin** | `admin@glowbook.vn` | `Password123!` | Quản trị toàn hệ thống, duyệt salon |
| **Chủ Salon** | `owner@glowbook.vn` | `Password123!` | Quản lý salon Bella Spa, duyệt lịch hẹn, nhân viên |
| **Khách hàng** | `customer@glowbook.vn` | `Password123!` | Đặt lịch, xem lịch sử, gửi đánh giá |

*(Bạn cũng có thể đăng ký tài khoản mới trực tiếp trên giao diện web).*

---

## 🗄️ Bước 1: Tạo Database (Bắt buộc)

Trước khi chạy web, bạn cần nạp cơ sở dữ liệu:

### Cách A — Bằng phpMyAdmin (Khuyên dùng):
1. Bật MySQL trong XAMPP (hoặc MySQL Server của bạn).
2. Mở trình duyệt vào: `http://localhost/phpmyadmin`
3. Bấm **New** (Tạo mới) ở thanh bên trái → Nhập tên database: `glowbook_db` → Bấm **Create**.
4. Chọn database `glowbook_db` vừa tạo → Nhấp tab **SQL** (hoặc tab **Import**).
5. Mở file `database/schema.sql`, copy toàn bộ nội dung và dán vào ô SQL → Bấm **Go** (Thực hiện).

### Cách B — Bằng dòng lệnh (Terminal / CMD):
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS glowbook_db CHARACTER SET utf8mb4;"
mysql -u root -p glowbook_db < database/schema.sql
```
*(Nếu MySQL XAMPP không có mật khẩu, chỉ cần nhấn Enter khi được hỏi password)*.

---

## 🚀 Bước 2: Chạy Project Trong Visual Studio Code

Bạn có thể chọn **1 trong 2 cách** sau:

### 🌟 Cách 1: Chạy trực tiếp bằng PHP Built-in Server (Nhanh nhất)
1. Mở thư mục `beauty-booking-php` bằng **Visual Studio Code**.
2. Kiểm tra file `config/database.php`:
   - `DB_PASS`: Nếu MySQL của bạn có mật khẩu thì điền vào (XAMPP mặc định để trống `''`).
3. Mở **Terminal trong VS Code** (`Ctrl + ~` hoặc menu `Terminal -> New Terminal`) và chạy lệnh:
   ```bash
   php -S localhost:8000 server.php
   ```
4. Mở trình duyệt web truy cập:
   👉 **http://localhost:8000**

---

### 🌟 Cách 2: Chạy qua XAMPP (Apache)
1. Copy toàn bộ thư mục `beauty-booking-php` vào thư mục `htdocs` của XAMPP:
   ```
   C:\xampp\htdocs\beauty-booking-php
   ```
2. Mở **XAMPP Control Panel** → Bấm **Start** cả **Apache** và **MySQL**.
3. Mở trình duyệt web truy cập:
   👉 **http://localhost/beauty-booking-php/public/**

---

## 📂 Cấu Trúc Mã Nguồn (Chuẩn MVC)

```
beauty-booking-php/
├── app/
│   ├── controllers/      # Bộ điều khiển xử lý logic
│   │   ├── AdminController.php      # Quản trị hệ thống
│   │   ├── AuthController.php       # Đăng nhập, đăng ký, đăng xuất
│   │   ├── BookingController.php    # Quy trình đặt lịch, xác nhận, hủy
│   │   ├── CustomerController.php   # Dashboard khách hàng, hồ sơ, voucher
│   │   ├── PublicController.php     # Trang chủ, tìm kiếm, chi tiết salon
│   │   ├── ReviewController.php     # Đánh giá sau dịch vụ
│   │   ├── SalonController.php      # Dashboard & quản lý salon
│   │   ├── ServiceController.php    # Quản lý dịch vụ salon
│   │   └── StaffController.php      # Quản lý nhân viên
│   ├── models/           # Lớp thao tác dữ liệu (PDO Prepared Statements)
│   │   ├── BookingModel.php
│   │   ├── BusinessModel.php
│   │   ├── ServiceModel.php
│   │   └── UserModel.php
│   └── views/            # Giao diện người dùng (.php template)
│       ├── admin/        # Giao diện quản trị viên
│       ├── auth/         # Form đăng nhập, đăng ký
│       ├── customer/     # Giao diện lịch hẹn, profile khách hàng
│       ├── layouts/      # Layout tổng thể (Header, Navigation, Footer)
│       ├── public/       # Trang chủ, tìm kiếm, đặt lịch
│       └── salon/        # Giao diện quản trị dành cho chủ salon
├── config/
│   ├── app.php           # Cấu hình website, tự động nhận diện URL
│   ├── database.php      # Thông tin kết nối MySQL
│   └── routes.php        # Bảng điều hướng Router (URL -> Controller)
├── core/                 # Thư viện lõi tự viết (Mini MVC Framework)
│   ├── Controller.php    # Base Controller (render view, flash, CSRF, auth guard)
│   ├── Database.php      # PDO Database Singleton
│   ├── Model.php         # Base Model (CRUD, phân trang, soft delete)
│   ├── Router.php        # Dispatcher xử lý URL dạng RESTful /slug/{id}
│   └── Session.php       # Quản lý Session, phân quyền và bảo mật CSRF
├── database/
│   └── schema.sql        # Cấu trúc 28 bảng + dữ liệu demo đầy đủ
├── helpers/
│   └── functions.php     # Hàm tiện ích (format tiền, ngày, upload ảnh, XSS filter)
├── public/               # Thư mục gốc web công khai
│   ├── css/style.css     # Giao diện làm đẹp tông hồng sang trọng
│   ├── js/app.js         # JavaScript tương tác client
│   ├── uploads/          # Thư mục lưu trữ ảnh tải lên
│   ├── .htaccess         # URL Rewriting cho máy chủ Apache
│   └── index.php         # Entry Point chính của toàn ứng dụng
├── server.php            # Router script cho PHP Built-in Server trong VS Code
└── README.md             # Tài liệu hướng dẫn sử dụng
```

---

## 🛡️ Điểm Nổi Bật Về Kỹ Thuật (Bảo Vệ Đồ Án)

1. **Không phụ thuộc thư viện bên ngoài nặng nề:** Viết thuần 100% bằng PHP 8+ hướng đối tượng, dễ dàng giải thích từng dòng code khi giảng viên vấn đáp.
2. **Bảo mật cơ sở dữ liệu:** Toàn bộ truy vấn SQL đều dùng `PDO::prepare` kết hợp tham số ẩn danh `?` ngăn chặn triệt để SQL Injection.
3. **Bảo mật Form & Session:** 
   - Mã hoá mật khẩu bằng thuật toán chuẩn công nghiệp `BCRYPT` (`password_hash`).
   - Tự động sinh và kiểm tra mã token chống tấn công CSRF (`_csrf_token`).
   - Hàm `e()` chống tấn công Cross-Site Scripting (XSS).
   - Cơ chế chặn Session Fixation (`session_regenerate_id`).
4. **Hỗ trợ Transaction & Khóa:** Đặt lịch hẹn và thanh toán được thực hiện trong khối Database Transaction đảm bảo tính toàn vẹn dữ liệu (ACID).
