# GlowBook Laravel — voucher, thanh toán và hoàn tiền

Bản bàn giao cập nhật ngày 07/10/2026. Runtime đã kiểm thử: PHP 8.3.33 của Laragon, Laravel 13.34.0 và MySQL 8.4.3. Không cần thay Laragon để chạy bản này; cần chọn đúng PHP và bật extensions bên dưới.

## Chức năng đã chuyển

- Đăng ký, đăng nhập, đăng xuất POST; password_hash bcrypt, CSRF, validation, giới hạn đăng nhập và phân quyền theo doanh nghiệp/chi nhánh/thời hạn.
- Quản lý dịch vụ, danh mục theo doanh nghiệp, giá decimal, thời lượng và trạng thái nhận lịch.
- Hồ sơ nhân viên, phân công dịch vụ, lịch làm 7 ngày, nghỉ phép; giờ mở cửa và ngày đóng cửa chi nhánh.
- Danh sách salon, kiểm tra khả dụng, tạo lịch, xem/hủy lịch của khách, quản lý trạng thái lịch của salon.
- Kiểm tra giờ mở cửa, kỹ năng, ca làm, nghỉ phép, thời gian nghỉ giữa lịch và lịch trùng; transaction khóa chi nhánh trước khi kiểm tra lại và ghi lịch.
- Token chống gửi lại cùng đơn; lưu tên/giá/thời lượng dịch vụ tại thời điểm đặt. Tự hết hạn lịch chờ qua scheduler.
- Blade responsive và Bootstrap 5.3.3 nội bộ, phân trang, thông báo lỗi và trạng thái gửi form đặt lịch.
- 44 migrations tạo đủ 42 bảng nghiệp vụ của schema nguồn, bổ sung ràng buộc và token đặt lịch. Seed 11 vai trò; không có mật khẩu mặc định.

Có migrations cho bảng không có nghĩa module tương ứng đã được triển khai. Các phần chưa chuyển bên dưới vẫn chưa hoạt động; thanh toán thủ công được mô tả ở cuối tài liệu.

## Lấy mã nguồn

Nhánh `main` giữ bản PHP MVC cũ. Nhánh `laravel-payment-refunds` chứa bản Laravel chuyển đổi theo giai đoạn này; các giới hạn chức năng được ghi bên dưới.

```powershell
git clone --branch laravel-payment-refunds https://github.com/trungduc610/Website-booking-salon.git glowbook-laravel
cd glowbook-laravel
```
## Cài trên Laragon

1. Clone nhánh Laravel bằng các lệnh bên dưới, rồi mở thư mục glowbook-laravel trong VS Code. Nếu dùng Virtual Host, Document Root phải trỏ đến thư mục public.
2. Chọn PHP 8.3.33; bật mbstring, openssl, pdo_mysql, fileinfo, curl, zip. Bật pdo_sqlite/sqlite3 để chạy tests. Kiểm tra `php -v` và `php -m` trong Terminal Laragon.
3. Từ thư mục dự án:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Không chạy Copy-Item nếu đã có .env. Repository có composer.lock và Bootstrap; vendor PHP, .env, uploads, log và dữ liệu local không được commit.

4. Tạo database MỚI glowbook_laravel_dev, charset utf8mb4/collation utf8mb4_unicode_ci. Điền DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD vào .env theo MySQL của bạn. Không trỏ vào database cũ.
5. Với artisan serve, đặt APP_URL=http://127.0.0.1:8000 rồi chạy:

```powershell
php artisan migrate --seed
php artisan serve --host=127.0.0.1
```

Mở http://127.0.0.1:8000/register. APP_DEBUG=false; lỗi chi tiết nằm trong storage/logs. SESSION_SECURE_COOKIE=false cho HTTP local; bật true khi dùng HTTPS.

Nếu Composer lỗi chứng chỉ, cấu hình CA bundle hợp lệ, ví dụ D:\laragon\etc\ssl\cacert.pem cho curl.cainfo/openssl.cafile. Không tắt xác minh TLS.

## Chạy thử từ đầu đến cuối

1. Đăng ký tài khoản chủ salon trên /register. Chạy lệnh quản trị sau, thay email bằng tài khoản vừa đăng ký:

```powershell
php artisan glowbook:create-salon ban@example.com --name="Salon của tôi" --branch="Chi nhánh chính"
```

Lệnh in ID chi nhánh và tạo salon chờ duyệt. Mỗi lần chạy tạo salon mới.

2. Dashboard → Chi nhánh được phân quyền → Quản lý dịch vụ. Thêm dịch vụ đang hoạt động và cho phép đặt lịch.
3. Chi nhánh → Nhân viên và lịch làm → Thêm nhân viên. Chọn dịch vụ, trạng thái ACTIVE và cho phép nhận lịch. Vào Hồ sơ & lịch làm để lưu ca làm cả tuần.
4. Vào Giờ mở cửa & ngày nghỉ; lưu giờ mở cửa và chính sách đặt/hủy. Ca nhân viên và giờ chi nhánh phải giao nhau đủ thời lượng dịch vụ.
5. Xuất bản bằng lệnh quản trị, thay 1 bằng ID chi nhánh thực tế:

```powershell
php artisan glowbook:publish-salon 1
```

Lệnh kích hoạt doanh nghiệp và chi nhánh; hiện chưa có màn hình duyệt salon.

6. Mở cửa sổ riêng, đăng ký tài khoản khách. Vào /salons, chọn salon, dịch vụ, ngày/giờ và kiểm tra khả dụng rồi đặt. Mặc định cần đặt trước ít nhất 60 phút, trong 90 ngày; có thể đổi trong chính sách chi nhánh.
7. Chủ salon vào Nhân viên và lịch làm → Quản lý lịch hẹn để xác nhận và chuyển trạng thái. Khách xem/hủy tại /customer/bookings theo thời hạn hủy.
8. Mở Terminal thứ hai để xử lý lịch chờ hết hạn:

```powershell
php artisan schedule:work
```

Giữ Terminal này chạy khi thử. Có thể chạy `php artisan glowbook:expire-bookings` thủ công. Mỗi lượt xử lý tối đa 200 lịch. Chưa cài tác vụ nền Windows cho bạn.

## Kiểm thử

```powershell
php artisan config:clear
php artisan test
php vendor/bin/pint --test
php artisan view:cache
```

Kết quả đã lưu: 44 tests / 172 assertions PASS trên SQLite và trên MySQL 8.4.3 thử riêng. Hai tiến trình MySQL đặt cùng nhân viên/khung giờ: chỉ một lịch được tạo, yêu cầu còn lại bị từ chối. Xem VERIFICATION.md.

phpunit.xml dùng SQLite :memory:. Không đổi cấu hình test sang database có dữ liệu cần giữ. tests/Support/concurrency.php chỉ dành cho môi trường MySQL thử riêng, yêu cầu biến GLOWBOOK_CONCURRENCY_TEST=1 và database glowbook_test; không phải bước cài ứng dụng.

## Giới hạn và công việc tiếp theo

- Đây là bản chuyển đổi theo giai đoạn, chưa thay thế đầy đủ ứng dụng gốc.
- Chỉ khách đăng nhập được đặt lịch; một nhân viên thực hiện toàn bộ các dịch vụ trong lịch. Chưa hỗ trợ ca qua nửa đêm hoặc chia dịch vụ cho nhiều nhân viên.
- Khi nhân viên còn lịch tương lai chưa hoàn tất, hệ thống chặn sửa hồ sơ/ca làm/thêm nghỉ phép; tương tự với cấu hình giờ chi nhánh. Cần xử lý lịch trước; chưa có chức năng dời lịch/phân công lại.
- Hồ sơ nhân viên chưa tạo tài khoản đăng nhập. Chế độ phân công thủ công bởi lễ tân chưa nhận đặt trực tuyến.
- Chưa chuyển cổng thanh toán trực tuyến, ví voucher, đánh giá, upload media, reset mật khẩu/xác minh email, CRUD đầy đủ doanh nghiệp/chi nhánh và quản trị nền tảng.
- Chưa có công cụ nhập dữ liệu cũ hoặc đối soát dữ liệu thực. Schema nền tạo mới từ ứng dụng nguồn; migration bổ sung thanh toán hỗ trợ nâng cấp bản voucher Laravel như hướng dẫn bên dưới. Không chạy migrate:fresh/rollback trên database đang sử dụng.

Thứ tự tiếp theo: đánh giá/media/tài khoản → quản trị còn lại → nhập bản sao dữ liệu, đối soát và nghiệm thu toàn hệ thống.

## Bổ sung voucher ngày 07/10

Chủ doanh nghiệp hoặc quản trị nền tảng vào Chi nhánh → Ưu đãi doanh nghiệp để tạo/tạm ngừng mã. Mã áp dụng cho các chi nhánh cùng doanh nghiệp. Hỗ trợ giảm phần trăm/số tiền, trần giảm, giá trị đơn tối thiểu, thời hạn, tổng lượt và lượt mỗi khách. Tiền được tính bằng số nguyên đơn vị 0,01 đồng; phần lẻ khi tính phần trăm làm tròn xuống.

Khách nhập mã tại form đặt lịch. Máy chủ kiểm tra mã trong transaction khi tạo lịch, lưu giá trước giảm/giảm giá/thành tiền. Gửi lại cùng token không trừ thêm lượt. Hủy, từ chối hoặc hết hạn trả lượt; lịch NO_SHOW vẫn tính lượt. Cần chạy scheduler để giải phóng lượt của lịch chờ hết hạn. Chưa hỗ trợ ví customer_vouchers/thu thập mã; đây là luồng nhập mã trực tiếp. Ưu đãi đã tạo chỉ cho đổi trạng thái để giữ ổn định điều kiện; tạo mã mới nếu cần điều kiện khác.

Thanh toán/hoàn tiền thủ công đã bổ sung bên dưới; chưa tích hợp cổng thanh toán trực tuyến.


## Thanh toán/hoàn tiền thủ công — 07/10/2026

Phạm vi đã chọn: tiền mặt và chuyển khoản do salon xác nhận. Ứng dụng ghi sổ tiền thực nhận/thực trả, không kết nối ngân hàng và không tự chuyển tiền.

### Nâng cấp từ bản voucher

Sau khi sao lưu database triển khai, chạy `composer install` rồi `php artisan migrate`. Migration mới `2026_10_07_000102_add_manual_payment_tracking` chỉ thêm token chống gửi lại, người ghi nhận/người trả tiền và mã giao dịch hoàn; không sửa migrations đã chạy. Không cần seed lại hoặc migrate:fresh. Chưa chạy migration lên database sử dụng của bạn trong lượt phát triển này.

### Cách sử dụng

1. Salon vào Quản lý lịch hẹn → Thanh toán và hoàn tiền. Lịch phải ở CONFIRMED, CHECKED_IN, IN_PROGRESS hoặc COMPLETED mới thu được. Xác nhận lịch trước khi thu.
2. Nhập số tiền thực nhận, chọn tiền mặt/chuyển khoản. Với chuyển khoản phải nhập mã giao dịch. Chỉ nhấn xác nhận sau khi đã kiểm tra tiền thực nhận. Có thể thu nhiều lần; tổng thu không vượt giá sau voucher được lưu khi đặt lịch.
3. Khách vào Chi tiết lịch hẹn → Thanh toán và hoàn tiền để xem giao dịch và gửi yêu cầu hoàn một phần/toàn bộ. Nhân sự có quyền thu tiền cũng có thể tạo yêu cầu thay khách. Mỗi yêu cầu thuộc một giao dịch thu; hoàn nhiều giao dịch cần tạo các yêu cầu tương ứng.
4. Chủ doanh nghiệp, quản lý chi nhánh hoặc quản trị nền tảng duyệt/từ chối. Từ chối phải có lý do. Duyệt chỉ giữ số tiền chờ trả, chưa được tính là đã hoàn.
5. Sau khi thực sự trả tiền, người có quyền duyệt nhấn Xác nhận đã trả tiền. Chuyển khoản hoàn phải có mã giao dịch. Lưu người duyệt, thời điểm duyệt, người xác nhận trả và thời điểm trả. Lễ tân có thể thu/tạo yêu cầu nhưng không duyệt/xác nhận hoàn.

### Quy tắc nghiệp vụ

- Tiền xử lý bằng số nguyên đơn vị 0,01 đồng, tối đa 2 chữ số thập phân. Mỗi khoản thu phải dương; đơn được voucher giảm về 0 không tạo giao dịch thu.
- Gửi lại cùng token và cùng dữ liệu không tạo thêm giao dịch/yêu cầu. Token dùng với dữ liệu khác trả 409. Đây là chống gửi lại cùng thao tác; người dùng vẫn cần đối chiếu chứng từ khi nhập một thao tác mới.
- Khóa transaction theo thứ tự chi nhánh → lịch → giao dịch, cùng thứ tự với hủy lịch. Tổng yêu cầu chờ/đã duyệt/đang xử lý/đã hoàn không vượt khoản đã thu. Từ chối giải phóng số tiền đang giữ.
- Hủy/từ chối/hết hạn lịch không tự chuyển tiền hay tự đánh dấu hoàn. Lịch đã hủy vẫn cho yêu cầu hoàn khoản đã thu. Trả lượt voucher tiếp tục theo quy tắc cũ.
- Tổng thu gốc, còn chưa thu, đã hoàn và thực giữ hiển thị riêng. Hoàn tiền không mở lại công nợ để thu thêm cùng lịch. Trạng thái lịch hẹn độc lập với trạng thái thanh toán; hoàn tất dịch vụ không tự xác nhận đã nhận tiền.
- Không có sửa/xóa giao dịch đã ghi nhận. Khoản thu sai cần được xử lý qua hoàn tiền; luồng sửa sổ/đối soát dữ liệu cũ chưa triển khai. Giao dịch cũ PENDING/PARTIALLY_PAID chặn thu mới cho đến khi được đối soát.
- Khách chỉ xem/yêu cầu hoàn lịch của mình; mọi thao tác salon kiểm tra quyền và đúng chi nhánh. Không có kết nối VNPay/MoMo, webhook, hóa đơn thuế hoặc hoàn tự động.

Mã chính: `app/Services/PaymentManager.php`; kiểm thử: `tests/Feature/PaymentTest.php`. Kết quả chạy thực xem VERIFICATION.md.



## Chạy từ VS Code / PowerShell

Sau khi cài dependencies, tạo .env, sinh APP_KEY và chạy migrations theo hướng dẫn trên:

```powershell
.\Start-GlowBook.ps1 -Check
.\Start-GlowBook.ps1
```

Script mặc định dùng `php` trên PATH và php.ini của PHP đó. Có thể truyền đường dẫn riêng:

```powershell
.\Start-GlowBook.ps1 -PhpPath 'D:\laragon\bin\php\YOUR-PHP-VERSION\php.exe' -IniPath '.php-local.ini'
```

Thay đường dẫn ví dụ bằng PHP thực trên máy bạn. Chỉ dùng `-IniPath` khi bạn đã tạo file php.ini riêng hợp lệ; đây không phải file được cung cấp sẵn qua GitHub. Nếu muốn lưu lựa chọn cho những lần chạy sau, copy `glowbook.local.example.json` thành `glowbook.local.json`, rồi điền PhpPath và IniPath. File local được Git bỏ qua. Không cần file này khi PHP trên PATH đã có đủ extensions.

Script truyền PHPRC cho tiến trình web con khi chọn IniPath; chỉ `php -c ... artisan serve` không đảm bảo web worker dùng cùng cấu hình. Script không sửa .env, không tạo lại APP_KEY và không migrate database. Giữ terminal mở; Ctrl+C để dừng. Thay đổi .env cần dừng và chạy lại script. Thêm `-Port 8001` nếu cổng 8000 đã được dùng.

Mở http://127.0.0.1:8000. Nếu gặp HTTP 500, kiểm tra storage/logs/laravel.log và đảm bảo đã tạo .env / APP_KEY; không đăng nội dung .env hoặc log chứa dữ liệu cá nhân lên GitHub.

## File được giữ riêng

Git bỏ qua .env và biến thể, cấu hình máy, vendor PHP, cache, sessions, log, uploads, bản dump database ở thư mục gốc và báo cáo chạy thử local. Chỉ commit .env.example với giá trị mẫu; mỗi máy tự tạo APP_KEY. Không dùng `git add -f` cho các file bị bỏ qua. .gitignore không bảo vệ file upload thủ công hoặc file đã nằm trong lịch sử Git.

Repository không đi kèm database, tài khoản người dùng thật hoặc mật khẩu mặc định. Dữ liệu tài khoản trong tests là fixture dùng trên database kiểm thử. Các kết quả trong VERIFICATION.md có ghi rõ môi trường và giới hạn của từng lượt chạy.
