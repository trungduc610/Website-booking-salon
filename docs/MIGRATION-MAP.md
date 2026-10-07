# Ánh xạ schema Laravel

Nguồn: database/schema.sql trong glowbook-audited.zip. Hiện có 44 migrations tạo đủ 42 bảng nghiệp vụ nguồn trên database mới. Các migration bổ sung constraint/token không tạo thêm bảng nghiệp vụ.

| Nhóm | Thay đổi |
|---|---|
| ID/FK | Giữ INT UNSIGNED tương thích nguồn |
| users | Giữ password_hash, email/phone UNIQUE, soft delete; thêm remember_token nullable; bỏ index email/phone dư thừa |
| user_roles | Bổ sung FK scope/người cấp; generated scope keys COALESCE(...,0) và UNIQUE ngăn role trùng khi scope NULL |
| bookings.voucher_id | Bổ sung FK; tạo vouchers trước bookings |
| bookings.request_token | UUID nullable UNIQUE chống gửi lại yêu cầu |
| Thời gian booking | CHECK end > start trên MySQL, kết hợp validation nghiệp vụ |
| Các bảng còn lại | Chuyển columns, enums, decimal, indexes và FK theo thứ tự phụ thuộc |
| Tên index | Tiền tố tên bảng để tránh trùng tên trên SQLite |
| Seed | 11 vai trò; không seed PII hoặc mật khẩu mẫu |

Schema nền tạo database mới từ ứng dụng nguồn. Migration 2026_10_07_000102 nâng cấp cộng thêm từ bản voucher Laravel, không thay đổi dữ liệu đã có. Chưa import/đối soát dữ liệu thật. Voucher nhập mã trực tiếp và thanh toán/hoàn tiền thủ công đã có nghiệp vụ Laravel. Ví voucher, cổng thanh toán trực tuyến, review/media còn chưa triển khai.

Trước import: restore bản sao, kiểm tra orphan FK, enum, email/phone trùng, thứ tự thời gian và scope quyền. Từ chối ID bằng 0 vì 0 đại diện NULL trong khóa scope. Bỏ cột generated khỏi INSERT, giữ password_hash hợp lệ.

FK riêng lẻ không chứng minh branch thuộc business của grant. Policy dùng business_id thật của Branch; công cụ nhập/cấp quyền tương lai phải kiểm tra quan hệ đó trước khi ghi.

Thanh toán: payments.request_token và refund_requests.request_token là UUID nullable UNIQUE để tương thích dòng cũ; các thao tác mới bắt buộc token. payments.recorded_by và refund_requests.processed_by tham chiếu users; refund_requests.transaction_ref lưu mã giao dịch trả tiền. Không backfill hoặc suy đoán người thao tác lịch sử.
