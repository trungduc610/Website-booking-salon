# Kiểm thử đồng thời trên MySQL riêng

Harness: `tests/Support/concurrency.php`. Cần PHP có `pdo_mysql`, MySQL 8 với `performance_schema=ON`, và schema **chỉ dùng cho kiểm thử** tên chính xác `glowbook_test`. Không chạy khi ứng dụng hoặc một bộ kiểm thử khác đang dùng schema này.

Script không đọc `.env` ứng dụng và bỏ qua cache cấu hình/routes/events. Mỗi tiến trình kiểm tra lại driver, schema cấu hình và `SELECT DATABASE()` trước khi ghi. Không dùng SQLite, `migrate:fresh`, rollback migration, truncate hay tắt foreign keys. Fixture được commit để hai tiến trình thấy được; cuối mỗi ca chỉ xóa các bản ghi do ca đó tạo. Nếu bị kill cưỡng bức, fixture có thể còn lại; chỉ xử lý trong database kiểm thử.

## Chuẩn bị database

Nhờ quản trị MySQL tạo schema `glowbook_test` và tài khoản kiểm thử riêng, chỉ có quyền trên schema này. Cấp thêm quyền đọc hai bảng quan sát khóa:

```sql
GRANT SELECT ON performance_schema.data_lock_waits TO 'glowbook_test_runner'@'localhost';
GRANT SELECT ON performance_schema.threads TO 'glowbook_test_runner'@'localhost';
```

Tài khoản cần quyền DDL trên `glowbook_test` để chạy migration và DML để tạo/xóa fixture. Nếu không có quyền quan sát khóa, script thất bại và không báo concurrency PASS. Không dùng tài khoản ứng dụng hoặc trỏ tới database ứng dụng. Có thể dùng instance MySQL tạm riêng trên cổng khác với datadir mới; không dùng lại datadir của server ứng dụng.

## Chạy từ PowerShell

Thiết lập thông tin kết nối riêng trong phiên terminal; không lưu mật khẩu vào repository hoặc đưa vào command line. `Read-Host` che mật khẩu khi nhập:

```powershell
$env:GLOWBOOK_CONCURRENCY_TEST = '1'
$env:GLOWBOOK_TEST_MYSQL_DATABASE = 'glowbook_test'
$env:GLOWBOOK_TEST_MYSQL_HOST = '127.0.0.1'
$env:GLOWBOOK_TEST_MYSQL_PORT = '3306' # đổi sang cổng instance kiểm thử nếu cần
$env:GLOWBOOK_TEST_MYSQL_USERNAME = 'glowbook_test_runner'
$env:GLOWBOOK_TEST_MYSQL_PASSWORD = Read-Host 'Mật khẩu MySQL kiểm thử' -MaskInput

php tests/Support/concurrency.php --migrate
if ($LASTEXITCODE -ne 0) { throw 'Migration kiểm thử thất bại' }
php tests/Support/concurrency.php
if ($LASTEXITCODE -ne 0) { throw 'Kiểm thử concurrency thất bại' }
Remove-Item Env:GLOWBOOK_TEST_MYSQL_PASSWORD -ErrorAction SilentlyContinue
```

`--migrate` chỉ áp dụng migration còn thiếu lên schema đã qua kiểm tra bảo vệ; không tạo schema và không reset dữ liệu. Dùng script này thay vì `php artisan test` cho concurrency: cấu hình PHPUnit thông thường dùng SQLite. Các bài kiểm tra bảo vệ cấu hình có thể chạy riêng bằng `php vendor/bin/phpunit tests/Feature/ConcurrencySafetyTest.php`; chúng từ chối trước bootstrap/kết nối DB.

## Điều kiện PASS

Hai ca chạy hai tiến trình PHP và hai kết nối MySQL riêng:

- `staff-slot`: hai khách đặt cùng nhân viên, cùng ngày và khoảng 10:00–11:00. Tiến trình đầu giữ khóa chi nhánh; tiến trình thứ hai phải xuất hiện trong `performance_schema.data_lock_waits` trước khi mở điểm đồng bộ. Một lịch thành công, yêu cầu còn lại lỗi `time`.
- `voucher-last-use`: hai chi nhánh khác nhau với nhân viên/dịch vụ riêng, cùng tranh voucher còn đúng một lượt. Tiến trình đầu giữ khóa voucher; phải quan sát tiến trình kia chờ chính khóa đó. Một lịch thành công, yêu cầu còn lại lỗi `voucher_code`.

Mỗi ca còn kiểm tra:

1. Gây lỗi sau khi đã trừ lượt và ghi đủ booking, booking_service, status_history trong transaction. Kiểm chứng rollback xóa toàn bộ ba loại dữ liệu, token và hoàn trả lượt voucher.
2. Gửi lại chính token vừa rollback để đặt thành công; gửi lại token thành công thêm hai lần phải trả cùng booking ID, dù voucher đã hết lượt.
3. Sau tranh chấp và replay chỉ tăng đúng một booking, một dịch vụ, một history; token thua không tồn tại; `used_quantity = total_quantity = 1`.

Harness chỉ thêm điểm đồng bộ/điểm gây lỗi trong tiến trình kiểm thử qua query listener. Logic đặt lịch, transaction và thứ tự khóa chi nhánh/voucher của ứng dụng không thay đổi. Timeout, lỗi tiến trình, thiếu quyền hoặc không thấy lock wait đều là FAIL (exit 1), không được coi là kết quả concurrency hợp lệ.
## Kết quả xác minh ngày 10/10/2026

Đã chạy cả hai ca trên MySQL Community 8.0.46 tạm riêng, cổng 13317, datadir riêng, schema `glowbook_test`. Đã xác minh bằng tài khoản chỉ có quyền trên schema kiểm thử và hai bảng quan sát khóa nêu trên. Cả hai ca PASS, có lock wait thực được MySQL ghi nhận; rollback và replay đạt yêu cầu. Sau cleanup, bookings, booking_services, booking_status_histories, vouchers, businesses, branches và users đều có 0 bản ghi. Instance tạm đã được tắt và datadir tạm đã được dọn.

Kiểm thử hồi quy `ConcurrencySafetyTest`, `BookingTest`, `BookingIdempotencyTest`, `VoucherTest`: 38 tests / 215 assertions PASS; phần hồi quy thông thường dùng SQLite bộ nhớ riêng. Kết luận đồng thời ở trên đến từ harness MySQL, không từ PHPUnit/SQLite. Pint cho ba file PHP thay đổi PASS.

Giới hạn: harness yêu cầu MySQL 8 và quyền đọc `performance_schema`; kiểm chứng hai tranh chấp với hai tiến trình, không đo tải nhiều người dùng hoặc mọi kiểu deadlock.