# Kiểm chứng bản nhân viên và đặt lịch

Tài liệu tổng hợp ngày 07/10/2026. Kết quả kiểm thử nghiệp vụ dưới đây được chạy ngày 04/10/2026 và lưu trong nhật ký; không ghi nhận chúng là một lượt chạy mới ngày 07/10.

| Kiểm tra | Kết quả |
|---|---|
| Runtime | PHP 8.3.33, Laravel 13.34.0, MySQL 8.4.3 |
| SQLite feature tests | PASS: 44 tests, 172 assertions |
| MySQL feature tests | PASS: 44 tests, 172 assertions |
| Schema | 43 migrations; đủ 42 bảng nghiệp vụ nguồn |
| Đặt lịch đồng thời trên MySQL | PASS: 2 tiến trình, 1 lịch tạo thành công, 1 xung đột, 1 dòng được lưu |
| Blade view:cache | PASS |
| route:list | PASS: 35 route không tính vendor/health |

MySQL dùng datadir thử riêng, loopback 127.0.0.1:33317. Không kết nối hoặc sửa database cũ. Bản ZIP loại .env, vendor PHP và dữ liệu/cache/log thử.

## Phạm vi kiểm thử nghiệp vụ

Authentication, phân quyền và dịch vụ: 26 tests. Nhân viên/lịch làm: 7 tests. Đặt lịch: 11 tests. Kiểm tra validation, phạm vi chi nhánh, quyền cập nhật, ca làm/ngày nghỉ, lịch trùng, token gửi lại, chuyển trạng thái, lịch hết hạn và xác nhận tự động. Thử đồng thời nằm riêng trong tests/Support/concurrency.php.

Migrations được thực thi trong suite với RefreshDatabase trên database thử. Chỉ MySQL là môi trường được dùng để kết luận về khóa chống đặt trùng; SQLite không chứng minh hành vi đồng thời trên MySQL.

## Kiểm chứng cũ vẫn liên quan

Ngày 03/10: HTTP thực đăng ký → dashboard → logout → login; thiếu CSRF trả 419. Bootstrap nội bộ HTTP 200, kiểm tra SHA384 bản 5.3.3. Quan sát login desktop và register mobile không tràn ngang. Composer audit không có advisory tại thời điểm đó; chưa chạy audit mới ngày 07/10.

## Giới hạn

Màn hình nhân viên và đặt lịch đã được render trong feature tests, chưa nghiệm thu trực quan đầy đủ trên trình duyệt/mobile. Chưa import dữ liệu thực. Chưa nghiệm thu thanh toán hoặc các module chưa chuyển. Kiểm thử hiện tại không tương đương cam kết không còn lỗi hoặc kiểm toán bảo mật toàn bộ hệ thống.

Ngày 07/10/2026: chạy lại Pint --test, kết quả PASS. Không thay đổi mã nghiệp vụ trong lượt hoàn thiện tài liệu và đóng gói này.

## Bổ sung voucher — kiểm thử thực ngày 07/10/2026

- SQLite toàn bộ suite: PASS, 51 tests / 202 assertions.
- MySQL toàn bộ suite trước khi thêm ca hết hạn/hết lượt: PASS, 50 tests / 195 assertions.
- MySQL chạy lại toàn bộ VoucherTest sau bổ sung: PASS, 7 tests / 30 assertions.
- Pint --test: PASS; Blade view:cache: PASS.
- Có kiểm thử giảm phần trăm/trần giảm, tiền không âm, rollback mã không hợp lệ, giới hạn mỗi khách, gửi lại token, trả lượt khi hủy/hết hạn, hết lượt/hết hạn/tạm ngừng và phân quyền tạo mã.
- Chưa có thử đồng thời riêng cho hai chi nhánh tranh lượt voucher cuối; chưa nghiệm thu trực quan màn hình voucher trên trình duyệt. Không dùng kết quả concurrency booking cũ để tuyên bố đã kiểm thử concurrency voucher.

## Thanh toán/hoàn tiền thủ công — kiểm thử thực 07/10/2026

- PHP 8.3.33 Laragon; dependencies cài từ composer.lock, không đổi phiên bản.
- Toàn bộ SQLite in-memory suite: PASS, 67 tests / 304 assertions. Nhật ký: payment-verification-results.txt.
- Thêm PaymentTest: 16 tests. Bao phủ thu từng phần/đủ tiền, chặn vượt giá sau voucher, đơn 0 đồng, số tiền không hợp lệ, mã chuyển khoản bắt buộc, token gửi lại và token đổi payload, giữ hạn mức hoàn khi chờ duyệt, từ chối giải phóng hạn mức, duyệt trước khi xác nhận trả, hoàn một phần/toàn bộ, không mở lại công nợ sau hoàn, hủy lịch vẫn giữ lịch sử thu, phân quyền khách/lễ tân/quản lý, sai chi nhánh/sai giao dịch, quyền hết hạn, tài khoản khóa và giao dịch cũ chưa đối soát.
- Màn hình thanh toán salon/khách và liên kết từ lịch hẹn được render qua HTTP feature tests, gồm trạng thái hoàn đã duyệt.
- Pint --test: PASS toàn dự án. Blade view:cache: PASS; đã view:clear sau kiểm tra. Route listing: đủ 6 routes mới (2 GET, 3 POST, 1 PATCH).
- Ban đầu test tài khoản khóa kỳ vọng 403; middleware hiện có chuyển về login (302). Đã sửa kỳ vọng theo hành vi thực, chạy lại toàn bộ suite thành công.
- Không tạo .env, không migrate hoặc truy cập database ứng dụng của người dùng. Cấu hình .php-test.ini chỉ phục vụ máy kiểm thử này, được gitignore; hướng dẫn chạy triển khai vẫn dùng PHP Laragon cấu hình của bạn.
- Chưa chạy suite thanh toán trên MySQL, chưa kiểm chứng đồng thời MySQL cho thu/hoàn. Khóa transaction được triển khai nhưng không dùng kết quả SQLite để tuyên bố đã xác minh concurrency. Chưa nghiệm thu trực quan desktop/mobile hoặc chuyển tiền thực.
